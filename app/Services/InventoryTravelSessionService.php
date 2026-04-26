<?php

namespace App\Services;

use App\Models\Product;
use PDO;

/**
 * Manager travel audit: START/END inventory snapshots and comparison report.
 */
class InventoryTravelSessionService {
    private PDO $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->ensureTables();
    }

    private function ensureTables(): void {
        try {
            if ($this->db->query("SHOW TABLES LIKE 'inventory_travel_sessions'")->rowCount() > 0) {
                return;
            }
            $path = __DIR__ . '/../../database/migrations/create_inventory_travel_sessions.sql';
            if (!is_readable($path)) {
                return;
            }
            $sql = file_get_contents($path);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '' || strpos($stmt, '--') === 0) {
                    continue;
                }
                try {
                    $this->db->exec($stmt);
                } catch (\PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        error_log('InventoryTravelSessionService migration: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('InventoryTravelSessionService::ensureTables: ' . $e->getMessage());
        }
    }

    public function getOpenSession(int $companyId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM inventory_travel_sessions
            WHERE company_id = ? AND status = 'open'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listSessions(int $companyId, int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT s.*, u.full_name AS manager_name, su.full_name AS staff_name
            FROM inventory_travel_sessions s
            INNER JOIN users u ON s.manager_user_id = u.id
            LEFT JOIN users su ON s.staff_user_id = su.id
            WHERE s.company_id = ?
            ORDER BY s.started_at DESC
            LIMIT " . max(1, min(200, $limit)));
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSession(int $sessionId, int $companyId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, u.full_name AS manager_name,
                   su.full_name AS staff_name
            FROM inventory_travel_sessions s
            INNER JOIN users u ON s.manager_user_id = u.id
            LEFT JOIN users su ON s.staff_user_id = su.id
            WHERE s.id = ? AND s.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sessionId, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @throws \RuntimeException
     */
    public function startSession(int $companyId, int $managerUserId): array {
        if ($this->getOpenSession($companyId)) {
            throw new \RuntimeException('An open travel session already exists. End it before starting a new one.');
        }
        $productModel = new Product();
        $snapshot = $productModel->getQuantitiesSnapshotForCompany($companyId);
        if (empty($snapshot)) {
            // Still allow session with zero rows? Prefer at least one row for audit.
            // Empty company inventory — create session with empty START snapshot.
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_travel_sessions (company_id, manager_user_id, started_at, status)
                VALUES (?, ?, NOW(), 'open')
            ");
            $stmt->execute([$companyId, $managerUserId]);
            $sessionId = (int)$this->db->lastInsertId();
            $this->insertSnapshotLines($sessionId, 'START', $snapshot);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findSession($sessionId, $companyId) ?? ['id' => $sessionId];
    }

    /**
     * @param 'single'|'all' $staffMode
     * @throws \RuntimeException
     */
    public function endSession(
        int $sessionId,
        int $companyId,
        int $managerUserId,
        string $staffMode,
        ?int $staffUserId
    ): array {
        $session = $this->findSession($sessionId, $companyId);
        if (!$session || $session['status'] !== 'open') {
            throw new \RuntimeException('Session not found or already closed.');
        }
        if ((int)$session['manager_user_id'] !== $managerUserId) {
            throw new \RuntimeException('Only the manager who started this session can end it.');
        }
        if (!in_array($staffMode, ['single', 'all'], true)) {
            throw new \RuntimeException('Invalid staff_mode.');
        }
        if ($staffMode === 'single') {
            if (!$staffUserId) {
                throw new \RuntimeException('staff_user_id is required when staff_mode is single.');
            }
            $this->assertStaffInCompany($staffUserId, $companyId);
        }

        $productModel = new Product();
        $snapshot = $productModel->getQuantitiesSnapshotForCompany($companyId);

        $this->db->beginTransaction();
        try {
            $this->insertSnapshotLines($sessionId, 'END', $snapshot);
            $stmt = $this->db->prepare("
                UPDATE inventory_travel_sessions
                SET ended_at = NOW(),
                    staff_mode = ?,
                    staff_user_id = ?,
                    status = 'closed'
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $staffMode,
                $staffMode === 'single' ? $staffUserId : null,
                $sessionId,
                $companyId,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findSession($sessionId, $companyId) ?? [];
    }

    public function buildReport(int $sessionId, int $companyId): array {
        $session = $this->findSession($sessionId, $companyId);
        if (!$session) {
            throw new \RuntimeException('Session not found.');
        }
        if ($session['status'] !== 'closed') {
            throw new \RuntimeException('Report is available after the session is ended.');
        }

        $stmt = $this->db->prepare("
            SELECT phase, product_id, quantity
            FROM inventory_travel_snapshot_lines
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $start = [];
        $end = [];
        foreach ($rows as $r) {
            $pid = (int)$r['product_id'];
            if ($r['phase'] === 'START') {
                $start[$pid] = (int)$r['quantity'];
            } elseif ($r['phase'] === 'END') {
                $end[$pid] = (int)$r['quantity'];
            }
        }
        $ids = array_unique(array_merge(array_keys($start), array_keys($end)));
        sort($ids);

        $names = (new Product())->getNamesByCompanyAndIds($companyId, $ids);

        $lines = [];
        $reduced = [];
        $increased = [];
        foreach ($ids as $pid) {
            $sq = $start[$pid] ?? 0;
            $eq = $end[$pid] ?? 0;
            $diff = $eq - $sq;
            $line = [
                'product_id' => $pid,
                'name' => $names[$pid] ?? ('Product #' . $pid),
                'start_quantity' => $sq,
                'end_quantity' => $eq,
                'difference' => $diff,
            ];
            $lines[] = $line;
            if ($diff < 0) {
                $reduced[] = $line;
            } elseif ($diff > 0) {
                $increased[] = $line;
            }
        }

        return [
            'session' => $session,
            'items' => $lines,
            'reduced' => $reduced,
            'increased' => $increased,
        ];
    }

    private function assertStaffInCompany(int $userId, int $companyId): void {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND company_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$userId, $companyId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('Selected staff is not valid for this company.');
        }
    }

    /**
     * @param list<array{product_id:int,quantity:int}> $lines
     */
    private function insertSnapshotLines(int $sessionId, string $phase, array $lines): void {
        if (empty($lines)) {
            return;
        }
        $chunk = 300;
        $chunks = array_chunk($lines, $chunk);
        foreach ($chunks as $batch) {
            $values = [];
            $params = [];
            foreach ($batch as $row) {
                $values[] = '(?, ?, ?, ?)';
                array_push($params, $sessionId, $phase, (int)$row['product_id'], (int)$row['quantity']);
            }
            $sql = 'INSERT INTO inventory_travel_snapshot_lines (session_id, phase, product_id, quantity) VALUES '
                . implode(',', $values);
            $this->db->prepare($sql)->execute($params);
        }
    }

}
