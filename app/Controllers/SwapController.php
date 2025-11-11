<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Swap;
use App\Models\SwappedItem;
use App\Models\SwapProfitLink;
use App\Models\CustomerProduct;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CompanyModule;
use App\Services\NotificationService;
use App\Services\AuditService;

class SwapController {
    private $swap;
    private $swappedItem;
    private $swapProfitLink;
    private $customerProduct;
    private $product;
    private $customer;
    private $notificationService;

    public function __construct() {
        $this->swap = new Swap();
        $this->swappedItem = new SwappedItem();
        $this->swapProfitLink = new SwapProfitLink();
        $this->customerProduct = new CustomerProduct();
        $this->product = new Product();
        $this->customer = new Customer();
        $this->notificationService = new NotificationService();
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
     * Display swap list for managers/salespersons
     */
    public function index() {
        // Get user from session (already authenticated by route middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $companyId = $user['company_id'];
        $userRole = $user['role'] ?? 'salesperson';
        
        // Check if Swap module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'swap', $userRole)) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('Swap module is not enabled for your company'));
            exit;
        }
        
        $status = $_GET['status'] ?? null;
        
        // Get all swaps to calculate stats (need to include swapped_items info)
        $allSwapsRaw = $this->swap->findByCompany($companyId, 1000, null);
        
        // Deduplicate swaps by ID (in case LEFT JOIN with swapped_items creates multiple rows)
        $allSwaps = [];
        $seenSwapIds = [];
        foreach ($allSwapsRaw as $swap) {
            $swapId = $swap['id'] ?? null;
            if ($swapId && !isset($seenSwapIds[$swapId])) {
                $seenSwapIds[$swapId] = true;
                $allSwaps[] = $swap;
            } elseif (!$swapId) {
                // If no ID, include it anyway (shouldn't happen)
                $allSwaps[] = $swap;
            } else {
                // Duplicate swap ID - merge resale_status if current is more specific
                // Prefer 'sold' > 'in_stock' > null
                $existingIdx = array_search($swapId, array_column($allSwaps, 'id'));
                if ($existingIdx !== false) {
                    $existingResaleStatus = $allSwaps[$existingIdx]['resale_status'] ?? null;
                    $newResaleStatus = $swap['resale_status'] ?? null;
                    // If new status is 'sold', update existing (sold is most specific)
                    if ($newResaleStatus === 'sold' && $existingResaleStatus !== 'sold') {
                        $allSwaps[$existingIdx]['resale_status'] = 'sold';
                    } elseif ($newResaleStatus === 'in_stock' && $existingResaleStatus === null) {
                        // If existing is null and new is in_stock, update
                        $allSwaps[$existingIdx]['resale_status'] = 'in_stock';
                    }
                }
            }
        }
        
        $swaps = $this->swap->findByCompany($companyId, 100, $status);
        $title = 'Swaps';
        
        // Get swapped items stats
        try {
            $swappedItemsStats = $this->swappedItem->getStats($companyId);
            $pendingSwappedItems = $this->swappedItem->getPendingResales($companyId);
        } catch (\Exception $e) {
            error_log("SwapController index: Error loading swapped items - " . $e->getMessage());
            $swappedItemsStats = ['in_stock_items' => 0, 'total_items' => 0, 'sold_items' => 0, 'total_estimated_value' => 0];
            $pendingSwappedItems = [];
        }
        
        // Get profit statistics
        try {
            $profitStats = $this->swapProfitLink->getStats($companyId);
        } catch (\Exception $e) {
            error_log("SwapController index: Error loading profit stats - " . $e->getMessage());
            $profitStats = [
                'total_estimated_profit' => 0,
                'total_final_profit' => 0,
                'avg_estimated_profit' => 0,
                'avg_final_profit' => 0,
                'finalized_links' => 0
            ];
        }
        
        // Get swap stats from Swap model (includes total_cash_received from database SUM)
        try {
            $swapModelStats = $this->swap->getStats($companyId);
        } catch (\Exception $e) {
            error_log("SwapController index: Error loading swap model stats - " . $e->getMessage());
            $swapModelStats = ['total_cash_received' => 0, 'total_value' => 0];
        }
        
        // Calculate stats from all swaps - use same logic as table display
        // Status logic:
        // - Resold: swapped_item.status = 'sold'
        // - Completed: swapped_item exists AND swapped_item.status = 'in_stock'
        // - Pending: no swapped_item exists OR swapped_item is NULL
        $swapStats = [
            'total' => count($allSwaps),
            'pending' => 0,
            'completed' => 0,
            'resold' => 0,
            'total_value' => 0,
            'total_cash_received' => floatval($swapModelStats['total_cash_received'] ?? 0),
            'in_stock' => 0, // Count items in stock (will be calculated in loop to match in_stock_value)
            'swapped_items_total' => $swappedItemsStats['total_items'] ?? 0,
            'swapped_items_sold' => $swappedItemsStats['sold_items'] ?? 0,
            'swapped_items_value' => $swappedItemsStats['total_estimated_value'] ?? 0,
            'in_stock_value' => 0, // Total value of items in stock
            'total_estimated_profit' => floatval($profitStats['total_estimated_profit'] ?? 0),
            'total_final_profit' => floatval($profitStats['total_final_profit'] ?? 0),
            'avg_estimated_profit' => floatval($profitStats['avg_estimated_profit'] ?? 0),
            'avg_final_profit' => floatval($profitStats['avg_final_profit'] ?? 0),
            'profit_realized_count' => intval($profitStats['finalized_links'] ?? 0)
        ];
        
        foreach ($allSwaps as $s) {
            $resaleStatus = $s['resale_status'] ?? null;
            $swapStatus = $s['status'] ?? 'pending';
            
            // Determine status - MUST MATCH VIEW DISPLAY LOGIC EXACTLY (swaps_index.php lines 269-284)
            // View logic:
            // - If resaleStatus === 'sold' → Resold
            // - If resaleStatus === 'in_stock' → Completed
            // - If swapStatus === 'completed' → Completed (regardless of resaleStatus)
            // - If swapStatus === 'resold' → Resold
            // - Otherwise → Pending
            
            if ($resaleStatus === 'sold') {
                $swapStats['resold']++;
            } elseif ($resaleStatus === 'in_stock') {
                // Swap has swapped_item in stock = completed (ready for resale)
                $swapStats['completed']++;
                // Count this as an in_stock item and add its value
                $swapStats['in_stock']++;
                $swapStats['in_stock_value'] += floatval($s['resell_price'] ?? $s['customer_product_value'] ?? 0);
            } elseif ($swapStatus === 'resold') {
                // Legacy: swap status is resold
                $swapStats['resold']++;
            } elseif ($swapStatus === 'completed') {
                // Swap status is completed = always count as completed (matches view display)
                $swapStats['completed']++;
                // Also count as in_stock if resaleStatus is 'in_stock'
                if ($resaleStatus === 'in_stock') {
                    $swapStats['in_stock']++;
                    $swapStats['in_stock_value'] += floatval($s['resell_price'] ?? $s['customer_product_value'] ?? 0);
                }
            } else {
                // No swapped_item or status is pending = pending
                $swapStats['pending']++;
            }
            
            $swapStats['total_value'] += floatval($s['total_value'] ?? 0);
            
            // Get added_cash - check multiple possible column names and log for debugging
            $addedCash = 0;
            if (isset($s['added_cash']) && $s['added_cash'] !== null && $s['added_cash'] !== 'NULL') {
                $addedCash = floatval($s['added_cash']);
            } elseif (isset($s['cash_added']) && $s['cash_added'] !== null && $s['cash_added'] !== 'NULL') {
                $addedCash = floatval($s['cash_added']);
            } elseif (isset($s['difference_paid_by_company']) && $s['difference_paid_by_company'] !== null) {
                // If company paid difference, that's negative cash received
                $addedCash = -floatval($s['difference_paid_by_company']);
            }
            
            // Accumulate cash received manually as fallback if model stats is 0
            if ($addedCash > 0) {
                if (!isset($swapStats['_manual_cash_total'])) {
                    $swapStats['_manual_cash_total'] = 0;
                }
                $swapStats['_manual_cash_total'] += $addedCash;
            }
            
            // Calculate profit - always check for profit data in swaps even if profitStats exists
            $profitEstimate = isset($s['profit_estimate']) && $s['profit_estimate'] !== null ? floatval($s['profit_estimate']) : null;
            $profitFinal = isset($s['final_profit']) && $s['final_profit'] !== null ? floatval($s['final_profit']) : null;
            $profitStatus = $s['profit_status'] ?? null;
            $isResold = ($resaleStatus === 'sold' || $swapStatus === 'resold');
            
            // Check if both sales are linked (means both items are sold, should have finalized profit)
            $hasCompanySaleId = !empty($s['company_item_sale_id']);
            $hasCustomerSaleId = !empty($s['customer_item_sale_id']);
            $bothItemsSold = $hasCompanySaleId && $hasCustomerSaleId;
            
            // Initialize calculation accumulators if needed
            if (!isset($swapStats['_calculated_estimated_profit'])) {
                $swapStats['_calculated_estimated_profit'] = 0;
                $swapStats['_calculated_final_profit'] = 0;
                $swapStats['_calculated_estimated_count'] = 0;
                $swapStats['_calculated_final_count'] = 0;
            }
            
            // CORE LOGIC: If both items are sold, profit MUST be realized (we have all the data)
            // If both sale IDs exist → both items are sold → profit is automatically realized
            if ($bothItemsSold) {
                // Both items are sold - profit MUST be realized, calculate it if not already done
                if ($profitFinal === null || $profitStatus !== 'finalized') {
                    try {
                        // Calculate and finalize profit immediately
                        $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                        $calculatedProfit = $swapProfitLinkModel->calculateSwapProfit($s['id']);
                        if ($calculatedProfit !== null) {
                            $profitFinal = $calculatedProfit;
                            $profitStatus = 'finalized';
                            // Re-fetch to get updated values
                            $updatedSwap = $this->swap->find($s['id'], $companyId);
                            if ($updatedSwap) {
                                $profitFinal = isset($updatedSwap['final_profit']) && $updatedSwap['final_profit'] !== null ? floatval($updatedSwap['final_profit']) : $profitFinal;
                                $profitStatus = $updatedSwap['profit_status'] ?? $profitStatus;
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("SwapController: Error calculating profit for swap #{$s['id']}: " . $e->getMessage());
                        // Even if calculation fails, if both are sold, use estimate as realized
                        if ($profitEstimate !== null) {
                            $profitFinal = $profitEstimate;
                        }
                    }
                }
                
                // Both items sold = realized profit (always, even if calculation failed)
                $profitToUse = $profitFinal !== null ? $profitFinal : ($profitEstimate !== null ? $profitEstimate : 0);
                $swapStats['_calculated_final_profit'] += $profitToUse;
                $swapStats['_calculated_final_count']++;
                
                // Track losses separately (negative profits)
                if ($profitToUse < 0) {
                    if (!isset($swapStats['_calculated_loss'])) {
                        $swapStats['_calculated_loss'] = 0;
                    }
                    $swapStats['_calculated_loss'] += abs($profitToUse);
                }
            } elseif ($isResold || $profitStatus === 'finalized') {
                // Legacy: status shows resold or profit is finalized (fallback for old swaps)
                $profitToUse = $profitFinal !== null ? $profitFinal : ($profitEstimate !== null ? $profitEstimate : 0);
                if ($profitToUse != 0 || $profitFinal !== null) {
                    $swapStats['_calculated_final_profit'] += $profitToUse;
                    $swapStats['_calculated_final_count']++;
                    if ($profitToUse < 0) {
                        if (!isset($swapStats['_calculated_loss'])) {
                            $swapStats['_calculated_loss'] = 0;
                        }
                        $swapStats['_calculated_loss'] += abs($profitToUse);
                    }
                }
            } elseif ($profitEstimate !== null) {
                // Pending: Only one item sold (or neither) - show as estimated
                $swapStats['_calculated_estimated_profit'] += $profitEstimate;
                $swapStats['_calculated_estimated_count']++;
            }
        }
        
        // Use calculated profits - prefer manual calculation if available (from swap data)
        // This ensures we get profit data even if swap_profit_links table is missing or incomplete
        if (isset($swapStats['_calculated_estimated_profit'])) {
            // Always use calculated if available (it's from actual swap records)
            // Only show estimated profit for items not yet sold (pending)
            $swapStats['total_estimated_profit'] = $swapStats['_calculated_estimated_profit'];
            if ($swapStats['_calculated_estimated_count'] > 0) {
                $swapStats['avg_estimated_profit'] = $swapStats['total_estimated_profit'] / $swapStats['_calculated_estimated_count'];
            }
        }
        if (isset($swapStats['_calculated_final_profit'])) {
            // Always use calculated if available (it's from actual swap records)
            // Include even if profit is 0 or negative (it's still realized)
            $swapStats['total_final_profit'] = $swapStats['_calculated_final_profit'];
            $swapStats['profit_realized_count'] = $swapStats['_calculated_final_count'];
            if ($swapStats['_calculated_final_count'] > 0) {
                $swapStats['avg_final_profit'] = $swapStats['total_final_profit'] / $swapStats['_calculated_final_count'];
            }
        }
        
        // Calculate total profit (positive profits only) and total loss (negative profits)
        $swapStats['total_profit'] = max(0, $swapStats['total_final_profit'] ?? 0); // Only positive profits
        $swapStats['total_loss'] = isset($swapStats['_calculated_loss']) ? $swapStats['_calculated_loss'] : max(0, -($swapStats['total_final_profit'] ?? 0)); // Losses as positive value
        
        // Use manual cash calculation if model stats is 0 but we have manual values
        if ($swapStats['total_cash_received'] == 0 && isset($swapStats['_manual_cash_total']) && $swapStats['_manual_cash_total'] > 0) {
            $swapStats['total_cash_received'] = $swapStats['_manual_cash_total'];
        }
        
        // Capture the view content
        ob_start();
        $viewFile = __DIR__ . '/../Views/swaps_index.php';
        if (!file_exists($viewFile)) {
            $viewFile = __DIR__ . '/../Views/swaps_dashboard.php';
        }
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
     * Show create swap form
     */
    public function create() {
        // Get user from session (already authenticated by route middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $companyId = $user['company_id'];
        
        $storeProducts = $this->swap->getAvailableProductsForSwap($companyId);
        $customers = $this->customer->findByCompany($companyId, 100);
        $pendingSwappedItems = $this->swappedItem->getPendingResales($companyId);
        
        $title = 'New Swap';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/swaps_create.php';
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
     * Show swapped items for resale
     */
    public function resale() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $companyId = $user['company_id'];
        $status = $_GET['status'] ?? null;
        
        $items = $this->swappedItem->findByCompany($companyId, $status);
        $title = 'Swapped Items for Resale';
        
        ob_start();
        include __DIR__ . '/../Views/swaps_resale.php';
        $content = ob_get_clean();
        
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
     * Show swap details
     */
    public function show($id) {
        // Get user from session (already authenticated by route middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $companyId = $user['company_id'];
        
        $swap = $this->swap->find($id, $companyId);
        if (!$swap) {
            $_SESSION['flash_error'] = 'Swap not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps');
            exit;
        }
        
        $title = 'Swap Details';
        
        // Capture the view content
        ob_start();
        // Create basic swap details view inline (swaps_show.php may not exist)
        echo '<div class="container mx-auto p-6">';
        echo '<h1 class="text-2xl font-bold mb-4">Swap Details</h1>';
        if ($swap) {
            echo '<div class="bg-white p-6 rounded-lg shadow">';
            echo '<p><strong>Transaction Code:</strong> ' . htmlspecialchars($swap['transaction_code'] ?? 'N/A') . '</p>';
            echo '<p><strong>Customer:</strong> ' . htmlspecialchars($swap['customer_name'] ?? 'N/A') . '</p>';
            echo '<p><strong>Status:</strong> ' . htmlspecialchars($swap['status'] ?? 'N/A') . '</p>';
            echo '<p><strong>Total Value:</strong> ₵' . number_format($swap['total_value'] ?? 0, 2) . '</p>';
            echo '</div>';
        }
        echo '</div>';
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
     * Store new swap using enhanced system (POST)
     */
    public function store() {
        // Get authenticated user
        $payload = AuthMiddleware::handle(['manager', 'admin', 'salesperson']);
        $companyId = $payload->company_id ?? null;
        $userRole = $payload->role ?? 'salesperson';
        
        // Check if Swap module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'swap', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'swap',
                'message' => 'The Swap module is not enabled for your company. Please contact your administrator.'
            ]);
            exit;
        }
        
        // Continue with existing store logic...
        $userId = $payload->sub;
        
        // Validate required fields
        $company_product_id = intval($_POST['store_product_id'] ?? 0);
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_contact'] ?? '');
        $customer_id = intval($_POST['customer_id'] ?? 0);
        
        // Customer product details
        $customer_brand_id = intval($_POST['customer_brand_id'] ?? 0);
        $customer_brand = trim($_POST['customer_brand'] ?? '');
        
        // If brand_id is provided but brand name is not, get brand name from database
        if ($customer_brand_id && !$customer_brand) {
            $brandModel = new \App\Models\Brand();
            $brand = $brandModel->find($customer_brand_id);
            if ($brand) {
                $customer_brand = $brand['name'];
            }
        }
        
        // If only brand name is provided (for backward compatibility)
        if (!$customer_brand && $customer_brand_id) {
            $brandModel = new \App\Models\Brand();
            $brand = $brandModel->find($customer_brand_id);
            if ($brand) {
                $customer_brand = $brand['name'];
            }
        }
        
        $customer_model = trim($_POST['customer_model'] ?? '');
        $customer_imei = trim($_POST['customer_imei'] ?? '');
        $customer_condition = $_POST['customer_condition'] ?? 'used';
        $estimated_value = floatval($_POST['swap_value'] ?? 0);
        $resell_price = floatval($_POST['resell_price'] ?? $estimated_value);
        $added_cash = floatval($_POST['cash_added'] ?? 0);

        if (!$company_product_id || !$customer_name || !$customer_phone || !$customer_brand || !$customer_model) {
            $_SESSION['flash_error'] = 'All required fields must be filled';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/create');
            exit;
        }

        // Get company product details
        $companyProduct = $this->product->find($company_product_id, $companyId);
        if (!$companyProduct || !is_array($companyProduct)) {
            $_SESSION['flash_error'] = 'Company product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/create');
            exit;
        }

        // Check if product is available for swap
        if (!$companyProduct['available_for_swap']) {
            $_SESSION['flash_error'] = 'This product is not available for swap';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/create');
            exit;
        }

        // Check if product has sufficient quantity
        if ($companyProduct['quantity'] <= 0) {
            $_SESSION['flash_error'] = 'Product is out of stock';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/create');
            exit;
        }

        try {
            // Create swap using stored procedure (handles all related records)
            $result = $this->swap->create([
                'company_id' => $companyId,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_id' => $customer_id ?: null,
                'company_product_id' => $company_product_id,
                'customer_brand' => $customer_brand,
                'customer_brand_id' => $customer_brand_id,
                'customer_model' => $customer_model,
                'customer_imei' => $customer_imei,
                'customer_condition' => $customer_condition,
                'estimated_value' => $estimated_value,
                'resell_price' => $resell_price,
                'added_cash' => $added_cash,
                'handled_by' => $userId,
                'notes' => trim($_POST['notes'] ?? ''),
                'auto_add_to_inventory' => true // Automatically add swapped item to inventory
            ]);

            if ($result && isset($result['swap_id'])) {
                // Log audit event
                try {
                    AuditService::log(
                        $companyId,
                        $userId,
                        'swap.completed',
                        'swap',
                        $result['swap_id'],
                        [
                            'transaction_code' => $result['transaction_code'],
                            'estimated_value' => $estimated_value,
                            'resell_price' => $resell_price,
                            'added_cash' => $added_cash,
                            'customer_id' => $customer_id,
                            'company_product_id' => $company_product_id
                        ]
                    );
                } catch (\Exception $auditError) {
                    error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
                }

                // Send SMS notification to customer about swap completion
                if (!empty($customer_phone)) {
                    try {
                        $notificationService = new \App\Services\NotificationService();
                        
                        // Get company product name
                        $companyProductName = 'Item';
                        try {
                            $productModel = new \App\Models\Product();
                            $product = $productModel->find($company_product_id);
                            if ($product && !empty($product['name'])) {
                                $companyProductName = $product['name'];
                            }
                        } catch (\Exception $e) {
                            error_log("SwapController store: Could not fetch company product name: " . $e->getMessage());
                        }
                        
                        $swapData = [
                            'phone_number' => $customer_phone,
                            'company_id' => $companyId,
                            'swap_id' => $result['swap_id'],
                            'transaction_code' => $result['transaction_code'],
                            'customer_brand' => $customer_brand,
                            'customer_model' => $customer_model,
                            'company_product_name' => $companyProductName,
                            'added_cash' => $added_cash
                        ];
                        
                        $smsResult = $notificationService->sendSwapNotification($swapData);
                        if ($smsResult['success']) {
                            error_log("SwapController store: SMS sent successfully to customer {$customer_phone}");
                        } else {
                            error_log("SwapController store: SMS failed - " . ($smsResult['error'] ?? 'Unknown error'));
                            // Don't fail swap if SMS fails
                        }
                    } catch (\Exception $smsException) {
                        error_log("SwapController store: Error sending SMS notification: " . $smsException->getMessage());
                        // Don't fail swap if SMS fails
                    }
                }

                $_SESSION['flash_success'] = 'Swap completed successfully! Transaction Code: ' . $result['transaction_code'];
                header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/' . $result['swap_id']);
                exit;
            } else {
                throw new \Exception('Failed to create swap transaction');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error completing swap: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/create');
            exit;
        }
    }

    /**
     * Update swap status
     */
    public function updateStatus($id) {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (!in_array($status, ['pending', 'completed', 'resold', 'cancelled'])) {
            $_SESSION['flash_error'] = 'Invalid status';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/' . $id);
            exit;
        }
        
        $success = $this->swap->updateStatus($id, $companyId, $status, $notes);
        
        if ($success) {
            // Send SMS notification for status update
            $swap = $this->swap->find($id, $companyId);
            if ($swap && $swap['customer_phone']) {
                $swapData = [
                    'swap_id' => $swap['tracking_code'],
                    'your_device' => $swap['customer_device_model'],
                    'swap_device' => $swap['swap_device_model'],
                    'status' => ucfirst(str_replace('_', ' ', $status)),
                    'phone_number' => $swap['customer_phone'],
                    'company_id' => $companyId  // Include company_id for SMS quota management
                ];
                
                // NotificationService will handle balance checking, quota decrementing, and logging
                $smsResult = $this->notificationService->sendSwapNotification($swapData);
                
                // Log additional info if SMS failed due to insufficient credits
                if (!$smsResult['success'] && isset($smsResult['insufficient_credits'])) {
                    error_log("Swap Status Update: SMS failed - Insufficient SMS credits for company {$companyId}. Remaining: " . ($smsResult['error'] ?? 'Unknown'));
                }
            }
            
            $_SESSION['flash_success'] = 'Swap status updated successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to update swap status';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/swaps/' . $id);
        exit;
    }

    /**
     * API endpoint: Get swaps for company
     */
    public function apiList() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $status = $_GET['status'] ?? null;
        $swaps = $this->swap->findByCompany($companyId, 100, $status);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $swaps
        ]);
        exit;
    }

    /**
     * API endpoint: Get swap details
     */
    public function apiShow($id) {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $swap = $this->swap->find($id, $companyId);
        if (!$swap) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Swap not found'
            ]);
            exit;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $swap
        ]);
        exit;
    }

    /**
     * API endpoint: Get swap statistics
     */
    public function apiStats() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        $userRole = $payload->role ?? 'manager';
        
        // Check if Swap module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'swap', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'swap'
            ]);
            exit;
        }
        
        $stats = $this->swap->getStats($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }

    /**
     * API endpoint: Get pending swaps for dashboard
     */
    public function apiPending() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $swaps = $this->swap->findPending($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $swaps
        ]);
        exit;
    }

    /**
     * API endpoint: Get completed swaps for resale
     */
    public function apiCompletedForResale() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $swaps = $this->swap->findCompletedForResale($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $swaps
        ]);
        exit;
    }

    /**
     * API endpoint: Get all swapped items (for tracking until resold)
     */
    public function apiSwappedItems() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $status = $_GET['status'] ?? null; // 'in_stock' or 'sold'
        $items = $this->swappedItem->findByCompany($companyId, $status);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $items
        ]);
        exit;
    }

    /**
     * API endpoint: Mark swapped item as sold
     */
    public function apiMarkSold() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['item_id'] ?? null;
        $actualPrice = $input['actual_price'] ?? null;
        
        if (!$itemId || !$actualPrice) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Item ID and actual price are required'
            ]);
            exit;
        }
        
        try {
            // Get the swapped item to find the swap_id
            $item = $this->swappedItem->find($itemId);
            if (!$item) {
                throw new \Exception('Swapped item not found');
            }
            
            // Mark as sold
            $this->swappedItem->markAsSold($itemId, $actualPrice);
            
            // Update swap status to 'resold' if swap_id exists
            if (!empty($item['swap_id'])) {
                try {
                    // Update swap status to 'resold'
                    $this->swap->updateStatus($item['swap_id'], $payload->company_id, 'resold');
                    
                    // Finalize profit if profit link exists
                    $profitLink = $this->swapProfitLink->findBySwapId($item['swap_id']);
                    if ($profitLink) {
                        $this->swapProfitLink->finalizeProfit($item['swap_id'], $actualPrice);
                    }
                } catch (\Exception $e) {
                    // Log but don't fail if status update fails
                    error_log("Failed to update swap status: " . $e->getMessage());
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Item marked as sold successfully'
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Get products available for swap
     */
    public function apiAvailableProducts() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $products = $this->swap->getAvailableProductsForSwap($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
        exit;
    }

    /**
     * API endpoint: Get active swaps for dashboard
     */
    public function apiActiveSwaps() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $swaps = $this->swap->getActiveSwaps($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $swaps
        ]);
        exit;
    }

    /**
     * API endpoint: Get swap profit tracking
     */
    public function apiProfitTracking() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $profitData = $this->swap->getProfitTracking($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $profitData
        ]);
        exit;
    }

    /**
     * API endpoint: Finalize swap profit
     */
    public function apiFinalizeProfit() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $input = json_decode(file_get_contents('php://input'), true);
        $swap_id = intval($input['swap_id'] ?? 0);
        $actual_resell_price = floatval($input['actual_resell_price'] ?? 0);
        
        if (!$swap_id || !$actual_resell_price) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Swap ID and actual resell price are required'
            ]);
            exit;
        }
        
        try {
            $success = $this->swapProfitLink->finalizeProfit($swap_id, $actual_resell_price);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Profit finalized successfully' : 'Failed to finalize profit'
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Generate swap receipt
     */
    public function generateReceipt($id) {
        // Get user from session (already authenticated by route middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $companyId = $user['company_id'];
        
        $swap = $this->swap->find($id, $companyId);
        if (!$swap) {
            $_SESSION['flash_error'] = 'Swap not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/swaps');
            exit;
        }
        
        $title = 'Swap Receipt - ' . ($swap['transaction_code'] ?? 'SWAP-' . $swap['id']);
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/swaps_receipt.php';
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
     * Dashboard view for swap tracking
     */
    public function dashboard() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson']);
        $companyId = $payload->company_id;
        
        $activeSwaps = $this->swap->getActiveSwaps($companyId);
        $profitTracking = $this->swap->getProfitTracking($companyId);
        $stats = $this->swap->getStats($companyId);
        
        $title = 'Swap Dashboard';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/swaps_dashboard.php';
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
     * Sync existing swapped items to products inventory
     */
    public function syncToInventory() {
        // Clean output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        header('Content-Type: application/json');
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Only managers can sync (with price setting)
        try {
            \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit;
        }
        
        // Get selected IDs from request - can be swap_ids or swapped_item_ids
        $input = json_decode(file_get_contents('php://input'), true);
        $selectedIds = $input['selected_ids'] ?? null;
        $swapIds = $input['swap_ids'] ?? null; // If swap_ids provided, sync items from those swaps
        $resellPrice = isset($input['resell_price']) ? floatval($input['resell_price']) : null; // Resell price from modal
        
        try {
            // If swap_ids provided, get swapped_item_ids from those swaps first
            if ($swapIds && is_array($swapIds) && !empty($swapIds)) {
                $swappedItemIds = [];
                foreach ($swapIds as $swapId) {
                    // Get swapped items for this swap
                    $items = $this->swappedItem->findBySwapId($swapId);
                    foreach ($items as $item) {
                        if (isset($item['id']) && (!isset($item['inventory_product_id']) || !$item['inventory_product_id'])) {
                            $swappedItemIds[] = $item['id'];
                        }
                    }
                }
                $selectedIds = $swappedItemIds;
            }
            
            // Set resell_price if provided from modal
            if ($resellPrice !== null && $resellPrice > 0) {
                $this->swappedItem->resellPrice = $resellPrice;
            }
            
            $result = $this->swappedItem->syncToInventory($companyId, $selectedIds);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error syncing: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Delete swap (for managers)
     */
    public function delete($id) {
        header('Content-Type: application/json');
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Only managers can delete
        try {
            \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit;
        }
        
        try {
            // Verify swap belongs to company
            $swap = $this->swap->find($id, $companyId);
            if (!$swap) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Swap not found']);
                exit;
            }
            
            // Delete swap (cascade should handle related records)
            $deleted = $this->swap->delete($id, $companyId);
            
            if ($deleted) {
                echo json_encode(['success' => true, 'message' => 'Swap deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to delete swap']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting swap: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}