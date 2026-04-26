<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * POS return records (table name: returns).
 */
class InventoryReturn {
    private $db;
    private $table = 'returns';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    public static function tableExists(): bool {
        try {
            $db = \Database::getInstance()->getConnection();
            $r = $db->query("SHOW TABLES LIKE 'returns'");
            return $r && $r->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createHeader(int $companyId, int $posSaleId, int $createdBy, string $createdRole, ?string $notes = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (company_id, pos_sale_id, notes, created_by, created_role) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$companyId, $posSaleId, $notes, $createdBy, $createdRole]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id, ?int $companyId = null): ?array {
        if (!self::tableExists()) {
            return null;
        }
        $sql = "SELECT r.*, ps.unique_id AS sale_code, ps.created_at AS sale_created_at, u.username AS created_by_username,
                       c.name AS company_name
                FROM {$this->table} r
                INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN companies c ON r.company_id = c.id
                WHERE r.id = ?";
        $params = [$id];
        if ($companyId !== null) {
            $sql .= ' AND r.company_id = ?';
            $params[] = $companyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByCompany(?int $companyId, int $limit = 100): array {
        if (!self::tableExists()) {
            return [];
        }
        $sql = "SELECT r.*, ps.unique_id AS sale_code, ps.created_at AS sale_created_at, u.username AS created_by_username,
                       c.name AS company_name
                FROM {$this->table} r
                INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN companies c ON r.company_id = c.id
                WHERE 1=1";
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND r.company_id = ?';
            $params[] = $companyId;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT ' . max(1, min(300, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
