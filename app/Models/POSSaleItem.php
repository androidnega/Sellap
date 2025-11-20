<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class POSSaleItem {
    private $conn;
    private $table = 'pos_sale_items';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new sale item
     */
    public function create($data) {
        // Check if swap_id column exists
        $swapColumnExists = $this->checkSwapColumnExists();
        
        // Check if is_swapped_item column exists
        $isSwappedItemColumnExists = $this->checkIsSwappedItemColumnExists();
        
        // Build column list and values dynamically
        $columns = ['pos_sale_id', 'item_type', 'item_id', 'item_description', 'quantity', 'unit_price', 'total_price'];
        $placeholders = [':pos_sale_id', ':item_type', ':item_id', ':item_description', ':quantity', ':unit_price', ':total_price'];
        
        if ($swapColumnExists) {
            $columns[] = 'swap_id';
            $placeholders[] = ':swap_id';
        }
        
        if ($isSwappedItemColumnExists) {
            $columns[] = 'is_swapped_item';
            $placeholders[] = ':is_swapped_item';
        }
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->conn->prepare($sql);
        
        $quantity = $data['quantity'] ?? 1;
        $unitPrice = $data['unit_price'] ?? $data['price'] ?? 0;
        $totalPrice = $quantity * $unitPrice;
        
        $params = [
            'pos_sale_id' => $data['pos_sale_id'] ?? $data['sale_id'],
            'item_type' => $data['item_type'] ?? 'OTHER',
            'item_id' => $data['item_id'] ?? $data['product_id'] ?? null,
            'item_description' => $data['item_description'] ?? $data['description'] ?? $data['name'] ?? '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice
        ];
        
        // Only add swap_id if column exists
        if ($swapColumnExists) {
            $params['swap_id'] = $data['swap_id'] ?? null;
        }
        
        // Only add is_swapped_item if column exists
        if ($isSwappedItemColumnExists) {
            $params['is_swapped_item'] = isset($data['is_swapped_item']) ? (intval($data['is_swapped_item']) ? 1 : 0) : 0;
        }
        
        try {
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("POSSaleItem create error: " . ($errorInfo[2] ?? 'Unknown error'));
                error_log("POSSaleItem create params: " . json_encode($params));
                throw new \Exception('Failed to create sale item: ' . ($errorInfo[2] ?? 'Unknown error'));
            }
            return $this->conn->lastInsertId();
        } catch (\PDOException $e) {
            error_log("POSSaleItem create PDO error: " . $e->getMessage());
            error_log("POSSaleItem create params: " . json_encode($params));
            throw new \Exception('Failed to create sale item: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if is_swapped_item column exists in the table
     */
    private function checkIsSwappedItemColumnExists() {
        try {
            $sql = "SHOW COLUMNS FROM {$this->table} LIKE 'is_swapped_item'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if swap_id column exists in the table
     */
    private function checkSwapColumnExists() {
        try {
            $sql = "SHOW COLUMNS FROM {$this->table} LIKE 'swap_id'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get items by sale ID
     */
    public function bySale($saleId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE pos_sale_id = :sale_id");
        $stmt->execute(['sale_id' => $saleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete sale items
     */
    public function deleteBySale($saleId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE pos_sale_id = :sale_id");
        return $stmt->execute(['sale_id' => $saleId]);
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts($companyId, $limit = 10, $dateFrom = null, $dateTo = null) {
        $sql = "
            SELECT 
                si.item_description,
                SUM(si.quantity) as total_quantity,
                SUM(si.total_price) as total_revenue,
                AVG(si.unit_price) as avg_price
            FROM {$this->table} si
            JOIN pos_sales ps ON si.pos_sale_id = ps.id
            WHERE ps.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($dateFrom) {
            $sql .= " AND DATE(ps.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND DATE(ps.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY si.item_description ORDER BY total_quantity DESC LIMIT " . (int)$limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

