<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class CompanyHolidayMessage {
    private $conn;
    private $table = 'company_holiday_messages';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    public static function tableExists(): bool {
        try {
            $db = \Database::getInstance()->getConnection();
            $r = $db->query("SHOW TABLES LIKE 'company_holiday_messages'");
            return $r && $r->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByCompany(int $companyId): array {
        if (!self::tableExists()) {
            return [];
        }
        $st = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = ? ORDER BY event_date ASC, id ASC");
        $st->execute([$companyId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findForCompany(int $id, int $companyId): ?array {
        if (!self::tableExists()) {
            return null;
        }
        $st = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? AND company_id = ? LIMIT 1");
        $st->execute([$id, $companyId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function create(int $companyId, array $data, ?int $createdBy): int {
        $st = $this->conn->prepare("INSERT INTO {$this->table}
            (company_id, label, event_date, is_annual, message_body, is_active, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            $companyId,
            $data['label'],
            $data['event_date'],
            !empty($data['is_annual']) ? 1 : 0,
            $data['message_body'],
            isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            $createdBy,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function update(int $id, int $companyId, array $data): bool {
        $st = $this->conn->prepare("UPDATE {$this->table} SET
            label = ?, event_date = ?, is_annual = ?, message_body = ?, is_active = ?
            WHERE id = ? AND company_id = ?");
        return $st->execute([
            $data['label'],
            $data['event_date'],
            !empty($data['is_annual']) ? 1 : 0,
            $data['message_body'],
            isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            $id,
            $companyId,
        ]);
    }

    public function delete(int $id, int $companyId): bool {
        $st = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ? AND company_id = ?");
        return $st->execute([$id, $companyId]) && $st->rowCount() > 0;
    }

    /**
     * Active holidays that match today's month/day (annual) or exact date (one-time).
     * @return list<array<string,mixed>>
     */
    public function listMatchingTodayForDispatch(): array {
        if (!self::tableExists()) {
            return [];
        }
        $sql = "SELECT h.* FROM {$this->table} h
            WHERE h.is_active = 1
            AND (
                (h.is_annual = 1 AND DATE_FORMAT(h.event_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d'))
                OR (h.is_annual = 0 AND h.event_date = CURDATE())
            )";
        $st = $this->conn->query($sql);
        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
