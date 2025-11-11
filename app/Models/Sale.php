<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Sale {
    private $db;
    private $table = 'sales_new';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new sale
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO sales_new (
                company_id, sale_type, customer_name, customer_contact, customer_id,
                subtotal, discount, tax, total, payment_method, payment_status,
                cashier_id, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['company_id'],
            $data['sale_type'] ?? 'normal',
            $data['customer_name'] ?? null,
            $data['customer_contact'] ?? null,
            $data['customer_id'] ?? null,
            $data['subtotal'] ?? 0,
            $data['discount'] ?? 0,
            $data['tax'] ?? 0,
            $data['total'] ?? 0,
            $data['payment_method'] ?? 'cash',
            $data['payment_status'] ?? 'paid',
            $data['cashier_id'],
            $data['notes'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a sale
     */
    public function update($id, array $data, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE sales_new SET 
                customer_name = ?, customer_contact = ?, customer_id = ?,
                subtotal = ?, discount = ?, tax = ?, total = ?,
                payment_method = ?, payment_status = ?, notes = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([
            $data['customer_name'] ?? null,
            $data['customer_contact'] ?? null,
            $data['customer_id'] ?? null,
            $data['subtotal'] ?? 0,
            $data['discount'] ?? 0,
            $data['tax'] ?? 0,
            $data['total'] ?? 0,
            $data['payment_method'] ?? 'cash',
            $data['payment_status'] ?? 'paid',
            $data['notes'] ?? null,
            $id,
            $company_id
        ]);
    }

    /**
     * Find sale by ID
     */
    public function find($id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT s.*, u.full_name as cashier_name, c.full_name as customer_name_from_table
            FROM sales_new s
            LEFT JOIN users u ON s.cashier_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find sales by company
     */
    public function findByCompany($company_id, $limit = 100, $sale_type = null, $date_from = null, $date_to = null) {
        $sql = "
            SELECT s.*, u.full_name as cashier_name, c.full_name as customer_name_from_table
            FROM sales_new s
            LEFT JOIN users u ON s.cashier_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.company_id = ?
        ";
        $params = [$company_id];
        
        if ($sale_type) {
            $sql .= " AND s.sale_type = ?";
            $params[] = $sale_type;
        }
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find sales by cashier
     */
    public function findByCashier($cashier_id, $company_id, $date_from = null, $date_to = null) {
        $sql = "
            SELECT s.*, c.full_name as customer_name_from_table
            FROM sales_new s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.cashier_id = ? AND s.company_id = ?
        ";
        $params = [$cashier_id, $company_id];
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sale statistics
     */
    public function getStats($company_id, $date_from = null, $date_to = null) {
        $sql = "
            SELECT 
                COUNT(*) as total_sales,
                SUM(CASE WHEN sale_type = 'normal' THEN 1 ELSE 0 END) as normal_sales,
                SUM(CASE WHEN sale_type = 'swap' THEN 1 ELSE 0 END) as swap_sales,
                SUM(CASE WHEN sale_type = 'repair' THEN 1 ELSE 0 END) as repair_sales,
                SUM(total) as total_revenue,
                SUM(discount) as total_discount,
                AVG(total) as average_sale
            FROM sales_new 
            WHERE company_id = ?
        ";
        $params = [$company_id];
        
        if ($date_from) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily sales report
     */
    public function getDailyReport($company_id, $date) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_sales,
                SUM(total) as total_revenue,
                SUM(CASE WHEN sale_type = 'normal' THEN total ELSE 0 END) as normal_revenue,
                SUM(CASE WHEN sale_type = 'swap' THEN total ELSE 0 END) as swap_revenue,
                SUM(CASE WHEN sale_type = 'repair' THEN total ELSE 0 END) as repair_revenue
            FROM sales_new 
            WHERE company_id = ? AND DATE(created_at) = ?
        ");
        $stmt->execute([$company_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a sale
     */
    public function delete($id, $company_id) {
        $stmt = $this->db->prepare("
            DELETE FROM sales_new 
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$id, $company_id]);
    }
}
