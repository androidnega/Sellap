<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class RepairAccessory {
    private $db;
    private $table = 'repair_accessories';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Add accessory to repair
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO repair_accessories (repair_id, product_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['repair_id'],
            $data['product_id'],
            $data['quantity'] ?? 1,
            $data['price']
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get accessories for a repair
     */
    public function getByRepair($repair_id) {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT ra.*, p.name as product_name, p.specs
            FROM repair_accessories ra
            LEFT JOIN {$productsTable} p ON ra.product_id = p.id
            WHERE ra.repair_id = ?
            ORDER BY ra.id ASC
        ");
        $stmt->execute([$repair_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Determine which products table exists (products or products_new)
     */
    private function getProductsTableName() {
        // Check if products_new exists
        $checkTable = $this->db->query("SHOW TABLES LIKE 'products_new'");
        if ($checkTable && $checkTable->rowCount() > 0) {
            return 'products_new';
        }
        // Default to products table
        return 'products';
    }

    /**
     * Update accessory quantity/price
     */
    public function update($id, array $data) {
        $stmt = $this->db->prepare("
            UPDATE repair_accessories SET 
                quantity = ?, price = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['quantity'],
            $data['price'],
            $id
        ]);
    }

    /**
     * Remove accessory from repair
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM repair_accessories WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Remove all accessories from repair
     */
    public function deleteByRepair($repair_id) {
        $stmt = $this->db->prepare("
            DELETE FROM repair_accessories WHERE repair_id = ?
        ");
        return $stmt->execute([$repair_id]);
    }

    /**
     * Calculate total accessories cost for repair
     */
    public function getTotalCost($repair_id) {
        $stmt = $this->db->prepare("
            SELECT SUM(quantity * price) as total_cost
            FROM repair_accessories 
            WHERE repair_id = ?
        ");
        $stmt->execute([$repair_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_cost'] ?? 0;
    }
}
