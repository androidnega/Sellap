<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\WebAuthMiddleware;
use App\Models\POSSale;
use App\Models\POSSaleItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\SalePayment;
use App\Services\NotificationService;
use App\Services\AuditService;
use App\Services\VersioningService;

class POSController {
    private $sale;
    private $saleItem;
    private $product;
    private $customer;
    private $company;
    private $notificationService;
    private $salePayment;

    public function __construct() {
        $this->sale = new POSSale();
        $this->saleItem = new POSSaleItem();
        $this->product = new Product();
        $this->customer = new Customer();
        $this->company = new Company();
        $this->notificationService = new NotificationService();
        $this->salePayment = new SalePayment();
    }
    
    /**
     * Get authenticated user from JWT token or session
     */
    private function getAuthenticatedUser() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Try JWT token authentication first
        $authHeader = '';
        
        // Get Authorization header - handle different server configurations
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        } else {
            // Fallback for servers that don't support getallheaders()
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        
        // Try JWT token if provided
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            try {
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                error_log("POSController: JWT authentication successful for user ID: " . ($payload->sub ?? 'unknown'));
                return [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id
                ];
            } catch (\Exception $e) {
                // JWT validation failed, try session
                error_log("POSController: JWT validation failed: " . $e->getMessage() . " - falling back to session");
            }
        }
        
        // Always try session authentication as fallback
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            error_log("POSController: Session authentication successful for user ID: " . ($_SESSION['user']['id'] ?? 'unknown'));
            return $_SESSION['user'];
        }
        
        error_log("POSController: Authentication failed - no valid token or session found");
        return null;
    }

    /**
     * Check if module is enabled (safeguard)
     */
    private function checkModuleEnabled($companyId, $moduleKey, $userRole) {
        // System admins bypass module checks
        if ($userRole === 'system_admin') {
            return true;
        }
        
        if (!$companyId) {
            return false;
        }
        
        return CompanyModule::isEnabled($companyId, $moduleKey);
    }
    
    /**
     * Mark restock logs as sold out when product quantity reaches 0
     */
    private function markRestockSoldOut($productId, $companyId) {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $db = \Database::getInstance()->getConnection();
            
            // Check if lifecycle columns exist
            try {
                $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'sold_out_date'");
                $hasLifecycleColumns = $checkColumns->rowCount() > 0;
            } catch (\Exception $e) {
                $hasLifecycleColumns = false;
            }
            
            if ($hasLifecycleColumns) {
                // Update the most recent active restock log for this product
                $stmt = $db->prepare("
                    UPDATE restock_logs 
                    SET sold_out_date = NOW(), 
                        status = 'sold_out'
                    WHERE product_id = ? 
                      AND company_id = ? 
                      AND status = 'active'
                      AND sold_out_date IS NULL
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$productId, $companyId]);
            }
        } catch (\Exception $e) {
            // Log but don't fail the sale
            error_log("POS Sale: Error marking restock as sold out: " . $e->getMessage());
        }
    }
    
    /**
     * Display POS interface
     */
    public function index() {
        // Handle web authentication
        $user = WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson']);
        
        // Get company_id from session
        $companyId = $_SESSION['user']['company_id'] ?? null;
        $userRole = $_SESSION['user']['role'] ?? 'salesperson';
        
        // Check if POS module is enabled
        if (!$this->checkModuleEnabled($companyId, 'pos_sales', $userRole)) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('POS module is not enabled for your company'));
            exit;
        }
        
        // Check if manager has permission to sell
        if ($userRole === 'manager' && !CompanyModule::isEnabled($companyId, 'manager_can_sell')) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('You do not have permission to process sales'));
            exit;
        }
        if (!$companyId) {
            // For debugging - let's use a default company ID if session doesn't have one
            $companyId = 2; // Default company ID for testing
            error_log("POSController: No company_id in session, using default: {$companyId}");
        }
        
        // Debug session data
        error_log("POSController: Session data: " . json_encode($_SESSION['user'] ?? 'No user session'));
        error_log("POSController: Using company ID: {$companyId}");
        
        $products = $this->product->findByCompany($companyId, 200);
        $customers = $this->customer->findByCompany($companyId, 100);
        
        $title = 'Point of Sale';
        $page = 'pos';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/pos_content.php';
        $content = ob_get_clean();
        
        // Pass user data to layout for sidebar role detection
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Render layout with local $title and $content variables in scope
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Render a print-friendly sales report for a date range
     * Managers/Admins can use browser "Save as PDF" to export
     */
    public function reportPrint() {
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        $companyId = $user['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? $dateFrom;

        // Fetch sales within range and simple totals using POSSale model
        $sales = $this->sale->findByCompany($companyId, 10000, null, $dateFrom, $dateTo) ?? [];

        $totalCount = 0;
        $totalRevenue = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;

        foreach ($sales as $s) {
            $totalCount++;
            $totalRevenue += (float)($s['final_amount'] ?? $s['total_amount'] ?? 0);
            $totalDiscount += (float)($s['discount'] ?? 0);
            $totalTax += (float)($s['tax'] ?? 0);
        }

        $company = $this->company->find($companyId);
        $companyName = $company['name'] ?? 'SellApp Store';

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\" />\n<title>Sales Report {$dateFrom} to {$dateTo}</title>\n<style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; 
                color: #4B5563;
                background: #FAFBFC;
                padding: 15px;
                line-height: 1.4;
            }
            .container { max-width: 1200px; margin: 0 auto; }
            .header { 
                background: linear-gradient(135deg, #E0E7FF 0%, #C7D2FE 100%);
                color: #3730A3;
                padding: 25px;
                border-radius: 10px 10px 0 0;
                margin-bottom: 0;
                border-bottom: 3px solid #818CF8;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .h1 { 
                font-size: 24px; 
                font-weight: 700;
                color: #312E81;
                margin-bottom: 8px;
            }
            .meta { 
                color: #5B21B6;
                font-size: 11px;
                opacity: 0.85;
            }
            .summary { 
                display: grid; 
                grid-template-columns: repeat(4, minmax(0,1fr)); 
                gap: 12px; 
                margin: 0;
                padding: 20px;
                background: white;
            }
            .card { 
                background: linear-gradient(135deg, #FEF3F7 0%, #FCE7F3 100%);
                border: 2px solid #F9A8D4;
                border-radius: 10px; 
                padding: 15px;
                text-align: center;
            }
            .card .label { 
                color: #9F1239;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }
            .card .value { 
                font-size: 20px; 
                font-weight: 700;
                color: #BE185D;
            }
            .table-container {
                background: white;
                border-radius: 0 0 10px 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                overflow: hidden;
                border: 1px solid #E5E7EB;
            }
            table { 
                width: 100%; 
                border-collapse: collapse;
            }
            thead {
                background: linear-gradient(135deg, #FCE7F3 0%, #FBCFE8 100%);
            }
            th { 
                color: #9F1239;
                padding: 12px 10px; 
                text-align: left; 
                border: none;
                font-weight: 600;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                border-bottom: 2px solid #F9A8D4;
            }
            td { 
                padding: 10px; 
                border-bottom: 1px solid #F3F4F6;
                font-size: 10px;
                color: #4B5563;
            }
            tbody tr:nth-child(even) { 
                background-color: #FEF3F7; 
            }
            .right { text-align: right; }
            .footer {
                background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
                padding: 18px 25px;
                text-align: center;
                color: #6B7280;
                font-size: 9px;
                border-top: 2px solid #E5E7EB;
                border-radius: 0 0 10px 10px;
            }
            .footer a {
                color: #818CF8;
                text-decoration: none;
                font-weight: 600;
            }
            .download-btn {
                background: linear-gradient(135deg, #818CF8 0%, #6366F1 100%);
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(129, 140, 248, 0.4);
                font-size: 14px;
            }
            .download-btn:hover {
                background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(129, 140, 248, 0.5);
            }
            @media print { 
                .no-print { display: none; }
                .download-btn { display: none; }
                @page { margin: 0.5cm; size: A4 landscape; }
            }
        </style>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        </script>
        </head>\n<body>\n<div class=\"container\">\n<div class=\"header\">\n  <div>\n    <div class=\"h1\">{$companyName} — Sales Report</div>\n    <div class=\"meta\">From {$dateFrom} to {$dateTo} | " . (getenv('APP_URL') ?: 'sellapp.store') . "</div>\n  </div>\n  <button class=\"download-btn no-print\" onclick=\"window.print()\">Print / Save as PDF</button>\n</div>\n
<div class=\"summary\">\n  <div class=\"card\"><div class=\"label\">Total Sales</div><div class=\"value\">" . number_format($totalCount) . "</div></div>\n  <div class=\"card\"><div class=\"label\">Total Revenue</div><div class=\"value\">₵" . number_format($totalRevenue, 2) . "</div></div>\n  <div class=\"card\"><div class=\"label\">Total Discount</div><div class=\"value\">₵" . number_format($totalDiscount, 2) . "</div></div>\n  <div class=\"card\"><div class=\"label\">Total Tax</div><div class=\"value\">₵" . number_format($totalTax, 2) . "</div></div>\n</div>\n
<div class=\"table-container\">
<table>\n  <thead>\n    <tr>\n      <th>ID</th>\n      <th>Date</th>\n      <th>Cashier</th>\n      <th>Customer</th>\n      <th class=\"right\">Subtotal</th>\n      <th class=\"right\">Discount</th>\n      <th class=\"right\">Tax</th>\n      <th class=\"right\">Total</th>\n    </tr>\n  </thead>\n  <tbody>\n";
        foreach ($sales as $row) {
            $id = htmlspecialchars((string)($row['id'] ?? ''));
            $date = htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at'] ?? 'now')));
            $cashier = htmlspecialchars($row['created_by_username'] ?? $row['cashier'] ?? '');
            $customer = htmlspecialchars($row['customer_name'] ?? 'Walk-in');
            $subtotal = number_format((float)($row['total_amount'] ?? 0), 2);
            $discount = number_format((float)($row['discount'] ?? 0), 2);
            $tax = number_format((float)($row['tax'] ?? 0), 2);
            $total = number_format((float)($row['final_amount'] ?? $row['total_amount'] ?? 0), 2);
            echo "    <tr>\n      <td>{$id}</td>\n      <td>{$date}</td>\n      <td>{$cashier}</td>\n      <td>{$customer}</td>\n      <td class=\"right\">₵{$subtotal}</td>\n      <td class=\"right\">₵{$discount}</td>\n      <td class=\"right\">₵{$tax}</td>\n      <td class=\"right\">₵{$total}</td>\n    </tr>\n";
        }
        $appUrl = defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: 'http://localhost');
        echo "  </tbody>\n</table>\n</div>\n<div class=\"footer\">\n    <p>This report was generated automatically by <a href=\"{$appUrl}\">SellApp Analytics System</a></p>\n</div>\n</div>\n</body>\n</html>";
        exit;
    }

    /**
     * API: Inventory stats for manager (products count breakdown)
     */
    public function apiInventoryStats() {
        // Use WebAuthMiddleware for web-based authentication
        try {
            $payload = WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
            $companyId = $payload->company_id ?? null;
        } catch (\Exception $e) {
            // Fallback to session if WebAuthMiddleware fails
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                return;
            }
            $companyId = $user['company_id'] ?? null;
        }

        if (!$companyId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }

        try {
            $db = \Database::getInstance()->getConnection();

            // Helper functions
            $tableExists = function($table) use ($db) {
                try {
                    $db->query("SELECT 1 FROM {$table} LIMIT 1");
                    return true;
                } catch (\Throwable $t) {
                    return false;
                }
            };

            $columnExists = function($table, $column) use ($db) {
                try {
                    $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                    return $stmt->rowCount() > 0;
                } catch (\Throwable $t) {
                    return false;
                }
            };

            $totalProducts = 0;
            $inStock = 0;
            $outOfStock = 0;
            $lowStock = 0;

            // Check which product table exists and get column names
            $productsTable = null;
            $quantityCol = null;
            $statusCol = null;
            $minQuantityCol = null;

            // Try products table first (most common)
            if ($tableExists('products')) {
                $productsTable = 'products';
                // Detect column names
                if ($columnExists('products', 'quantity')) {
                    $quantityCol = 'quantity';
                } elseif ($columnExists('products', 'qty')) {
                    $quantityCol = 'qty';
                }
                
                if ($columnExists('products', 'status')) {
                    $statusCol = 'status';
                }
                
                if ($columnExists('products', 'min_quantity')) {
                    $minQuantityCol = 'min_quantity';
                }
            } elseif ($tableExists('products_new')) {
                $productsTable = 'products_new';
                $quantityCol = 'quantity';
                if ($columnExists('products_new', 'status')) {
                    $statusCol = 'status';
                }
                if ($columnExists('products_new', 'min_quantity')) {
                    $minQuantityCol = 'min_quantity';
                }
            }

            if ($productsTable && $quantityCol) {
                // Build dynamic queries based on available columns
                $quantityExpr = "COALESCE(p.{$quantityCol}, 0)";
                
                // Total products - try using Product model first for consistency
                try {
                    $productModel = new Product();
                    $allCompanyProducts = $productModel->findByCompany($companyId, 10000);
                    $totalProducts = count($allCompanyProducts);
                    error_log("Inventory Stats (via Model): Found {$totalProducts} total products for company {$companyId}");
                    
                    // Calculate stats from model data if we got products
                    if ($totalProducts > 0) {
                        foreach ($allCompanyProducts as $product) {
                            $qty = intval($product['quantity'] ?? $product['qty'] ?? 0);
                            
                            if ($qty > 0) {
                                $inStock++;
                                // Check for low stock
                                $minQty = intval($product['min_quantity'] ?? 5);
                                if ($qty <= $minQty) {
                                    $lowStock++;
                                }
                            } else {
                                $outOfStock++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error using Product model: " . $e->getMessage());
                    // Fallback to direct SQL
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM {$productsTable} p WHERE p.company_id = ?");
                        $stmt->execute([$companyId]);
                        $totalProducts = (int)($stmt->fetchColumn() ?: 0);
                        error_log("Inventory Stats (via SQL): Found {$totalProducts} total products for company {$companyId}");
                    } catch (\Exception $e2) {
                        error_log("Error counting total products: " . $e2->getMessage());
                    }
                }
                
                // If model method didn't populate stats, use SQL
                if ($totalProducts > 0 && ($inStock === 0 && $outOfStock === 0)) {
                    // In stock (quantity > 0)
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM {$productsTable} p WHERE p.company_id = ? AND {$quantityExpr} > 0");
                        $stmt->execute([$companyId]);
                        $inStock = (int)($stmt->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        error_log("Error counting in stock products: " . $e->getMessage());
                    }

                    // Out of stock
                    try {
                        $outStockCondition = "{$quantityExpr} <= 0";
                        if ($statusCol) {
                            $outStockCondition .= " OR p.{$statusCol} = 'out_of_stock'";
                        }
                        $stmt = $db->prepare("SELECT COUNT(*) FROM {$productsTable} p WHERE p.company_id = ? AND ({$outStockCondition})");
                        $stmt->execute([$companyId]);
                        $outOfStock = (int)($stmt->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        error_log("Error counting out of stock products: " . $e->getMessage());
                    }

                    // Low stock
                    try {
                        if ($minQuantityCol) {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM {$productsTable} p WHERE p.company_id = ? AND {$quantityExpr} > 0 AND {$quantityExpr} <= COALESCE(p.{$minQuantityCol}, 5)");
                        } else {
                            // Default to 5 if min_quantity column doesn't exist
                            $stmt = $db->prepare("SELECT COUNT(*) FROM {$productsTable} p WHERE p.company_id = ? AND {$quantityExpr} > 0 AND {$quantityExpr} <= 5");
                        }
                        $stmt->execute([$companyId]);
                        $lowStock = (int)($stmt->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        error_log("Error counting low stock products: " . $e->getMessage());
                    }
                }

                // If still zero, try without company_id filter (for debugging)
                if ($totalProducts === 0) {
                    error_log("Warning: No products found with company_id={$companyId}, checking all products");
                    try {
                        $allProducts = (int)($db->query("SELECT COUNT(*) FROM {$productsTable}")->fetchColumn() ?: 0);
                        error_log("Total products in database (all companies): {$allProducts}");
                        
                        // Check if company_id column exists and what values it has
                        if ($columnExists($productsTable, 'company_id')) {
                            $stmt = $db->query("SELECT DISTINCT company_id, COUNT(*) as count FROM {$productsTable} GROUP BY company_id");
                            $companyCounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                            error_log("Products by company: " . json_encode($companyCounts));
                        }
                    } catch (\Exception $e) {
                        error_log("Error checking all products: " . $e->getMessage());
                    }
                }
            } else {
                error_log("Warning: No products table found or no quantity column detected");
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_products' => $totalProducts,
                    'in_stock_products' => $inStock,
                    'out_of_stock_products' => $outOfStock,
                    'low_stock_products' => $lowStock
                ],
                'debug' => [
                    'company_id' => $companyId,
                    'table' => $productsTable,
                    'quantity_col' => $quantityCol
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Fatal error in apiInventoryStats: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'total_products' => 0,
                    'in_stock_products' => 0,
                    'out_of_stock_products' => 0,
                    'low_stock_products' => 0
                ]
            ]);
        }
    }

    /**
     * API: Get comprehensive audit data for manager POS page
     */
    public function apiAuditData() {
        try {
            $payload = WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
            $companyId = $payload->company_id ?? null;
        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                return;
            }
            $companyId = $user['company_id'] ?? null;
        }

        if (!$companyId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $limit = intval($_GET['limit'] ?? 10);

            // Recent Sales - use POSSale model for consistency
            $recentSales = [];
            try {
                // Check which column exists for user reference
                $colStmt = $db->query("SHOW COLUMNS FROM pos_sales");
                $columns = array_column($colStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $userRefCol = in_array('created_by_user_id', $columns) ? 'created_by_user_id' : 
                             (in_array('created_by', $columns) ? 'created_by' : null);
                
                // Check sale_items table column name
                $itemColStmt = $db->query("SHOW COLUMNS FROM pos_sale_items");
                $itemColumns = array_column($itemColStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $saleIdCol = in_array('pos_sale_id', $itemColumns) ? 'pos_sale_id' : 
                            (in_array('sale_id', $itemColumns) ? 'sale_id' : null);
                
                if ($userRefCol) {
                    // Try using POSSale model first
                    try {
                        $saleModel = new \App\Models\POSSale();
                        $allSales = $saleModel->findByCompany($companyId, $limit);
                        
                        foreach ($allSales as $sale) {
                            // Get item count
                            $itemCount = 0;
                            if ($saleIdCol) {
                                try {
                                    $itemStmt = $db->prepare("SELECT COUNT(*) FROM pos_sale_items WHERE {$saleIdCol} = ?");
                                    $itemStmt->execute([$sale['id']]);
                                    $itemCount = (int)($itemStmt->fetchColumn() ?: 0);
                                } catch (\Exception $e) {
                                    // Ignore
                                }
                            }
                            
                            $recentSales[] = [
                                'id' => $sale['id'],
                                'sale_id' => $sale['unique_id'] ?? $sale['id'],
                                'final_amount' => $sale['final_amount'] ?? $sale['total_amount'] ?? 0,
                                'payment_method' => strtolower($sale['payment_method'] ?? 'cash'),
                                'created_at' => $sale['created_at'],
                                'cashier_name' => $sale['cashier_name'] ?? $sale['cashier'] ?? 'Unknown',
                                'item_count' => $itemCount
                            ];
                        }
                    } catch (\Exception $e) {
                        error_log("Error using POSSale model: " . $e->getMessage());
                        // Fallback to direct query
                        $userJoin = "LEFT JOIN users u ON ps.{$userRefCol} = u.id";
                        $usernameSelect = "COALESCE(u.username, u.full_name, 'Unknown') as cashier_name";
                        
                        $itemCountSubquery = "0 as item_count";
                        if ($saleIdCol) {
                            $itemCountSubquery = "(SELECT COUNT(*) FROM pos_sale_items WHERE {$saleIdCol} = ps.id) as item_count";
                        }
                        
                        $stmt = $db->prepare("
                            SELECT ps.*, {$usernameSelect}, {$itemCountSubquery}
                            FROM pos_sales ps
                            {$userJoin}
                            WHERE ps.company_id = ?
                            ORDER BY ps.created_at DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$companyId, $limit]);
                        $recentSales = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    }
                } else {
                    error_log("Error: No user reference column found in pos_sales table");
                }
            } catch (\Exception $e) {
                error_log("Error fetching recent sales: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }

            // Recent Repairs - try using Repair model for consistency
            $recentRepairs = [];
            try {
                // Check which repairs table exists
                $repairsTable = null;
                try {
                    $checkTable = $db->query("SHOW TABLES LIKE 'repairs_new'");
                    if ($checkTable->rowCount() > 0) {
                        $repairsTable = 'repairs_new';
                    } else {
                        $checkTable = $db->query("SHOW TABLES LIKE 'repairs'");
                        if ($checkTable->rowCount() > 0) {
                            $repairsTable = 'repairs';
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error checking repairs table: " . $e->getMessage());
                }
                
                if ($repairsTable) {
                    // Detect column names
                    $colStmt = $db->query("SHOW COLUMNS FROM {$repairsTable}");
                    $columns = array_column($colStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                    
                    $hasCustomerName = in_array('customer_name', $columns);
                    $hasCustomerContact = in_array('customer_contact', $columns);
                    $hasDeviceBrand = in_array('device_brand', $columns);
                    $hasPhoneDescription = in_array('phone_description', $columns);
                    $hasIssueDescription = in_array('issue_description', $columns);
                    $hasCost = in_array('total_cost', $columns) ? 'total_cost' : (in_array('repair_cost', $columns) ? 'repair_cost' : null);
                    $hasStatus = in_array('status', $columns) ? 'status' : (in_array('repair_status', $columns) ? 'repair_status' : null);
                    
                    $customerNameSelect = $hasCustomerName ? "r.customer_name" : 
                                         (in_array('customer_id', $columns) ? "COALESCE(c.full_name, c.name)" : "NULL as customer_name");
                    $customerPhoneSelect = $hasCustomerContact ? "r.customer_contact" :
                                          (in_array('customer_id', $columns) ? "COALESCE(c.phone, c.phone_number)" : "NULL as customer_phone");
                    $deviceInfo = $hasDeviceBrand ? "CONCAT(r.device_brand, ' ', COALESCE(r.device_model, ''))" :
                              ($hasPhoneDescription ? "r.phone_description" : "'Device'");
                    $issueField = $hasIssueDescription ? "r.issue_description" : "r.issue";
                    $costField = $hasCost ? "r.{$hasCost}" : "0";
                    $statusField = $hasStatus ? "r.{$hasStatus}" : "'pending'";
                    
                    $stmt = $db->prepare("
                        SELECT r.*, 
                               {$customerNameSelect} as customer_name,
                               {$customerPhoneSelect} as customer_phone,
                               {$deviceInfo} as device_info,
                               {$issueField} as issue,
                               {$costField} as cost,
                               {$statusField} as status
                        FROM {$repairsTable} r
                        " . (in_array('customer_id', $columns) ? "LEFT JOIN customers c ON r.customer_id = c.id" : "") . "
                        WHERE r.company_id = ?
                        ORDER BY r.created_at DESC
                        LIMIT ?
                    ");
                    $stmt->execute([$companyId, $limit]);
                    $recentRepairs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } else {
                    // Try using Repair model
                    try {
                        $repairModel = new \App\Models\Repair();
                        $allRepairs = $repairModel->findByCompany($companyId, $limit);
                        $recentRepairs = array_slice($allRepairs, 0, $limit);
                    } catch (\Exception $e) {
                        error_log("Error using Repair model: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching recent repairs: " . $e->getMessage());
            }

            // Recent Swaps - use Swap model for consistency
            $recentSwaps = [];
            try {
                $swapModel = new \App\Models\Swap();
                $allSwaps = $swapModel->findByCompany($companyId, $limit);
                
                // Deduplicate by swap ID (in case LEFT JOIN creates multiple rows)
                $seenIds = [];
                foreach ($allSwaps as $swap) {
                    $swapId = $swap['id'] ?? null;
                    if ($swapId && !isset($seenIds[$swapId])) {
                        $seenIds[$swapId] = true;
                        // Format for display
                        $recentSwaps[] = [
                            'id' => $swap['id'],
                            'transaction_code' => $swap['transaction_code'] ?? $swap['unique_id'] ?? null,
                            'customer_name' => $swap['customer_name'] ?? $swap['customer_name_from_table'] ?? $swap['customer_name'] ?? 'Guest',
                            'product_name' => $swap['company_product_name'] ?? null,
                            'total_value' => $swap['total_value'] ?? $swap['final_price'] ?? 0,
                            'status' => $swap['status'] ?? $swap['swap_status'] ?? 'pending',
                            'created_at' => $swap['created_at'] ?? $swap['swap_date'] ?? date('Y-m-d H:i:s')
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching recent swaps: " . $e->getMessage());
                // Fallback to direct query
                try {
                    $colStmt = $db->query("SHOW COLUMNS FROM swaps");
                    $swapColumns = array_column($colStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                    
                    if (in_array('company_product_id', $swapColumns)) {
                        $stmt = $db->prepare("
                            SELECT s.*, 
                                   COALESCE(c.name, c.full_name, s.customer_name) as customer_name,
                                   p.name as product_name
                            FROM swaps s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            LEFT JOIN products_new p ON s.company_product_id = p.id
                            WHERE s.company_id = ?
                            ORDER BY s.created_at DESC
                            LIMIT ?
                        ");
                    } else {
                        $stmt = $db->prepare("
                            SELECT s.*, 
                                   COALESCE(c.name, c.full_name, s.customer_name) as customer_name,
                                   NULL as product_name
                            FROM swaps s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            WHERE s.company_id = ?
                            ORDER BY s.created_at DESC
                            LIMIT ?
                        ");
                    }
                    $stmt->execute([$companyId, $limit]);
                    $recentSwaps = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e2) {
                    error_log("Error in fallback swaps query: " . $e2->getMessage());
                }
            }

            // Top Selling Products (last 30 days)
            $topProducts = [];
            try {
                // Check column names dynamically
                $itemColStmt = $db->query("SHOW COLUMNS FROM pos_sale_items");
                $itemColumns = array_column($itemColStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $saleIdCol = in_array('pos_sale_id', $itemColumns) ? 'pos_sale_id' : 
                            (in_array('sale_id', $itemColumns) ? 'sale_id' : null);
                
                if (!$saleIdCol) {
                    throw new \Exception("Could not find sale ID column in pos_sale_items");
                }
                
                // Try to get product names from products table via item_id
                // First check if item_id column exists and join with products
                $hasItemId = in_array('item_id', $itemColumns);
                
                if ($hasItemId) {
                    // Check which products table exists
                    $productsTable = 'products_new';
                    $productsCheck = $db->query("SHOW TABLES LIKE 'products_new'");
                    if ($productsCheck->rowCount() === 0) {
                        $productsCheck2 = $db->query("SHOW TABLES LIKE 'products'");
                        if ($productsCheck2->rowCount() > 0) {
                            $productsTable = 'products';
                        } else {
                            $productsTable = null;
                        }
                    }
                    
                    if ($productsTable) {
                        // Join with products table to get actual product names and brands
                        $stmt = $db->prepare("
                            SELECT 
                                COALESCE(p.name, psi.item_description) as product_name,
                                COALESCE(b.name, 'N/A') as brand,
                                SUM(psi.quantity) as total_sold, 
                                SUM(psi.total_price) as total_revenue
                            FROM pos_sale_items psi
                            INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                            LEFT JOIN {$productsTable} p ON psi.item_id = p.id
                            LEFT JOIN brands b ON p.brand_id = b.id
                            WHERE ps.company_id = ? AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            GROUP BY COALESCE(p.id, psi.item_description), COALESCE(p.name, psi.item_description), COALESCE(b.name, 'N/A')
                            ORDER BY total_sold DESC
                            LIMIT ?
                        ");
                    } else {
                        // Fallback: use item_description
                        $stmt = $db->prepare("
                            SELECT 
                                psi.item_description as product_name,
                                'N/A' as brand,
                                SUM(psi.quantity) as total_sold, 
                                SUM(psi.total_price) as total_revenue
                            FROM pos_sale_items psi
                            INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                            WHERE ps.company_id = ? AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            GROUP BY psi.item_description
                            ORDER BY total_sold DESC
                            LIMIT ?
                        ");
                    }
                } else {
                    // Fallback: use item_description only
                    $stmt = $db->prepare("
                        SELECT 
                            psi.item_description as product_name,
                            'N/A' as brand,
                            SUM(psi.quantity) as total_sold, 
                            SUM(psi.total_price) as total_revenue
                        FROM pos_sale_items psi
                        INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                        WHERE ps.company_id = ? AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY psi.item_description
                        ORDER BY total_sold DESC
                        LIMIT ?
                    ");
                }
                
                $stmt->execute([$companyId, $limit]);
                $topProducts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log("Error fetching top products: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }

            // Financial Summary (Today)
            $financialSummary = [
                'today_revenue' => 0,
                'today_sales_count' => 0,
                'today_repairs_count' => 0,
                'today_swaps_count' => 0,
                'month_revenue' => 0,
                'month_sales_count' => 0
            ];
            try {
                // Today's revenue - EXCLUDE swap transactions
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue
                    FROM pos_sales
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                    AND swap_id IS NULL
                ");
                $stmt->execute([$companyId]);
                $today = $stmt->fetch(\PDO::FETCH_ASSOC);
                $financialSummary['today_revenue'] = floatval($today['revenue'] ?? 0);
                $financialSummary['today_sales_count'] = intval($today['count'] ?? 0);

                // This month's revenue - EXCLUDE swap transactions
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue
                    FROM pos_sales
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                    AND swap_id IS NULL
                ");
                $stmt->execute([$companyId]);
                $month = $stmt->fetch(\PDO::FETCH_ASSOC);
                $financialSummary['month_revenue'] = floatval($month['revenue'] ?? 0);
                $financialSummary['month_sales_count'] = intval($month['count'] ?? 0);

                // Today's repairs
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count FROM repairs
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$companyId]);
                $financialSummary['today_repairs_count'] = intval($stmt->fetchColumn() ?? 0);

                // Today's swaps count
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count FROM swaps
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$companyId]);
                $financialSummary['today_swaps_count'] = intval($stmt->fetchColumn() ?? 0);
                
                // Today's swap revenue (cash top-up only, not including resale value until resold)
                // Check which columns exist
                $checkTotalValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
                $hasTotalValue = $checkTotalValue->rowCount() > 0;
                $checkAddedCash = $db->query("SHOW COLUMNS FROM swaps LIKE 'added_cash'");
                $hasAddedCash = $checkAddedCash->rowCount() > 0;
                
                $swapRevenue = 0;
                if ($hasTotalValue || $hasAddedCash) {
                    // For swaps created today, total_value should be added_cash (cash top-up)
                    // But we need to check if the swap has been resold to include resale value
                    if ($hasTotalValue) {
                        // Use total_value which should be added_cash for new swaps, or added_cash + resale for resold swaps
                        $stmt = $db->prepare("
                            SELECT COALESCE(SUM(total_value), 0) as revenue
                            FROM swaps
                            WHERE company_id = ? AND DATE(created_at) = CURDATE()
                        ");
                        $stmt->execute([$companyId]);
                        $swapRevenue = floatval($stmt->fetchColumn() ?? 0);
                    } elseif ($hasAddedCash) {
                        // Fallback to added_cash if total_value doesn't exist
                        $stmt = $db->prepare("
                            SELECT COALESCE(SUM(added_cash), 0) as revenue
                            FROM swaps
                            WHERE company_id = ? AND DATE(created_at) = CURDATE()
                        ");
                        $stmt->execute([$companyId]);
                        $swapRevenue = floatval($stmt->fetchColumn() ?? 0);
                    }
                }
                $financialSummary['today_swaps_revenue'] = $swapRevenue;
                
                // Get payment statistics if partial payments module is enabled
                if ($this->checkModuleEnabled($companyId, 'partial_payments', $user['role'] ?? '')) {
                    try {
                        $paymentStatsSql = "SELECT payment_status, COUNT(*) as count 
                                           FROM pos_sales 
                                           WHERE company_id = ? AND DATE(created_at) = CURDATE()
                                           GROUP BY payment_status";
                        $paymentStatsQuery = $db->prepare($paymentStatsSql);
                        $paymentStatsQuery->execute([$companyId]);
                        $paymentResults = $paymentStatsQuery->fetchAll(\PDO::FETCH_ASSOC);
                        
                        $fullyPaid = 0;
                        $partial = 0;
                        $unpaid = 0;
                        
                        foreach ($paymentResults as $row) {
                            $status = strtoupper($row['payment_status'] ?? 'PAID');
                            $count = (int)($row['count'] ?? 0);
                            
                            if ($status === 'PAID') {
                                $fullyPaid = $count;
                            } elseif ($status === 'PARTIAL') {
                                $partial = $count;
                            } elseif ($status === 'UNPAID') {
                                $unpaid = $count;
                            }
                        }
                        
                        $financialSummary['payment_stats'] = [
                            'fully_paid' => $fullyPaid,
                            'partial' => $partial,
                            'unpaid' => $unpaid
                        ];
                    } catch (\Exception $e) {
                        error_log("POSController::apiAuditData - Error getting payment stats: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching financial summary: " . $e->getMessage());
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'recent_sales' => $recentSales,
                    'recent_repairs' => $recentRepairs,
                    'recent_swaps' => $recentSwaps,
                    'top_products' => $topProducts,
                    'financial_summary' => $financialSummary
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch audit data',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add product to cart (AJAX)
     */
    public function addToCart() {
        // Ensure clean JSON output
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        // Read JSON data from request body
        $input = json_decode(file_get_contents('php://input'), true);
        $productId = $input['product_id'] ?? $_POST['product_id'] ?? null;
        $quantity = (int)($input['quantity'] ?? $_POST['quantity'] ?? 1);
        
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        // Get product details
        $product = $this->product->find($productId, $user['company_id']);
        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit;
        }
        
        // Check stock availability
        if ($product['quantity'] < $quantity) {
            echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
            exit;
        }
        
        // Initialize cart if not exists
        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }
        
        // Add or update item in cart
        if (isset($_SESSION['pos_cart'][$productId])) {
            $_SESSION['pos_cart'][$productId]['quantity'] += $quantity;
        } else {
            $_SESSION['pos_cart'][$productId] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'price' => $product['price'],
                'cost' => $product['cost'] ?? 0,
                'quantity' => $quantity,
                'total' => $product['price'] * $quantity,
                'profit' => ($product['price'] - ($product['cost'] ?? 0)) * $quantity
            ];
        }
        
        // Update totals
        $_SESSION['pos_cart'][$productId]['total'] = $_SESSION['pos_cart'][$productId]['quantity'] * $_SESSION['pos_cart'][$productId]['price'];
        $_SESSION['pos_cart'][$productId]['profit'] = ($_SESSION['pos_cart'][$productId]['price'] - $_SESSION['pos_cart'][$productId]['cost']) * $_SESSION['pos_cart'][$productId]['quantity'];
        
        ob_clean();
        
        // Filter profit/cost from cart for salespersons
        $cart = $this->sanitizeCartForRole($_SESSION['pos_cart'], $user['role'] ?? 'salesperson');
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart',
            'cart' => $cart
        ]);
        exit;
    }
    
    /**
     * Remove product from cart (AJAX)
     */
    public function removeFromCart() {
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        // Read JSON data from request body
        $input = json_decode(file_get_contents('php://input'), true);
        $productId = $input['product_id'] ?? $_POST['product_id'] ?? null;
        
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        // Remove item from cart
        if (isset($_SESSION['pos_cart'][$productId])) {
            unset($_SESSION['pos_cart'][$productId]);
        }
        
        ob_clean();
        
        // Filter profit/cost from cart for salespersons
        $cart = $this->sanitizeCartForRole($_SESSION['pos_cart'] ?? [], $user['role'] ?? 'salesperson');
        
        echo json_encode([
            'success' => true,
            'message' => 'Product removed from cart',
            'cart' => $cart
        ]);
        exit;
    }
    
    /**
     * Update cart item quantity (AJAX)
     */
    public function updateCartQuantity() {
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        // Read JSON data from request body
        $input = json_decode(file_get_contents('php://input'), true);
        $productId = $input['product_id'] ?? $_POST['product_id'] ?? null;
        $quantity = (int)($input['quantity'] ?? $_POST['quantity'] ?? 0);
        
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            if (isset($_SESSION['pos_cart'][$productId])) {
                unset($_SESSION['pos_cart'][$productId]);
            }
        } else {
            // Update quantity
            if (isset($_SESSION['pos_cart'][$productId])) {
                $_SESSION['pos_cart'][$productId]['quantity'] = $quantity;
                $_SESSION['pos_cart'][$productId]['total'] = $_SESSION['pos_cart'][$productId]['quantity'] * $_SESSION['pos_cart'][$productId]['price'];
                $_SESSION['pos_cart'][$productId]['profit'] = ($_SESSION['pos_cart'][$productId]['price'] - $_SESSION['pos_cart'][$productId]['cost']) * $_SESSION['pos_cart'][$productId]['quantity'];
            }
        }
        
        ob_clean();
        
        // Filter profit/cost from cart for salespersons
        $cart = $this->sanitizeCartForRole($_SESSION['pos_cart'] ?? [], $user['role'] ?? 'salesperson');
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated',
            'cart' => $cart
        ]);
        exit;
    }
    
    /**
     * Clear cart (AJAX)
     */
    public function clearCart() {
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        // Clear cart
        $_SESSION['pos_cart'] = [];
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Cart cleared',
            'cart' => []
        ]);
        exit;
    }
    
    /**
     * Get cart contents (AJAX)
     */
    public function getCart() {
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $cart = $_SESSION['pos_cart'] ?? [];
        $subtotal = 0;
        $totalProfit = 0;
        
        foreach ($cart as $item) {
            $subtotal += $item['total'];
            $totalProfit += $item['profit'];
        }
        
        ob_clean();
        
        // Filter profit/cost from cart for salespersons, but keep profit calculation in backend
        $role = $user['role'] ?? 'salesperson';
        $sanitizedCart = $this->sanitizeCartForRole($cart, $role);
        
        $response = [
            'success' => true,
            'cart' => $sanitizedCart,
            'subtotal' => $subtotal,
            'item_count' => count($cart)
        ];
        
        // Only include profit in response for managers/admins
        if (in_array($role, ['manager', 'admin', 'system_admin'])) {
            $response['total_profit'] = $totalProfit;
        }
        
        echo json_encode($response);
        exit;
    }

    /**
     * Process a sale (POST)
     */
    public function processSale() {
        // Get authenticated user
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $companyId = $user['company_id'] ?? null;
        $userRole = $user['role'] ?? 'salesperson';
        
        // Check if POS module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'pos_sales', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'pos_sales',
                'message' => 'The POS / Sales module is not enabled for your company. Please contact your administrator.'
            ]);
            exit;
        }
        
        // Register error handlers to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                // Clean any output
                while (ob_get_level()) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                    http_response_code(500);
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Fatal error occurred',
                    'message' => 'A fatal error occurred while processing the sale: ' . $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        });
        
        // Set custom error handler
        set_error_handler(function($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        // Clean all existing output buffers first
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering
        ob_start();
        
        // Enable error logging but suppress display (we'll handle errors ourselves)
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Log the start of the request
        error_log("POSController processSale: Starting sale processing");
        
        // Set JSON header immediately
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            error_log("POSController processSale: Session started, checking authentication");
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            
            error_log("POSController processSale: User authenticated: " . ($user ? 'yes (ID: ' . ($user['id'] ?? 'unknown') . ', Role: ' . ($user['role'] ?? 'unknown') . ')' : 'no'));
            if (!$user) {
                // Clean output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                }
                
                echo json_encode(['success' => false, 'error' => 'Authentication required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // Check if user has required role (salesperson, manager, admin, or system_admin can complete sales)
            $userRole = $user['role'] ?? '';
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson'];
            if (!in_array($userRole, $allowedRoles)) {
                // Clean output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to complete sales'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            error_log("POSController processSale: User role verified: {$userRole}");
            
            $companyId = $user['company_id'] ?? null;
            $userId = $user['id'] ?? null;
            
            if (!$companyId || !$userId) {
                throw new \Exception('Invalid user data: missing company_id or user_id');
            }
            
            error_log("POSController processSale: Company ID: {$companyId}, User ID: {$userId}");
            
            // Get and parse input
            $rawInput = file_get_contents('php://input');
            if ($rawInput === false || $rawInput === '') {
                throw new \Exception('Empty request body received');
            }
            
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON in request: ' . json_last_error_msg());
            }
            
            if ($input === null) {
                throw new \Exception('Failed to parse JSON input');
            }
            
            // Validate required fields
            if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
                // Clean output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    http_response_code(400);
                    header('Content-Type: application/json');
                }
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Sale items are required'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            $sale_type = $input['sale_type'] ?? 'normal';
            $customer_name = $input['customer_name'] ?? null;
            $customer_contact = $input['customer_contact'] ?? null;
            $customer_id = $input['customer_id'] ?? null;
            $discount = floatval($input['discount'] ?? 0);
            $tax = floatval($input['tax'] ?? 0);
            $payment_method = $input['payment_method'] ?? 'cash';
            $notes = $input['notes'] ?? null;
            
            // Check if partial payments module is enabled
            $partialPaymentsEnabled = $this->checkModuleEnabled($companyId, 'partial_payments', $user['role'] ?? '');
            
            // Amount received for partial payment (only if module is enabled)
            $amount_received = 0;
            if ($partialPaymentsEnabled && isset($input['amount_received'])) {
                $amount_received = floatval($input['amount_received']);
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($input['items'] as $item) {
                $subtotal += floatval($item['total_price']);
            }
            
            $total = $subtotal - $discount + $tax;
            
            // Validate sale data
            if ($total < 0) {
                throw new \Exception('Total amount cannot be negative');
            }
            
            // Validate amount_received
            if ($amount_received < 0) {
                throw new \Exception('Amount received cannot be negative');
            }
            
            // If partial payments not enabled or amount_received is 0, default to full payment
            if (!$partialPaymentsEnabled || $amount_received == 0) {
                $amount_received = $total;
            }
            
            // Determine payment status based on amount received
            $paymentStatus = 'PAID';
            if ($amount_received < $total) {
                $paymentStatus = 'PARTIAL';
            } elseif ($amount_received == 0) {
                $paymentStatus = 'UNPAID';
            }
            
            if (!$this->sale || !method_exists($this->sale, 'create')) {
                throw new \Exception('Sale model not properly initialized');
            }
            
            // Create sale
            try {
                // Ensure payment_method is properly formatted (uppercase)
                $paymentMethodFormatted = strtoupper($payment_method);
                $allowedPaymentMethods = ['CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER'];
                if (!in_array($paymentMethodFormatted, $allowedPaymentMethods)) {
                    $paymentMethodFormatted = 'CASH'; // Fallback to CASH
                }
                
                $saleId = $this->sale->create([
                    'company_id' => $companyId,
                    'customer_id' => $customer_id,
                    'total_amount' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'final_amount' => $total,
                    'payment_method' => $paymentMethodFormatted,
                    'payment_status' => $paymentStatus,
                    'created_by_user_id' => $userId,
                    'notes' => $notes
                ]);
                
                if (!$saleId || $saleId <= 0) {
                    throw new \Exception('Failed to create sale - invalid sale ID returned');
                }

                // Log audit event
                try {
                    AuditService::log(
                        $companyId,
                        $userId,
                        'sale.created',
                        'pos_sale',
                        $saleId,
                        [
                            'total_amount' => $subtotal,
                            'discount' => $discount,
                            'tax' => $tax,
                            'final_amount' => $total,
                            'payment_method' => $paymentMethodFormatted,
                            'items_count' => count($input['items']),
                            'customer_id' => $customer_id
                        ]
                    );

                    // Create version entry for data versioning
                    $versionService = new VersioningService();
                    $saleData = [
                        'company_id' => $companyId,
                        'customer_id' => $customer_id,
                        'total_amount' => $subtotal,
                        'discount' => $discount,
                        'tax' => $tax,
                        'final_amount' => $total,
                        'payment_method' => $paymentMethodFormatted,
                        'created_by_user_id' => $userId
                    ];
                    $versionService->createVersion(
                        $companyId,
                        'pos_sales',
                        $saleId,
                        'create',
                        null,
                        $saleData,
                        $userId
                    );
                } catch (\Exception $auditError) {
                    error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
                }
            } catch (\Exception $saleError) {
                error_log("POS Sale Creation Error: " . $saleError->getMessage());
                error_log("POS Sale Creation Trace: " . $saleError->getTraceAsString());
                error_log("POS Sale Creation Data: company_id={$companyId}, user_id={$userId}, subtotal={$subtotal}, discount={$discount}, tax={$tax}");
                throw new \Exception('Failed to create sale: ' . $saleError->getMessage());
            }
            
            // Add sale items and update product quantities
            foreach ($input['items'] as $itemIndex => $item) {
                try {
                    // Validate item data
                    if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                        error_log("POS Sale: Invalid quantity for item at index {$itemIndex}: " . json_encode($item));
                        continue; // Skip invalid items
                    }
                    
                    if (!isset($item['unit_price']) || !isset($item['total_price'])) {
                        error_log("POS Sale: Missing price data for item at index {$itemIndex}: " . json_encode($item));
                        continue; // Skip invalid items
                    }
                    
                    // Determine item type - use 'PHONE', 'ACCESSORY', 'PART', 'SERVICE', or 'OTHER' (PRODUCT is not in enum)
                    $itemType = 'OTHER'; // Default fallback
                    if (isset($item['category_name'])) {
                        $categoryName = strtolower($item['category_name']);
                        if (stripos($categoryName, 'phone') !== false || stripos($categoryName, 'mobile') !== false) {
                            $itemType = 'PHONE';
                        } elseif (stripos($categoryName, 'accessory') !== false) {
                            $itemType = 'ACCESSORY';
                        } elseif (stripos($categoryName, 'part') !== false || stripos($categoryName, 'component') !== false) {
                            $itemType = 'PART';
                        } elseif (stripos($categoryName, 'service') !== false) {
                            $itemType = 'SERVICE';
                        }
                    }
                    
                    // Create sale item
                    if (!$this->saleItem || !method_exists($this->saleItem, 'create')) {
                        throw new \Exception('SaleItem model not properly initialized');
                    }
                    
                    // Check if this is a swapped item being resold
                    $isSwappedItem = false;
                    if (isset($item['product_id'])) {
                        try {
                            $product = $this->product->find($item['product_id'], $companyId);
                            if ($product) {
                                $isSwappedItem = isset($product['is_swapped_item']) && ($product['is_swapped_item'] == 1 || $product['is_swapped_item'] === '1' || $product['is_swapped_item'] === true);
                                
                                // Also check if product is linked via inventory_product_id in swapped_items
                                if (!$isSwappedItem) {
                                    try {
                                        $db = \Database::getInstance()->getConnection();
                                        $checkStmt = $db->prepare("SELECT id FROM swapped_items WHERE inventory_product_id = ? LIMIT 1");
                                        $checkStmt->execute([$item['product_id']]);
                                        $swappedItem = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                                        if ($swappedItem) {
                                            $isSwappedItem = true;
                                        }
                                    } catch (\Exception $e) {
                                        // Silently fail - just continue
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Continue if product check fails
                        }
                    }
                    
                    $saleItemId = $this->saleItem->create([
                        'pos_sale_id' => $saleId,
                        'item_id' => $item['product_id'] ?? null,
                        'item_type' => $itemType,
                        'item_description' => $item['name'] ?? 'Product',
                        'quantity' => intval($item['quantity']),
                        'unit_price' => floatval($item['unit_price']),
                        'total_price' => floatval($item['total_price']),
                        'is_swapped_item' => $isSwappedItem ? 1 : 0
                    ]);
                    
                    // Note: lastInsertId() can return "0" (string) for tables without auto-increment
                    // We check for false/null/empty instead
                    if ($saleItemId === false || $saleItemId === null || $saleItemId === '') {
                        error_log("POS Sale: Failed to create sale item at index {$itemIndex} - no ID returned");
                        // Continue with other items even if one fails, but log the error
                    }
                } catch (\Exception $itemError) {
                    error_log("POS Sale: Error creating sale item at index {$itemIndex}: " . $itemError->getMessage());
                    // Continue with other items
                    continue;
                }
                
                // Update product quantity if it's a product sale
                if (isset($item['product_id'])) {
                    try {
                        if (!$this->product || !method_exists($this->product, 'find')) {
                            error_log("POS Sale: Product model not properly initialized");
                            continue;
                        }
                        
                        $product = $this->product->find($item['product_id'], $companyId);
                        if (!$product) {
                            error_log("POS Sale: Product not found for ID: " . $item['product_id']);
                            continue;
                        }
                        // Check if this is a swapped item being resold
                        $isSwappedItem = isset($product['is_swapped_item']) && $product['is_swapped_item'] == 1;
                        $swapRefId = $product['swap_ref_id'] ?? null;
                        
                        // Also check if product is linked via inventory_product_id in swapped_items
                        $isSwappedViaInventory = false;
                        $swappedItem = null;
                        if (!$isSwappedItem || !$swapRefId) {
                            try {
                                $db = \Database::getInstance()->getConnection();
                                $checkStmt = $db->prepare("SELECT id, swap_id FROM swapped_items WHERE inventory_product_id = ? LIMIT 1");
                                $checkStmt->execute([$item['product_id']]);
                                $swappedItem = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                                if ($swappedItem) {
                                    $isSwappedViaInventory = true;
                                    $swapRefId = $swappedItem['id'];
                                }
                            } catch (\Exception $e) {
                                // Silently fail - just continue
                            }
                        }
                        
                        // For swapped items, set quantity to 0 (hide from POS after sale)
                        // For regular items, subtract the sold quantity
                        if (($isSwappedItem && $swapRefId) || $isSwappedViaInventory) {
                            // Swapped items are single items, set to 0 when sold
                            $this->product->updateQuantity($item['product_id'], 0, $companyId);
                            
                            try {
                                // Mark swapped item as resold
                                $swappedItemModel = new \App\Models\SwappedItem();
                                $swappedItemModel->markAsSold($swapRefId, $item['unit_price']);
                                
                                // Get swap_id and finalize profit
                                $swapModel = new \App\Models\Swap();
                                $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                                $swapId = null;
                                
                                try {
                                    // Get swap_id from swapped_item
                                    if ($isSwappedViaInventory && $swappedItem && isset($swappedItem['swap_id'])) {
                                        $swapId = $swappedItem['swap_id'];
                                    } elseif ($isSwappedItem && $swapRefId) {
                                        // Try to get from swapRefId
                                        $swappedItemFull = $swappedItemModel->find($swapRefId);
                                        if ($swappedItemFull && isset($swappedItemFull['swap_id'])) {
                                            $swapId = $swappedItemFull['swap_id'];
                                        }
                                    } elseif ($isSwappedViaInventory && $swapRefId) {
                                        // Re-fetch to get swap_id
                                        $swappedItemFull = $swappedItemModel->find($swapRefId);
                                        if ($swappedItemFull && isset($swappedItemFull['swap_id'])) {
                                            $swapId = $swappedItemFull['swap_id'];
                                        }
                                    }
                                    
                                    // Update swap status to 'resold' and link customer item sale for profit calculation
                                    if ($swapId) {
                                        $swapModel->updateStatus($swapId, $companyId, 'resold');
                                        
                                        // Link customer item sale to profit link (triggers automatic profit calculation)
                                        try {
                                            $profitLink = $swapProfitLinkModel->findBySwapId($swapId);
                                            if ($profitLink) {
                                                // Link the customer item sale - this will auto-calculate profit if both sales are linked
                                                $swapProfitLinkModel->linkCustomerItemSale($swapId, $saleId);
                                                error_log("POS Sale: Linked customer item sale #{$saleId} to swap #{$swapId} for profit calculation");
                                            } else {
                                                error_log("POS Sale: No profit link found for swap #{$swapId}");
                                            }
                                        } catch (\Exception $profitError) {
                                            error_log("POS Sale: Error linking customer sale for profit calculation - swap #{$swapId}: " . $profitError->getMessage());
                                        }
                                    }
                                } catch (\Exception $swapException) {
                                    error_log("POS Sale: Could not update swap status to resold - " . $swapException->getMessage());
                                }
                                
                                error_log("POS Sale: Marked swapped item #{$swapRefId} as resold for sale #{$saleId}");
                            } catch (\Exception $swapError) {
                                // Log but don't fail the sale
                                error_log("POS Sale: Error marking swapped item as resold - " . $swapError->getMessage());
                            }
                        } else {
                            // Regular product - subtract sold quantity
                            $oldQuantity = $product['quantity'];
                            $newQuantity = max(0, $oldQuantity - $item['quantity']);
                            
                            if (method_exists($this->product, 'updateQuantity')) {
                                $this->product->updateQuantity($item['product_id'], $newQuantity, $companyId);
                                
                                // Track when restock batch is sold out
                                if ($newQuantity == 0 && $oldQuantity > 0) {
                                    $this->markRestockSoldOut($item['product_id'], $companyId);
                                }
                            } else {
                                error_log("POS Sale: updateQuantity method not found on Product model");
                            }
                        }
                    } catch (\Exception $productError) {
                        error_log("POS Sale: Error updating product quantity for product ID {$item['product_id']}: " . $productError->getMessage());
                        // Continue with other items even if product update fails
                    }
                }
            }
            
            // Check if this sale contains swapped items being resold
            // If so, skip SMS notification (business is done immediately after swapping)
            $hasSwappedItems = false;
            foreach ($input['items'] as $item) {
                if (isset($item['is_swapped_item']) && $item['is_swapped_item'] == 1) {
                    $hasSwappedItems = true;
                    break;
                }
                // Also check if product has swapped flag
                if (isset($item['product_id'])) {
                    try {
                        $product = $this->product->find($item['product_id'], $companyId);
                        if ($product && (($product['is_swapped_item'] ?? 0) == 1 || ($product['is_swapped_for_resale'] ?? false))) {
                            $hasSwappedItems = true;
                            break;
                        }
                    } catch (\Exception $e) {
                        // Continue checking other items
                    }
                }
            }
            
            // Send SMS notification if customer contact is provided (non-critical, don't fail sale if this fails)
            // BUT: Skip SMS if this sale contains swapped items being resold (business done immediately after swapping)
            // First, try to get customer phone from customer_id if customer_contact is not provided
            $phoneNumberToUse = null;
            if (!$hasSwappedItems) {
                $phoneNumberToUse = $customer_contact;
                if (!$phoneNumberToUse && $customer_id) {
                    try {
                        $customer = $this->customer->find($customer_id, $companyId);
                        if ($customer) {
                            $phoneNumberToUse = $customer['phone_number'] ?? $customer['phone'] ?? null;
                        }
                    } catch (\Exception $e) {
                        error_log("POS Sale: Could not fetch customer phone: " . $e->getMessage());
                    }
                }
            } else {
                error_log("POS Sale: Sale contains swapped items being resold - skipping SMS notification (business done immediately after swapping)");
            }
            
            // Create payment record for the sale (supports partial payments - only if module enabled)
            if ($partialPaymentsEnabled) {
                try {
                    if ($amount_received > 0) {
                        $this->salePayment->create([
                            'pos_sale_id' => $saleId,
                            'company_id' => $companyId,
                            'amount' => $amount_received,
                            'payment_method' => $paymentMethodFormatted,
                            'recorded_by_user_id' => $userId,
                            'notes' => 'Initial payment' . ($amount_received < $total ? ' (Partial)' : '')
                        ]);
                    }
                } catch (\Exception $paymentError) {
                    error_log("POS Sale: Error creating payment record (non-fatal): " . $paymentError->getMessage());
                    // Don't fail the sale if payment record creation fails
                }
            }
            
            // Clean all output buffers completely
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure no output before JSON
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            // Get payment stats for response (only if partial payments enabled)
            $paymentStats = null;
            if ($partialPaymentsEnabled) {
                try {
                    $paymentStats = $this->salePayment->getPaymentStats($saleId, $companyId);
                } catch (\Exception $e) {
                    error_log("POS Sale: Error getting payment stats (non-fatal): " . $e->getMessage());
                }
            }
            
            // Send SMS notification with payment information
            if ($phoneNumberToUse) {
                try {
                    $itemsList = implode(', ', array_map(function($item) {
                        return $item['name'] . ' (x' . $item['quantity'] . ')';
                    }, $input['items']));
                    
                    // Get payment information for SMS if partial payments enabled
                    $paymentInfoForSMS = null;
                    if ($partialPaymentsEnabled && $paymentStats) {
                        $paymentInfoForSMS = [
                            'total_paid' => $amount_received,
                            'remaining' => $paymentStats['remaining'],
                            'payment_status' => $paymentStatus
                        ];
                    }
                    
                    $purchaseData = [
                        'order_id' => 'POS-' . $saleId,
                        'amount' => number_format($total, 2),
                        'items' => $itemsList,
                        'phone_number' => $phoneNumberToUse,
                        'company_id' => $companyId,  // Include company_id for SMS quota management
                        'payment_info' => $paymentInfoForSMS  // Include payment info for partial payments
                    ];
                    
                    error_log("POS Sale: Attempting to send SMS to {$phoneNumberToUse} for company {$companyId}, sale ID: {$saleId}");
                    
                    if ($this->notificationService && method_exists($this->notificationService, 'sendPurchaseConfirmation')) {
                        // NotificationService will handle balance checking, quota decrementing, and logging
                        $smsResult = $this->notificationService->sendPurchaseConfirmation($purchaseData);
                        
                        // Log result for debugging
                        if ($smsResult['success']) {
                            error_log("POS Sale: SMS sent successfully to {$phoneNumberToUse}");
                        } else {
                            error_log("POS Sale: SMS failed - " . ($smsResult['error'] ?? 'Unknown error'));
                            
                            // Log additional info if SMS failed due to insufficient credits
                            if (isset($smsResult['insufficient_credits'])) {
                                error_log("POS Sale: SMS failed - Insufficient SMS credits for company {$companyId}. Remaining: " . ($smsResult['error'] ?? 'Unknown'));
                            }
                        }
                    } else {
                        error_log("POS Sale: NotificationService not available or sendPurchaseConfirmation method missing");
                    }
                } catch (\Exception $smsError) {
                    // Log but don't fail the sale if SMS fails
                    error_log("POS Sale: SMS notification exception: " . $smsError->getMessage());
                    error_log("POS Sale: SMS exception trace: " . $smsError->getTraceAsString());
                }
            } else {
                error_log("POS Sale: No customer phone number available for SMS notification. customer_contact: " . ($customer_contact ?? 'null') . ", customer_id: " . ($customer_id ?? 'null'));
            }
            
            // Fetch the sale to get unique_id
            $saleRecord = $this->sale->findById($saleId);
            $uniqueId = $saleRecord['unique_id'] ?? 'SEL-SALE-' . str_pad($saleId, 3, '0', STR_PAD_LEFT);
            
            $responseData = [
                'success' => true,
                'message' => 'Sale processed successfully',
                'sale_id' => $saleId,
                'unique_id' => $uniqueId,
                'total' => $total,
                'data' => [
                    'sale_id' => $saleId,
                    'unique_id' => $uniqueId,
                    'total' => $total,
                    'items_count' => count($input['items'])
                ]
            ];
            
            // Add partial payment info only if module is enabled
            if ($partialPaymentsEnabled && $paymentStats) {
                $responseData['amount_received'] = $amount_received;
                $responseData['payment_status'] = $paymentStatus;
                $responseData['remaining'] = $paymentStats['remaining'];
                $responseData['data']['amount_received'] = $amount_received;
                $responseData['data']['payment_status'] = $paymentStatus;
                $responseData['data']['remaining'] = $paymentStats['remaining'];
            }
            
            $response = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // Check if JSON encoding was successful
            if ($response === false) {
                throw new \Exception('Failed to encode sale response to JSON: ' . json_last_error_msg());
            }
            
            // Output the JSON response
            echo $response;
            exit;
            
        } catch (\ErrorException $e) {
            // Handle PHP errors converted to exceptions
            $errorMessage = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            
            error_log("POSController processSale PHP error: " . $errorMessage);
            error_log("POSController processSale trace: " . $errorTrace);
            
            // Clean all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure proper headers
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            $isDevelopment = (defined('APP_ENV') && APP_ENV === 'development') || 
                           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
            
            $errorResponseData = [
                'success' => false,
                'error' => 'PHP Error: ' . $errorMessage,
                'message' => 'An error occurred while processing the sale'
            ];
            
            if ($isDevelopment) {
                $errorResponseData['debug'] = [
                    'error_message' => $errorMessage,
                    'error_file' => $e->getFile() . ':' . $e->getLine(),
                    'error_severity' => $e->getSeverity()
                ];
            }
            
            echo json_encode($errorResponseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            
            error_log("POSController processSale error: " . $errorMessage);
            error_log("POSController processSale trace: " . $errorTrace);
            
            // Clean all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure proper headers
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            // Return user-friendly error message (don't expose internal details in production)
            $userMessage = 'Failed to process sale';
            $debugInfo = null;
            
            // For development, include more details
            $isDevelopment = (defined('APP_ENV') && APP_ENV === 'development') || 
                           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
            
            if (strpos($errorMessage, 'Authentication') !== false) {
                $userMessage = 'Authentication failed. Please login again.';
            } elseif (strpos($errorMessage, 'sale') !== false || strpos($errorMessage, 'Sale') !== false) {
                $userMessage = 'Failed to create sale record. Please try again.';
            } elseif (strpos($errorMessage, 'database') !== false || strpos($errorMessage, 'Database') !== false || strpos($errorMessage, 'SQL') !== false) {
                $userMessage = 'Database error occurred. Please contact support.';
            }
            
            $errorResponseData = [
                'success' => false,
                'error' => $userMessage,
                'message' => $userMessage
            ];
            
            // Include debug info in development mode
            if ($isDevelopment) {
                $errorResponseData['debug'] = [
                    'error_message' => $errorMessage,
                    'error_file' => $e->getFile() . ':' . $e->getLine()
                ];
            }
            
            $errorResponse = json_encode($errorResponseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            echo $errorResponse;
            exit;
        } catch (\Throwable $e) {
            // Catch any other throwable (including errors in PHP 7+)
            $errorMessage = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            
            error_log("POSController processSale throwable error: " . $errorMessage);
            error_log("POSController processSale trace: " . $errorTrace);
            
            // Clean all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure proper headers
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            $isDevelopment = (defined('APP_ENV') && APP_ENV === 'development') || 
                           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
            
            $errorResponseData = [
                'success' => false,
                'error' => 'Error: ' . $errorMessage,
                'message' => 'An unexpected error occurred'
            ];
            
            if ($isDevelopment) {
                $errorResponseData['debug'] = [
                    'error_message' => $errorMessage,
                    'error_file' => $e->getFile() . ':' . $e->getLine(),
                    'error_type' => get_class($e)
                ];
            }
            
            echo json_encode($errorResponseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } finally {
            // Restore error handler
            restore_error_handler();
        }
    }

    /**
     * Display sales history
     */
    public function salesHistory() {
        // Handle web authentication
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        
        $title = 'Sales History';
        $page = 'sales-history';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/pos_sales_history.php';
        $content = ob_get_clean();
        
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show sale details
     */
    public function showSale($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'salesperson', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        $sale = $this->sale->find($id, $companyId);
        if (!$sale) {
            $_SESSION['flash_error'] = 'Sale not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/pos/sales-history');
            exit;
        }
        
        $items = $this->saleItem->bySale($id);
        
        $title = 'Sale Details';
        $viewFile = __DIR__ . '/../Views/pos_sale_details.php';
        
        // Capture the view content
        ob_start();
        include $viewFile;
        $content = ob_get_clean();
        
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * API endpoint: Get products for POS
     */
    public function apiProducts() {
        // Clean all existing output buffers first
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering
        ob_start();
        
        // Suppress any PHP errors/warnings that might output HTML
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Set JSON header immediately
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access this resource'
                ]);
                exit;
            }
            
            // Check if user has required role
            $userRole = $user['role'] ?? '';
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to access this resource'
                ]);
                exit;
            }
            
            // Get company_id from authenticated user
            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                // For debugging - let's use a default company ID if session doesn't have one
                $companyId = 2; // Default company ID for testing
                error_log("POSController apiProducts: No company_id in user data, using default: {$companyId}");
            }
            
            // Debug user data
            error_log("POSController apiProducts: User data: " . json_encode($user));
            
            $category_id = $_GET['category_id'] ?? null;
            $search = $_GET['search'] ?? null;
            
            try {
                if ($search) {
                    $products = $this->product->search($companyId, $search, $category_id);
                } else {
                    // Get all products including swapped items (swapped items should always be visible for resale)
                    $products = $this->product->findByCompanyForPOS($companyId, 200, $category_id);
                }
            } catch (\Exception $e) {
                error_log("POSController: Error fetching products: " . $e->getMessage());
                $products = [];
            }
            
            // Ensure products is an array
            if (!is_array($products)) {
                $products = [];
            }
            
            // Ensure swapped items always show as available for resale
            // The Product model should already return is_swapped_item and swap_ref_id fields
            foreach ($products as &$product) {
                // Skip if product is not an array
                if (!is_array($product)) {
                    continue;
                }
                
                // Check if item is swapped - multiple ways to detect
                // The Product model query should already include these flags
                $hasIsSwappedFlag = isset($product['is_swapped_item']) && (
                    $product['is_swapped_item'] == 1 || 
                    $product['is_swapped_item'] === '1' || 
                    $product['is_swapped_item'] === true ||
                    intval($product['is_swapped_item'] ?? 0) > 0
                );
                
                $hasSwapRef = false;
                if (isset($product['swap_ref_id']) && $product['swap_ref_id'] !== null) {
                    $swapRef = $product['swap_ref_id'];
                    if ($swapRef !== 'NULL' && $swapRef !== '' && trim(strval($swapRef)) !== '') {
                        $swapRefInt = intval($swapRef);
                        $hasSwapRef = $swapRefInt > 0;
                    }
                }
                
                $isSwappedItem = $hasIsSwappedFlag || $hasSwapRef;
                
                if ($isSwappedItem) {
                    // Only process swapped items if they have quantity > 0 (available for resale)
                    // Swapped items with quantity = 0 are already filtered out by the SQL query
                    if (($product['quantity'] ?? 0) > 0) {
                        // Mark as swapped item for frontend
                        $product['is_swapped_item'] = 1;
                        $product['is_swapped_for_resale'] = true;
                        
                        // Ensure quantity is 1 (swapped items are single items)
                        $product['quantity'] = 1;
                        
                        // Ensure status is available for swapped items
                        $product['status'] = 'available';
                        
                        // Use resell_price or display_price if available, otherwise use price
                        if (isset($product['resell_price']) && floatval($product['resell_price']) > 0) {
                            $product['price'] = floatval($product['resell_price']);
                        } elseif (isset($product['display_price']) && floatval($product['display_price']) > 0) {
                            $product['price'] = floatval($product['display_price']);
                        }
                        // If price is still 0, keep original price (might need to be set manually)
                    }
                }
            }
            
            // Clean all output buffers completely
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure no output before JSON
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            // Ensure products is an array before encoding
            if (!is_array($products)) {
                $products = [];
            }
            
            $responseData = [
                'success' => true,
                'data' => $products
            ];
            
            $response = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // Check if JSON encoding was successful
            if ($response === false) {
                $errorMsg = json_last_error_msg();
                error_log("POSController: JSON encoding failed: " . $errorMsg);
                error_log("POSController: Products data type: " . gettype($products));
                error_log("POSController: Products count: " . (is_array($products) ? count($products) : 'N/A'));
                throw new \Exception('Failed to encode products to JSON: ' . $errorMsg);
            }
            
            echo $response;
            exit;
            
        } catch (\Exception $e) {
            error_log("POSController apiProducts error: " . $e->getMessage());
            error_log("POSController apiProducts trace: " . $e->getTraceAsString());
            
            // Clean all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ensure proper headers
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            $errorResponse = json_encode([
                'success' => false,
                'error' => 'Failed to load products',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            echo $errorResponse;
            exit;
        }
    }

    /**
     * API endpoint: Get customers for POS
     */
    public function apiCustomers() {
        // Start output buffering to prevent any HTML output
        ob_start();
        
        // Suppress any PHP errors/warnings that might output HTML
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clean any existing output
        if (ob_get_level()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access this resource'
                ]);
                exit;
            }
            
            // Check if user has required role
            $userRole = $user['role'] ?? '';
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to access this resource'
                ]);
                exit;
            }
            
            // Get company_id from authenticated user
            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                // For debugging - let's use a default company ID if session doesn't have one
                $companyId = 2; // Default company ID for testing
                error_log("POSController apiCustomers: No company_id in user data, using default: {$companyId}");
            }
            
            // Debug user data
            error_log("POSController apiCustomers: User data: " . json_encode($user));
            
            $customers = $this->customer->findByCompany($companyId, 100);
            
            // Clean output buffer and send JSON response
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $customers
            ]);
            exit;
            
        } catch (\Exception $e) {
            error_log("POSController apiCustomers error: " . $e->getMessage());
            http_response_code(500);
            // Clean output buffer and send JSON error response
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while loading customers'
            ]);
            exit;
        }
    }

    /**
     * API endpoint: Get sales for company
     */
    public function apiSales() {
        // Start output buffering to prevent any HTML output
        ob_start();
        
        // Suppress any PHP errors/warnings that might output HTML
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clean any existing output
        if (ob_get_level()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access this resource'
                ]);
                exit;
            }
            
            // Check if user has required role
            $userRole = $user['role'] ?? '';
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to access this resource'
                ]);
                exit;
            }
            
            $companyId = $user['company_id'] ?? null;
            $userId = $user['id'] ?? null;
        
            $sale_type = $_GET['sale_type'] ?? null;
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 20);
            
            // Get user role from database for accurate filtering
            require_once __DIR__ . '/../../config/database.php';
            $db = \Database::getInstance()->getConnection();
            $userRoleCheck = $db->prepare("SELECT role FROM users WHERE id = ?");
            $userRoleCheck->execute([$userId]);
            $userRoleData = $userRoleCheck->fetch(\PDO::FETCH_ASSOC);
            $actualUserRole = $userRoleData['role'] ?? $userRole;
            
            if ($actualUserRole === 'salesperson') {
                // Salesperson: Only show their own sales, exclude technician sales
                $sales = $this->sale->findByCashierExcludingRole($userId, $companyId, 'technician', $date_from, $date_to);
                $totalSales = count($sales);
                $totalPages = ceil($totalSales / $limit);
                $offset = ($page - 1) * $limit;
                $sales = array_slice($sales, $offset, $limit);
            } elseif ($actualUserRole === 'technician') {
                // Technician: Only show their own sales
                $sales = $this->sale->findByCashier($userId, $companyId, $date_from, $date_to);
                $totalSales = count($sales);
                $totalPages = ceil($totalSales / $limit);
                $offset = ($page - 1) * $limit;
                $sales = array_slice($sales, $offset, $limit);
            } else {
                // Manager/Admin: Show all sales with role tags
                $sales = $this->sale->findByCompanyWithRoles($companyId, $limit, $sale_type, $date_from, $date_to);
                $totalSales = $this->sale->getTotalCountByCompany($companyId, $date_from, $date_to);
                $totalPages = ceil($totalSales / $limit);
            }
            
            // Add payment information if partial payments module is enabled
            $partialPaymentsEnabled = $this->checkModuleEnabled($companyId, 'partial_payments', $userRole);
            if ($partialPaymentsEnabled) {
                foreach ($sales as &$sale) {
                    try {
                        $paymentStats = $this->salePayment->getPaymentStats($sale['id'], $companyId);
                        $sale['total_paid'] = $paymentStats['total_paid'];
                        $sale['remaining'] = $paymentStats['remaining'];
                    } catch (\Exception $e) {
                        // If payment tracking fails, assume full payment
                        $sale['total_paid'] = floatval($sale['final_amount'] ?? 0);
                        $sale['remaining'] = 0;
                    }
                }
                unset($sale); // Break reference
            }
            
            // Calculate total profit for managers only
            $totalProfit = null;
            if (in_array($actualUserRole, ['manager', 'admin', 'system_admin'])) {
                try {
                    // Check which products table exists
                    $checkProducts = $db->query("SHOW TABLES LIKE 'products'");
                    $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                    $hasProducts = $checkProducts->rowCount() > 0;
                    $hasProductsNew = $checkProductsNew->rowCount() > 0;
                    $productsTable = $hasProductsNew ? 'products_new' : ($hasProducts ? 'products' : null);
                    
                    if ($productsTable) {
                        // Check which cost column exists (prioritize cost_price, then cost, then purchase_price)
                        $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                        $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                        $checkPurchasePrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'purchase_price'");
                        $hasCostPrice = $checkCostPrice->rowCount() > 0;
                        $hasCost = $checkCost->rowCount() > 0;
                        $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
                        
                        // Determine cost column to use
                        $costColumn = '0';
                        if ($hasCostPrice) {
                            $costColumn = 'COALESCE(p.cost_price, 0)';
                        } elseif ($hasCost) {
                            $costColumn = 'COALESCE(p.cost, 0)';
                        } elseif ($hasPurchasePrice) {
                            $costColumn = 'COALESCE(p.purchase_price, 0)';
                        }
                        
                        // Check if is_swap_mode column exists
                        $checkIsSwapMode = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
                        $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
                        
                        // Build WHERE clause for date filtering
                        $whereClause = "ps.company_id = ?";
                        $params = [$companyId];
                        
                        // Exclude swap sales from profit calculation (swaps should only appear on swap page)
                        if ($hasIsSwapMode) {
                            $whereClause .= " AND (ps.is_swap_mode = 0 OR ps.is_swap_mode IS NULL)";
                        }
                        
                        if ($date_from) {
                            $whereClause .= " AND DATE(ps.created_at) >= ?";
                            $params[] = $date_from;
                        }
                        
                        if ($date_to) {
                            $whereClause .= " AND DATE(ps.created_at) <= ?";
                            $params[] = $date_to;
                        }
                        
                        // Calculate total revenue and cost (excluding swap sales)
                        $profitQuery = $db->prepare("
                            SELECT 
                                COALESCE(SUM(ps.final_amount), 0) as revenue,
                                COALESCE(SUM(
                                    (SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0)
                                     FROM pos_sale_items psi 
                                     LEFT JOIN {$productsTable} p ON (
                                         (psi.item_id = p.id AND p.company_id = ps.company_id)
                                         OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                                     )
                                     WHERE psi.pos_sale_id = ps.id AND p.id IS NOT NULL)
                                ), 0) as cost
                            FROM pos_sales ps
                            WHERE {$whereClause}
                        ");
                        
                        $profitQuery->execute($params);
                        $profitResult = $profitQuery->fetch(\PDO::FETCH_ASSOC);
                        
                        $revenue = floatval($profitResult['revenue'] ?? 0);
                        $cost = floatval($profitResult['cost'] ?? 0);
                        $totalProfit = $revenue - $cost;
                        
                        // Profit cannot be negative
                        if ($totalProfit < 0) {
                            $totalProfit = 0;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error calculating total profit in apiSales: " . $e->getMessage());
                    $totalProfit = null;
                }
            }
            
            // Clean output buffer and send JSON response
            ob_clean();
            $response = [
                'success' => true,
                'data' => $sales,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalSales,
                    'items_per_page' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ];
            
            // Add total profit for managers
            if ($totalProfit !== null) {
                $response['total_profit'] = $totalProfit;
            }
            
            echo json_encode($response);
            exit;
            
        } catch (\Exception $e) {
            error_log("POSController apiSales error: " . $e->getMessage());
            http_response_code(500);
            // Clean output buffer and send JSON error response
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while loading sales'
            ]);
            exit;
        }
    }

    /**
     * API endpoint: Get sale details
     */
    public function apiSaleDetails($id) {
        // Start output buffering to prevent any HTML output
        ob_start();
        
        // Suppress any PHP errors/warnings that might output HTML
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clean any existing output
        if (ob_get_level()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access this resource'
                ]);
                exit;
            }
            
            // Check if user has required role
            $userRole = $user['role'] ?? '';
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to access this resource'
                ]);
                exit;
            }
            
            $companyId = $user['company_id'] ?? null;
            $isSystemAdmin = ($userRole === 'system_admin');
            
            // For system_admin, allow viewing any sale; for others, filter by company
            if ($isSystemAdmin) {
                // System admin can view any sale - find without company filter
                if (!class_exists('Database')) {
                    require_once __DIR__ . '/../../config/database.php';
                }
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT ps.*, 
                           c.name as company_name,
                           cust.full_name as customer_name,
                           cust.phone_number as customer_phone,
                           u.full_name as created_by_name
                    FROM pos_sales ps
                    LEFT JOIN companies c ON ps.company_id = c.id
                    LEFT JOIN customers cust ON ps.customer_id = cust.id
                    LEFT JOIN users u ON ps.created_by_user_id = u.id
                    WHERE ps.id = ?
                    LIMIT 1
                ");
                $stmt->execute([$id]);
                $sale = $stmt->fetch(\PDO::FETCH_ASSOC);
                $saleCompanyId = $sale['company_id'] ?? null;
            } else {
                $sale = $this->sale->find($id, $companyId);
                $saleCompanyId = $companyId;
            }
            
            if (!$sale) {
                http_response_code(404);
                // Clean output buffer and send JSON error response
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Sale not found'
                ]);
                exit;
            }
            
            $items = $this->saleItem->bySale($id);
            
            // Enhance items with product information
            $enhancedItems = [];
            foreach ($items as $item) {
                $enhancedItem = $item;
                
                // If item has a product ID, get product details
                if (!empty($item['item_id'])) {
                    // Use sale's company_id for product lookup
                    $product = $this->product->find($item['item_id'], $saleCompanyId);
                    if ($product) {
                        $enhancedItem['product_name'] = $product['name'];
                        $enhancedItem['brand_name'] = $product['brand_name'] ?? $product['brand'] ?? '';
                        $enhancedItem['product_id'] = $product['product_id'] ?? $product['id'];
                        $enhancedItem['category_name'] = $product['category_name'] ?? '';
                    }
                }
                
                $enhancedItems[] = $enhancedItem;
            }
            
            $sale['items'] = $enhancedItems;
            
            // Clean output buffer and send JSON response
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $sale
            ]);
            exit;
            
        } catch (\Exception $e) {
            error_log("POSController apiSaleDetails error: " . $e->getMessage());
            http_response_code(500);
            // Clean output buffer and send JSON error response
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while loading sale details'
            ]);
            exit;
        }
    }

    /**
     * API endpoint: Get sales statistics
     */
    public function apiStats() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        $userRole = $payload->role ?? 'manager';
        
        // Check if POS module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'pos_sales', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'pos_sales'
            ]);
            exit;
        }
        
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        
        $stats = $this->sale->getStats($companyId, $date_from, $date_to);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }

    /**
     * API endpoint: Get daily sales report
     */
    public function apiDailyReport() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $report = $this->sale->getDailyReport($companyId, $date);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $report,
            'date' => $date
        ]);
        exit;
    }

    /**
     * API endpoint: Get top selling products
     */
    public function apiTopSellingProducts() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $limit = intval($_GET['limit'] ?? 10);
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        
        $products = $this->saleItem->getTopSellingProducts($companyId, $limit, $date_from, $date_to);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
        exit;
    }

    /**
     * API endpoint: Get quick stats for POS (items, sales today, revenue today)
     */
    public function apiQuickStats() {
        // Start output buffering
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                exit;
            }
            
            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID not found'
                ]);
                exit;
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Total items (products count)
            $itemsQuery = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
            $itemsQuery->execute([$companyId]);
            $totalItems = (int)($itemsQuery->fetch()['total'] ?? 0);
            
            // Sales today (count)
            $salesTodayQuery = $db->prepare("
                SELECT COUNT(*) as count 
                FROM pos_sales 
                WHERE company_id = ? AND DATE(created_at) = CURDATE()
            ");
            $salesTodayQuery->execute([$companyId]);
            $salesToday = (int)($salesTodayQuery->fetch()['count'] ?? 0);
            
            // Revenue today - EXCLUDE swap transactions
            $revenueTodayQuery = $db->prepare("
                SELECT COALESCE(SUM(final_amount), 0) as revenue 
                FROM pos_sales 
                WHERE company_id = ? AND DATE(created_at) = CURDATE()
                AND swap_id IS NULL
            ");
            $revenueTodayQuery->execute([$companyId]);
            $revenueToday = (float)($revenueTodayQuery->fetch()['revenue'] ?? 0);
            
            // Swap count today
            $swapCountQuery = $db->prepare("
                SELECT COUNT(*) as count 
                FROM swaps 
                WHERE company_id = ? AND DATE(created_at) = CURDATE()
            ");
            $swapCountQuery->execute([$companyId]);
            $swapCountToday = (int)($swapCountQuery->fetch()['count'] ?? 0);
            
            // Swap revenue today (cash top-up only)
            $checkTotalValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
            $hasTotalValue = $checkTotalValue->rowCount() > 0;
            $swapRevenueToday = 0;
            if ($hasTotalValue) {
                $swapRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(total_value), 0) as revenue
                    FROM swaps
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                ");
                $swapRevenueQuery->execute([$companyId]);
                $swapRevenueToday = (float)($swapRevenueQuery->fetch()['revenue'] ?? 0);
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_items' => $totalItems,
                    'sales_today' => $salesToday,
                    'revenue_today' => $revenueToday,
                    'swap_count_today' => $swapCountToday,
                    'swap_revenue_today' => $swapRevenueToday
                ]
            ]);
            exit;
            
        } catch (\Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch stats',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Generate receipt for a sale
     */
    public function generateReceipt($saleId) {
        // Handle web authentication
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson']);
        
        // Get company_id from session
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $companyId = 1; // Default company ID for testing
        }
        
        // Get sale details
        $sale = $this->sale->find($saleId, $companyId);
        if (!$sale) {
            http_response_code(404);
            echo "Sale not found.";
            exit;
        }
        
        // Get sale items
        $items = $this->saleItem->bySale($saleId);
        
        // Get payment information if partial payments module is enabled
        $paymentInfo = null;
        $paymentHistory = [];
        if ($this->checkModuleEnabled($companyId, 'partial_payments', $_SESSION['user']['role'] ?? '')) {
            try {
                $paymentInfo = $this->salePayment->getPaymentStats($saleId, $companyId);
                $paymentHistory = $this->salePayment->getBySaleId($saleId, $companyId);
            } catch (\Exception $e) {
                error_log("Receipt: Error loading payment info: " . $e->getMessage());
            }
        }
        
        // Get company information
        $company = $this->company->find($companyId);
        $companyName = $company['name'] ?? "SellApp Store";
        $companyAddress = $company['address'] ?? "123 Business Street, City, Country";
        $companyPhone = $company['phone'] ?? "+233 XX XXX XXXX";
        
        // Set headers for printing
        header('Content-Type: text/html; charset=utf-8');
        
        // Generate receipt HTML
        $receipt = $this->generateReceiptHTML($sale, $items, $companyName, $companyAddress, $companyPhone, $paymentInfo, $paymentHistory);
        
        echo $receipt;
        exit;
    }
    
    /**
     * Generate receipt HTML
     */
    private function generateReceiptHTML($sale, $items, $companyName, $companyAddress, $companyPhone, $paymentInfo = null, $paymentHistory = []) {
        $receiptDate = date('Y-m-d H:i:s', strtotime($sale['created_at']));
        $subtotal = $sale['total_amount'] ?? 0;
        $discount = $sale['discount'] ?? 0;
        $tax = $sale['tax'] ?? 0;
        $total = $sale['final_amount'] ?? 0;
        
        // Check if this is a swap transaction (only if columns exist)
        $isSwap = false;
        $swapDetails = null;
        
        // Check if swap columns exist before trying to access them
        if (isset($sale['is_swap_mode']) && isset($sale['swap_id'])) {
            $isSwap = $sale['is_swap_mode'] ?? false;
            if ($isSwap && $sale['swap_id']) {
                try {
                    $swapModel = new \App\Models\Swap();
                    $swapDetails = $swapModel->find($sale['swap_id'], $sale['company_id']);
                } catch (\Exception $e) {
                    // Swap details not available, continue without them
                    $isSwap = false;
                    $swapDetails = null;
                }
            }
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Receipt #{$sale['id']}</title>
            <style>
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    line-height: 1.4;
                    margin: 0;
                    padding: 20px;
                    background: white;
                }
                .receipt {
                    max-width: 300px;
                    margin: 0 auto;
                    border: 1px solid #ccc;
                    padding: 15px;
                }
                .header {
                    text-align: center;
                    border-bottom: 1px dashed #333;
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
                .company-name {
                    font-size: 16px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .company-details {
                    font-size: 10px;
                    color: #666;
                }
                .sale-info {
                    margin-bottom: 15px;
                }
                .sale-info div {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .items {
                    border-bottom: 1px dashed #333;
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
                .item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                }
                .item-name {
                    flex: 1;
                }
                .item-details {
                    font-size: 10px;
                    color: #666;
                    margin-left: 10px;
                }
                .totals {
                    margin-bottom: 15px;
                }
                .totals div {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .total {
                    font-weight: bold;
                    border-top: 1px solid #333;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .footer {
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px dashed #333;
                    padding-top: 10px;
                }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .receipt { border: none; max-width: none; }
                }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <div class='company-name'>{$companyName}</div>
                    <div class='company-details'>
                        {$companyAddress}<br>
                        Tel: {$companyPhone}
                    </div>
                </div>
                
                <div class='sale-info'>
                    <div><span>Receipt #:</span><span>{$sale['id']}</span></div>
                    <div><span>Date:</span><span>{$receiptDate}</span></div>
                    <div><span>Cashier:</span><span>" . ($_SESSION['user']['username'] ?? 'Unknown') . "</span></div>
                    <div><span>Customer:</span><span>" . ($sale['customer_name'] ?? 'Walk-in Customer') . "</span></div>
                    " . ($isSwap ? "<div><span>Transaction Type:</span><span>SWAP</span></div>" : "") . "
                    " . ($isSwap && $swapDetails && isset($swapDetails['transaction_code']) ? "<div><span>Swap Code:</span><span>{$swapDetails['transaction_code']}</span></div>" : "") . "
                </div>
                
                <div class='items'>
                    <div style='font-weight: bold; margin-bottom: 8px;'>ITEMS:</div>
                    " . $this->generateItemsHTML($items) . "
                </div>
                
                " . ($isSwap && $swapDetails ? $this->generateSwapDetailsHTML($swapDetails) : "") . "
                
                <div class='totals'>
                    <div><span>Subtotal:</span><span>₵" . number_format($subtotal, 2) . "</span></div>
                    " . ($discount > 0 ? "<div><span>Discount:</span><span>-₵" . number_format($discount, 2) . "</span></div>" : "") . "
                    " . ($tax > 0 ? "<div><span>Tax:</span><span>₵" . number_format($tax, 2) . "</span></div>" : "") . "
                    <div class='total'><span>TOTAL:</span><span>₵" . number_format($total, 2) . "</span></div>
                </div>
                
                " . ($paymentInfo ? $this->generatePaymentInfoHTML($paymentInfo, $paymentHistory, $total) : "") . "
                
                <div class='footer'>
                    <div>Payment Method: " . strtoupper($sale['payment_method'] ?? 'CASH') . "</div>
                    <div>Payment Status: " . strtoupper($sale['payment_status'] ?? 'PAID') . "</div>
                    <div>Thank you for your business!</div>
                    <div>Visit us again soon</div>
                </div>
            </div>
            
            <script>
                // Auto print when page loads
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>";
    }
    
    /**
     * Generate items HTML for receipt
     */
    private function generateItemsHTML($items) {
        $html = '';
        foreach ($items as $item) {
            $html .= "
                <div class='item'>
                    <div>
                        <div class='item-name'>{$item['item_description']}</div>
                        <div class='item-details'>{$item['quantity']} × ₵" . number_format($item['unit_price'], 2) . "</div>
                    </div>
                    <div>₵" . number_format($item['total_price'], 2) . "</div>
                </div>";
        }
        return $html;
    }

    /**
     * Generate payment information HTML for receipt
     */
    private function generatePaymentInfoHTML($paymentInfo, $paymentHistory, $totalAmount) {
        $totalPaid = floatval($paymentInfo['total_paid'] ?? 0);
        $remaining = floatval($paymentInfo['remaining'] ?? 0);
        $status = $paymentInfo['payment_status'] ?? 'PAID';
        
        $html = "<div class='payment-info' style='border-top: 1px dashed #333; padding-top: 10px; margin-top: 10px; margin-bottom: 10px;'>";
        $html .= "<div style='font-weight: bold; margin-bottom: 5px;'>PAYMENT INFORMATION:</div>";
        $html .= "<div style='display: flex; justify-content: space-between; margin-bottom: 3px;'><span>Total Amount:</span><span>₵" . number_format($totalAmount, 2) . "</span></div>";
        $html .= "<div style='display: flex; justify-content: space-between; margin-bottom: 3px;'><span>Total Paid:</span><span style='color: #10b981; font-weight: bold;'>₵" . number_format($totalPaid, 2) . "</span></div>";
        
        if ($remaining > 0) {
            $html .= "<div style='display: flex; justify-content: space-between; margin-bottom: 5px;'><span>Remaining:</span><span style='color: #f59e0b; font-weight: bold;'>₵" . number_format($remaining, 2) . "</span></div>";
        }
        
        if (count($paymentHistory) > 0) {
            $html .= "<div style='margin-top: 8px; font-size: 10px;'>";
            $html .= "<div style='font-weight: bold; margin-bottom: 3px;'>Payment History:</div>";
            foreach ($paymentHistory as $payment) {
                $paymentDate = date('M d, Y H:i', strtotime($payment['created_at']));
                $html .= "<div style='display: flex; justify-content: space-between; font-size: 9px; color: #666; margin-bottom: 2px;'>";
                $html .= "<span>{$paymentDate} - " . strtoupper($payment['payment_method'] ?? 'CASH') . "</span>";
                $html .= "<span>₵" . number_format(floatval($payment['amount'] ?? 0), 2) . "</span>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }
        
        $html .= "</div>";
        return $html;
    }
    
    /**
     * Generate swap details HTML for receipt
     */
    private function generateSwapDetailsHTML($swapDetails) {
        $customerBrand = $swapDetails['customer_brand'] ?? '';
        $customerModel = $swapDetails['customer_model'] ?? '';
        $customerDevice = trim($customerBrand . ' ' . $customerModel);
        if (empty($customerDevice)) {
            $customerDevice = 'N/A';
        }
        $estimatedValue = $swapDetails['customer_estimated_value'] ?? 0;
        $topup = $swapDetails['added_cash'] ?? 0;
        $totalValue = $estimatedValue + $topup;
        
        return "
        <div class='swap-details' style='border-top: 1px dashed #333; padding-top: 10px; margin-bottom: 15px;'>
            <div style='font-weight: bold; margin-bottom: 8px;'>SWAP DETAILS:</div>
            <div style='margin-bottom: 5px;'>
                <div><span>Customer Device:</span><span>{$customerDevice}</span></div>
                <div><span>Device Value:</span><span>₵" . number_format($estimatedValue, 2) . "</span></div>
                <div><span>Cash Top-up:</span><span>₵" . number_format($topup, 2) . "</span></div>
                <div style='font-weight: bold; border-top: 1px solid #333; padding-top: 3px; margin-top: 3px;'>
                    <span>Total Customer Value:</span><span>₵" . number_format($totalValue, 2) . "</span>
                </div>
            </div>
            <div style='font-size: 10px; color: #666; margin-top: 5px;'>
                Note: Customer device has been added to inventory for resale
            </div>
        </div>";
    }

    /**
     * Helper function to safely clean output buffers and send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200) {
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Process a swap sale (POST)
     */
    public function processSwapSale() {
        // Enable error reporting and output buffering to catch all errors
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Start output buffering to catch any errors
        ob_start();
        
        // Set JSON header first
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            // Log the start of swap processing
            error_log("Swap processing started");
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                $this->sendJsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
            }
            
            $companyId = $user['company_id'] ?? null;
            $userId = $user['id'] ?? null;
            
            if (!$companyId || !$userId) {
                error_log("Swap processing: Missing company_id or user_id. company_id: " . ($companyId ?? 'null') . ", user_id: " . ($userId ?? 'null'));
                $this->sendJsonResponse([
                    'success' => false, 
                    'error' => 'Authentication failed: Missing company or user information',
                    'debug' => ['company_id' => $companyId, 'user_id' => $userId, 'user_data' => $user]
                ], 401);
            }
            
            error_log("Swap processing: Authenticated user - company_id: $companyId, user_id: $userId");
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Swap processing: JSON decode error - " . json_last_error_msg());
                $this->sendJsonResponse([
                    'success' => false, 
                    'error' => 'Invalid JSON data: ' . json_last_error_msg()
                ], 400);
            }
            
            error_log("Swap processing: Received input data - " . json_encode($input));
        
            // Validate required fields
            if (!isset($input['company_product_id'])) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Company product ID is required'
                ], 400);
            }
        
            // Check if customer is selected or provided
            $customerId = isset($input['customer_id']) ? intval($input['customer_id']) : null;
            $customerName = trim($input['customer_name'] ?? '');
            $customerPhone = trim($input['customer_phone'] ?? '');
            
            if (!$customerId && (!$customerName || !$customerPhone)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Please select a customer or provide customer name and phone'
                ], 400);
            }
            
            // If customer is selected, get customer details
            if ($customerId) {
                $customer = $this->customer->find($customerId, $companyId);
                if ($customer) {
                    $customerName = $customer['full_name'];
                    $customerPhone = $customer['phone_number'];
                }
            }
            
            // Validate customer phone format if provided
            if ($customerPhone && !preg_match('/^[0-9+\-\s()]{10,15}$/', $customerPhone)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Please enter a valid phone number'
                ], 400);
            }
            
            $companyProductId = intval($input['company_product_id']);
            $customerBrandId = intval($input['customer_brand_id'] ?? 0);
            $customerBrand = trim($input['customer_brand'] ?? '');
            
            // If brand_id is provided but brand name is not, get brand name from database
            if ($customerBrandId && !$customerBrand) {
                $brandModel = new \App\Models\Brand();
                $brand = $brandModel->find($customerBrandId);
                if ($brand) {
                    $customerBrand = $brand['name'];
                }
            }
            
            $customerModel = trim($input['customer_model'] ?? '');
            $customerImei = trim($input['customer_imei'] ?? '');
            $customerCondition = $input['customer_condition'] ?? 'used';
            $estimatedValue = floatval($input['customer_estimated_value'] ?? 0);
            $topup = floatval($input['customer_topup'] ?? 0);
            $notes = trim($input['notes'] ?? '');
            $customerSpecs = !empty($input['customer_specs']) ? $input['customer_specs'] : null;
            
            // Get company product details
            $companyProduct = $this->product->find($companyProductId, $companyId);
            if (!$companyProduct || !is_array($companyProduct)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Company product not found'
                ], 404);
            }
            
            // Validate that estimated_value + topup equals the selling price
            // This ensures profit is instantly visible: profit = selling_price - cost_price
            $sellingPrice = $companyProduct['price'];
            $totalCustomerValue = $estimatedValue + $topup;
            
            // Allow small rounding differences (0.01)
            if (abs($totalCustomerValue - $sellingPrice) > 0.01) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Estimated Value (₵' . number_format($estimatedValue, 2) . ') + Cash Top-up (₵' . number_format($topup, 2) . ') = ₵' . number_format($totalCustomerValue, 2) . ' must equal Selling Price (₵' . number_format($sellingPrice, 2) . ')'
                ], 400);
            }
            
            // Validate minimum swap value (at least 30% of product price as safety check)
            $minValue = $sellingPrice * 0.3;
            if ($totalCustomerValue < $minValue) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Customer offer too low. Minimum value required: ₵' . number_format($minValue, 2)
                ], 400);
            }
            
            // Check if product is available for swap
            if (!$companyProduct['available_for_swap']) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'This product is not available for swap'
                ], 400);
            }
            
            // Check if product has sufficient quantity
            if ($companyProduct['quantity'] <= 0) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Product is out of stock'
                ], 400);
            }
            
            // Validate required fields for swap creation
            if (empty($customerBrand) || empty($customerModel)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Customer device brand and model are required'
                ], 400);
            }
            
            // Process the swap transaction
            // Use existing Swap model to create swap
            error_log("Swap processing: Creating swap with data - " . json_encode([
                'company_id' => $companyId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_id' => $customerId,
                'company_product_id' => $companyProductId,
                'customer_brand' => $customerBrand,
                'customer_model' => $customerModel,
                'estimated_value' => $estimatedValue,
                'added_cash' => $topup,
                'handled_by' => $userId
            ]));
            
            $swapModel = new \App\Models\Swap();
            try {
                $result = $swapModel->create([
                    'company_id' => $companyId,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'customer_id' => $customerId,
                    'company_product_id' => $companyProductId,
                    'customer_brand' => $customerBrand,
                    'customer_model' => $customerModel,
                    'customer_imei' => $customerImei ?: null,
                    'customer_condition' => $customerCondition ?: 'used',
                    'estimated_value' => $estimatedValue,
                    'resell_price' => $estimatedValue, // Use estimated value as resell price
                    'added_cash' => $topup,
                    'handled_by' => $userId,
                    'notes' => $notes ?: null,
                    'customer_specs' => $customerSpecs
                ]);
                
                error_log("Swap processing: Swap created successfully - swap_id: " . ($result['swap_id'] ?? 'unknown'));
            } catch (\Exception $swapException) {
                error_log("Swap processing: Swap model create failed - " . $swapException->getMessage());
                error_log("Swap processing: Swap model create trace - " . $swapException->getTraceAsString());
                throw new \Exception('Failed to create swap: ' . $swapException->getMessage(), 0, $swapException);
            }
            
            if ($result && isset($result['swap_id'])) {
                $swapId = $result['swap_id'];
                
                // Get the created swap details
                $swap = $swapModel->find($swapId, $companyId);
                
                // Create POS sale record linked to swap
                $saleId = $this->sale->create([
                    'company_id' => $companyId,
                    'customer_name' => $customerName,
                    'customer_contact' => $customerPhone,
                    'customer_id' => $customerId,
                    'total_amount' => $companyProduct['price'],
                    'discount' => 0,
                    'tax' => 0,
                    'final_amount' => $companyProduct['price'],
                    'payment_method' => 'swap',
                    'payment_status' => 'paid',
                    'created_by_user_id' => $userId,
                    'notes' => 'Swap transaction: ' . $result['transaction_code'],
                    'swap_id' => $swapId,
                    'is_swap_mode' => true
                ]);
                
                // Create sale item for company product
                // Use 'PHONE' if it's a phone product, otherwise 'OTHER' (since PRODUCT is not in enum)
                $itemType = 'OTHER';
                if (isset($companyProduct['category_name']) && stripos($companyProduct['category_name'], 'phone') !== false) {
                    $itemType = 'PHONE';
                }
                
                $this->saleItem->create([
                    'pos_sale_id' => $saleId,
                    'item_id' => $companyProductId,
                    'item_type' => $itemType,
                    'item_description' => $companyProduct['name'],
                    'quantity' => 1,
                    'unit_price' => $companyProduct['price'],
                    'total_price' => $companyProduct['price'],
                    'swap_id' => $swapId
                ]);
                
                // Link company item sale to profit link
                // Note: Profit link should have been created by Swap model during swap creation
                // But we link the sale ID after the sale is created (since we need the sale_id)
                try {
                    $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                    $profitLink = $swapProfitLinkModel->findBySwapId($swapId);
                    if ($profitLink) {
                        // Link the company item sale (this triggers profit calculation if both are sold)
                        $swapProfitLinkModel->linkCompanyItemSale($swapId, $saleId);
                        error_log("Swap processing: Linked company item sale #{$saleId} to swap #{$swapId} for profit tracking");
                    } else {
                        error_log("Swap processing: Profit link not found for swap #{$swapId} - profit link should have been created by Swap model");
                    }
                } catch (\Exception $profitLinkError) {
                    // Log but don't fail the swap transaction
                    error_log("Swap processing: Error linking company sale to profit link - " . $profitLinkError->getMessage());
                }
                
                // Send SMS notification to customer about swap completion
                if (!empty($customerPhone)) {
                    try {
                        $notificationService = new \App\Services\NotificationService();
                        
                        $swapData = [
                            'phone_number' => $customerPhone,
                            'company_id' => $companyId,
                            'swap_id' => $swapId,
                            'transaction_code' => $result['transaction_code'],
                            'customer_brand' => $customerBrand,
                            'customer_model' => $customerModel,
                            'company_product_name' => $companyProduct['name'],
                            'added_cash' => $topup
                        ];
                        
                        $smsResult = $notificationService->sendSwapNotification($swapData);
                        if ($smsResult['success']) {
                            error_log("Swap processing: SMS sent successfully to customer {$customerPhone}");
                        } else {
                            error_log("Swap processing: SMS failed - " . ($smsResult['error'] ?? 'Unknown error'));
                            // Don't fail swap if SMS fails
                        }
                    } catch (\Exception $smsException) {
                        error_log("Swap processing: Error sending SMS notification: " . $smsException->getMessage());
                        // Don't fail swap if SMS fails
                    }
                }
                
                // Send success response
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Swap processed successfully',
                    'sale_id' => $saleId,
                    'swap_id' => $swapId,
                    'transaction_code' => $result['transaction_code'],
                    'data' => [
                        'sale_id' => $saleId,
                        'swap_id' => $swapId,
                        'transaction_code' => $result['transaction_code']
                    ]
                ], 200);
            } else {
                throw new \Exception('Failed to create swap transaction');
            }
            
        } catch (\Exception $e) {
            error_log("Swap processing error: " . $e->getMessage());
            error_log("Swap processing error trace: " . $e->getTraceAsString());
            
            // Ensure all output buffers are cleaned
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Always return valid JSON
            http_response_code(500);
            header('Content-Type: application/json');
            
            // Always show detailed error for troubleshooting
            $errorMessage = 'Error processing swap: ' . $e->getMessage();
            
            $response = [
                'success' => false,
                'message' => $errorMessage,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10) // Limit trace lines
            ];
            
            // Also check for previous errors
            $previous = $e->getPrevious();
            if ($previous) {
                $response['previous_error'] = $previous->getMessage();
                $response['previous_file'] = $previous->getFile();
                $response['previous_line'] = $previous->getLine();
            }
            
            // Send error response
            $this->sendJsonResponse($response, 500);
        } catch (\Throwable $e) {
            // Catch any fatal errors or exceptions
            error_log("Fatal error in processSwapSale: " . $e->getMessage());
            error_log("Fatal error trace: " . $e->getTraceAsString());
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Fatal error: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'type' => get_class($e)
            ], 500);
        }
    }

    /**
     * Sanitize cart data based on user role - hide profit/cost from salespersons
     */
    private function sanitizeCartForRole($cart, $role) {
        // If user is manager/admin, return full cart data
        if (in_array($role, ['manager', 'admin', 'system_admin'])) {
            return $cart;
        }
        
        // For salespersons, remove profit and cost fields but keep other data
        $sanitizedCart = [];
        foreach ($cart as $key => $item) {
            $sanitizedCart[$key] = $item;
            // Remove profit and cost fields
            unset($sanitizedCart[$key]['profit']);
            unset($sanitizedCart[$key]['cost']);
        }
        
        return $sanitizedCart;
    }
    
    /**
     * Get products available for swap (API)
     */
    public function getSwapAvailableProducts() {
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $companyId = $user['company_id'];
        
        try {
            // Get products available for swap
            $swapModel = new \App\Models\Swap();
            $products = $swapModel->getAvailableProductsForSwap($companyId);
            
            echo json_encode([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            error_log("Error loading swap products: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error loading swap products'
            ]);
        }
        exit;
    }

    /**
     * Search customers for swap modal (API)
     */
    public function searchCustomers() {
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $companyId = $user['company_id'];
        $searchTerm = $_GET['q'] ?? '';
        
        try {
            $customers = $this->customer->search($searchTerm, $companyId);
            
            echo json_encode([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            error_log("Error searching customers: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error searching customers'
            ]);
        }
        exit;
    }

    /**
     * Delete a single sale (for managers with permission)
     * DELETE /api/pos/sale/{id}
     */
    public function deleteSale($id) {
        header('Content-Type: application/json');
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $userRole = $user['role'] ?? '';
            $companyId = $user['company_id'] ?? null;
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Company ID not found']);
                exit;
            }
            
            // Check if user is manager and has permission
            if ($userRole === 'manager') {
                if (!CompanyModule::isEnabled($companyId, 'manager_delete_sales')) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Delete sales permission not enabled for managers'
                    ]);
                    exit;
                }
            } elseif (!in_array($userRole, ['system_admin', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            // Verify sale belongs to company
            $sale = $this->sale->find($id, $companyId);
            if (!$sale) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sale not found']);
                exit;
            }
            
            // Delete sale items first (cascade should handle this, but being explicit)
            $db = \Database::getInstance()->getConnection();
            $deleteItemsStmt = $db->prepare("DELETE FROM pos_sale_items WHERE pos_sale_id = ?");
            $deleteItemsStmt->execute([$id]);
            
            // Delete sale
            $deleted = $this->sale->delete($id, $companyId);
            
            if ($deleted) {
                echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to delete sale']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting sale: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Bulk delete sales (for managers with permission)
     * DELETE /api/pos/sales/bulk
     */
    public function bulkDeleteSales() {
        header('Content-Type: application/json');
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            // Check authentication
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $userRole = $user['role'] ?? '';
            $companyId = $user['company_id'] ?? null;
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Company ID not found']);
                exit;
            }
            
            // Check if user is manager and has permission
            if ($userRole === 'manager') {
                if (!CompanyModule::isEnabled($companyId, 'manager_bulk_delete_sales')) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Bulk delete sales permission not enabled for managers'
                    ]);
                    exit;
                }
            } elseif (!in_array($userRole, ['system_admin', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No sale IDs provided']);
                exit;
            }
            
            $deletedCount = 0;
            $errors = [];
            $db = \Database::getInstance()->getConnection();
            
            foreach ($ids as $saleId) {
                $saleId = intval($saleId);
                if ($saleId <= 0) continue;
                
                // Verify the sale exists and belongs to the company
                $sale = $this->sale->find($saleId, $companyId);
                if (!$sale) {
                    $errors[] = "Sale ID {$saleId} not found or access denied";
                    continue;
                }
                
                // Delete sale items first
                $deleteItemsStmt = $db->prepare("DELETE FROM pos_sale_items WHERE pos_sale_id = ?");
                $deleteItemsStmt->execute([$saleId]);
                
                // Delete the sale
                $deleted = $this->sale->delete($saleId, $companyId);
                
                if ($deleted) {
                    $deletedCount++;
                } else {
                    $errors[] = "Failed to delete sale ID {$saleId}";
                }
            }
            
            if ($deletedCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully deleted {$deletedCount} sale(s)",
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No sales were deleted',
                    'errors' => $errors
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error bulk deleting sales: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Add partial payment to an existing sale
     * POST /api/pos/sale/{id}/payment
     */
    public function addPayment($saleId) {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $userId = $user['id'];
            $companyId = $user['company_id'] ?? null;

            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Company ID not found']);
                exit;
            }
            
            // Check if partial payments module is enabled
            if (!$this->checkModuleEnabled($companyId, 'partial_payments', $user['role'] ?? '')) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Partial payments feature is not enabled for this company'
                ]);
                exit;
            }

            // Verify sale exists and belongs to company
            $sale = $this->sale->find($saleId, $companyId);
            if (!$sale) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sale not found']);
                exit;
            }

            // Get payment data from request
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $amount = floatval($input['amount'] ?? 0);
            $paymentMethod = $input['payment_method'] ?? 'CASH';
            $notes = $input['notes'] ?? null;

            // Validate amount
            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than 0']);
                exit;
            }

            // Get current payment stats
            $paymentStats = $this->salePayment->getPaymentStats($saleId, $companyId);
            $remaining = $paymentStats['remaining'];

            // Check if payment exceeds remaining amount
            if ($amount > $remaining) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Payment amount (₵" . number_format($amount, 2) . ") exceeds remaining balance (₵" . number_format($remaining, 2) . ")"
                ]);
                exit;
            }

            // Create payment record
            $paymentId = $this->salePayment->create([
                'pos_sale_id' => $saleId,
                'company_id' => $companyId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'recorded_by_user_id' => $userId,
                'notes' => $notes
            ]);

            // Get updated payment stats
            $updatedStats = $this->salePayment->getPaymentStats($saleId, $companyId);

            // Send SMS notification for partial payment (non-critical, don't fail if this fails)
            try {
                // Get customer phone number from sale
                $customerPhone = null;
                if ($sale && isset($sale['customer_id']) && $sale['customer_id']) {
                    try {
                        $customer = $this->customer->find($sale['customer_id'], $companyId);
                        if ($customer) {
                            $customerPhone = $customer['phone_number'] ?? $customer['phone'] ?? null;
                        }
                    } catch (\Exception $e) {
                        error_log("POSController::addPayment - Could not fetch customer phone: " . $e->getMessage());
                    }
                }
                
                // If no customer phone from customer_id, try to get from sale data
                if (!$customerPhone && $sale) {
                    // Check if sale has customer_contact field (for backward compatibility)
                    $customerPhone = $sale['customer_contact'] ?? null;
                }
                
                if ($customerPhone) {
                    $isComplete = ($updatedStats['remaining'] <= 0);
                    $notificationService = new \App\Services\NotificationService();
                    $smsResult = $notificationService->sendPartialPaymentNotification([
                        'phone_number' => $customerPhone,
                        'company_id' => $companyId,
                        'sale_id' => $sale['unique_id'] ?? $saleId,
                        'amount_paid' => $amount,
                        'remaining' => $updatedStats['remaining'],
                        'total' => $sale['final_amount'] ?? $updatedStats['total'],
                        'is_complete' => $isComplete
                    ]);
                    
                    if (!$smsResult['success']) {
                        error_log("POSController::addPayment - SMS notification failed: " . ($smsResult['error'] ?? 'Unknown error'));
                    } else {
                        error_log("POSController::addPayment - SMS notification sent successfully to {$customerPhone}");
                    }
                } else {
                    error_log("POSController::addPayment - No customer phone number found for sale {$saleId}, skipping SMS notification");
                }
            } catch (\Exception $smsError) {
                // Don't fail the payment if SMS fails
                error_log("POSController::addPayment - Error sending SMS notification (non-fatal): " . $smsError->getMessage());
            }

            // Log audit event
            try {
                AuditService::log(
                    $companyId,
                    $userId,
                    'sale.payment_added',
                    'pos_sale_payment',
                    $paymentId,
                    [
                        'sale_id' => $saleId,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'remaining_before' => $remaining,
                        'remaining_after' => $updatedStats['remaining']
                    ]
                );
            } catch (\Exception $auditError) {
                error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
            }

            // Get all payments for response
            $payments = $this->salePayment->getBySaleId($saleId, $companyId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'payment_id' => $paymentId,
                'payment_stats' => $updatedStats,
                'payments' => $payments
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error recording payment: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Get payment history for a sale
     * GET /api/pos/sale/{id}/payments
     */
    public function getPayments($saleId) {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;

            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Company ID not found']);
                exit;
            }

            // Verify sale exists and belongs to company
            $sale = $this->sale->find($saleId, $companyId);
            if (!$sale) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sale not found']);
                exit;
            }

            // Get payments and stats
            $payments = $this->salePayment->getBySaleId($saleId, $companyId);
            $paymentStats = $this->salePayment->getPaymentStats($saleId, $companyId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'sale_id' => $saleId,
                'final_amount' => floatval($sale['final_amount']),
                'payment_stats' => $paymentStats,
                'payments' => $payments
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching payments: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Partial Payments Management Page
     * GET /dashboard/pos/partial-payments
     */
    public function partialPayments() {
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson']);
        
        $GLOBALS['currentPage'] = 'partial-payments';
        
        // Check if partial payments module is enabled
        $user = $_SESSION['user'] ?? null;
        $companyId = $user['company_id'] ?? null;
        
        if ($companyId && !$this->checkModuleEnabled($companyId, 'partial_payments', $user['role'] ?? '')) {
            $_SESSION['flash_error'] = 'Partial payments feature is not enabled for your company.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/pos');
            exit;
        }
        
        $title = 'Partial Payments';
        $page = 'partial-payments';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/partial_payments.php';
        $content = ob_get_clean();
        
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Get sales with partial payment information
     * GET /api/pos/partial-payments
     */
    public function apiPartialPayments() {
        try {
            // Clean any output buffers first
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Company ID not found']);
                exit;
            }
            
            // Check if partial payments module is enabled
            if (!$this->checkModuleEnabled($companyId, 'partial_payments', $user['role'] ?? '')) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Partial payments feature is not enabled'
                ]);
                exit;
            }

            // Get filter parameters
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
            $search = $_GET['search'] ?? '';
            $paymentStatus = $_GET['payment_status'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            
            // Get sales with payment information
            // Get a larger set first to filter properly
            $sales = $this->sale->findByCompany($companyId, 10000, null, $dateFrom, $dateTo);
            
            // Get payment stats for each sale first to determine actual payment status
            // IMPORTANT: Only include sales that were actually created with partial payment
            // or have payment records indicating partial payment
            $salesWithPaymentInfo = [];
            foreach ($sales as $sale) {
                try {
                    $originalPaymentStatus = strtoupper($sale['payment_status'] ?? 'PAID');
                    $finalAmount = floatval($sale['final_amount'] ?? 0);
                    
                    // Get payment stats
                    $paymentStats = $this->salePayment->getPaymentStats($sale['id'], $companyId);
                    $totalPaid = floatval($paymentStats['total_paid'] ?? 0);
                    $remaining = $finalAmount - $totalPaid;
                    
                    // Only include sales that were actually created with partial payment
                    // Check if sale has payment records (indicating it was tracked for partial payments)
                    $hasPaymentRecords = $totalPaid > 0;
                    
                    // Exclude swap transactions that were fully paid (swaps are typically fully paid at time of swap)
                    $isSwapTransaction = !empty($sale['swap_id']) || (!empty($sale['is_swap_mode']) && $sale['is_swap_mode'] == 1) || 
                                         (isset($sale['payment_method']) && strtoupper($sale['payment_method']) === 'SWAP');
                    if ($isSwapTransaction && $originalPaymentStatus === 'PAID' && !$hasPaymentRecords) {
                        // Swap transaction that was fully paid, skip it
                        continue;
                    }
                    
                    // If sale was marked as PAID at creation and has no payment records,
                    // it means it was fully paid at purchase - exclude it from partial payments
                    if ($originalPaymentStatus === 'PAID' && !$hasPaymentRecords) {
                        // This sale was fully paid at purchase, skip it
                        continue;
                    }
                    
                    // If sale was marked as PAID but has payment records, check if it's actually fully paid
                    if ($originalPaymentStatus === 'PAID' && $hasPaymentRecords) {
                        if ($remaining <= 0) {
                            // Fully paid via payment records, skip it
                            continue;
                        }
                    }
                    
                    // Determine actual payment status based on payment records
                    if ($remaining <= 0) {
                        $sale['payment_status'] = 'PAID';
                    } elseif ($totalPaid > 0) {
                        $sale['payment_status'] = 'PARTIAL';
                    } else {
                        // Only mark as UNPAID if it was originally marked as UNPAID or PARTIAL
                        if ($originalPaymentStatus === 'UNPAID' || $originalPaymentStatus === 'PARTIAL') {
                            $sale['payment_status'] = 'UNPAID';
                        } else {
                            // Was fully paid at purchase, skip it
                            continue;
                        }
                    }
                    
                    $sale['total_paid'] = $paymentStats['total_paid'];
                    $sale['remaining'] = $paymentStats['remaining'];
                } catch (\Exception $e) {
                    // If payment tracking fails, check original payment status
                    $originalPaymentStatus = strtoupper($sale['payment_status'] ?? 'PAID');
                    
                    // Exclude swap transactions that were fully paid
                    $isSwapTransaction = !empty($sale['swap_id']) || (!empty($sale['is_swap_mode']) && $sale['is_swap_mode'] == 1) || 
                                         (isset($sale['payment_method']) && strtoupper($sale['payment_method']) === 'SWAP');
                    if ($isSwapTransaction && $originalPaymentStatus === 'PAID') {
                        // Swap transaction that was fully paid, skip it
                        continue;
                    }
                    
                    if ($originalPaymentStatus === 'PAID') {
                        // Was fully paid at purchase, skip it
                        continue;
                    }
                    // For UNPAID/PARTIAL sales, include them but mark as the original status
                    $sale['payment_status'] = $originalPaymentStatus;
                    $sale['total_paid'] = 0;
                    $sale['remaining'] = floatval($sale['final_amount'] ?? 0);
                }
                $salesWithPaymentInfo[] = $sale;
            }
            
            // Default filter: Only show PARTIAL and UNPAID sales (exclude fully PAID)
            // Unless a specific status filter is provided
            if (empty($paymentStatus)) {
                $salesWithPaymentInfo = array_filter($salesWithPaymentInfo, function($sale) {
                    $status = strtoupper($sale['payment_status'] ?? 'PAID');
                    return $status === 'PARTIAL' || $status === 'UNPAID';
                });
            } else {
                // Filter by specific payment status if provided
                $salesWithPaymentInfo = array_filter($salesWithPaymentInfo, function($sale) use ($paymentStatus) {
                    return strtoupper($sale['payment_status'] ?? 'PAID') === strtoupper($paymentStatus);
                });
            }
            
            // Filter by search term if provided
            if ($search) {
                $searchLower = strtolower($search);
                $salesWithPaymentInfo = array_filter($salesWithPaymentInfo, function($sale) use ($searchLower) {
                    return strpos(strtolower($sale['id'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($sale['customer_name'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($sale['customer_name_from_table'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($sale['unique_id'] ?? ''), $searchLower) !== false;
                });
            }
            
            // Paginate
            $total = count($salesWithPaymentInfo);
            $offset = ($page - 1) * $limit;
            $paginatedSales = array_slice($salesWithPaymentInfo, $offset, $limit);
            
            // Clean any output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'sales' => $paginatedSales,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            // Clean any output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching payments: ' . $e->getMessage()
            ]);
        }
        exit;
    }

}