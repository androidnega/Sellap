<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Company;
use App\Middleware\AuthMiddleware;
use App\Helpers\PaginationHelper;

class RestockController
{
    private $productModel;
    private $companyModel;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->companyModel = new Company();
        $this->ensureRestockLogsMigration();
    }
    
    /**
     * Ensure restock_logs table has lifecycle tracking columns
     */
    private function ensureRestockLogsMigration()
    {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $db = \Database::getInstance()->getConnection();
            
            // Check if columns already exist
            $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'quantity_at_restock'");
            if ($checkColumns->rowCount() > 0) {
                return; // Migration already applied
            }
            
            // Apply migration
            try {
                $db->exec("
                    ALTER TABLE restock_logs 
                    ADD COLUMN quantity_at_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity in stock when restocked',
                    ADD COLUMN quantity_after_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity after restock',
                    ADD COLUMN sold_out_date DATETIME NULL COMMENT 'Date when this restock batch was fully sold out',
                    ADD COLUMN user_id BIGINT UNSIGNED NULL COMMENT 'User who performed the restock',
                    ADD COLUMN status VARCHAR(20) DEFAULT 'active' COMMENT 'active, sold_out, cancelled'
                ");
                
                // Add indexes
                try {
                    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_sold_out_date (sold_out_date)");
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                try {
                    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_status (status)");
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                try {
                    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_user_id (user_id)");
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                error_log("RestockController: Successfully applied lifecycle tracking migration");
            } catch (\Exception $e) {
                error_log("RestockController: Migration error - " . $e->getMessage());
                // Continue anyway - code has fallback logic
            }
        } catch (\Exception $e) {
            error_log("RestockController: Error checking/applying migration - " . $e->getMessage());
            // Continue anyway - code has fallback logic
        }
    }

    /**
     * Display restock index page
     */
    public function index()
    {
        $payload = \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $payload->company_id;

        // Get pagination parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get search parameters
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $stockStatus = $_GET['stock_status'] ?? '';

        // Build query conditions
        $conditions = ['company_id = ?'];
        $params = [$companyId];

        if (!empty($search)) {
            $conditions[] = '(name LIKE ? OR product_id LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($category)) {
            $conditions[] = 'category_id = ?';
            $params[] = $category;
        }

        if ($stockStatus === 'low') {
            $conditions[] = 'quantity <= 10';
        } elseif ($stockStatus === 'out') {
            $conditions[] = 'quantity = 0';
        }

        $whereClause = implode(' AND ', $conditions);

        // Get products with pagination
        $products = $this->productModel->getProductsForRestock($whereClause, $params, $limit, $offset);
        $totalProducts = $this->productModel->getProductsCountForRestock($whereClause, $params);

        // Get categories for filter
        $categories = $this->productModel->getCategoriesByCompany($companyId);

        // Build pagination URL with search and filter params preserved
        $paginationBaseUrl = BASE_URL_PATH . '/dashboard/restock';
        $queryParams = [];
        if (!empty($search)) {
            $queryParams[] = 'search=' . urlencode($search);
        }
        if (!empty($category)) {
            $queryParams[] = 'category=' . urlencode($category);
        }
        if (!empty($stockStatus)) {
            $queryParams[] = 'stock_status=' . urlencode($stockStatus);
        }
        $paginationUrl = $paginationBaseUrl;
        if (!empty($queryParams)) {
            $paginationUrl .= '?' . implode('&', $queryParams);
        }

        // Get pagination
        $paginationData = PaginationHelper::generate($page, $totalProducts, $limit, $paginationUrl);
        $pagination = PaginationHelper::render($paginationData);

        $this->renderRestockIndex($products, $categories, $pagination, $search, $category, $stockStatus);
    }

    /**
     * Show restock form for a specific product
     */
    public function show($id)
    {
        $payload = \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $payload->company_id;

        // Use products table for restock flows
        $product = $this->productModel->findInNew($id, $companyId);
        
        if (!$product || $product['company_id'] != $companyId) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
            exit;
        }

        $this->renderRestockForm($product);
    }

    /**
     * Process restock form submission
     */
    public function update($id)
    {
        $payload = \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $payload->company_id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
            exit;
        }

        $product = $this->productModel->findInNew($id, $companyId);
        
        if (!$product || $product['company_id'] != $companyId) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
            exit;
        }

        // Validate input
        $quantityToAdd = (int)($_POST['quantity_to_add'] ?? 0);
        $newCost = floatval($_POST['new_cost'] ?? $product['cost']);
        $newPrice = floatval($_POST['new_price'] ?? $product['price']);
        $notes = trim($_POST['notes'] ?? '');

        if ($quantityToAdd < 0) {
            $_SESSION['flash_error'] = 'Quantity to add cannot be negative';
            header("Location: " . BASE_URL_PATH . "/dashboard/restock/$id");
            exit;
        }

        if ($newCost < 0 || $newPrice < 0) {
            $_SESSION['flash_error'] = 'Cost and price cannot be negative';
            header("Location: " . BASE_URL_PATH . "/dashboard/restock/$id");
            exit;
        }

        // Update product - use direct SQL to avoid the full update method
        require_once __DIR__ . '/../../config/database.php';
        $db = \Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            UPDATE products 
            SET quantity = ?, 
                cost = ?, 
                price = ?, 
                updated_at = ?,
                status = CASE 
                    WHEN ? <= 0 THEN 'out_of_stock' 
                    ELSE 'available' 
                END
            WHERE id = ? AND company_id = ?
        ");
        
        $newQuantity = $product['quantity'] + $quantityToAdd;
        $success = $stmt->execute([
            $newQuantity,
            $newCost,
            $newPrice,
            date('Y-m-d H:i:s'),
            $newQuantity,
            $id,
            $companyId
        ]);

        if ($success) {
            // Log restock activity
            $this->logRestockActivity($id, $quantityToAdd, $newCost, $newPrice, $notes, $companyId);
            
            // Update supplier tracking if product has a supplier
            $this->updateSupplierTrackingOnRestock($companyId, $id, $quantityToAdd, $newCost);
            
            $_SESSION['flash_success'] = "Product restocked successfully! Added $quantityToAdd units.";
            header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
            exit;
        } else {
            $_SESSION['flash_error'] = 'Failed to restock product';
            header("Location: " . BASE_URL_PATH . "/dashboard/restock/$id");
            exit;
        }
    }

    /**
     * Bulk restock multiple products
     */
    public function bulkRestock()
    {
        $payload = \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $payload->company_id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
            exit;
        }

        $restockData = $_POST['restock'] ?? [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($restockData as $productId => $data) {
            if (empty($data['quantity_to_add']) || (int)$data['quantity_to_add'] <= 0) {
                continue;
            }

            $product = $this->productModel->findInNew($productId, $companyId);
            if (!$product || $product['company_id'] != $companyId) {
                $errorCount++;
                continue;
            }

            $quantityToAdd = (int)$data['quantity_to_add'];
            $newCost = floatval($data['new_cost'] ?? $product['cost']);
            $newPrice = floatval($data['new_price'] ?? $product['price']);

            // Update product using direct SQL to avoid the full update method
            require_once __DIR__ . '/../../config/database.php';
            $db = \Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                UPDATE products 
                SET quantity = ?, 
                    cost = ?, 
                    price = ?, 
                    updated_at = ?,
                    status = CASE 
                        WHEN ? <= 0 THEN 'out_of_stock' 
                        ELSE 'available' 
                    END
                WHERE id = ? AND company_id = ?
            ");
            
            $newQuantity = $product['quantity'] + $quantityToAdd;
            $success = $stmt->execute([
                $newQuantity,
                $newCost,
                $newPrice,
                date('Y-m-d H:i:s'),
                $newQuantity,
                $productId,
                $companyId
            ]);

            if ($success) {
                $this->logRestockActivity($productId, $quantityToAdd, $newCost, $newPrice, '', $companyId);
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        if ($successCount > 0) {
            $_SESSION['flash_success'] = "Bulk restock completed! $successCount products updated successfully.";
        }
        if ($errorCount > 0) {
            $_SESSION['flash_error'] = "$errorCount products failed to update.";
        }

        header('Location: ' . BASE_URL_PATH . '/dashboard/restock');
        exit;
    }

    /**
     * Get restock history
     */
    public function history()
    {
        $payload = \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $payload->company_id;

        // Get pagination parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get restock history
        $history = $this->getRestockHistory($companyId, $limit, $offset);
        $totalHistory = $this->getRestockHistoryCount($companyId);

        // Get pagination
        $paginationData = PaginationHelper::generate($page, $totalHistory, $limit, '/dashboard/restock/history');
        $pagination = PaginationHelper::render($paginationData);

        $this->renderRestockHistory($history, $pagination);
    }

    /**
     * Log restock activity
     */
    private function logRestockActivity($productId, $quantityAdded, $newCost, $newPrice, $notes, $companyId)
    {
        $db = \Database::getInstance()->getConnection();
        
        // Get current quantity before restock
        $product = $this->productModel->findInNew($productId, $companyId);
        $quantityAtRestock = $product['quantity'] ?? 0;
        $quantityAfterRestock = $quantityAtRestock + $quantityAdded;
        
        // Get user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user']['id'] ?? null;
        
        // Check if columns exist, use dynamic SQL
        try {
            $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'quantity_at_restock'");
            $hasLifecycleColumns = $checkColumns->rowCount() > 0;
        } catch (\Exception $e) {
            $hasLifecycleColumns = false;
        }
        
        if ($hasLifecycleColumns) {
            $stmt = $db->prepare("
                INSERT INTO restock_logs (
                    product_id, company_id, quantity_added, new_cost, new_price, notes, 
                    quantity_at_restock, quantity_after_restock, user_id, status, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $productId, $companyId, $quantityAdded, $newCost, $newPrice, $notes,
                $quantityAtRestock, $quantityAfterRestock, $userId
            ]);
        } else {
            // Fallback for old table structure
            $stmt = $db->prepare("
                INSERT INTO restock_logs (product_id, company_id, quantity_added, new_cost, new_price, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$productId, $companyId, $quantityAdded, $newCost, $newPrice, $notes]);
        }
    }

    /**
     * Get restock history
     */
    private function getRestockHistory($companyId, $limit, $offset)
    {
        $db = \Database::getInstance()->getConnection();
        
        // Check if lifecycle columns exist
        try {
            $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'quantity_at_restock'");
            $hasLifecycleColumns = $checkColumns->rowCount() > 0;
        } catch (\Exception $e) {
            $hasLifecycleColumns = false;
        }
        
        // Ensure limit and offset are integers (MariaDB doesn't support placeholders for LIMIT/OFFSET)
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        if ($hasLifecycleColumns) {
            $stmt = $db->prepare("
                SELECT 
                    rl.*,
                    p.name as product_name,
                    p.product_id as product_sku,
                    p.quantity as current_quantity,
                    c.name as category_name,
                    u.username as restocked_by,
                    TIMESTAMPDIFF(DAY, rl.created_at, COALESCE(rl.sold_out_date, NOW())) as days_active,
                    CASE 
                        WHEN rl.sold_out_date IS NOT NULL THEN 'Sold Out'
                        WHEN p.quantity <= 0 THEN 'Out of Stock'
                        WHEN rl.quantity_after_restock > 0 AND p.quantity < (rl.quantity_after_restock * 0.1) THEN 'Low Stock'
                        ELSE 'In Stock'
                    END as lifecycle_status
                FROM restock_logs rl
                JOIN products p ON rl.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON rl.user_id = u.id
                WHERE rl.company_id = ?
                ORDER BY rl.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
        } else {
            $stmt = $db->prepare("
                SELECT 
                    rl.*,
                    p.name as product_name,
                    p.product_id as product_sku,
                    p.quantity as current_quantity,
                    c.name as category_name,
                    NULL as restocked_by,
                    NULL as days_active,
                    CASE 
                        WHEN p.quantity <= 0 THEN 'Out of Stock'
                        WHEN p.quantity <= 10 THEN 'Low Stock'
                        ELSE 'In Stock'
                    END as lifecycle_status
                FROM restock_logs rl
                JOIN products p ON rl.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE rl.company_id = ?
                ORDER BY rl.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
        }
        
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Get restock history count
     */
    private function getRestockHistoryCount($companyId)
    {
        $db = \Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM restock_logs rl
            WHERE rl.company_id = ?
        ");
        
        $stmt->execute([$companyId]);
        $result = $stmt->fetch();
        return $result['count'];
    }

    /**
     * Render restock index page
     */
    private function renderRestockIndex($products, $categories, $pagination, $search, $category, $stockStatus)
    {
        $content = '
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Inventory Restock</h1>
                <div class="flex gap-2">
                    <a href="' . BASE_URL_PATH . '/dashboard/restock/history" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <i class="fas fa-history mr-2"></i>Restock History
                    </a>
                    <button onclick="toggleBulkRestock()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-layer-group mr-2"></i>Bulk Restock
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="' . htmlspecialchars($search) . '" 
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="min-w-48">
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>';
        
        foreach ($categories as $cat) {
            $selected = $category == $cat['id'] ? 'selected' : '';
            $content .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }
        
        $content .= '
                        </select>
                    </div>
                    <div class="min-w-48">
                        <select name="stock_status" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Stock Levels</option>
                            <option value="low"' . ($stockStatus === 'low' ? ' selected' : '') . '>Low Stock (≤10)</option>
                            <option value="out"' . ($stockStatus === 'out' ? ' selected' : '') . '>Out of Stock</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </form>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <form id="bulkRestockForm" method="POST" action="' . BASE_URL_PATH . '/dashboard/restock/bulk" style="display: none;">
                    <div class="p-4 bg-yellow-50 border-b">
                        <h3 class="text-lg font-semibold text-yellow-800">Bulk Restock Mode</h3>
                        <p class="text-sm text-yellow-600">Update multiple products at once. Leave fields empty to skip products.</p>
                    </div>
                </form>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selling Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">';

        foreach ($products as $product) {
            $stockClass = $product['quantity'] == 0 ? 'text-red-600 font-bold' : ($product['quantity'] <= 10 ? 'text-yellow-600 font-semibold' : 'text-green-600');
            $stockBadge = $product['quantity'] == 0 ? 'Out of Stock' : ($product['quantity'] <= 10 ? 'Low Stock' : 'In Stock');
            
            $content .= '
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            ' . ($product['image_url'] ? 
                                                '<img class="h-12 w-12 rounded-lg object-cover" src="' . (str_starts_with($product['image_url'], 'http') ? $product['image_url'] : BASE_URL_PATH . '/' . $product['image_url']) . '" alt="' . htmlspecialchars($product['name']) . '">' :
                                                '<div class="h-12 w-12 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-box text-gray-400"></i></div>'
                                            ) . '
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($product['name'] ?? '') . '</div>
                                            <div class="text-sm text-gray-500">SKU: ' . htmlspecialchars($product['product_id'] ?? ('PID-' . str_pad($product['id'] ?? 0, 3, '0', STR_PAD_LEFT))) . '</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="' . $stockClass . '">' . $product['quantity'] . '</span>
                                    <div class="text-xs text-gray-500">' . $stockBadge . '</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₵' . number_format($product['cost'], 2) . '</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₵' . number_format($product['price'], 2) . '</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="' . BASE_URL_PATH . '/dashboard/restock/' . $product['id'] . '" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit mr-1"></i>Restock
                                    </a>
                                    <div class="bulk-restock-fields mt-2" style="display: none;">
                                        <div class="flex gap-2">
                                            <input type="number" name="restock[' . $product['id'] . '][quantity_to_add]" placeholder="Qty" class="w-16 px-2 py-1 text-xs border rounded">
                                            <input type="number" step="0.01" name="restock[' . $product['id'] . '][new_cost]" placeholder="Cost" class="w-20 px-2 py-1 text-xs border rounded">
                                            <input type="number" step="0.01" name="restock[' . $product['id'] . '][new_price]" placeholder="Price" class="w-20 px-2 py-1 text-xs border rounded">
                                        </div>
                                    </div>
                                </td>
                            </tr>';
        }

        $content .= '
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-3 bg-gray-50 border-t">
                    ' . $pagination . '
                </div>
            </div>
        </div>

        <script>
        function toggleBulkRestock() {
            const form = document.getElementById("bulkRestockForm");
            const fields = document.querySelectorAll(".bulk-restock-fields");
            const button = event.target;
            
            if (form.style.display === "none") {
                form.style.display = "block";
                fields.forEach(field => field.style.display = "block");
                button.innerHTML = "<i class=\"fas fa-times mr-2\"></i>Cancel Bulk";
                button.className = "bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700";
            } else {
                form.style.display = "none";
                fields.forEach(field => field.style.display = "none");
                button.innerHTML = "<i class=\"fas fa-layer-group mr-2\"></i>Bulk Restock";
                button.className = "bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700";
            }
        }
        </script>';

        $this->renderWithLayout('Restock Inventory', $content);
    }

    /**
     * Render restock form
     */
    private function renderRestockForm($product)
    {
        $content = '
        <div class="container mx-auto px-4 py-6">
            <div class="max-w-2xl mx-auto">
                <div class="flex items-center mb-6">
                    <a href="' . BASE_URL_PATH . '/dashboard/restock" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i> Back to Restock
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">Restock Product</h1>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <!-- Product Info -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-16 w-16">
                                ' . (!empty($product['image_url']) ? 
                                    '<img class="h-16 w-16 rounded-lg object-cover" src="' . (str_starts_with($product['image_url'], 'http') ? $product['image_url'] : BASE_URL_PATH . '/' . $product['image_url']) . '" alt="' . htmlspecialchars($product['name'] ?? '') . '">' :
                                    '<div class="h-16 w-16 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-box text-gray-400 text-2xl"></i></div>'
                                ) . '
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">' . htmlspecialchars($product['name'] ?? '') . '</h3>
                                <p class="text-sm text-gray-500">SKU: ' . htmlspecialchars($product['product_id'] ?? ('PID-' . str_pad($product['id'] ?? 0, 3, '0', STR_PAD_LEFT))) . '</p>
                                <p class="text-sm text-gray-500">Current Stock: <span class="font-semibold">' . ($product['quantity'] ?? 0) . '</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Restock Form -->
                    <form method="POST" action="' . BASE_URL_PATH . '/dashboard/restock/update/' . $product['id'] . '">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity to Add</label>
                                <input type="number" name="quantity_to_add" min="0" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Enter quantity to add">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Total Stock</label>
                                <input type="number" id="newTotalStock" readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100"
                                       value="' . ($product['quantity'] ?? 0) . '">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Cost Price (₵)</label>
                                <input type="number" name="new_cost" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="' . ($product['cost'] ?? 0) . '">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Selling Price (₵)</label>
                                <input type="number" name="new_price" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="' . ($product['price'] ?? 0) . '">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Add any notes about this restock..."></textarea>
                        </div>
                        
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="' . BASE_URL_PATH . '/dashboard/restock" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Restock Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const quantityInput = document.querySelector("input[name=\"quantity_to_add\"]");
            const newTotalInput = document.getElementById("newTotalStock");
            const currentStock = ' . ($product['quantity'] ?? 0) . ';
            
            quantityInput.addEventListener("input", function() {
                const quantityToAdd = parseInt(this.value) || 0;
                newTotalInput.value = currentStock + quantityToAdd;
            });
        });
        </script>';

        $this->renderWithLayout('Restock Product', $content);
    }

    /**
     * Render restock history
     */
    private function renderRestockHistory($history, $pagination)
    {
        $content = '
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Restock History</h1>
                <a href="' . BASE_URL_PATH . '/dashboard/restock" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Restock
                </a>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Restocked Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Added</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock After</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sold Out Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Restocked By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">';

        foreach ($history as $entry) {
            $soldOutDate = $entry['sold_out_date'] ?? null;
            $soldOutDisplay = $soldOutDate ? date('M j, Y g:i A', strtotime($soldOutDate)) : '<span class="text-gray-400">Not sold out</span>';
            
            $daysActive = $entry['days_active'] ?? null;
            $durationDisplay = $daysActive !== null ? $daysActive . ' days' : 'N/A';
            
            $status = $entry['lifecycle_status'] ?? 'In Stock';
            $statusClass = $status === 'Sold Out' ? 'bg-red-100 text-red-800' : 
                          ($status === 'Out of Stock' ? 'bg-red-100 text-red-800' : 
                          ($status === 'Low Stock' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'));
            
            $quantityAfterRestock = $entry['quantity_after_restock'] ?? ($entry['quantity_added'] ?? 0);
            $currentQuantity = $entry['current_quantity'] ?? 0;
            
            $restockedBy = $entry['restocked_by'] ?? 'System';
            
            $content .= '
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>' . date('M j, Y', strtotime($entry['created_at'])) . '</div>
                                    <div class="text-xs text-gray-500">' . date('g:i A', strtotime($entry['created_at'])) . '</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($entry['product_name']) . '</div>
                                    <div class="text-sm text-gray-500">SKU: ' . htmlspecialchars($entry['product_sku']) . '</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">
                                    +' . $entry['quantity_added'] . '
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>' . $quantityAfterRestock . '</div>
                                    <div class="text-xs text-gray-500">Current: ' . $currentQuantity . '</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ' . $soldOutDisplay . '
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    ' . $durationDisplay . '
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full ' . $statusClass . '">
                                        ' . $status . '
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    ' . htmlspecialchars($restockedBy) . '
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ' . htmlspecialchars($entry['notes'] ?: 'No notes') . '
                                </td>
                            </tr>';
        }

        $content .= '
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-3 bg-gray-50 border-t">
                    ' . $pagination . '
                </div>
            </div>
        </div>';

        $this->renderWithLayout('Restock History', $content);
    }

    /**
     * Render with layout
     */
    private function renderWithLayout($title, $content)
    {
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        // Pass user data to layout for sidebar role detection
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        include __DIR__ . '/../Views/simple_layout.php';
    }
    
    /**
     * Update supplier tracking when restocking
     * Maintains supplier tracking even when products go out of stock and are restocked
     */
    private function updateSupplierTrackingOnRestock($companyId, $productId, $quantityAdded, $unitCost) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if tracking table exists
            $tableExists = $db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            if (!$tableExists) {
                return; // Silently skip if table doesn't exist
            }
            
            // Get product's supplier_id
            $productStmt = $db->prepare("SELECT supplier_id FROM products WHERE id = ? AND company_id = ?");
            $productStmt->execute([$productId, $companyId]);
            $product = $productStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$product || !$product['supplier_id']) {
                return; // No supplier linked, skip tracking
            }
            
            $supplierId = $product['supplier_id'];
            $amount = $quantityAdded * $unitCost;
            
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
                $newTotalQuantity = $existing['total_quantity_received'] + $quantityAdded;
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
                    $quantityAdded,
                    $amount,
                    $existing['id']
                ]);
            } else {
                // Create new record (product was created without supplier, but now has one)
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
                    $quantityAdded,
                    $amount,
                    $quantityAdded,
                    $amount
                ]);
            }
        } catch (\Exception $e) {
            error_log("Error updating supplier tracking on restock: " . $e->getMessage());
        }
    }
}
