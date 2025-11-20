<?php

namespace App\Controllers;

use App\Models\Supplier;
use App\Models\Product;

class SupplierController {
    private $supplier;
    private $product;

    public function __construct() {
        $this->supplier = new Supplier();
        $this->product = new Product();
    }

    /**
     * Display suppliers list
     */
    public function index() {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 10;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $suppliers = $this->supplier->findByCompanyPaginated($companyId, $currentPage, $itemsPerPage, $status, $search);
        $totalItems = $this->supplier->getTotalCountByCompany($companyId, $status, $search);
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/suppliers'
        );
        
        $page = 'suppliers';
        $title = 'Supplier Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/suppliers_index.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show create supplier form
     */
    public function create() {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        $page = 'suppliers';
        $title = 'Add New Supplier';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/suppliers_form.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Store new supplier
     */
    public function store() {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $userId = $_SESSION['user']['id'] ?? 1;
            
            // Validate required fields
            if (empty($_POST['name'])) {
                throw new \Exception('Supplier name is required');
            }
            
            $supplierId = $this->supplier->create([
                'company_id' => $companyId,
                'name' => trim($_POST['name']),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'alternate_phone' => trim($_POST['alternate_phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'tax_id' => trim($_POST['tax_id'] ?? ''),
                'payment_terms' => trim($_POST['payment_terms'] ?? ''),
                'credit_limit' => floatval($_POST['credit_limit'] ?? 0),
                'notes' => trim($_POST['notes'] ?? ''),
                'status' => $_POST['status'] ?? 'active',
                'created_by' => $userId
            ]);
            
            if ($supplierId) {
                $_SESSION['flash_success'] = 'Supplier created successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
            } else {
                throw new \Exception('Failed to create supplier');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers/create');
        }
        exit;
    }

    /**
     * Show edit supplier form
     */
    public function edit($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $supplier = $this->supplier->find($id, $companyId);
        
        if (!$supplier) {
            $_SESSION['flash_error'] = 'Supplier not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
            exit;
        }
        
        $page = 'suppliers';
        $title = 'Edit Supplier';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/suppliers_form.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Update supplier
     */
    public function update($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            
            // Validate required fields
            if (empty($_POST['name'])) {
                throw new \Exception('Supplier name is required');
            }
            
            $success = $this->supplier->update($id, [
                'name' => trim($_POST['name']),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'alternate_phone' => trim($_POST['alternate_phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'tax_id' => trim($_POST['tax_id'] ?? ''),
                'payment_terms' => trim($_POST['payment_terms'] ?? ''),
                'credit_limit' => floatval($_POST['credit_limit'] ?? 0),
                'notes' => trim($_POST['notes'] ?? ''),
                'status' => $_POST['status'] ?? 'active'
            ], $companyId);
            
            if ($success) {
                $_SESSION['flash_success'] = 'Supplier updated successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
            } else {
                throw new \Exception('Failed to update supplier');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers/edit/' . $id);
        }
        exit;
    }

    /**
     * View supplier details
     */
    public function view($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $supplier = $this->supplier->find($id, $companyId);
        
        if (!$supplier) {
            $_SESSION['flash_error'] = 'Supplier not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
            exit;
        }
        
        // Get supplier statistics
        $stats = $this->supplier->getStats($id, $companyId);
        
        // Get products supplied by this supplier
        $products = $this->supplier->getProducts($id, $companyId);
        
        // Get tracking data for products
        $trackingData = $this->getSupplierTrackingData($id, $companyId);
        
        // Get all products for the company (for adding to supplier)
        $productModel = new \App\Models\Product();
        $allProducts = $productModel->findByCompany($companyId, 1000);
        
        $page = 'suppliers';
        $title = 'Supplier Details';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/suppliers_view.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Delete supplier
     * For admin: allows deletion even with purchase orders
     * Inventory is unassigned (not deleted) when supplier is deleted
     */
    public function delete($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $userRole = $_SESSION['user']['role'] ?? 'manager';
            $supplier = $this->supplier->find($id, $companyId);
            
            if (!$supplier) {
                $_SESSION['flash_error'] = 'Supplier not found';
                header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
                exit;
            }
            
            // Managers and admins can delete suppliers even with purchase orders
            // Only restrict if user is not manager, admin, or system_admin
            $stats = $this->supplier->getStats($id, $companyId);
            $allowedRoles = ['system_admin', 'admin', 'manager'];
            if ($stats['total_orders'] > 0 && !in_array($userRole, $allowedRoles)) {
                $_SESSION['flash_error'] = "Cannot delete supplier. They have {$stats['total_orders']} purchase order(s). Only managers, admins, and system admins can delete suppliers with purchase orders.";
                header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
                exit;
            }
            
            // Unassign inventory and purchase orders from supplier before deletion
            try {
                $this->supplier->unassignInventory($id, $companyId);
            } catch (\Exception $e) {
                // If unassigning fails (e.g., foreign key constraint), show the error
                $_SESSION['flash_error'] = $e->getMessage();
                header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
                exit;
            }
            
            $success = $this->supplier->delete($id, $companyId);
            
            if ($success) {
                $_SESSION['flash_success'] = 'Supplier deleted successfully. Inventory items have been unassigned and associated purchase orders have been deleted.';
            } else {
                throw new \Exception('Failed to delete supplier');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/suppliers');
        exit;
    }

    /**
     * API endpoint: Get suppliers for dropdown
     */
    public function apiGetSuppliers() {
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $suppliers = $this->supplier->getActiveForDropdown($companyId);
            
            echo json_encode([
                'success' => true,
                'data' => $suppliers
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
        exit;
    }

    /**
     * Link product to supplier and create tracking
     */
    public function linkProduct() {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $companyId = $_SESSION['user']['company_id'] ?? null;
            
            if (!$companyId) {
                throw new \Exception('Company ID not found');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $supplierId = (int)($data['supplier_id'] ?? 0);
            $productId = (int)($data['product_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 0);
            $unitCost = floatval($data['unit_cost'] ?? $data['unit_cost'] ?? 0);
            $supplierProductCode = $data['supplier_product_code'] ?? null;
            
            if (!$supplierId || !$productId) {
                throw new \Exception('Supplier ID and Product ID are required');
            }
            
            // Verify supplier exists
            $supplier = $this->supplier->find($supplierId, $companyId);
            if (!$supplier) {
                throw new \Exception('Supplier not found');
            }
            
            // Verify product exists
            $product = $this->product->find($productId, $companyId);
            if (!$product) {
                throw new \Exception('Product not found');
            }
            
            // Link product to supplier (support both old and new parameter formats)
            $linkData = [
                'supplier_product_code' => $supplierProductCode,
                'unit_cost' => $unitCost,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? 1,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'is_preferred' => $data['is_preferred'] ?? 0,
                'notes' => $data['notes'] ?? null
            ];
            
            $success = $this->supplier->linkProduct($supplierId, $productId, $companyId, $linkData);
            
            if (!$success) {
                throw new \Exception('Failed to link product to supplier');
            }
            
            // Update product's supplier_id
            $db = \Database::getInstance()->getConnection();
            $hasSupplierId = $db->query("SHOW COLUMNS FROM products LIKE 'supplier_id'")->rowCount() > 0;
            if ($hasSupplierId) {
                $updateStmt = $db->prepare("UPDATE products SET supplier_id = ? WHERE id = ? AND company_id = ?");
                $updateStmt->execute([$supplierId, $productId, $companyId]);
            }
            
            // Create or update tracking record if quantity and cost provided
            if ($quantity > 0 && $unitCost > 0) {
                $amount = $quantity * $unitCost;
                $this->updateSupplierTracking($companyId, $supplierId, $productId, $quantity, $amount);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Product linked to supplier successfully'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Get supplier tracking data for products
     */
    private function getSupplierTrackingData($supplierId, $companyId) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if tracking table exists
            $tableExists = $db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            if (!$tableExists) {
                return [];
            }
            
            $stmt = $db->prepare("
                SELECT 
                    spt.product_id,
                    spt.total_quantity_received,
                    spt.total_amount_spent,
                    spt.last_restock_quantity,
                    spt.last_restock_amount,
                    spt.last_restock_date,
                    spt.first_received_date,
                    p.name as product_name,
                    p.quantity as current_quantity
                FROM supplier_product_tracking spt
                INNER JOIN products p ON spt.product_id = p.id
                WHERE spt.supplier_id = ? AND spt.company_id = ?
                ORDER BY spt.last_restock_date DESC
            ");
            $stmt->execute([$supplierId, $companyId]);
            $tracking = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Convert to associative array by product_id
            $result = [];
            foreach ($tracking as $record) {
                $result[$record['product_id']] = $record;
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("Error getting supplier tracking data: " . $e->getMessage());
            return [];
        }
    }
    
    
    /**
     * Update supplier product tracking
     */
    private function updateSupplierTracking($companyId, $supplierId, $productId, $quantity, $amount) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if tracking table exists
            $tableExists = $db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            if (!$tableExists) {
                return;
            }
            
            // Check if record exists
            $checkStmt = $db->prepare("
                SELECT id, total_quantity_received, total_amount_spent 
                FROM supplier_product_tracking 
                WHERE supplier_id = ? AND product_id = ? AND company_id = ?
            ");
            $checkStmt->execute([$supplierId, $productId, $companyId]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing record
                $newTotalQuantity = $existing['total_quantity_received'] + $quantity;
                $newTotalAmount = $existing['total_amount_spent'] + $amount;
                
                $updateStmt = $db->prepare("
                    UPDATE supplier_product_tracking 
                    SET total_quantity_received = ?,
                        total_amount_spent = ?,
                        last_restock_quantity = ?,
                        last_restock_amount = ?,
                        last_restock_date = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $newTotalQuantity,
                    $newTotalAmount,
                    $quantity,
                    $amount,
                    $existing['id']
                ]);
            } else {
                // Create new record
                $insertStmt = $db->prepare("
                    INSERT INTO supplier_product_tracking 
                    (company_id, supplier_id, product_id, total_quantity_received, total_amount_spent,
                     last_restock_quantity, last_restock_amount, last_restock_date, first_received_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                ");
                $insertStmt->execute([
                    $companyId,
                    $supplierId,
                    $productId,
                    $quantity,
                    $amount,
                    $quantity,
                    $amount
                ]);
            }
        } catch (\Exception $e) {
            error_log("Error updating supplier tracking: " . $e->getMessage());
        }
    }
}

