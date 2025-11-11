<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class SaleItem {
    private $db;
    private $table = 'sales_items';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Add item to sale
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO sales_items (
                sale_id, product_id, swap_id, repair_id, item_type,
                item_description, quantity, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['sale_id'],
            $data['product_id'] ?? null,
            $data['swap_id'] ?? null,
            $data['repair_id'] ?? null,
            $data['item_type'] ?? 'product',
            $data['item_description'],
            $data['quantity'] ?? 1,
            $data['unit_price'],
            $data['total_price']
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get items for a sale
     */
    public function getBySale($sale_id) {
        $stmt = $this->db->prepare("
            SELECT si.*, 
                   p.name as product_name, p.specs as product_specs,
                   s.store_product_id, s.customer_product_id,
                   r.issue_description as repair_description
            FROM sales_items si
            LEFT JOIN products_new p ON si.product_id = p.id
            LEFT JOIN swaps_new s ON si.swap_id = s.id
            LEFT JOIN repairs_new r ON si.repair_id = r.id
            WHERE si.sale_id = ?
            ORDER BY si.id ASC
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON specs for products
        foreach ($items as &$item) {
            if ($item['product_specs']) {
                $item['product_specs'] = json_decode($item['product_specs'], true);
            }
        }
        
        return $items;
    }

    /**
     * Update sale item
     */
    public function update($id, array $data) {
        $stmt = $this->db->prepare("
            UPDATE sales_items SET 
                quantity = ?, unit_price = ?, total_price = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['quantity'],
            $data['unit_price'],
            $data['total_price'],
            $id
        ]);
    }

    /**
     * Remove item from sale
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM sales_items WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Remove all items from sale
     */
    public function deleteBySale($sale_id) {
        $stmt = $this->db->prepare("
            DELETE FROM sales_items WHERE sale_id = ?
        ");
        return $stmt->execute([$sale_id]);
    }

    /**
     * Calculate total for sale
     */
    public function getTotalForSale($sale_id) {
        $stmt = $this->db->prepare("
            SELECT SUM(total_price) as total
            FROM sales_items 
            WHERE sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Get item statistics for a sale
     */
    public function getStatsForSale($sale_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_value,
                SUM(CASE WHEN item_type = 'product' THEN total_price ELSE 0 END) as product_value,
                SUM(CASE WHEN item_type = 'swap' THEN total_price ELSE 0 END) as swap_value,
                SUM(CASE WHEN item_type = 'repair' THEN total_price ELSE 0 END) as repair_value
            FROM sales_items 
            WHERE sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts($company_id, $limit = 10, $date_from = null, $date_to = null) {
        $sql = "
            SELECT p.name, p.brand, 
                   SUM(si.quantity) as total_quantity,
                   SUM(si.total_price) as total_revenue,
                   COUNT(DISTINCT si.sale_id) as sale_count
            FROM sales_items si
            JOIN sales_new s ON si.sale_id = s.id
            JOIN products_new p ON si.product_id = p.id
            WHERE s.company_id = ? AND si.item_type = 'product'
        ";
        $params = [$company_id];
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " GROUP BY p.id ORDER BY total_quantity DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
