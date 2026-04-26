<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class StockMovement {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    public static function tableExists(): bool {
        try {
            $db = \Database::getInstance()->getConnection();
            $r = $db->query("SHOW TABLES LIKE 'stock_movements'");
            return $r && $r->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function insert(
        int $companyId,
        int $productId,
        string $type,
        int $quantity,
        ?int $referenceId,
        ?string $referenceType,
        ?int $createdBy,
        ?string $createdRole
    ): void {
        if (!self::tableExists() || $quantity <= 0) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO stock_movements (company_id, product_id, type, quantity, reference_id, reference_type, created_by, created_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $companyId,
            $productId,
            $type,
            $quantity,
            $referenceId,
            $referenceType,
            $createdBy,
            $createdRole,
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByCompany(?int $companyId, int $limit = 200, ?string $type = null): array {
        if (!self::tableExists()) {
            return [];
        }
        $sql = 'SELECT sm.*, p.name AS product_name, c.name AS company_name
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.id AND p.company_id = sm.company_id
                LEFT JOIN companies c ON sm.company_id = c.id
                WHERE 1=1';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND sm.company_id = ?';
            $params[] = $companyId;
        }
        if ($type) {
            $sql .= ' AND sm.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY sm.created_at DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
