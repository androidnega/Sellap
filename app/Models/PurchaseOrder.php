<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class PurchaseOrder {
    private $db;
    private $table = 'purchase_orders';
    private $itemsTable = 'purchase_order_items';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Generate unique order number
     */
    public function generateOrderNumber($companyId) {
        $prefix = 'PO-' . date('Ymd') . '-';
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM purchase_orders 
            WHERE company_id = ? AND order_number LIKE ?
        ");
        $stmt->execute([$companyId, $prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = ($result['count'] ?? 0) + 1;
        return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get all purchase orders for a company
     */
    public function findByCompany($companyId, $status = null, $supplierId = null) {
        $sql = "
            SELECT po.*, s.name as supplier_name, s.contact_person as supplier_contact,
                   u.full_name as created_by_name,
                   COUNT(poi.id) as item_count
            FROM purchase_orders po
            INNER JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN users u ON po.created_by = u.id
            LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
            WHERE po.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND po.status = ?";
            $params[] = $status;
        }
        
        if ($supplierId) {
            $sql .= " AND po.supplier_id = ?";
            $params[] = $supplierId;
        }
        
        $sql .= " GROUP BY po.id ORDER BY po.order_date DESC, po.id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get purchase orders with pagination
     */
    public function findByCompanyPaginated($companyId, $page = 1, $limit = 10, $status = null, $supplierId = null, $search = null) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT po.*, s.name as supplier_name, s.contact_person as supplier_contact,
                   u.full_name as created_by_name,
                   COUNT(poi.id) as item_count
            FROM purchase_orders po
            INNER JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN users u ON po.created_by = u.id
            LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
            WHERE po.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND po.status = ?";
            $params[] = $status;
        }
        
        if ($supplierId) {
            $sql .= " AND po.supplier_id = ?";
            $params[] = $supplierId;
        }
        
        if ($search) {
            $sql .= " AND (po.order_number LIKE ? OR s.name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY po.id ORDER BY po.order_date DESC, po.id DESC LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of purchase orders
     */
    public function getTotalCountByCompany($companyId, $status = null, $supplierId = null, $search = null) {
        $sql = "
            SELECT COUNT(DISTINCT po.id) as total 
            FROM purchase_orders po
            INNER JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND po.status = ?";
            $params[] = $status;
        }
        
        if ($supplierId) {
            $sql .= " AND po.supplier_id = ?";
            $params[] = $supplierId;
        }
        
        if ($search) {
            $sql .= " AND (po.order_number LIKE ? OR s.name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Find purchase order by ID
     */
    public function find($id, $companyId = null) {
        $sql = "
            SELECT po.*, s.name as supplier_name, s.contact_person, s.email as supplier_email,
                   s.phone as supplier_phone, s.address as supplier_address,
                   u.full_name as created_by_name
            FROM purchase_orders po
            INNER JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN users u ON po.created_by = u.id
            WHERE po.id = ?
        ";
        
        $params = [$id];
        
        if ($companyId) {
            $sql .= " AND po.company_id = ?";
            $params[] = $companyId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new purchase order
     */
    public function create(array $data) {
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Generate order number if not provided
            if (empty($data['order_number'])) {
                $data['order_number'] = $this->generateOrderNumber($data['company_id']);
            }
            
            // Calculate totals if items are provided
            $subtotal = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $subtotal += ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0);
                }
            }
            
            $taxAmount = $data['tax_amount'] ?? 0;
            $shippingCost = $data['shipping_cost'] ?? 0;
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount + $shippingCost - $discountAmount;
            
            // Insert purchase order
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    company_id, supplier_id, order_number, order_date, expected_delivery_date,
                    status, subtotal, tax_amount, shipping_cost, discount_amount, total_amount,
                    payment_status, payment_method, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['supplier_id'],
                $data['order_number'],
                $data['order_date'] ?? date('Y-m-d'),
                $data['expected_delivery_date'] ?? null,
                $data['status'] ?? 'draft',
                $subtotal,
                $taxAmount,
                $shippingCost,
                $discountAmount,
                $totalAmount,
                $data['payment_status'] ?? 'unpaid',
                $data['payment_method'] ?? null,
                $data['notes'] ?? null,
                $data['created_by']
            ]);
            
            $orderId = $this->db->lastInsertId();
            
            // Insert items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($orderId, $item);
                }
            }
            
            // Commit transaction
            $this->db->commit();
            return $orderId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update a purchase order
     */
    public function update($id, array $data, $companyId = null) {
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Recalculate totals if items are provided
            $subtotal = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $subtotal += ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0);
                }
            } else {
                // Get existing subtotal if items not provided
                $existing = $this->find($id, $companyId);
                $subtotal = $existing['subtotal'] ?? 0;
            }
            
            $taxAmount = $data['tax_amount'] ?? 0;
            $shippingCost = $data['shipping_cost'] ?? 0;
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount + $shippingCost - $discountAmount;
            
            // Update purchase order
            $sql = "
                UPDATE purchase_orders SET
                    supplier_id = ?, order_date = ?, expected_delivery_date = ?,
                    status = ?, subtotal = ?, tax_amount = ?, shipping_cost = ?,
                    discount_amount = ?, total_amount = ?, payment_status = ?,
                    payment_method = ?, notes = ?
                WHERE id = ?
            ";
            
            $params = [
                $data['supplier_id'] ?? null,
                $data['order_date'] ?? null,
                $data['expected_delivery_date'] ?? null,
                $data['status'] ?? null,
                $subtotal,
                $taxAmount,
                $shippingCost,
                $discountAmount,
                $totalAmount,
                $data['payment_status'] ?? null,
                $data['payment_method'] ?? null,
                $data['notes'] ?? null,
                $id
            ];
            
            if ($companyId) {
                $sql .= " AND company_id = ?";
                $params[] = $companyId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Update items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $this->deleteItems($id);
                
                // Add new items
                foreach ($data['items'] as $item) {
                    $this->addItem($id, $item);
                }
            }
            
            // Commit transaction
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a purchase order
     */
    public function delete($id, $companyId = null) {
        // Items will be deleted via CASCADE
        $sql = "DELETE FROM purchase_orders WHERE id = ?";
        $params = [$id];
        
        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Add item to purchase order
     */
    public function addItem($orderId, array $item) {
        $totalCost = ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0);
        
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_items (
                purchase_order_id, product_id, product_name, product_sku,
                quantity, unit_cost, total_cost, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $orderId,
            $item['product_id'] ?? null,
            $item['product_name'] ?? '',
            $item['product_sku'] ?? null,
            $item['quantity'] ?? 1,
            $item['unit_cost'] ?? 0,
            $totalCost,
            $item['notes'] ?? null
        ]);
    }

    /**
     * Get items for a purchase order
     */
    public function getItems($orderId) {
        $stmt = $this->db->prepare("
            SELECT poi.*, p.name as current_product_name, p.sku as current_product_sku
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.purchase_order_id = ?
            ORDER BY poi.id ASC
        ");
        
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete all items for a purchase order
     */
    private function deleteItems($orderId) {
        $stmt = $this->db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
        return $stmt->execute([$orderId]);
    }

    /**
     * Update received quantity for an item
     */
    public function updateReceivedQuantity($itemId, $quantityReceived) {
        $stmt = $this->db->prepare("
            UPDATE purchase_order_items 
            SET quantity_received = ? 
            WHERE id = ?
        ");
        
        return $stmt->execute([$quantityReceived, $itemId]);
    }

    /**
     * Mark purchase order as received
     */
    public function markAsReceived($id, $companyId = null) {
        $sql = "UPDATE purchase_orders SET status = 'received', delivery_date = CURDATE() WHERE id = ?";
        $params = [$id];
        
        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get purchase order statistics
     */
    public function getStats($companyId, $supplierId = null) {
        $sql = "
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'received' THEN 1 END) as received_orders,
                SUM(CASE WHEN status = 'received' THEN total_amount ELSE 0 END) as total_spent,
                SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount ELSE 0 END) as unpaid_amount
            FROM purchase_orders
            WHERE company_id = ?
        ";
        
        $params = [$companyId];
        
        if ($supplierId) {
            $sql .= " AND supplier_id = ?";
            $params[] = $supplierId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

