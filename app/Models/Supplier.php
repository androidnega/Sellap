<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Supplier {
    private $db;
    private $table = 'suppliers';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all suppliers for a company
     */
    public function findByCompany($companyId, $status = null) {
        $sql = "
            SELECT s.*, 
                   u.full_name as created_by_name,
                   COUNT(DISTINCT po.id) as total_orders,
                   (
                       COUNT(DISTINCT sp.product_id) + 
                       (SELECT COUNT(DISTINCT p.id) 
                        FROM products p 
                        WHERE p.supplier = s.name 
                        AND p.company_id = s.company_id
                        AND p.id NOT IN (
                            SELECT product_id FROM supplier_products WHERE supplier_id = s.id
                        ))
                   ) as total_products
            FROM suppliers s
            LEFT JOIN users u ON s.created_by = u.id
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
            WHERE s.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get suppliers with pagination
     */
    public function findByCompanyPaginated($companyId, $page = 1, $limit = 10, $status = null, $search = null) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT s.*, 
                   u.full_name as created_by_name,
                   COUNT(DISTINCT po.id) as total_orders,
                   (
                       COUNT(DISTINCT sp.product_id) + 
                       (SELECT COUNT(DISTINCT p.id) 
                        FROM products p 
                        WHERE p.supplier = s.name 
                        AND p.company_id = s.company_id
                        AND p.id NOT IN (
                            SELECT product_id FROM supplier_products WHERE supplier_id = s.id
                        ))
                   ) as total_products
            FROM suppliers s
            LEFT JOIN users u ON s.created_by = u.id
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
            WHERE s.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $sql .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.name ASC LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of suppliers
     */
    public function getTotalCountByCompany($companyId, $status = null, $search = null) {
        $sql = "SELECT COUNT(DISTINCT s.id) as total FROM suppliers s WHERE s.company_id = ?";
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $sql .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Find supplier by ID
     */
    public function find($id, $companyId = null) {
        $sql = "
            SELECT s.*, u.full_name as created_by_name
            FROM suppliers s
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ";
        
        $params = [$id];
        
        if ($companyId) {
            $sql .= " AND s.company_id = ?";
            $params[] = $companyId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new supplier
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO suppliers (
                company_id, name, contact_person, email, phone, alternate_phone,
                address, city, state, country, postal_code, tax_id,
                payment_terms, credit_limit, notes, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['company_id'],
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['alternate_phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['country'] ?? null,
            $data['postal_code'] ?? null,
            $data['tax_id'] ?? null,
            $data['payment_terms'] ?? null,
            $data['credit_limit'] ?? 0.00,
            $data['notes'] ?? null,
            $data['status'] ?? 'active',
            $data['created_by'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a supplier
     */
    public function update($id, array $data, $companyId = null) {
        $sql = "
            UPDATE suppliers SET
                name = ?, contact_person = ?, email = ?, phone = ?, alternate_phone = ?,
                address = ?, city = ?, state = ?, country = ?, postal_code = ?,
                tax_id = ?, payment_terms = ?, credit_limit = ?, notes = ?, status = ?
            WHERE id = ?
        ";
        
        $params = [
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['alternate_phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['country'] ?? null,
            $data['postal_code'] ?? null,
            $data['tax_id'] ?? null,
            $data['payment_terms'] ?? null,
            $data['credit_limit'] ?? 0.00,
            $data['notes'] ?? null,
            $data['status'] ?? 'active',
            $id
        ];
        
        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Unassign inventory from supplier (set supplier_id to NULL)
     * This is called before deleting a supplier to preserve inventory data
     * Also unassigns purchase orders from supplier
     */
    public function unassignInventory($supplierId, $companyId = null) {
        try {
            // Unassign products (set supplier_id to NULL)
            $sql = "UPDATE products SET supplier_id = NULL WHERE supplier_id = ?";
            $params = [$supplierId];
            
            if ($companyId) {
                $sql .= " AND company_id = ?";
                $params[] = $companyId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Handle purchase orders - they have foreign key constraint with RESTRICT
            // Since supplier_id is NOT NULL, we need to temporarily disable foreign key checks
            // or delete the purchase orders. We'll delete them to avoid orphaned records.
            $tableExists = $this->db->query("SHOW TABLES LIKE 'purchase_orders'")->rowCount() > 0;
            if ($tableExists) {
                $columnExists = $this->db->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_id'")->rowCount() > 0;
                if ($columnExists) {
                    // Count purchase orders for this supplier
                    $countSQL = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
                    $countParams = [$supplierId];
                    
                    if ($companyId) {
                        $countSQL .= " AND company_id = ?";
                        $countParams[] = $companyId;
                    }
                    
                    $countStmt = $this->db->prepare($countSQL);
                    $countStmt->execute($countParams);
                    $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
                    $orderCount = $countResult['count'] ?? 0;
                    
                    if ($orderCount > 0) {
                        // Temporarily disable foreign key checks to allow deletion
                        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
                        
                        try {
                            // Delete purchase order items first (they reference purchase_orders)
                            $deleteItemsSQL = "
                                DELETE poi FROM purchase_order_items poi
                                INNER JOIN purchase_orders po ON poi.purchase_order_id = po.id
                                WHERE po.supplier_id = ?
                            ";
                            $deleteItemsParams = [$supplierId];
                            
                            if ($companyId) {
                                $deleteItemsSQL .= " AND po.company_id = ?";
                                $deleteItemsParams[] = $companyId;
                            }
                            
                            $deleteItemsStmt = $this->db->prepare($deleteItemsSQL);
                            $deleteItemsStmt->execute($deleteItemsParams);
                            
                            // Delete purchase orders
                            $deletePOSQL = "DELETE FROM purchase_orders WHERE supplier_id = ?";
                            $deletePOParams = [$supplierId];
                            
                            if ($companyId) {
                                $deletePOSQL .= " AND company_id = ?";
                                $deletePOParams[] = $companyId;
                            }
                            
                            $deletePOStmt = $this->db->prepare($deletePOSQL);
                            $deletePOStmt->execute($deletePOParams);
                            
                        } finally {
                            // Re-enable foreign key checks
                            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
                        }
                    }
                }
            }
            
            // Delete supplier_products relationships
            $sql2 = "DELETE FROM supplier_products WHERE supplier_id = ?";
            $params2 = [$supplierId];
            
            if ($companyId) {
                $sql2 .= " AND company_id = ?";
                $params2[] = $companyId;
            }
            
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute($params2);
            
            // Delete supplier_product_tracking if table exists
            $tableExists = $this->db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            if ($tableExists) {
                $sql3 = "DELETE FROM supplier_product_tracking WHERE supplier_id = ?";
                $params3 = [$supplierId];
                
                if ($companyId) {
                    $sql3 .= " AND company_id = ?";
                    $params3[] = $companyId;
                }
                
                $stmt3 = $this->db->prepare($sql3);
                $stmt3->execute($params3);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error unassigning inventory from supplier: " . $e->getMessage());
            throw $e; // Re-throw to let controller handle it
        }
    }

    /**
     * Delete a supplier
     */
    public function delete($id, $companyId = null) {
        $sql = "DELETE FROM suppliers WHERE id = ?";
        $params = [$id];
        
        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get supplier statistics
     */
    public function getStats($supplierId, $companyId = null) {
        // First get the supplier name
        $supplier = $this->find($supplierId, $companyId);
        if (!$supplier) {
            return [
                'total_orders' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'total_spent' => 0,
                'unpaid_amount' => 0,
                'total_products' => 0
            ];
        }
        $supplierName = $supplier['name'];
        
        // Get purchase order stats
        $sql = "
            SELECT 
                COALESCE(COUNT(DISTINCT po.id), 0) as total_orders,
                COALESCE(COUNT(DISTINCT CASE WHEN po.status = 'received' THEN po.id END), 0) as completed_orders,
                COALESCE(COUNT(DISTINCT CASE WHEN po.status = 'pending' THEN po.id END), 0) as pending_orders,
                COALESCE(SUM(CASE WHEN po.status = 'received' THEN po.total_amount ELSE 0 END), 0) as total_spent,
                COALESCE(SUM(CASE WHEN po.payment_status = 'unpaid' THEN po.total_amount ELSE 0 END), 0) as unpaid_amount
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            WHERE s.id = ?
        ";
        
        $params = [$supplierId];
        
        if ($companyId) {
            $sql .= " AND s.company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get product count separately
        $productSql = "
            SELECT 
                COALESCE(COUNT(DISTINCT sp.product_id), 0) as linked_products
            FROM suppliers s
            LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
            WHERE s.id = ?
        ";
        
        $productParams = [$supplierId];
        if ($companyId) {
            $productSql .= " AND s.company_id = ?";
            $productParams[] = $companyId;
        }
        
        $productStmt = $this->db->prepare($productSql);
        $productStmt->execute($productParams);
        $productResult = $productStmt->fetch(PDO::FETCH_ASSOC);
        $linkedProducts = $productResult['linked_products'] ?? 0;
        
        // Get products from supplier field
        $fieldProductSql = "
            SELECT COUNT(DISTINCT p.id) as field_products
            FROM products p
            WHERE p.supplier = ? AND p.company_id = ?
            AND p.id NOT IN (
                SELECT product_id FROM supplier_products WHERE supplier_id = ?
            )
        ";
        
        $fieldProductParams = [$supplierName, $companyId, $supplierId];
        $fieldProductStmt = $this->db->prepare($fieldProductSql);
        $fieldProductStmt->execute($fieldProductParams);
        $fieldProductResult = $fieldProductStmt->fetch(PDO::FETCH_ASSOC);
        $fieldProducts = $fieldProductResult['field_products'] ?? 0;
        
        // Combine all stats
        $stats['total_products'] = (int)$linkedProducts + (int)$fieldProducts;
        
        return $stats;
    }

    /**
     * Get products supplied by this supplier
     * Includes products from supplier_products table AND products with supplier name in supplier field
     */
    public function getProducts($supplierId, $companyId = null) {
        // First, get the supplier name
        $supplier = $this->find($supplierId, $companyId);
        if (!$supplier) {
            return [];
        }
        $supplierName = $supplier['name'];
        
        // Build the query to get products from both sources
        $sql = "
            SELECT 
                sp.id,
                sp.supplier_id,
                sp.product_id,
                sp.supplier_product_code,
                sp.unit_cost,
                sp.minimum_order_quantity,
                sp.lead_time_days,
                sp.is_preferred,
                sp.notes,
                p.name as product_name, 
                p.sku as product_sku, 
                p.price as current_price, 
                p.quantity as current_quantity,
                'linked' as source
            FROM supplier_products sp
            INNER JOIN products p ON sp.product_id = p.id
            WHERE sp.supplier_id = ?
        ";
        
        $params = [$supplierId];
        
        if ($companyId) {
            $sql .= " AND sp.company_id = ?";
            $params[] = $companyId;
        }
        
        // Also include products that have the supplier name in the supplier field
        $sql .= "
            UNION
            SELECT 
                NULL as id,
                ? as supplier_id,
                p.id as product_id,
                NULL as supplier_product_code,
                NULL as unit_cost,
                NULL as minimum_order_quantity,
                NULL as lead_time_days,
                0 as is_preferred,
                NULL as notes,
                p.name as product_name,
                p.sku as product_sku,
                p.price as current_price,
                p.quantity as current_quantity,
                'field' as source
            FROM products p
            WHERE p.supplier = ? AND p.company_id = ?
            AND p.id NOT IN (
                SELECT product_id FROM supplier_products WHERE supplier_id = ?
            )
        ";
        
        $params[] = $supplierId; // For the UNION query supplier_id
        $params[] = $supplierName; // For the supplier name match
        $params[] = $companyId; // For company_id in products
        $params[] = $supplierId; // For the NOT IN clause
        
        $sql .= " ORDER BY is_preferred DESC, product_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Link a product to a supplier
     */
    public function linkProduct($supplierId, $productId, $companyId, array $data = []) {
        $stmt = $this->db->prepare("
            INSERT INTO supplier_products (
                company_id, supplier_id, product_id, supplier_product_code,
                unit_cost, minimum_order_quantity, lead_time_days, is_preferred, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                supplier_product_code = VALUES(supplier_product_code),
                unit_cost = VALUES(unit_cost),
                minimum_order_quantity = VALUES(minimum_order_quantity),
                lead_time_days = VALUES(lead_time_days),
                is_preferred = VALUES(is_preferred),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            $companyId,
            $supplierId,
            $productId,
            $data['supplier_product_code'] ?? null,
            $data['unit_cost'] ?? null,
            $data['minimum_order_quantity'] ?? 1,
            $data['lead_time_days'] ?? null,
            $data['is_preferred'] ?? 0,
            $data['notes'] ?? null
        ]);
    }

    /**
     * Unlink a product from a supplier
     */
    public function unlinkProduct($supplierId, $productId) {
        $stmt = $this->db->prepare("
            DELETE FROM supplier_products 
            WHERE supplier_id = ? AND product_id = ?
        ");
        
        return $stmt->execute([$supplierId, $productId]);
    }

    /**
     * Get all active suppliers for dropdown
     */
    public function getActiveForDropdown($companyId) {
        $stmt = $this->db->prepare("
            SELECT id, name, contact_person, phone
            FROM suppliers
            WHERE company_id = ? AND status = 'active'
            ORDER BY name ASC
        ");
        
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

