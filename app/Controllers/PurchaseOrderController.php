<?php

namespace App\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;

class PurchaseOrderController {
    private $purchaseOrder;
    private $supplier;
    private $product;

    public function __construct() {
        $this->purchaseOrder = new PurchaseOrder();
        $this->supplier = new Supplier();
        $this->product = new Product();
    }

    /**
     * Display purchase orders list
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
        $supplierId = $_GET['supplier_id'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $orders = $this->purchaseOrder->findByCompanyPaginated($companyId, $currentPage, $itemsPerPage, $status, $supplierId, $search);
        $totalItems = $this->purchaseOrder->getTotalCountByCompany($companyId, $status, $supplierId, $search);
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/purchase-orders'
        );
        
        // Get suppliers for filter dropdown
        $suppliers = $this->supplier->getActiveForDropdown($companyId);
        
        // Get statistics
        $stats = $this->purchaseOrder->getStats($companyId);
        
        $page = 'purchase_orders';
        $title = 'Purchase Orders';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/purchase_orders_index.php';
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
     * Show create purchase order form
     */
    public function create() {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $suppliers = $this->supplier->getActiveForDropdown($companyId);
        
        $page = 'purchase_orders';
        $title = 'Create Purchase Order';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/purchase_orders_form.php';
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
     * Store new purchase order
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
            if (empty($_POST['supplier_id'])) {
                throw new \Exception('Supplier is required');
            }
            
            if (empty($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) == 0) {
                throw new \Exception('At least one item is required');
            }
            
            // Prepare items
            $items = [];
            foreach ($_POST['items'] as $item) {
                if (empty($item['product_name']) || empty($item['quantity']) || empty($item['unit_cost'])) {
                    continue; // Skip invalid items
                }
                
                $items[] = [
                    'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    'product_name' => trim($item['product_name']),
                    'product_sku' => trim($item['product_sku'] ?? ''),
                    'quantity' => (int)$item['quantity'],
                    'unit_cost' => floatval($item['unit_cost']),
                    'notes' => trim($item['notes'] ?? '')
                ];
            }
            
            if (empty($items)) {
                throw new \Exception('At least one valid item is required');
            }
            
            $orderId = $this->purchaseOrder->create([
                'company_id' => $companyId,
                'supplier_id' => (int)$_POST['supplier_id'],
                'order_date' => $_POST['order_date'] ?? date('Y-m-d'),
                'expected_delivery_date' => !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null,
                'status' => $_POST['status'] ?? 'draft',
                'tax_amount' => floatval($_POST['tax_amount'] ?? 0),
                'shipping_cost' => floatval($_POST['shipping_cost'] ?? 0),
                'discount_amount' => floatval($_POST['discount_amount'] ?? 0),
                'payment_method' => trim($_POST['payment_method'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'items' => $items,
                'created_by' => $userId
            ]);
            
            if ($orderId) {
                // Purchase orders are for tracking only - no inventory updates
                $_SESSION['flash_success'] = 'Purchase order created successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders/view/' . $orderId);
            } else {
                throw new \Exception('Failed to create purchase order');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders/create');
        }
        exit;
    }

    /**
     * View purchase order details
     */
    public function view($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $order = $this->purchaseOrder->find($id, $companyId);
        
        if (!$order) {
            $_SESSION['flash_error'] = 'Purchase order not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders');
            exit;
        }
        
        // Get order items
        $items = $this->purchaseOrder->getItems($id);
        
        $page = 'purchase_orders';
        $title = 'Purchase Order Details';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/purchase_orders_view.php';
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
     * Show edit purchase order form
     */
    public function edit($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $order = $this->purchaseOrder->find($id, $companyId);
        
        if (!$order) {
            $_SESSION['flash_error'] = 'Purchase order not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders');
            exit;
        }
        
        // Get order items
        $items = $this->purchaseOrder->getItems($id);
        
        // Get suppliers
        $suppliers = $this->supplier->getActiveForDropdown($companyId);
        
        $page = 'purchase_orders';
        $title = 'Edit Purchase Order';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/purchase_orders_form.php';
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
     * Update purchase order
     */
    public function update($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            
            // Get existing order to check status change
            $existingOrder = $this->purchaseOrder->find($id, $companyId);
            if (!$existingOrder) {
                throw new \Exception('Purchase order not found');
            }
            
            $oldStatus = $existingOrder['status'];
            $newStatus = $_POST['status'] ?? $oldStatus;
            
            // Validate required fields
            if (empty($_POST['supplier_id'])) {
                throw new \Exception('Supplier is required');
            }
            
            // Prepare items
            $items = [];
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (empty($item['product_name']) || empty($item['quantity']) || empty($item['unit_cost'])) {
                        continue;
                    }
                    
                    $items[] = [
                        'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                        'product_name' => trim($item['product_name']),
                        'product_sku' => trim($item['product_sku'] ?? ''),
                        'quantity' => (int)$item['quantity'],
                        'unit_cost' => floatval($item['unit_cost']),
                        'notes' => trim($item['notes'] ?? '')
                    ];
                }
            }
            
            $success = $this->purchaseOrder->update($id, [
                'supplier_id' => (int)$_POST['supplier_id'],
                'order_date' => $_POST['order_date'] ?? null,
                'expected_delivery_date' => !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null,
                'status' => $newStatus,
                'tax_amount' => floatval($_POST['tax_amount'] ?? 0),
                'shipping_cost' => floatval($_POST['shipping_cost'] ?? 0),
                'discount_amount' => floatval($_POST['discount_amount'] ?? 0),
                'payment_status' => $_POST['payment_status'] ?? null,
                'payment_method' => trim($_POST['payment_method'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'items' => $items
            ], $companyId);
            
            if ($success) {
                // Purchase orders are for tracking only - no inventory updates
                $_SESSION['flash_success'] = 'Purchase order updated successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders/view/' . $id);
            } else {
                throw new \Exception('Failed to update purchase order');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders/edit/' . $id);
        }
        exit;
    }

    /**
     * Mark purchase order as received
     */
    public function markAsReceived($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $success = $this->purchaseOrder->markAsReceived($id, $companyId);
            
            if ($success) {
                // Purchase orders are for tracking only - no inventory updates
                echo json_encode([
                    'success' => true,
                    'message' => 'Purchase order marked as received (tracking only - inventory not updated)'
                ]);
            } else {
                throw new \Exception('Failed to update purchase order');
            }
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
     * Update product quantities when purchase order is received
     */
    private function updateProductQuantities($orderId) {
        $items = $this->purchaseOrder->getItems($orderId);
        
        foreach ($items as $item) {
            if ($item['product_id']) {
                // Get current product
                $product = $this->product->find($item['product_id'], null);
                if ($product) {
                    $newQuantity = ($product['quantity'] ?? 0) + ($item['quantity_received'] > 0 ? $item['quantity_received'] : $item['quantity']);
                    $this->product->updateQuantity($item['product_id'], $newQuantity, $product['company_id']);
                }
            }
        }
    }

    /**
     * Delete purchase order
     * For admin: allows deletion of any purchase order
     * For others: only allows deletion of draft orders
     */
    public function delete($id) {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $userRole = $_SESSION['user']['role'] ?? 'manager';
            $order = $this->purchaseOrder->find($id, $companyId);
            
            if (!$order) {
                $_SESSION['flash_error'] = 'Purchase order not found';
                header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders');
                exit;
            }
            
            // Only allow deletion of draft orders for non-admin users
            if ($order['status'] !== 'draft' && $userRole !== 'system_admin') {
                $_SESSION['flash_error'] = 'Only draft purchase orders can be deleted. Only system admins can delete purchase orders with other statuses.';
                header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders');
                exit;
            }
            
            $success = $this->purchaseOrder->delete($id, $companyId);
            
            if ($success) {
                $_SESSION['flash_success'] = 'Purchase order deleted successfully';
            } else {
                throw new \Exception('Failed to delete purchase order');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/purchase-orders');
        exit;
    }

    /**
     * API endpoint: Get products for purchase order (search)
     */
    public function apiSearchProducts() {
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $query = trim($_GET['q'] ?? '');
            
            if (empty($query)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            $products = $this->product->search($companyId, $query);
            
            echo json_encode([
                'success' => true,
                'data' => $products
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
}

