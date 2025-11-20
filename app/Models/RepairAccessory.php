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
        // Validate required fields
        if (empty($data['repair_id']) || $data['repair_id'] <= 0) {
            throw new \Exception('Invalid repair_id: ' . var_export($data['repair_id'] ?? null, true));
        }
        
        if (empty($data['product_id']) || $data['product_id'] <= 0) {
            throw new \Exception('Invalid product_id: ' . var_export($data['product_id'] ?? null, true));
        }
        
        // Ensure repair_id is an integer for foreign key constraint
        $repairId = (int)$data['repair_id'];
        $productId = (int)$data['product_id'];
        $quantity = (int)($data['quantity'] ?? 1);
        $price = floatval($data['price']);
        
        $stmt = $this->db->prepare("
            INSERT INTO repair_accessories (repair_id, product_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $repairId,
                $productId,
                $quantity,
                $price
            ]);
            
            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("RepairAccessory::create() - PDO Error: " . $e->getMessage());
            error_log("RepairAccessory::create() - Repair ID: {$repairId} (type: " . gettype($repairId) . "), Product ID: {$productId}, Quantity: {$quantity}, Price: {$price}");
            
            // Check if repair exists in database (within transaction, this should work)
            try {
                $verifyRepair = $this->db->prepare("SELECT id, company_id FROM repairs_new WHERE id = ?");
                $verifyRepair->execute([$repairId]);
                $repairData = $verifyRepair->fetch(PDO::FETCH_ASSOC);
                
                if (!$repairData) {
                    error_log("RepairAccessory::create() - Repair ID {$repairId} NOT FOUND in repairs_new table");
                    throw new \Exception("Foreign key constraint failed: Repair ID {$repairId} does not exist in repairs_new table. Please ensure the repair was created successfully before adding accessories.");
                } else {
                    error_log("RepairAccessory::create() - Repair ID {$repairId} EXISTS in repairs_new table (company_id: " . ($repairData['company_id'] ?? 'NULL') . ")");
                    // Repair exists but insert still failed - might be a different constraint issue
                    throw new \Exception("Failed to create repair accessory: " . $e->getMessage() . " (Repair ID {$repairId} exists in database)");
                }
            } catch (\PDOException $verifyError) {
                error_log("RepairAccessory::create() - Error verifying repair: " . $verifyError->getMessage());
                throw new \Exception("Failed to create repair accessory: " . $e->getMessage() . " (Could not verify repair existence: " . $verifyError->getMessage() . ")");
            }
        }
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
