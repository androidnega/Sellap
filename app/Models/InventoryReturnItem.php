<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class InventoryReturnItem {
    private $db;
    private $table = 'return_items';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    public function insert(int $returnId, int $posSaleItemId, ?int $productId, int $quantity): void {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (return_id, pos_sale_item_id, product_id, quantity) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$returnId, $posSaleItemId, $productId, $quantity]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByReturn(int $returnId): array {
        $stmt = $this->db->prepare(
            "SELECT ri.*, psi.item_description
             FROM {$this->table} ri
             INNER JOIN pos_sale_items psi ON ri.pos_sale_item_id = psi.id
             WHERE ri.return_id = ?
             ORDER BY ri.id ASC"
        );
        $stmt->execute([$returnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
