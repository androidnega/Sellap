<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class CustomerProduct {
    private $db;
    private $table = 'customer_products';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new customer product
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO customer_products (
                company_id, brand, model, specs, condition, 
                estimated_value, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['company_id'],
            $data['brand'],
            $data['model'],
            $data['specs'] ? json_encode($data['specs']) : null,
            $data['condition'] ?? 'used',
            $data['estimated_value'] ?? 0,
            $data['status'] ?? 'in_stock',
            $data['notes'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a customer product
     */
    public function update($id, array $data, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE customer_products SET 
                brand = ?, model = ?, specs = ?, condition = ?,
                estimated_value = ?, status = ?, notes = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([
            $data['brand'],
            $data['model'],
            $data['specs'] ? json_encode($data['specs']) : null,
            $data['condition'] ?? 'used',
            $data['estimated_value'] ?? 0,
            $data['status'] ?? 'in_stock',
            $data['notes'] ?? null,
            $id,
            $company_id
        ]);
    }

    /**
     * Find customer product by ID
     */
    public function find($id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM customer_products 
            WHERE id = ? AND company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decode JSON specs if present
        if ($product && $product['specs']) {
            $product['specs'] = json_decode($product['specs'], true);
        }
        
        return $product;
    }

    /**
     * Find customer products by company
     */
    public function findByCompany($company_id, $limit = 100, $status = null) {
        $sql = "SELECT * FROM customer_products WHERE company_id = ?";
        $params = [$company_id];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY received_at DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON specs for all products
        foreach ($products as &$product) {
            if ($product['specs']) {
                $product['specs'] = json_decode($product['specs'], true);
            }
        }
        
        return $products;
    }

    /**
     * Find available customer products for resale
     */
    public function findAvailable($company_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM customer_products 
            WHERE company_id = ? AND status = 'in_stock'
            ORDER BY received_at DESC
        ");
        $stmt->execute([$company_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON specs for all products
        foreach ($products as &$product) {
            if ($product['specs']) {
                $product['specs'] = json_decode($product['specs'], true);
            }
        }
        
        return $products;
    }

    /**
     * Update customer product status
     */
    public function updateStatus($id, $company_id, $status) {
        $stmt = $this->db->prepare("
            UPDATE customer_products SET status = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([$status, $id, $company_id]);
    }

    /**
     * Get customer product statistics
     */
    public function getStats($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN status = 'in_stock' THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold,
                SUM(CASE WHEN status = 'swapped' THEN 1 ELSE 0 END) as swapped,
                SUM(estimated_value) as total_value
            FROM customer_products 
            WHERE company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search customer products
     */
    public function search($company_id, $query) {
        $stmt = $this->db->prepare("
            SELECT * FROM customer_products 
            WHERE company_id = ? 
            AND (brand LIKE ? OR model LIKE ?)
            ORDER BY received_at DESC
        ");
        $stmt->execute([$company_id, "%$query%", "%$query%"]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON specs for all products
        foreach ($products as &$product) {
            if ($product['specs']) {
                $product['specs'] = json_decode($product['specs'], true);
            }
        }
        
        return $products;
    }

    /**
     * Delete a customer product
     */
    public function delete($id, $company_id) {
        $stmt = $this->db->prepare("
            DELETE FROM customer_products 
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$id, $company_id]);
    }
}
