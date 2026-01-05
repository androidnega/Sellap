<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Swap;
use App\Models\SwappedItem;
use App\Models\SwapProfitLink;
use App\Models\CustomerProduct;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Company;
use App\Models\CompanyModule;
use App\Services\NotificationService;
use App\Services\AuditService;
use PDO;

class SwapController {
    private $swap;
    private $swappedItem;
    private $swapProfitLink;
    private $customerProduct;
    private $product;
    private $customer;
    private $company;
    private $notificationService;

    public function __construct() {
        $this->swap = new Swap();
        $this->swappedItem = new SwappedItem();
        $this->swapProfitLink = new SwapProfitLink();
        $this->customerProduct = new CustomerProduct();
        $this->product = new Product();
        $this->customer = new Customer();
        $this->company = new Company();
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
            'total_cash_received' => 0, // Will be calculated manually from swaps in the loop
            'cash_received_count' => 0, // Count of swaps where cash was received
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
            
            // Get added_cash - check multiple possible column names
            // This is the cash that customers added to their phone value when swapping
            $addedCash = 0;
            if (isset($s['added_cash']) && $s['added_cash'] !== null && $s['added_cash'] !== 'NULL' && floatval($s['added_cash']) > 0) {
                $addedCash = floatval($s['added_cash']);
            } elseif (isset($s['cash_added']) && $s['cash_added'] !== null && $s['cash_added'] !== 'NULL' && floatval($s['cash_added']) > 0) {
                $addedCash = floatval($s['cash_added']);
            } elseif (isset($s['difference_paid_by_company']) && $s['difference_paid_by_company'] !== null) {
                // If company paid difference, that's negative cash received (customer didn't add cash)
                $addedCash = -floatval($s['difference_paid_by_company']);
            }
            
            // If added_cash is still 0, calculate it from the difference
            // Formula: added_cash = total_value (or company_product_price) - customer_product_value
            // This represents the cash the customer needs to add to make up the difference
            if ($addedCash == 0 || $addedCash <= 0) {
                $totalValue = floatval($s['total_value'] ?? 0);
                $companyProductPrice = floatval($s['company_product_price'] ?? 0);
                $customerProductValue = floatval($s['customer_product_value'] ?? 0);
                
                // Use total_value if available, otherwise use company_product_price
                $baseValue = $totalValue > 0 ? $totalValue : $companyProductPrice;
                
                // If we have both base value and customer product value, calculate the difference
                if ($baseValue > 0 && $customerProductValue > 0 && $baseValue > $customerProductValue) {
                    $calculatedAddedCash = $baseValue - $customerProductValue;
                    // Only use calculated value if it's positive (customer adds cash)
                    if ($calculatedAddedCash > 0) {
                        $addedCash = $calculatedAddedCash;
                    }
                }
            }
            
            // For total_value calculation: use total_value from database (which should be added_cash for new swaps)
            // But if total_value seems wrong (much larger than added_cash), use added_cash instead
            $dbTotalValue = floatval($s['total_value'] ?? 0);
            $resaleStatus = $s['resale_status'] ?? null;
            $isResold = ($resaleStatus === 'sold' || $swapStatus === 'resold');
            
            // If swap is resold, total_value should include both added_cash and resale value
            // If swap is not resold, total_value should only be added_cash
            if ($isResold) {
                // For resold swaps, use total_value as-is (it should already include resale value)
                $swapStats['total_value'] += $dbTotalValue;
            } else {
                // For non-resold swaps, use added_cash (cash top-up only)
                // If total_value is much larger than added_cash, it's probably an old swap with wrong value
                if ($dbTotalValue > 0 && $addedCash > 0 && $dbTotalValue > ($addedCash * 1.5)) {
                    // Old swap format - use added_cash instead
                    $swapStats['total_value'] += $addedCash;
                } else {
                    // Use total_value if it seems correct, otherwise use added_cash
                    $swapStats['total_value'] += ($dbTotalValue > 0 ? $dbTotalValue : $addedCash);
                }
            }
            
            // Accumulate cash received - sum all positive added_cash values
            // This represents money customers paid in addition to their phone value
            if ($addedCash > 0) {
                $swapStats['total_cash_received'] += $addedCash;
                $swapStats['cash_received_count']++; // Count swaps where cash was received
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
                        // Do not use estimate as fallback - only count finalized profits
                    }
                }
                
                // Only count finalized profits - never use estimates as realized gains
                // If both items are sold, profit is realized (even if status not finalized)
                if ($profitFinal !== null) {
                    $profitToUse = $profitFinal;
                    $swapStats['_calculated_final_profit'] += $profitToUse;
                    $swapStats['_calculated_final_count']++;
                    
                    // Track losses separately (negative profits)
                    if ($profitToUse < 0) {
                        if (!isset($swapStats['_calculated_loss'])) {
                            $swapStats['_calculated_loss'] = 0;
                        }
                        $swapStats['_calculated_loss'] += abs($profitToUse);
                    }
                } elseif ($profitEstimate !== null) {
                    // If no final profit but both items sold, try to calculate it
                    try {
                        $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                        $calculatedProfit = $swapProfitLinkModel->calculateSwapProfit($s['id']);
                        if ($calculatedProfit !== null) {
                            $profitToUse = $calculatedProfit;
                            $swapStats['_calculated_final_profit'] += $profitToUse;
                            $swapStats['_calculated_final_count']++;
                            
                            if ($profitToUse < 0) {
                                if (!isset($swapStats['_calculated_loss'])) {
                                    $swapStats['_calculated_loss'] = 0;
                                }
                                $swapStats['_calculated_loss'] += abs($profitToUse);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("SwapController: Error calculating profit for swap #{$s['id']}: " . $e->getMessage());
                    }
                }
            } elseif ($isResold) {
                // Item is resold - profit is realized (matches view logic)
                // Check swap_profit_links table if final_profit column doesn't exist in swaps
                if ($profitFinal === null) {
                    // Try to get profit from swap_profit_links table
                    try {
                        $db = \Database::getInstance()->getConnection();
                        $profitLinkQuery = $db->prepare("
                            SELECT final_profit, profit_estimate, status as profit_status, 
                                   company_item_sale_id, customer_item_sale_id,
                                   company_product_cost, customer_phone_value, amount_added_by_customer
                            FROM swap_profit_links
                            WHERE swap_id = ?
                            LIMIT 1
                        ");
                        $profitLinkQuery->execute([$s['id']]);
                        $profitLink = $profitLinkQuery->fetch(PDO::FETCH_ASSOC);
                        if ($profitLink) {
                            $profitFinal = isset($profitLink['final_profit']) && $profitLink['final_profit'] !== null ? floatval($profitLink['final_profit']) : null;
                            // Do NOT use profit_estimate as final_profit - keep them separate
                            // Estimated profit should NOT show in profit section until totally resold
                            if ($profitStatus === null && isset($profitLink['profit_status'])) {
                                $profitStatus = $profitLink['profit_status'];
                            }
                            if (!$hasCustomerSaleId && isset($profitLink['customer_item_sale_id'])) {
                                $hasCustomerSaleId = !empty($profitLink['customer_item_sale_id']);
                            }
                            // If we have company_item_sale_id, try to calculate profit from the sale
                            if ($profitFinal === null && !empty($profitLink['company_item_sale_id'])) {
                                try {
                                    // Get sale details to calculate profit
                                    $saleQuery = $db->prepare("
                                        SELECT ps.final_amount, ps.total_cost
                                        FROM pos_sales ps
                                        WHERE ps.id = ?
                                        LIMIT 1
                                    ");
                                    $saleQuery->execute([$profitLink['company_item_sale_id']]);
                                    $sale = $saleQuery->fetch(PDO::FETCH_ASSOC);
                                    if ($sale) {
                                        $sellingPrice = floatval($sale['final_amount'] ?? 0);
                                        $costPrice = floatval($profitLink['company_product_cost'] ?? 0);
                                        // Profit = Selling Price - Cost Price
                                        $profitFinal = $sellingPrice - $costPrice;
                                        error_log("SwapController: Calculated profit for swap #{$s['id']} from sale: Selling Price ₵{$sellingPrice} - Cost ₵{$costPrice} = ₵{$profitFinal}");
                                    }
                                } catch (\Exception $e) {
                                    error_log("SwapController: Error calculating profit from sale for swap #{$s['id']}: " . $e->getMessage());
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("SwapController: Error fetching profit from swap_profit_links for swap #{$s['id']}: " . $e->getMessage());
                    }
                }
                
                // If still no final profit but item is resold, try to calculate it using SwapProfitLink model
                if ($profitFinal === null) {
                    try {
                        $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                        $calculatedProfit = $swapProfitLinkModel->calculateSwapProfit($s['id']);
                        if ($calculatedProfit !== null) {
                            $profitFinal = $calculatedProfit;
                            $profitStatus = 'finalized';
                        }
                    } catch (\Exception $e) {
                        error_log("SwapController: Error calculating profit for swap #{$s['id']}: " . $e->getMessage());
                    }
                }
                
                // Item is resold - profit is REALIZED (use final_profit if available, otherwise estimate)
                $profitToUse = $profitFinal !== null ? $profitFinal : $profitEstimate;
                if ($profitToUse !== null) {
                    $swapStats['_calculated_final_profit'] += $profitToUse;
                    $swapStats['_calculated_final_count']++;
                    if ($profitToUse < 0) {
                        if (!isset($swapStats['_calculated_loss'])) {
                            $swapStats['_calculated_loss'] = 0;
                        }
                        $swapStats['_calculated_loss'] += abs($profitToUse);
                    }
                    error_log("SwapController: Resold swap #{$s['id']} - realized profit ₵{$profitToUse}" . ($profitFinal !== null ? " (final)" : " (estimate)"));
                }
            } elseif ($profitStatus === 'finalized' && $hasCustomerSaleId) {
                // Profit is finalized and customer item has been resold
                if ($profitFinal !== null) {
                    $profitToUse = $profitFinal;
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
        
        // total_cash_received is now always calculated from the loop above
        // It represents the sum of all added_cash values from swaps where customers added cash
        
        // Assign swapStats to stats for the view
        $stats = $swapStats;
        
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
        // Load all customers (no limit) for search functionality
        $customers = $this->customer->findByCompany($companyId, 10000);
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
        
        // Get customer_id - check both select and backup input
        $customer_id = null;
        if (!empty($_POST['customer_id'])) {
            $customer_id = intval($_POST['customer_id']);
        } elseif (!empty($_POST['customer_id_backup'])) {
            // Fallback to backup input if main select didn't submit
            $customer_id = intval($_POST['customer_id_backup']);
        }
        
        // Debug log
        error_log("Swap create: customer_id from POST = " . var_export($_POST['customer_id'] ?? 'not set', true));
        error_log("Swap create: customer_id_backup from POST = " . var_export($_POST['customer_id_backup'] ?? 'not set', true));
        error_log("Swap create: parsed customer_id = " . var_export($customer_id, true));
        
        // If customer_id is not provided but customer name and phone are, create or find customer
        if (!$customer_id && $customer_name && $customer_phone) {
            // Try to find existing customer by phone
            $existingCustomer = $this->customer->findByPhoneInCompany($customer_phone, $companyId);
            
            if ($existingCustomer) {
                // Use existing customer
                $customer_id = $existingCustomer['id'];
            } else {
                // Create new customer
                try {
                    $newCustomerId = $this->customer->create([
                        'company_id' => $companyId,
                        'full_name' => $customer_name,
                        'phone_number' => $customer_phone,
                        'email' => '',
                        'address' => ''
                    ]);
                    
                    if ($newCustomerId) {
                        $customer_id = $newCustomerId;
                    }
                } catch (\Exception $e) {
                    error_log("Swap create: Failed to create customer - " . $e->getMessage());
                    // Continue without customer_id - will be handled by schema check
                }
            }
        }
        
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
                        $notificationService = new NotificationService();
                        
                        // Get company product name
                        $companyProductName = 'Item';
                        try {
                            $productModel = new \App\Models\Product();
                            $product = $productModel->find($company_product_id, $companyId);
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
        
        // Get company information
        $company = $this->company->find($companyId);
        $companyName = $company['name'] ?? "SellApp Store";
        $companyAddress = $company['address'] ?? "123 Business Street, City, Country";
        $companyPhone = $company['phone'] ?? $company['phone_number'] ?? "+233 XX XXX XXXX";
        
        // Set headers for printing
        header('Content-Type: text/html; charset=utf-8');
        
        // Generate receipt HTML in POS standard format
        $receipt = $this->generateReceiptHTML($swap, $companyName, $companyAddress, $companyPhone, $user);
        
        echo $receipt;
        exit;
    }
    
    /**
     * Generate receipt HTML in POS standard format
     */
    private function generateReceiptHTML($swap, $companyName, $companyAddress, $companyPhone, $user) {
        $receiptDate = date('Y-m-d H:i:s', strtotime($swap['swap_date'] ?? $swap['created_at'] ?? 'now'));
        $transactionCode = $swap['transaction_code'] ?? 'SWAP-' . str_pad($swap['id'], 6, '0', STR_PAD_LEFT);
        $customerName = $swap['customer_name'] ?? $swap['customer_name_from_table'] ?? 'Walk-in Customer';
        $customerPhone = $swap['customer_phone'] ?? $swap['customer_phone_from_table'] ?? 'N/A';
        $handledBy = $swap['handled_by_name'] ?? $user['username'] ?? 'Unknown';
        
        // Company product details - try multiple sources
        $companyProductName = $swap['company_product_name'] ?? null;
        $companyProductPrice = floatval($swap['company_product_price'] ?? 0);
        
        // If product name is missing or 'N/A', try to get it from other sources
        if (empty($companyProductName) || $companyProductName === 'N/A' || $companyProductName === null) {
            // Try to construct from company_brand if available
            $companyBrand = $swap['company_brand'] ?? '';
            if (!empty($companyBrand)) {
                $companyProductName = $companyBrand . ' Product';
            } else {
                // Try to query product directly if we have company_product_id
                if (!empty($swap['company_product_id'])) {
                    try {
                        $product = $this->product->find($swap['company_product_id'], $swap['company_id']);
                        if ($product) {
                            $companyProductName = $product['name'] ?? '';
                            if (empty($companyProductName)) {
                                // Try to construct from brand and name
                                $brand = $product['brand'] ?? '';
                                $name = $product['name'] ?? '';
                                if (!empty($brand) || !empty($name)) {
                                    $companyProductName = trim($brand . ' ' . $name);
                                }
                            }
                            // Update price if not already set
                            if ($companyProductPrice == 0 && !empty($product['price'])) {
                                $companyProductPrice = floatval($product['price']);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Swap receipt: Error fetching product: " . $e->getMessage());
                    }
                }
                
                // If still empty, try new_phone_id (old schema)
                if (empty($companyProductName) && !empty($swap['new_phone_id'])) {
                    try {
                        $db = \Database::getInstance()->getConnection();
                        $stmt = $db->prepare("SELECT brand, model, selling_price FROM phones WHERE id = ? AND company_id = ?");
                        $stmt->execute([$swap['new_phone_id'], $swap['company_id']]);
                        $phone = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($phone) {
                            $brand = trim($phone['brand'] ?? '');
                            $model = trim($phone['model'] ?? '');
                            if (!empty($brand) || !empty($model)) {
                                $companyProductName = trim($brand . ' ' . $model);
                            }
                            if ($companyProductPrice == 0 && !empty($phone['selling_price'])) {
                                $companyProductPrice = floatval($phone['selling_price']);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Swap receipt: Error fetching phone: " . $e->getMessage());
                    }
                }
                
                // Final fallback
                if (empty($companyProductName)) {
                    if (!empty($swap['company_product_id'])) {
                        $companyProductName = 'Product #' . $swap['company_product_id'];
                    } elseif (!empty($swap['new_phone_id'])) {
                        $companyProductName = 'Phone #' . $swap['new_phone_id'];
                    } else {
                        $companyProductName = 'Product';
                    }
                }
            }
        }
        
        // Customer product details
        $customerProductBrand = $swap['customer_product_brand'] ?? '';
        $customerProductModel = $swap['customer_product_model'] ?? '';
        $customerProduct = trim($customerProductBrand . ' ' . $customerProductModel);
        if (empty($customerProduct)) {
            $customerProduct = 'N/A';
        }
        $customerProductValue = floatval($swap['customer_product_value'] ?? 0);
        
        // Get added_cash - check multiple possible column names
        $addedCash = 0;
        if (isset($swap['added_cash']) && $swap['added_cash'] !== null && $swap['added_cash'] !== 'NULL') {
            $addedCash = floatval($swap['added_cash']);
        } elseif (isset($swap['cash_added']) && $swap['cash_added'] !== null && $swap['cash_added'] !== 'NULL') {
            $addedCash = floatval($swap['cash_added']);
        } elseif (isset($swap['difference_paid_by_company']) && $swap['difference_paid_by_company'] !== null) {
            // If company paid difference, that's negative cash received
            $addedCash = -floatval($swap['difference_paid_by_company']);
        }
        
        $totalValue = floatval($swap['total_value'] ?? $companyProductPrice);
        
        // If added_cash is still 0 or null, calculate it from the difference
        // Formula: added_cash = total_value (or company_product_price) - customer_product_value
        // This represents the cash the customer needs to add to make up the difference
        if ($addedCash == 0) {
            // Use total_value if available, otherwise use company_product_price
            $baseValue = $totalValue > 0 ? $totalValue : $companyProductPrice;
            if ($baseValue > 0 && $customerProductValue > 0) {
                $calculatedAddedCash = $baseValue - $customerProductValue;
                // Only use calculated value if it's positive (customer adds cash)
                // If negative, it means customer product is worth more, so no cash added
                if ($calculatedAddedCash > 0) {
                    $addedCash = $calculatedAddedCash;
                }
            }
        }
        
        $faviconPath = (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . '/assets/images/favicon.svg';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Swap Receipt #{$transactionCode}</title>
            <link rel=\"icon\" type=\"image/svg+xml\" href=\"{$faviconPath}\">
            <link rel=\"shortcut icon\" type=\"image/svg+xml\" href=\"{$faviconPath}\">
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
                .swap-section {
                    border-top: 1px dashed #333;
                    padding-top: 10px;
                    margin-bottom: 15px;
                }
                .swap-section-title {
                    font-weight: bold;
                    margin-bottom: 8px;
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
                    <div><span>Receipt #:</span><span>{$transactionCode}</span></div>
                    <div><span>Date:</span><span>{$receiptDate}</span></div>
                    <div><span>Cashier:</span><span>{$handledBy}</span></div>
                    <div><span>Customer:</span><span>{$customerName}</span></div>
                    <div><span>Transaction Type:</span><span>SWAP</span></div>
                </div>
                
                <div class='items'>
                    <div style='font-weight: bold; margin-bottom: 8px;'>ITEMS:</div>
                    <div class='item'>
                        <div>
                            <div class='item-name'>{$companyProductName}</div>
                            <div class='item-details'>Given to Customer</div>
                        </div>
                        <div>₵" . number_format($companyProductPrice, 2) . "</div>
                    </div>
                </div>
                
                <div class='swap-section'>
                    <div class='swap-section-title'>SWAP DETAILS:</div>
                    <div style='margin-bottom: 5px;'>
                        <div><span>Customer Device:</span><span>{$customerProduct}</span></div>
                        <div><span>Device Value:</span><span>₵" . number_format($customerProductValue, 2) . "</span></div>
                    </div>
                </div>
                
                <div class='totals'>
                    <div><span>Subtotal:</span><span>₵" . number_format($companyProductPrice, 2) . "</span></div>
                    <div><span>Cash Added:</span><span>₵" . number_format($addedCash, 2) . "</span></div>
                    <div class='total'><span>TOTAL:</span><span>₵" . number_format($totalValue, 2) . "</span></div>
                </div>
                
                <div class='footer'>
                    <div>Payment Method: CASH</div>
                    <div>Payment Status: PAID</div>
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
        // Clean output buffer first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set JSON header
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Only managers can delete
        try {
            \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit;
        }
        
        try {
            // Verify swap belongs to company
            $swap = $this->swap->find($id, $companyId);
            if (!$swap) {
                ob_end_clean();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Swap not found']);
                exit;
            }
            
            // Delete swap (cascade should handle related records)
            $deleted = $this->swap->delete($id, $companyId);
            
            ob_end_clean();
            if ($deleted) {
                echo json_encode(['success' => true, 'message' => 'Swap deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to delete swap']);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Swap delete error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting swap: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API: Search customers for swap page (live search)
     * GET /api/swap/customers?q=search_term
     */
    public function apiSearchCustomers() {
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $companyId = $user['company_id'];
        $searchTerm = $_GET['q'] ?? '';
        
        try {
            // If search term is empty, return all customers (up to 10000)
            if (empty($searchTerm)) {
                $customers = $this->customer->findByCompany($companyId, 10000);
            } else {
                // Use search method for filtered results
                $customers = $this->customer->search($searchTerm, $companyId, 10000);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $customers,
                'count' => count($customers)
            ]);
        } catch (\Exception $e) {
            error_log("Error searching customers for swap: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error searching customers',
                'data' => []
            ]);
        }
        exit;
    }
}