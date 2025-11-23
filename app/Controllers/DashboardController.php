<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use App\Models\CompanyModule;
use App\Helpers\DashboardWidgets;

/**
 * Dashboard Controller
 * Handles unified dashboard with role-based routing
 */
class DashboardController {
    
    /**
     * Unified dashboard entry point
     * Uses the unified layout system with role-based sidebar
     * Authentication is handled by WebAuthMiddleware
     */
    public function index() {
        // Start session to get user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // User is already authenticated by WebAuthMiddleware
            $this->renderDashboard();
    }
    
    
    /**
     * Render dashboard content
     */
    private function renderDashboard() {
        // Check user role and render appropriate dashboard
        if (isset($_SESSION['user'])) {
            $userRole = $_SESSION['user']['role'] ?? 'salesperson';
            
            if ($userRole === 'system_admin') {
                $this->renderAdminDashboard();
            } elseif ($userRole === 'manager' || $userRole === 'admin') {
                $this->renderManagerDashboard();
            } elseif ($userRole === 'technician') {
                $this->renderTechnicianDashboard();
            } else {
                $this->renderSalespersonDashboard();
            }
        } else {
            // Default to salesperson dashboard if no session
            $this->renderSalespersonDashboard();
        }
    }
    
    /**
     * Render admin dashboard for system administrators
     */
    private function renderAdminDashboard() {
        include APP_PATH . '/Views/admin-dashboard-enhanced.php';
    }
    
    /**
     * Render enhanced manager dashboard
     */
    private function renderManagerDashboard() {
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        $companyId = $userData['company_id'] ?? null;
        $role = $userData['role'] ?? 'manager';
        
        // Get date range from request if provided (for filtering)
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        // Calculate dashboard metrics in PHP (with date range if provided)
        $dashboardMetrics = $this->calculateDashboardMetrics($companyId, $role, $dateFrom, $dateTo);
        
        // Pass metrics to view
        extract($dashboardMetrics);
        
        // Use comprehensive dashboard if it exists, otherwise fallback to basic
        $comprehensiveDashboard = APP_PATH . '/Views/manager-dashboard-comprehensive.php';
        if (file_exists($comprehensiveDashboard)) {
            include $comprehensiveDashboard;
        } else {
            include APP_PATH . '/Views/manager-dashboard.php';
        }
    }
    
    /**
     * Calculate dashboard metrics for manager dashboard (pure PHP)
     */
    private function calculateDashboardMetrics($companyId, $role = 'manager', $dateFrom = null, $dateTo = null) {
        if (!$companyId) {
            return $this->getEmptyMetrics();
        }
        
        $db = \Database::getInstance()->getConnection();
        $metrics = [];
        
        try {
            // Get enabled modules
            $companyModule = new CompanyModule();
            $enabledModuleKeys = $role === 'system_admin' 
                ? ['products_inventory', 'pos_sales', 'swap', 'repairs', 'customers', 'staff_management', 'reports_analytics']
                : $companyModule->getEnabledModules($companyId);
            
            // POS / Sales metrics
            if ($role === 'system_admin' || in_array('pos_sales', $enabledModuleKeys)) {
                // Today's revenue - EXCLUDE swap transactions
                // Use swap_id IS NULL to exclude all swap-related sales
                $todayRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                    AND swap_id IS NULL
                ");
                $todayRevenueQuery->execute([$companyId]);
                $metrics['today_revenue'] = (float)($todayRevenueQuery->fetch()['revenue'] ?? 0);
                
                // Today's sales count - EXCLUDE swap transactions
                $todaySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                    AND swap_id IS NULL
                ");
                $todaySalesQuery->execute([$companyId]);
                $metrics['today_sales'] = (int)($todaySalesQuery->fetch()['count'] ?? 0);
                
            } else {
                $metrics['today_revenue'] = 0;
                $metrics['today_sales'] = 0;
            }
            
            // Repairs metrics
            if ($role === 'system_admin' || in_array('repairs', $enabledModuleKeys)) {
                $activeRepairsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM repairs 
                    WHERE company_id = ? AND UPPER(repair_status) IN ('PENDING','IN_PROGRESS')
                ");
                $activeRepairsQuery->execute([$companyId]);
                $metrics['active_repairs'] = (int)($activeRepairsQuery->fetch()['count'] ?? 0);
            } else {
                $metrics['active_repairs'] = 0;
            }
            
            // Swap metrics
            if ($role === 'system_admin' || in_array('swap', $enabledModuleKeys)) {
                $pendingSwapsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM swaps 
                    WHERE company_id = ? AND UPPER(swap_status) = 'PENDING'
                ");
                $pendingSwapsQuery->execute([$companyId]);
                $metrics['pending_swaps'] = (int)($pendingSwapsQuery->fetch()['count'] ?? 0);
            } else {
                $metrics['pending_swaps'] = 0;
            }
            
        } catch (\Exception $e) {
            error_log("DashboardController::calculateDashboardMetrics - Error: " . $e->getMessage());
            return $this->getEmptyMetrics();
        }
        
        return $metrics;
    }
    
    /**
     * Calculate profit metrics from inventory products
     * Now calculates monthly profit by default (or date range if provided)
     */
    private function calculateProfitMetrics($companyId, $db, $dateFrom = null, $dateTo = null) {
        $result = [
            'today_profit' => 0.00,
            'monthly_profit' => 0.00,
            'display_profit' => 0.00
        ];
        
        try {
            // Check which products table exists
            $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
            
            // Check which cost columns exist
            $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
            $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
            $checkPurchasePrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'purchase_price'");
            $hasCostPrice = $checkCostPrice->rowCount() > 0;
            $hasCost = $checkCost->rowCount() > 0;
            $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
            
            // Determine cost column to use (prioritize cost_price, then cost, then purchase_price)
            $costColumn = '0';
            if ($hasCostPrice) {
                $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
            } elseif ($hasCost) {
                $costColumn = 'COALESCE(p.cost, 0)';
            } elseif ($hasPurchasePrice) {
                $costColumn = 'COALESCE(p.purchase_price, 0)';
            }
            
            // Calculate today's profit (for reference)
            $todayProfitQuery = $db->prepare("
                SELECT 
                    COALESCE(SUM(psi.total_price), 0) as revenue,
                    COALESCE(SUM(CASE 
                        WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                        ELSE 0
                    END), 0) as cost
                FROM pos_sale_items psi
                INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                WHERE ps.company_id = ? 
                AND DATE(ps.created_at) = CURDATE()
                AND psi.item_id IS NOT NULL 
                AND psi.item_id > 0
            ");
            $todayProfitQuery->execute([$companyId]);
            $todayResult = $todayProfitQuery->fetch(\PDO::FETCH_ASSOC);
            $todayRevenue = floatval($todayResult['revenue'] ?? 0);
            $todayCost = floatval($todayResult['cost'] ?? 0);
            $todayProfit = $todayRevenue - $todayCost;
            if ($todayProfit < 0) $todayProfit = 0;
            $result['today_profit'] = round($todayProfit, 2);
            
            // Calculate monthly profit (or date range profit if dates provided)
            // Default to current month if no dates provided
            // Try multiple matching strategies: by item_id, by description, or use fallback
            if ($dateFrom && $dateTo) {
                // Use provided date range
                $dateFromStart = $dateFrom . ' 00:00:00';
                $dateToEnd = $dateTo . ' 23:59:59';
                $monthlyProfitQuery = $db->prepare("
                    SELECT 
                        COALESCE(SUM(psi.total_price), 0) as revenue,
                        COALESCE(SUM(CASE 
                            WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                            ELSE 0
                        END), 0) as cost
                    FROM pos_sale_items psi
                    INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = ? 
                    AND ps.created_at >= ? 
                    AND ps.created_at <= ?
                    AND p.id IS NOT NULL
                ");
                $monthlyProfitQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
            } else {
                // Default to current month (from day 1 to today)
                // Match by item_id OR by description if item_id is missing
                $monthlyProfitQuery = $db->prepare("
                    SELECT 
                        COALESCE(SUM(psi.total_price), 0) as revenue,
                        COALESCE(SUM(CASE 
                            WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                            ELSE 0
                        END), 0) as cost
                    FROM pos_sale_items psi
                    INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = ? 
                    AND MONTH(ps.created_at) = MONTH(CURDATE()) 
                    AND YEAR(ps.created_at) = YEAR(CURDATE())
                    AND p.id IS NOT NULL
                ");
                $monthlyProfitQuery->execute([$companyId]);
            }
            
            $monthlyResult = $monthlyProfitQuery->fetch(\PDO::FETCH_ASSOC);
            $monthlyRevenue = floatval($monthlyResult['revenue'] ?? 0);
            $monthlyCost = floatval($monthlyResult['cost'] ?? 0);
            $monthlyProfit = $monthlyRevenue - $monthlyCost;
            if ($monthlyProfit < 0) $monthlyProfit = 0;
            
            // If we have revenue but cost is 0, check if products exist but don't have cost values
            // In this case, we can't calculate accurate profit, but we should log it
            if ($monthlyRevenue > 0 && $monthlyCost == 0) {
                // Check if products exist but have no cost
                $checkProductsQuery = $db->prepare("
                    SELECT COUNT(DISTINCT p.id) as products_found
                    FROM pos_sale_items psi
                    INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = ? 
                    AND MONTH(ps.created_at) = MONTH(CURDATE()) 
                    AND YEAR(ps.created_at) = YEAR(CURDATE())
                    AND p.id IS NOT NULL
                ");
                $checkProductsQuery->execute([$companyId]);
                $productsFound = intval($checkProductsQuery->fetch()['products_found'] ?? 0);
                
                if ($productsFound > 0) {
                    $warningMsg = "Profit Calculation WARNING - Company {$companyId}: Found {$productsFound} products but cost is 0. Products may not have cost values set.";
                    error_log($warningMsg);
                    
                    // Also log to custom file
                    $logFile = STORAGE_PATH . '/logs/profit_calculation.log';
                    $logDir = dirname($logFile);
                    if (!is_dir($logDir)) {
                        @mkdir($logDir, 0755, true);
                    }
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $warningMsg . "\n", FILE_APPEND);
                }
            }
            
            $result['monthly_profit'] = round($monthlyProfit, 2);
            
            // Debug: Check if we're missing items due to item_id being NULL/0
            $debugQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN item_id IS NULL OR item_id = 0 THEN 1 ELSE 0 END) as items_without_id,
                    SUM(CASE WHEN item_id IS NOT NULL AND item_id > 0 THEN 1 ELSE 0 END) as items_with_id,
                    COALESCE(SUM(CASE WHEN item_id IS NULL OR item_id = 0 THEN total_price ELSE 0 END), 0) as revenue_without_id
                FROM pos_sale_items psi
                INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                WHERE ps.company_id = ? 
                AND MONTH(ps.created_at) = MONTH(CURDATE()) 
                AND YEAR(ps.created_at) = YEAR(CURDATE())
            ");
            $debugQuery->execute([$companyId]);
            $debugResult = $debugQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Log to both PHP error log and custom file
            $logMessages = [
                "Profit Calculation Debug - Company: {$companyId}, Monthly Revenue: {$monthlyRevenue}, Monthly Cost: {$monthlyCost}, Monthly Profit: {$monthlyProfit}",
                "Profit Calculation Debug - Total Items: " . ($debugResult['total_items'] ?? 0) . ", With ID: " . ($debugResult['items_with_id'] ?? 0) . ", Without ID: " . ($debugResult['items_without_id'] ?? 0) . ", Revenue Without ID: " . ($debugResult['revenue_without_id'] ?? 0)
            ];
            
            foreach ($logMessages as $msg) {
                error_log($msg);
            }
            
            // Also log to custom file
            $logFile = STORAGE_PATH . '/logs/profit_calculation.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            foreach ($logMessages as $msg) {
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
            }
            
            // If we have revenue but no profit, and items are missing item_id, try to match by description
            if ($monthlyRevenue > 0 && $monthlyProfit == 0 && ($debugResult['items_without_id'] ?? 0) > 0) {
                $attemptMsg = "Profit Calculation: Attempting to match items by description for company {$companyId}";
                error_log($attemptMsg);
                
                // Log to custom file
                $logFile = STORAGE_PATH . '/logs/profit_calculation.log';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $attemptMsg . "\n", FILE_APPEND);
                
                // Try to calculate profit by matching item_description to product name
                $descriptionMatchQuery = $db->prepare("
                    SELECT 
                        COALESCE(SUM(psi.total_price), 0) as revenue,
                        COALESCE(SUM(psi.quantity * {$costColumn}), 0) as cost
                    FROM pos_sale_items psi
                    INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR (psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id
                    )
                    WHERE ps.company_id = ? 
                    AND MONTH(ps.created_at) = MONTH(CURDATE()) 
                    AND YEAR(ps.created_at) = YEAR(CURDATE())
                    AND p.id IS NOT NULL
                ");
                $descriptionMatchQuery->execute([$companyId]);
                $descriptionResult = $descriptionMatchQuery->fetch(\PDO::FETCH_ASSOC);
                $descRevenue = floatval($descriptionResult['revenue'] ?? 0);
                $descCost = floatval($descriptionResult['cost'] ?? 0);
                $descProfit = $descRevenue - $descCost;
                if ($descProfit < 0) $descProfit = 0;
                
                if ($descProfit > $monthlyProfit) {
                    $foundMsg = "Profit Calculation: Found profit via description matching: Revenue={$descRevenue}, Cost={$descCost}, Profit={$descProfit}";
                    error_log($foundMsg);
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $foundMsg . "\n", FILE_APPEND);
                    $result['monthly_profit'] = round($descProfit, 2);
                    $monthlyProfit = $descProfit;
                }
            }
            
            // Display profit: Always use monthly/date range profit (not today's)
            $result['display_profit'] = round($monthlyProfit, 2);
            
        } catch (\Exception $e) {
            $errorMsg = "DashboardController::calculateProfitMetrics - Error: " . $e->getMessage();
            $errorTrace = "DashboardController::calculateProfitMetrics - Trace: " . $e->getTraceAsString();
            
            // Log to PHP error log
            error_log($errorMsg);
            error_log($errorTrace);
            
            // Also log to custom file
            $logFile = STORAGE_PATH . '/logs/profit_calculation.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $errorTrace . "\n", FILE_APPEND);
        }
        
        return $result;
    }
    
    /**
     * Get empty metrics structure
     */
    private function getEmptyMetrics() {
        return [
            'today_revenue' => 0,
            'today_sales' => 0,
            'active_repairs' => 0,
            'pending_swaps' => 0
        ];
    }
    
    /**
     * Render salesperson dashboard
     */
    private function renderSalespersonDashboard() {
        include APP_PATH . '/Views/salesperson-dashboard.php';
    }
    
    /**
     * Render technician dashboard
     */
    private function renderTechnicianDashboard() {
        // Session is already started by WebAuthMiddleware
        // Don't start it again to avoid session conflicts
        $userData = $_SESSION['user'] ?? null;
        
        // Debug logging to track session state
        error_log("renderTechnicianDashboard: Session ID - " . session_id());
        error_log("renderTechnicianDashboard: Session status - " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE"));
        error_log("renderTechnicianDashboard: User data - " . ($userData ? json_encode($userData) : "NULL"));
        
        // WebAuthMiddleware already authenticated the user
        // If session is missing here, it's a critical issue - don't redirect (causes loop)
        if (!$userData) {
            error_log("renderTechnicianDashboard: CRITICAL - No user data despite WebAuthMiddleware check");
            error_log("renderTechnicianDashboard: All session data - " . print_r($_SESSION, true));
            // Show error instead of redirecting
            echo '<!DOCTYPE html><html><head><title>Session Error</title></head><body>';
            echo '<h1>Session Error</h1>';
            echo '<p>Your session could not be established properly.</p>';
            echo '<p>Session ID: ' . session_id() . '</p>';
            echo '<p>Please try <a href="' . BASE_URL_PATH . '/?force_logout=1">logging in again</a>.</p>';
            echo '<p>If this problem persists, please contact support.</p>';
            echo '</body></html>';
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userId = $userData['id'] ?? null;
        
        // Initialize arrays
        $pendingRepairs = [];
        $inProgressRepairs = [];
        $completedRepairs = [];
        $allRepairs = [];
        
        // Ensure user ID and company ID are valid integers
        if (!empty($userId) && !empty($companyId)) {
            // Ensure IDs are integers
            $userId = (int)$userId;
            $companyId = (int)$companyId;
            
            // Get technician's repairs - use direct queries for each status
            $repairModel = new \App\Models\Repair();
            $db = \Database::getInstance()->getConnection();
            
            // Get all repairs (for stats)
            $allRepairs = $repairModel->findByTechnician($userId, $companyId, null);
            
            // Get repairs by status using direct queries (more reliable)
            $pendingRepairs = $repairModel->findByTechnician($userId, $companyId, 'pending');
            $inProgressRepairs = $repairModel->findByTechnician($userId, $companyId, 'in_progress');
            $completedRepairs = $repairModel->findByTechnician($userId, $companyId, 'completed');
            
            // IMPORTANT: Also find repairs that have associated sales (parts sold by technician)
            // These are repairs where the technician sold parts, even if they weren't assigned as the technician
            $repairIdsFromSales = [];
            try {
                $salesQuery = $db->prepare("
                    SELECT DISTINCT ps.notes
                    FROM pos_sales ps
                    WHERE ps.company_id = ?
                      AND ps.created_by_user_id = ?
                      AND (ps.notes LIKE 'Repair #%' OR ps.notes LIKE '%Products sold by repairer%')
                ");
                $salesQuery->execute([$companyId, $userId]);
                $salesWithRepairNotes = $salesQuery->fetchAll(\PDO::FETCH_ASSOC);
                
                // Extract repair IDs from notes (format: "Repair #123 - Products sold by repairer")
                foreach ($salesWithRepairNotes as $sale) {
                    $notes = $sale['notes'] ?? '';
                    if (preg_match('/Repair\s+#(\d+)/i', $notes, $matches)) {
                        $repairId = intval($matches[1]);
                        if ($repairId > 0) {
                            $repairIdsFromSales[] = $repairId;
                        }
                    }
                }
                
                // Get repairs that have sales but might not be in the technician's direct list
                if (!empty($repairIdsFromSales)) {
                    $repairIdsStr = implode(',', array_map('intval', array_unique($repairIdsFromSales)));
                    
                    // Check which repairs table exists
                    $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                    $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                    $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
                    $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
                    
                    // Build customer_contact_merged based on which table we're using
                    // We'll use r.customer_contact directly and merge with c.phone_number in PHP
                    if ($hasRepairsNew) {
                        // repairs_new table has customer_contact column
                        $contactSelect = "COALESCE(
                            NULLIF(TRIM(c.phone_number), ''),
                            NULLIF(TRIM(r.customer_contact), ''),
                            ''
                        ) as customer_contact_merged";
                    } else {
                        // repairs table doesn't have customer_contact, only customer_id
                        $contactSelect = "COALESCE(
                            NULLIF(TRIM(c.phone_number), ''),
                            ''
                        ) as customer_contact_merged";
                    }
                    
                    // Get these repairs
                    // Explicitly select all repair fields to ensure customer_name and customer_contact are included
                    $salesRepairsQuery = $db->prepare("
                        SELECT r.id, r.company_id, r.technician_id, r.product_id, r.device_brand, r.device_model,
                               r.customer_name, r.customer_contact, r.customer_id, r.issue_description,
                               r.repair_cost, r.parts_cost, r.accessory_cost, r.total_cost,
                               r.status, r.repair_status, r.payment_status, r.tracking_code, r.notes, r.created_at, r.updated_at,
                               p.name as product_name, 
                               c.full_name as customer_name_from_table,
                               c.phone_number,
                               {$contactSelect}
                        FROM {$repairsTable} r
                        LEFT JOIN products p ON r.product_id = p.id
                        LEFT JOIN customers c ON r.customer_id = c.id
                        WHERE r.id IN ({$repairIdsStr})
                          AND CAST(r.company_id AS UNSIGNED) = CAST(? AS UNSIGNED)
                        ORDER BY r.created_at DESC
                    ");
                    $salesRepairsQuery->execute([$companyId]);
                    $repairsFromSales = $salesRepairsQuery->fetchAll(\PDO::FETCH_ASSOC);
                    
                    // Normalize status and merge with existing repairs
                    $existingRepairIds = array_column($allRepairs, 'id');
                    foreach ($repairsFromSales as $repair) {
                        // Normalize status
                        if (!$hasRepairsNew && isset($repair['repair_status'])) {
                            $repair['status'] = strtolower($repair['repair_status']);
                        } elseif ($hasRepairsNew && isset($repair['status'])) {
                            $repair['status'] = strtolower($repair['status']);
                        }
                        
                        // Map customer name - prioritize merged data, then repair record, then customer table
                        // Use customer_name_merged if available (from SQL COALESCE in Repair model)
                        $customerName = '';
                        if (isset($repair['customer_name_merged']) && !empty(trim($repair['customer_name_merged']))) {
                            $customerName = trim($repair['customer_name_merged']);
                        } elseif (isset($repair['customer_name']) && !empty(trim($repair['customer_name']))) {
                            // r.customer_name from repair record (most reliable - actual booking data)
                            $customerName = trim($repair['customer_name']);
                        } elseif (isset($repair['customer_name_from_table']) && !empty(trim($repair['customer_name_from_table']))) {
                            // Fall back to customer table
                            $customerName = trim($repair['customer_name_from_table']);
                        }
                        // Only show "Unknown Customer" if we truly have no data (shouldn't happen for bookings)
                        $repair['customer_name'] = $customerName ?: 'Unknown Customer';
                        
                        // Ensure customer_contact is properly set from multiple sources
                        // Prioritize customer_contact_merged (from SQL COALESCE), then r.customer_contact, then phone_number
                        $contact = '';
                        if (isset($repair['customer_contact_merged']) && !empty(trim($repair['customer_contact_merged']))) {
                            // Merged contact from SQL COALESCE (prioritizes repair record, then customer table)
                            $contact = trim($repair['customer_contact_merged']);
                        } elseif (!empty(trim($repair['customer_contact'] ?? ''))) {
                            // r.customer_contact from repair record (most reliable)
                            $contact = trim($repair['customer_contact']);
                        } elseif (!empty(trim($repair['phone_number'] ?? ''))) {
                            // c.phone_number from customers table
                            $contact = trim($repair['phone_number']);
                        }
                        $repair['customer_contact'] = $contact;
                        
                        // Ensure issue_description is preserved - NEVER use notes as fallback (notes contains profit info)
                        if (!empty(trim($repair['issue_description'] ?? ''))) {
                            $repair['issue_description'] = trim($repair['issue_description']);
                        } else {
                            // Only use a generic default, never use notes field
                            $repair['issue_description'] = 'Repair service';
                        }
                        
                        // Add to allRepairs if not already there
                        if (!in_array($repair['id'], $existingRepairIds)) {
                            $allRepairs[] = $repair;
                            $existingRepairIds[] = $repair['id'];
                            
                            // Add to appropriate status list
                            $status = strtolower(trim($repair['status'] ?? ''));
                            if ($status === 'pending' || $status === '' || $status === null) {
                                $pendingRepairs[] = $repair;
                            } elseif ($status === 'in_progress') {
                                $inProgressRepairs[] = $repair;
                            } elseif ($status === 'completed') {
                                $completedRepairs[] = $repair;
                            } elseif ($status === 'delivered') {
                                // Delivered repairs are also counted in allRepairs for stats
                                // They don't need a separate list, but they're included in the count
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error finding repairs from sales: " . $e->getMessage());
            }
            
            // Log for debugging
            error_log("DashboardController::renderTechnicianDashboard() - User ID: {$userId}, Company ID: {$companyId}");
            error_log("DashboardController::renderTechnicianDashboard() - Total repairs: " . count($allRepairs));
            error_log("DashboardController::renderTechnicianDashboard() - Pending: " . count($pendingRepairs));
            error_log("DashboardController::renderTechnicianDashboard() - In Progress: " . count($inProgressRepairs));
            error_log("DashboardController::renderTechnicianDashboard() - Completed: " . count($completedRepairs));
            error_log("DashboardController::renderTechnicianDashboard() - Repairs from sales: " . count($repairIdsFromSales));
        }
        $totalRepairs = count($allRepairs);
        $totalRevenue = array_sum(array_column($allRepairs, 'total_cost'));
        
        // Calculate counts from allRepairs to ensure accuracy
        $completedCount = count(array_filter($allRepairs, function($r) {
            $status = strtolower(trim($r['status'] ?? $r['repair_status'] ?? ''));
            return $status === 'completed';
        }));
        
        $pendingCount = count(array_filter($allRepairs, function($r) {
            $status = strtolower(trim($r['status'] ?? $r['repair_status'] ?? ''));
            return $status === 'pending' || $status === '';
        }));
        
        $deliveredCount = count(array_filter($allRepairs, function($r) {
            $status = strtolower(trim($r['status'] ?? $r['repair_status'] ?? ''));
            return $status === 'delivered';
        }));
        
        // Calculate repair cost and parts cost
        $totalRepairCost = 0; // Workmanship/labour cost
        $totalPartsCost = 0;  // Parts + accessories cost
        foreach ($allRepairs as $repair) {
            // Get repair cost (workmanship/labour) - check multiple possible field names
            $repairCost = floatval($repair['repair_cost'] ?? $repair['labour_cost'] ?? 0);
            // Get parts cost
            $partsCost = floatval($repair['parts_cost'] ?? 0);
            // Get accessory cost
            $accessoryCost = floatval($repair['accessory_cost'] ?? 0);
            // Get total cost
            $totalCost = floatval($repair['total_cost'] ?? 0);
            
            // If individual costs are 0 but total_cost exists, use total_cost as repair_cost
            // This handles cases where only total_cost is set
            if ($repairCost == 0 && $partsCost == 0 && $accessoryCost == 0 && $totalCost > 0) {
                // Use total_cost as repair_cost (workmanship)
                $totalRepairCost += $totalCost;
            } else {
                // Sum repair costs (workmanship)
                $totalRepairCost += $repairCost;
                
                // Sum parts cost - use parts_cost if set, otherwise accessory_cost
                // When accessories are selected, parts_cost is set to equal accessory_cost in RepairController,
                // so we use parts_cost as the primary source to avoid double counting
                if ($partsCost > 0) {
                    $totalPartsCost += $partsCost;
                } else {
                    // Only use accessory_cost if parts_cost is not set
                    $totalPartsCost += $accessoryCost;
                }
            }
        }
        
        // Ensure variables are defined even if empty
        $totalRepairCost = $totalRepairCost ?? 0;
        $totalPartsCost = $totalPartsCost ?? 0;
        $completedCount = $completedCount ?? 0;
        $pendingCount = $pendingCount ?? 0;
        $deliveredCount = $deliveredCount ?? 0;
        $pendingRepairs = $pendingRepairs ?? [];
        $inProgressRepairs = $inProgressRepairs ?? [];
        
        // Pass variables to view
        $GLOBALS['totalRepairCost'] = $totalRepairCost;
        $GLOBALS['totalPartsCost'] = $totalPartsCost;
        $GLOBALS['completedCount'] = $completedCount;
        $GLOBALS['pendingCount'] = $pendingCount;
        $GLOBALS['deliveredCount'] = $deliveredCount;
        $GLOBALS['pendingRepairs'] = $pendingRepairs;
        $GLOBALS['inProgressRepairs'] = $inProgressRepairs;
        
        $title = 'Technician Dashboard';
        
        ob_start();
        include __DIR__ . '/../Views/technician_dashboard.php';
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
     * Render with layout
     */
    private function renderWithLayout($title, $content) {
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - SellApp</title>
    <script>
        window.APP_BASE_PATH = "' . $basePath . '";
        const BASE = window.APP_BASE_PATH || "";
    </script>
    
    <!-- Preconnect to CDN for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    
    <!-- Robust Tailwind CSS loader with online/offline detection and retry mechanism -->
    <script>
        (function() {
            let tailwindLoaded = false;
            let retryCount = 0;
            const maxRetries = 10;
            const retryDelay = 1000; // 1 second
            
            function loadTailwind() {
                // Check if already loaded
                if (tailwindLoaded || window.tailwind) {
                    return;
                }
                
                // Check if script already exists
                const existingScript = document.querySelector("script[data-tailwind-loader]");
                if (existingScript) {
                    return;
                }
                
                const script = document.createElement("script");
                script.src = "https://cdn.tailwindcss.com";
                script.async = true;
                script.setAttribute("data-tailwind-loader", "true");
                
                script.onload = function() {
                    tailwindLoaded = true;
                    retryCount = 0;
                    // Trigger a re-render to apply styles
                    if (window.tailwind && typeof window.tailwind.refresh === "function") {
                        window.tailwind.refresh();
                    }
                    // Show body once Tailwind is loaded
                    document.body.classList.add("tailwind-loaded");
                    // Dispatch custom event for other scripts
                    window.dispatchEvent(new CustomEvent("tailwindLoaded"));
                };
                
                script.onerror = function() {
                    // Script failed to load
                    if (retryCount < maxRetries) {
                        retryCount++;
                        setTimeout(loadTailwind, retryDelay);
                    }
                };
                
                document.head.appendChild(script);
            }
            
            // Try loading immediately
            loadTailwind();
            
            // Listen for online event and retry
            window.addEventListener("online", function() {
                if (!tailwindLoaded) {
                    retryCount = 0; // Reset retry count when back online
                    loadTailwind();
                }
            });
            
            // Periodic check when offline (in case online event doesn\'t fire)
            let offlineCheckInterval = setInterval(function() {
                if (navigator.onLine && !tailwindLoaded) {
                    retryCount = 0;
                    loadTailwind();
                }
            }, 2000); // Check every 2 seconds
            
            // Clear interval when Tailwind is loaded
            const checkLoaded = setInterval(function() {
                if (tailwindLoaded) {
                    clearInterval(offlineCheckInterval);
                    clearInterval(checkLoaded);
                }
            }, 500);
            
            // Fallback: Show body after 5 seconds even if Tailwind hasn\'t loaded
            setTimeout(function() {
                if (!tailwindLoaded) {
                    document.body.classList.add("tailwind-loaded");
                }
            }, 5000);
        })();
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Hide body until Tailwind is loaded to prevent FOUC */
        body {
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s ease-in;
        }
        
        body.tailwind-loaded {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    ' . $content . '
        
        <script>
        // Load dashboard data
        document.addEventListener("DOMContentLoaded", function() {
            loadDashboardData();
        });
        
        function loadDashboardData() {
            const token = localStorage.getItem("sellapp_token");
            if (!token) return;
            
            fetch(BASE + "/api/dashboard/sales-metrics", {
                headers: {
                    "Authorization": "Bearer " + token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("today-sales").textContent = data.metrics.sales || 0;
                    document.getElementById("today-revenue").textContent = "₵" + (data.metrics.revenue || 0).toFixed(2);
                    document.getElementById("today-customers").textContent = data.metrics.customers || 0;
                }
            })
            .catch(error => {
                console.error("Error loading dashboard data:", error);
            });
        }
    </script>
</body>
</html>';
    }
    
    /**
     * Get platform-wide metrics for admin dashboard
     */
    public function platformMetrics() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            // Get active companies count (all companies, since status may not be set)
            $companiesQuery = $db->query("SELECT COUNT(*) as total FROM companies");
            $activeCompanies = $companiesQuery->fetch()['total'] ?? 0;

            // Get total users
            $usersQuery = $db->query("SELECT COUNT(*) as total FROM users");
            $totalUsers = $usersQuery->fetch()['total'] ?? 0;

            // Get platform revenue (sum of all sales + repairs revenue - all time)
            $salesRevenueQuery = $db->query("
                SELECT COALESCE(SUM(final_amount), 0) as total 
                FROM pos_sales
            ");
            $salesRevenue = $salesRevenueQuery->fetch()['total'] ?? 0;
            
            $repairsRevenueQuery = $db->query("
                SELECT COALESCE(SUM(total_cost), 0) as total 
                FROM repairs 
                WHERE payment_status = 'PAID'
            ");
            $repairsRevenue = $repairsRevenueQuery->fetch()['total'] ?? 0;
            
            $platformRevenue = $salesRevenue + $repairsRevenue;

            $metrics = [
                'active_companies' => (int)$activeCompanies,
                'total_users' => (int)$totalUsers,
                'platform_revenue' => (float)$platformRevenue,
                'platform_status' => 'Operational'
            ];

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'metrics' => $metrics
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch platform metrics',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get company performance metrics for admin dashboard
     */
    public function companyPerformance() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            $query = $db->query("
                SELECT 
                    c.id,
                    c.name,
                    c.email,
                    COALESCE(c.status, 'active') as status,
                    COUNT(DISTINCT u.id) as user_count,
                    COALESCE(SUM(ps.final_amount), 0) as revenue,
                    COUNT(DISTINCT ps.id) as sales_count,
                    COUNT(DISTINCT r.id) as repairs_count,
                    COUNT(DISTINCT s.id) as swaps_count
                FROM companies c
                LEFT JOIN users u ON c.id = u.company_id
                LEFT JOIN pos_sales ps ON c.id = ps.company_id
                LEFT JOIN repairs r ON c.id = r.company_id
                LEFT JOIN swaps s ON c.id = s.company_id
                GROUP BY c.id, c.name, c.email, c.status
                ORDER BY revenue DESC
            ");
            $companies = $query->fetchAll();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'companies' => $companies
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch company performance',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get recent activity for admin dashboard
     */
    public function recentActivity() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        try {
            // Use session-based authentication for web pages
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if (!$userData || ($userData['role'] ?? '') !== 'system_admin') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized'
                ]);
                return;
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Check if tables exist first
            $tablesExist = true;
            try {
                $db->query("SELECT 1 FROM pos_sales LIMIT 1");
                $db->query("SELECT 1 FROM repairs LIMIT 1");
            } catch (\Exception $e) {
                $tablesExist = false;
            }
            
            if (!$tablesExist) {
                // Return empty activities if tables don't exist
                $activities = [];
            } else {
                try {
                    $query = $db->query("
                        SELECT 
                            'New Sale' as description,
                            CONCAT('₵', FORMAT(COALESCE(ps.final_amount, 0), 2)) as details,
                            ps.created_at as time
                        FROM pos_sales ps
                        LEFT JOIN companies c ON ps.company_id = c.id
                        WHERE ps.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                        
                        UNION ALL
                        
                        SELECT 
                            'New Repair' as description,
                            CONCAT('Device: ', COALESCE(r.device_model, 'Unknown')) as details,
                            r.created_at as time
                        FROM repairs r
                        LEFT JOIN companies c ON r.company_id = c.id
                        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                        
                        ORDER BY time DESC
                        LIMIT 10
                    ");
                    $activities = $query->fetchAll() ?: [];
                } catch (\Exception $queryError) {
                    // If the complex query fails, try simpler queries
                    error_log("Complex query failed, trying simpler approach: " . $queryError->getMessage());
                    
                    $activities = [];
                    
                    // Try to get recent sales
                    try {
                        $salesQuery = $db->query("SELECT 'New Sale' as description, '₵0.00' as details, NOW() as time FROM pos_sales LIMIT 5");
                        $sales = $salesQuery->fetchAll() ?: [];
                        $activities = array_merge($activities, $sales);
                    } catch (\Exception $e) {
                        // Ignore if this fails too
                    }
                    
                    // Try to get recent repairs
                    try {
                        $repairsQuery = $db->query("SELECT 'New Repair' as description, 'Device: Unknown' as details, NOW() as time FROM repairs LIMIT 5");
                        $repairs = $repairsQuery->fetchAll() ?: [];
                        $activities = array_merge($activities, $repairs);
                    } catch (\Exception $e) {
                        // Ignore if this fails too
                    }
                }
            }

            // Format time for display
            foreach ($activities as &$activity) {
                if (isset($activity['time'])) {
                    $activity['time'] = date('H:i', strtotime($activity['time']));
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'activities' => $activities
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("Recent Activity Error: " . $e->getMessage());
            error_log("Recent Activity Trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch recent activity',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get company-specific metrics for manager dashboard
     */
    public function companyMetrics() {
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set JSON header
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Prioritize session-based authentication for web requests
        $companyId = null;
        $role = null;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        
        if ($user && isset($user['company_id'])) {
            $companyId = $user['company_id'];
            $role = $user['role'] ?? '';
            
            // Check if user has required role
            $allowedRoles = ['system_admin', 'admin', 'manager'];
            if (!in_array($role, $allowedRoles, true)) {
                ob_end_clean();
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ]);
                return;
            }
        } else {
            // Try token-based authentication as fallback
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin']);
                    $companyId = $payload->company_id;
                    $role = $payload->role;
                } catch (\Exception $e) {
                    // Token validation failed
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Authentication required'
                    ]);
                    return;
                }
            } else {
                // No session and no token
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                return;
            }
        }

        // Get enabled modules for this company
        $companyModule = new CompanyModule();
        $enabledModuleKeys = $role === 'system_admin' ? [] : $companyModule->getEnabledModules($companyId);
        
        // If system admin, get all modules (empty array means all enabled)
        if ($role === 'system_admin') {
            $enabledModuleKeys = ['products_inventory', 'pos_sales', 'swap', 'repairs', 'customers', 'staff_management', 'reports_analytics'];
        }
        
        $db = \Database::getInstance()->getConnection();
        
        try {
            $metrics = [];
            
            // POS / Sales metrics (only if module enabled)
            if ($role === 'system_admin' || in_array('pos_sales', $enabledModuleKeys)) {
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use swap_id IS NULL to exclude all swap-related sales
                $todayRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                    AND swap_id IS NULL
                ");
                $todayRevenueQuery->execute([$companyId]);
                $todayRevenue = $todayRevenueQuery->fetch()['revenue'] ?? 0;

                $todaySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                    AND swap_id IS NULL
                ");
                $todaySalesQuery->execute([$companyId]);
                $todaySales = $todaySalesQuery->fetch()['count'] ?? 0;

                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use swap_id IS NULL to exclude all swap-related sales
                $monthlyRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                    AND swap_id IS NULL
                ");
                $monthlyRevenueQuery->execute([$companyId]);
                $monthlyRevenue = $monthlyRevenueQuery->fetch()['revenue'] ?? 0;

                $monthlySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                    AND swap_id IS NULL
                ");
                $monthlySalesQuery->execute([$companyId]);
                $monthlySales = $monthlySalesQuery->fetch()['count'] ?? 0;
                
                $metrics['today_revenue'] = (float)$todayRevenue;
                $metrics['today_sales'] = (int)$todaySales;
                $metrics['monthly_revenue'] = (float)$monthlyRevenue;
                $metrics['monthly_sales'] = (int)$monthlySales;
                
                // Calculate profit from inventory products sold (by salesperson and technician)
                try {
                    // Check which products table exists
                    $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                    $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
                    
                    // Check which cost columns exist
                    $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                    $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                    $checkPurchasePrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'purchase_price'");
                    $hasCostPrice = $checkCostPrice->rowCount() > 0;
                    $hasCost = $checkCost->rowCount() > 0;
                    $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
                    
                    // Determine cost column to use (prioritize cost_price, then cost, then purchase_price)
                    $costColumn = '0';
                    if ($hasCostPrice) {
                        $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
                    } elseif ($hasCost) {
                        $costColumn = 'COALESCE(p.cost, 0)';
                    } elseif ($hasPurchasePrice) {
                        $costColumn = 'COALESCE(p.purchase_price, 0)';
                    }
                    
                    error_log("Profit Calculation Debug - Products Table: {$productsTable}, Cost Column: {$costColumn}");
                    
                    // Calculate today's profit: Revenue - Cost (from inventory products only)
                    // Include all sales from inventory products (both salesperson and technician)
                    // Use LEFT JOIN first to see all items, then filter for those with valid products
                    // This ensures we capture profit even if some products are missing cost data
                    $todayProfitQuery = $db->prepare("
                        SELECT 
                            COALESCE(SUM(psi.total_price), 0) as revenue,
                            COALESCE(SUM(CASE 
                                WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                                ELSE 0
                            END), 0) as cost,
                            COUNT(DISTINCT psi.id) as item_count,
                            COUNT(DISTINCT ps.id) as sale_count,
                            SUM(CASE WHEN p.id IS NULL AND psi.item_id IS NOT NULL AND psi.item_id > 0 THEN 1 ELSE 0 END) as missing_products
                        FROM pos_sale_items psi
                        INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                        WHERE ps.company_id = ? 
                        AND DATE(ps.created_at) = CURDATE()
                        AND psi.item_id IS NOT NULL 
                        AND psi.item_id > 0
                    ");
                    $todayProfitQuery->execute([$companyId]);
                    $todayProfitResult = $todayProfitQuery->fetch(\PDO::FETCH_ASSOC);
                    $todayProfitRevenue = floatval($todayProfitResult['revenue'] ?? 0);
                    $todayProfitCost = floatval($todayProfitResult['cost'] ?? 0);
                    $todayItemCount = intval($todayProfitResult['item_count'] ?? 0);
                    $todaySaleCount = intval($todayProfitResult['sale_count'] ?? 0);
                    $todayMissingProducts = intval($todayProfitResult['missing_products'] ?? 0);
                    $todayProfit = $todayProfitRevenue - $todayProfitCost;
                    if ($todayProfit < 0) $todayProfit = 0; // Profit cannot be negative
                    
                    error_log("Profit Calculation Debug - Today: Revenue={$todayProfitRevenue}, Cost={$todayProfitCost}, Items={$todayItemCount}, Sales={$todaySaleCount}, Missing Products={$todayMissingProducts}, Profit={$todayProfit}");
                    
                    // Calculate monthly profit (current month)
                    $monthlyProfitQuery = $db->prepare("
                        SELECT 
                            COALESCE(SUM(psi.total_price), 0) as revenue,
                            COALESCE(SUM(CASE 
                                WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                                ELSE 0
                            END), 0) as cost,
                            COUNT(DISTINCT psi.id) as item_count,
                            COUNT(DISTINCT ps.id) as sale_count,
                            SUM(CASE WHEN p.id IS NULL AND psi.item_id IS NOT NULL AND psi.item_id > 0 THEN 1 ELSE 0 END) as missing_products
                        FROM pos_sale_items psi
                        INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                        WHERE ps.company_id = ? 
                        AND MONTH(ps.created_at) = MONTH(CURDATE()) 
                        AND YEAR(ps.created_at) = YEAR(CURDATE())
                        AND psi.item_id IS NOT NULL 
                        AND psi.item_id > 0
                    ");
                    $monthlyProfitQuery->execute([$companyId]);
                    $monthlyProfitResult = $monthlyProfitQuery->fetch(\PDO::FETCH_ASSOC);
                    $monthlyProfitRevenue = floatval($monthlyProfitResult['revenue'] ?? 0);
                    $monthlyProfitCost = floatval($monthlyProfitResult['cost'] ?? 0);
                    $monthlyItemCount = intval($monthlyProfitResult['item_count'] ?? 0);
                    $monthlySaleCount = intval($monthlyProfitResult['sale_count'] ?? 0);
                    $monthlyMissingProducts = intval($monthlyProfitResult['missing_products'] ?? 0);
                    $monthlyProfit = $monthlyProfitRevenue - $monthlyProfitCost;
                    if ($monthlyProfit < 0) $monthlyProfit = 0; // Profit cannot be negative
                    
                    error_log("Profit Calculation Debug - Monthly: Revenue={$monthlyProfitRevenue}, Cost={$monthlyProfitCost}, Items={$monthlyItemCount}, Sales={$monthlySaleCount}, Missing Products={$monthlyMissingProducts}, Profit={$monthlyProfit}");
                    
                    // Also calculate all-time profit for the dashboard (from all sales with inventory products)
                    // This gives a better overview when today has no sales
                    $allTimeProfitQuery = $db->prepare("
                        SELECT 
                            COALESCE(SUM(psi.total_price), 0) as revenue,
                            COALESCE(SUM(CASE 
                                WHEN p.id IS NOT NULL THEN psi.quantity * {$costColumn}
                                ELSE 0
                            END), 0) as cost
                        FROM pos_sale_items psi
                        INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                        WHERE ps.company_id = ? 
                        AND psi.item_id IS NOT NULL 
                        AND psi.item_id > 0
                    ");
                    $allTimeProfitQuery->execute([$companyId]);
                    $allTimeProfitResult = $allTimeProfitQuery->fetch(\PDO::FETCH_ASSOC);
                    $allTimeProfitRevenue = floatval($allTimeProfitResult['revenue'] ?? 0);
                    $allTimeProfitCost = floatval($allTimeProfitResult['cost'] ?? 0);
                    $allTimeProfit = $allTimeProfitRevenue - $allTimeProfitCost;
                    if ($allTimeProfit < 0) $allTimeProfit = 0;
                    
                    error_log("Profit Calculation Debug - All Time: Revenue={$allTimeProfitRevenue}, Cost={$allTimeProfitCost}, Profit={$allTimeProfit}");
                    
                    // Use monthly profit for today's profit display (since it's more meaningful when today has no sales)
                    // But still provide today's profit separately
                    $metrics['today_profit'] = round($todayProfit, 2);
                    $metrics['monthly_profit'] = round($monthlyProfit, 2);
                    // For dashboard display, show monthly profit if today is 0, otherwise show today's
                    $metrics['display_profit'] = round(($todayProfit > 0 ? $todayProfit : $monthlyProfit), 2);
                } catch (\Exception $e) {
                    error_log("DashboardController::companyMetrics - Error calculating profit: " . $e->getMessage());
                    error_log("DashboardController::companyMetrics - Error trace: " . $e->getTraceAsString());
                    $metrics['today_profit'] = 0.00;
                    $metrics['monthly_profit'] = 0.00;
                }
                
                // Get payment statistics if partial payments module is enabled
                if (CompanyModule::isEnabled($companyId, 'partial_payments')) {
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
                        
                        $metrics['payment_stats'] = [
                            'fully_paid' => $fullyPaid,
                            'partial' => $partial,
                            'unpaid' => $unpaid
                        ];
                    } catch (\Exception $e) {
                        error_log("DashboardController::companyMetrics - Error getting payment stats: " . $e->getMessage());
                    }
                }
            }

            // Repairs metrics (only if module enabled)
            if ($role === 'system_admin' || in_array('repairs', $enabledModuleKeys)) {
                $activeRepairsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM repairs 
                    WHERE company_id = ? AND UPPER(repair_status) IN ('PENDING','IN_PROGRESS')
                ");
                $activeRepairsQuery->execute([$companyId]);
                $activeRepairs = $activeRepairsQuery->fetch()['count'] ?? 0;

                $monthlyRepairsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM repairs 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $monthlyRepairsQuery->execute([$companyId]);
                $monthlyRepairs = $monthlyRepairsQuery->fetch()['count'] ?? 0;
                
                $metrics['active_repairs'] = (int)$activeRepairs;
                $metrics['monthly_repairs'] = (int)$monthlyRepairs;
            }

            // Swap metrics (only if module enabled)
            if ($role === 'system_admin' || in_array('swap', $enabledModuleKeys)) {
                $pendingSwapsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM swaps 
                    WHERE company_id = ? AND UPPER(swap_status) = 'PENDING'
                ");
                $pendingSwapsQuery->execute([$companyId]);
                $pendingSwaps = $pendingSwapsQuery->fetch()['count'] ?? 0;

                $monthlySwapsQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM swaps 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $monthlySwapsQuery->execute([$companyId]);
                $monthlySwaps = $monthlySwapsQuery->fetch()['count'] ?? 0;

                // Get swap stats
                try {
                    $swapStatsQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as total_swaps,
                            SUM(CASE WHEN COALESCE(status, 'pending') = 'pending' THEN 1 ELSE 0 END) as pending_count,
                            SUM(CASE WHEN COALESCE(status, 'pending') = 'completed' THEN 1 ELSE 0 END) as completed_count,
                            SUM(CASE WHEN COALESCE(status, 'pending') = 'resold' THEN 1 ELSE 0 END) as resold_count,
                            SUM(COALESCE(total_value, 0)) as total_swap_value
                        FROM swaps 
                        WHERE company_id = ?
                    ");
                    $swapStatsQuery->execute([$companyId]);
                    $swapStats = $swapStatsQuery->fetch() ?? [];
                } catch (\Exception $e) {
                    $swapStats = [];
                }
                
                $metrics['pending_swaps'] = (int)$pendingSwaps;
                $metrics['monthly_swaps'] = (int)$monthlySwaps;
                $metrics['total_swaps'] = (int)($swapStats['total_swaps'] ?? 0);
                $metrics['completed_swaps'] = (int)($swapStats['completed_count'] ?? 0);
                $metrics['resold_swaps'] = (int)($swapStats['resold_count'] ?? 0);
                $metrics['total_swap_value'] = (float)($swapStats['total_swap_value'] ?? 0);
            }

            // Filter metrics using DashboardWidgets helper (additional filtering layer)
            $filteredMetrics = DashboardWidgets::filterMetrics($metrics, $enabledModuleKeys, $role);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'metrics' => $filteredMetrics
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch company metrics',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get recent sales for manager dashboard
     * Supports both Bearer token and session-based authentication
     */
    public function recentSales() {
        // Clean any existing output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Suppress PHP warnings for clean JSON output
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        ini_set('display_errors', 0);
        
        // Set JSON header early
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $companyId = null;
            
            // Try Authorization header first (Bearer token)
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    $companyId = $payload->company_id ?? null;
                } catch (\Exception $e) {
                    // Token validation failed, try session fallback
                    error_log("Token validation failed in recentSales: " . $e->getMessage());
                }
            }
            
            // Fallback to session-based user if header missing/invalid
            if ($companyId === null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
                    ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
                $companyId = $user['company_id'] ?? null;
            }
            
            if (!$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID is required']);
                return;
            }
            
        $db = \Database::getInstance()->getConnection();
        
            $query = $db->prepare("
            SELECT 
                    ps.id,
                    COALESCE(c.full_name, 'Walk-in') AS customer_name,
                    ps.final_amount as amount,
                    ps.created_at,
                    COUNT(psi.id) as items
                FROM pos_sales ps
                LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                LEFT JOIN customers c ON ps.customer_id = c.id
                WHERE ps.company_id = ?
                GROUP BY ps.id, c.full_name, ps.final_amount, ps.created_at
                ORDER BY ps.created_at DESC
                LIMIT 10
            ");
            $query->execute([$companyId]);
            $sales = $query->fetchAll(\PDO::FETCH_ASSOC);

            // Format time for display
            foreach ($sales as &$sale) {
                $sale['time'] = isset($sale['created_at']) ? date('H:i', strtotime($sale['created_at'])) : '';
            }
            unset($sale); // Break the reference

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'sales' => $sales ?: []
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            ob_end_clean();
            error_log("DashboardController recentSales error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch recent sales',
                'message' => $e->getMessage()
            ]);
        } catch (\Error $e) {
            ob_end_clean();
            error_log("DashboardController recentSales fatal error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get inventory alerts for manager and salesperson dashboards
     */
    public function inventoryAlerts() {
        // Register shutdown function to catch fatal errors
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
                    'message' => 'A fatal error occurred: ' . $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        });
        
        // Clean any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start output buffering
        ob_start();
        
        // Set JSON header immediately
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        error_log("Inventory alerts: Method called");
        
        // Ensure Database class is loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Prioritize session-based authentication for web requests
        $user = $_SESSION['user'] ?? null;
        $companyId = null;
        
        error_log("Inventory alerts: User session - " . ($user ? "found, company_id: " . ($user['company_id'] ?? 'none') : "not found"));
        
        if ($user && isset($user['company_id'])) {
            $companyId = $user['company_id'];
            $userRole = $user['role'] ?? '';
            
            // Check if user has required role
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                ob_end_clean();
                if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied'
                ]);
                exit;
            }
        } else {
            // Try token-based authentication as fallback
            try {
                $payload = AuthMiddleware::handle(['manager', 'admin', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            } catch (\Exception $e) {
                ob_end_clean();
                if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                exit;
            }
        }
        
        // Validate company_id
        if (!$companyId) {
            error_log("Inventory alerts: No company_id provided");
            ob_end_clean();
            if (!headers_sent()) {
                http_response_code(400);
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false,
                'error' => 'Company ID is required'
            ]);
            exit;
        }
        
        // Get database connection with error handling
        try {
        $db = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log("Inventory alerts: Database connection failed - " . $e->getMessage());
            // Return empty results instead of error to prevent dashboard breakage
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => true,
                'low_stock' => [],
                'out_of_stock' => [],
                'total_count' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Error $e) {
            error_log("Inventory alerts: Database connection fatal error - " . $e->getMessage());
            // Return empty results instead of error to prevent dashboard breakage
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => true,
                'low_stock' => [],
                'out_of_stock' => [],
                'total_count' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            // Check which products table exists
            $tableName = null;
            $hasMinQuantity = false;
            $quantityCol = 'quantity';
            $hasStatus = false;
            $hasBrand = false;
            $hasSupplier = false;
            $hasCompanyId = false;
            
            // Try products table first (most common)
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'products'");
                if ($checkTable && $checkTable->rowCount() > 0) {
                    $tableName = 'products';
                    try {
                        $checkCol = $db->query("SHOW COLUMNS FROM `products` LIKE 'min_quantity'");
                        $hasMinQuantity = $checkCol && $checkCol->rowCount() > 0;
                    } catch (\Exception $e) {
                        error_log("Error checking min_quantity column: " . $e->getMessage());
                        $hasMinQuantity = false;
                    }
                    
                    try {
                        $checkCol2 = $db->query("SHOW COLUMNS FROM `products` LIKE 'quantity'");
                        if ($checkCol2 && $checkCol2->rowCount() > 0) {
                        $quantityCol = 'quantity';
                    } else {
                            $checkCol3 = $db->query("SHOW COLUMNS FROM `products` LIKE 'qty'");
                            if ($checkCol3 && $checkCol3->rowCount() > 0) {
                            $quantityCol = 'qty';
                        }
                    }
                    } catch (\Exception $e) {
                        error_log("Error checking quantity column: " . $e->getMessage());
                    }
                    
                    try {
                        $checkStatus = $db->query("SHOW COLUMNS FROM `products` LIKE 'status'");
                        $hasStatus = $checkStatus && $checkStatus->rowCount() > 0;
                    } catch (\Exception $e) {
                        error_log("Error checking status column: " . $e->getMessage());
                        $hasStatus = false;
                    }
                    
                    try {
                        $checkCompanyId = $db->query("SHOW COLUMNS FROM `products` LIKE 'company_id'");
                        $hasCompanyId = $checkCompanyId && $checkCompanyId->rowCount() > 0;
                    } catch (\Exception $e) {
                        error_log("Error checking company_id column: " . $e->getMessage());
                        $hasCompanyId = false;
                    }
                }
            } catch (\Exception $e) {
                error_log("Error checking products table: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
            } catch (\Error $e) {
                error_log("Fatal error checking products table: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
            }
            
            // Fallback to products_new
            if (!$tableName) {
                try {
                    $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
                    if ($checkTable && $checkTable->rowCount() > 0) {
                        $tableName = 'products_new';
                        try {
                            $checkCol = $db->query("SHOW COLUMNS FROM `products_new` LIKE 'min_quantity'");
                            $hasMinQuantity = $checkCol && $checkCol->rowCount() > 0;
                        } catch (\Exception $e) {
                            error_log("Error checking min_quantity column in products_new: " . $e->getMessage());
                            $hasMinQuantity = false;
                        }
                        
                        try {
                            $checkCol2 = $db->query("SHOW COLUMNS FROM `products_new` LIKE 'quantity'");
                            if ($checkCol2 && $checkCol2->rowCount() > 0) {
                            $quantityCol = 'quantity';
                        } else {
                                $checkCol3 = $db->query("SHOW COLUMNS FROM `products_new` LIKE 'qty'");
                                if ($checkCol3 && $checkCol3->rowCount() > 0) {
                                $quantityCol = 'qty';
                            }
                        }
                        } catch (\Exception $e) {
                            error_log("Error checking quantity column in products_new: " . $e->getMessage());
                        }
                        
                        try {
                            $checkStatus = $db->query("SHOW COLUMNS FROM `products_new` LIKE 'status'");
                            $hasStatus = $checkStatus && $checkStatus->rowCount() > 0;
                        } catch (\Exception $e) {
                            error_log("Error checking status column in products_new: " . $e->getMessage());
                            $hasStatus = false;
                        }
                        
                        try {
                            $checkCompanyId = $db->query("SHOW COLUMNS FROM `products_new` LIKE 'company_id'");
                            $hasCompanyId = $checkCompanyId && $checkCompanyId->rowCount() > 0;
                        } catch (\Exception $e) {
                            error_log("Error checking company_id column in products_new: " . $e->getMessage());
                            $hasCompanyId = false;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error checking products_new table: " . $e->getMessage());
                    error_log("Error trace: " . $e->getTraceAsString());
                } catch (\Error $e) {
                    error_log("Fatal error checking products_new table: " . $e->getMessage());
                    error_log("Error trace: " . $e->getTraceAsString());
                }
            }
            
            if (!$tableName) {
                error_log("No products table found for inventory alerts");
                ob_end_clean();
                if (!headers_sent()) {
                header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => true,
                    'low_stock' => [],
                    'out_of_stock' => [],
                    'total_count' => 0
                ]);
                exit;
            }
            
            // Check if company_id column exists
            if (!$hasCompanyId) {
                error_log("Products table {$tableName} does not have company_id column");
                ob_end_clean();
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => true,
                    'low_stock' => [],
                    'out_of_stock' => [],
                    'total_count' => 0
                ]);
                exit;
            }
            
            // Check for brand, brand_id, and supplier columns
            $hasBrand = false;
            $hasBrandId = false;
            $hasSupplier = false;
            
            try {
                $checkBrand = $db->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'brand'");
                $hasBrand = $checkBrand && $checkBrand->rowCount() > 0;
            } catch (\Exception $e) {
                error_log("Error checking brand column: " . $e->getMessage());
            }
            
            if (!$hasBrand) {
                try {
                    $checkBrandId = $db->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'brand_id'");
                    $hasBrandId = $checkBrandId && $checkBrandId->rowCount() > 0;
                } catch (\Exception $e) {
                    error_log("Error checking brand_id column: " . $e->getMessage());
                }
            }
            
            try {
                $checkSupplier = $db->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'supplier'");
                $hasSupplier = $checkSupplier && $checkSupplier->rowCount() > 0;
            } catch (\Exception $e) {
                error_log("Error checking supplier column: " . $e->getMessage());
            }
            
            // Build query to get low stock and out of stock items
            $minQtyDefault = 5; // Default minimum threshold for low stock
            
            // Simplified logic:
            // Out of stock: quantity > 0 AND status IN ('out_of_stock', 'OUT_OF_STOCK', 'sold')
            // Note: Items with quantity = 0 are filtered out from product listings, so they should not appear in alerts
            // Low stock: quantity > 0 AND quantity <= minQtyDefault AND status = 'available'
            
            // Build WHERE conditions - items that are either out of stock OR low stock
            $alertConditions = [];
            
            // Out of stock conditions - only items with quantity > 0 that have out-of-stock status
            // Items with quantity = 0 are filtered out from products, so exclude them from alerts
            $outOfStockConditions = [];
            if ($hasStatus) {
                // Only show items with quantity > 0 that have out-of-stock status
                $outOfStockConditions[] = "(COALESCE(p.{$quantityCol}, 0) > 0 AND (LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold'))";
            }
            // Only add out-of-stock condition if we have status column and conditions exist
            if (!empty($outOfStockConditions)) {
                $alertConditions[] = "(" . implode(' OR ', $outOfStockConditions) . ")";
            }
            
            // Low stock conditions (quantity > 0 AND quantity <= threshold)
            // Don't restrict by status for low stock - any item with low quantity should alert
            $lowStockCondition = "COALESCE(p.{$quantityCol}, 0) > 0 AND COALESCE(p.{$quantityCol}, 0) <= {$minQtyDefault}";
            // Only exclude items that are explicitly sold or swapped
            if ($hasStatus) {
                $lowStockCondition .= " AND (LOWER(p.status) != 'sold' AND LOWER(p.status) != 'swapped')";
            }
            $alertConditions[] = "({$lowStockCondition})";
            
            // Final WHERE clause: company_id AND (out_of_stock OR low_stock)
            $whereClause = "p.company_id = ? AND (" . implode(' OR ', $alertConditions) . ")";
            
            // Build SELECT with dynamic columns
            $selectCols = "p.name, COALESCE(p.{$quantityCol}, 0) as quantity, {$minQtyDefault} as min_quantity";
            
            // Handle brand - check if we have brand column or need to join brands table
            if ($hasBrand) {
                $selectCols .= ", p.brand";
            } elseif ($hasBrandId) {
                // Join with brands table to get brand name
                $selectCols .= ", COALESCE(b.name, '') as brand";
            } else {
                $selectCols .= ", NULL as brand";
            }
            
            $selectCols .= ($hasSupplier ? ", p.supplier" : ", NULL as supplier");
            
            // Build CASE statement for alert_type
            // Only consider items with quantity > 0 as out of stock (items with quantity = 0 are filtered out)
            $outOfStockCase = "FALSE"; // Default to false if no status column
            if ($hasStatus) {
                $outOfStockCase = "(COALESCE(p.{$quantityCol}, 0) > 0 AND (LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold'))";
            }
            
            // Build ORDER BY clause
            $orderByOutOfStock = "FALSE"; // Default to false if no status column
            if ($hasStatus) {
                $orderByOutOfStock = "(COALESCE(p.{$quantityCol}, 0) > 0 AND (LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold'))";
            }
            
            // Initialize alerts array
            $alerts = [];
            
            // Validate that we have all required variables before building query
            if (empty($tableName) || empty($quantityCol) || !$hasCompanyId) {
                error_log("Inventory alerts: Missing required variables - tableName=" . ($tableName ?? 'NULL') . ", quantityCol=" . ($quantityCol ?? 'NULL') . ", hasCompanyId=" . ($hasCompanyId ? 'yes' : 'no'));
                // Return empty results - $alerts is already set to []
            } else {
                // Build the SQL query - using validated column names
                // Add JOIN for brands if needed
                $joinClause = "";
                if ($hasBrandId && !$hasBrand) {
                    $joinClause = "LEFT JOIN brands b ON p.brand_id = b.id";
                }
                
                $sql = "
                SELECT 
                    {$selectCols},
                    CASE 
                        WHEN {$outOfStockCase} THEN 'out_of_stock'
                            WHEN COALESCE(p.{$quantityCol}, 0) > 0 AND COALESCE(p.{$quantityCol}, 0) <= {$minQtyDefault} THEN 'low_stock'
                        ELSE 'normal'
                    END as alert_type
                    FROM `{$tableName}` p
                    {$joinClause}
                WHERE {$whereClause}
                ORDER BY 
                    CASE WHEN {$orderByOutOfStock} THEN 0 ELSE 1 END,
                    COALESCE(p.{$quantityCol}, 0) ASC
                LIMIT 20
                ";
            
                error_log("=== INVENTORY ALERTS DEBUG ===");
                error_log("Inventory alerts SQL: " . str_replace(["\n", "\r", "  "], [" ", " ", " "], $sql));
                error_log("Inventory alerts params: company_id={$companyId}, table={$tableName}, quantityCol={$quantityCol}, hasStatus=" . ($hasStatus ? 'yes' : 'no') . ", hasBrand=" . ($hasBrand ? 'yes' : 'no') . ", hasBrandId=" . ($hasBrandId ? 'yes' : 'no'));
                error_log("WHERE clause: {$whereClause}");
                error_log("===============================");
                
                try {
                    $query = $db->prepare($sql);
                    
                    if ($query === false) {
                        $errorInfo = $db->errorInfo();
                        error_log("Failed to prepare inventory alerts query. PDO Error: " . print_r($errorInfo, true));
                        throw new \Exception("Failed to prepare SQL query: " . ($errorInfo[2] ?? 'Unknown database error'));
                    }
                    
                    $result = $query->execute([$companyId]);
                    if ($result === false) {
                        $errorInfo = $query->errorInfo();
                        error_log("Failed to execute inventory alerts query. PDO Error: " . print_r($errorInfo, true));
                        error_log("SQL Query was: " . $sql);
                        error_log("Company ID parameter: " . $companyId);
                        throw new \PDOException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown database error'));
                    }
                    
                $alerts = $query->fetchAll(\PDO::FETCH_ASSOC);
                    error_log("Found " . count($alerts) . " alert items from query");
                    
                    // Debug: Log sample of results if any found
                    if (count($alerts) > 0) {
                        error_log("Sample alert item: " . json_encode($alerts[0]));
                    } else {
                        // Debug: Check if there are any products at all for this company
                        $checkQuery = $db->prepare("SELECT COUNT(*) as total FROM `{$tableName}` WHERE company_id = ?");
                        $checkQuery->execute([$companyId]);
                        $totalProducts = $checkQuery->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                        error_log("Total products for company_id {$companyId}: {$totalProducts}");
                        
                        // Get ALL products to see their actual data
                        $sampleQuery = $db->prepare("SELECT name, {$quantityCol} as qty, status FROM `{$tableName}` WHERE company_id = ? ORDER BY {$quantityCol} ASC LIMIT 10");
                        $sampleQuery->execute([$companyId]);
                        $samples = $sampleQuery->fetchAll(\PDO::FETCH_ASSOC);
                        error_log("Sample products (sorted by quantity ASC): " . json_encode($samples));
                        
                        // Check for products with quantity <= 0
                        $checkOutOfStock = $db->prepare("SELECT COUNT(*) as total FROM `{$tableName}` WHERE company_id = ? AND COALESCE({$quantityCol}, 0) <= 0");
                        $checkOutOfStock->execute([$companyId]);
                        $outOfStockCount = $checkOutOfStock->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                        error_log("Products with quantity <= 0: {$outOfStockCount}");
                        
                        // Check for products with low stock (quantity > 0 and <= 5)
                        $checkLowStock = $db->prepare("SELECT COUNT(*) as total FROM `{$tableName}` WHERE company_id = ? AND COALESCE({$quantityCol}, 0) > 0 AND COALESCE({$quantityCol}, 0) <= 5");
                        $checkLowStock->execute([$companyId]);
                        $lowStockCount = $checkLowStock->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                        error_log("Products with low stock (1-5): {$lowStockCount}");
                        
                        // Get actual low stock items
                        $lowStockItemsQuery = $db->prepare("SELECT name, {$quantityCol} as qty, status FROM `{$tableName}` WHERE company_id = ? AND COALESCE({$quantityCol}, 0) > 0 AND COALESCE({$quantityCol}, 0) <= 5 LIMIT 5");
                        $lowStockItemsQuery->execute([$companyId]);
                        $lowStockSamples = $lowStockItemsQuery->fetchAll(\PDO::FETCH_ASSOC);
                        error_log("Actual low stock items: " . json_encode($lowStockSamples));
                        
                        // Get actual out of stock items
                        $outOfStockItemsQuery = $db->prepare("SELECT name, {$quantityCol} as qty, status FROM `{$tableName}` WHERE company_id = ? AND COALESCE({$quantityCol}, 0) <= 0 LIMIT 5");
                        $outOfStockItemsQuery->execute([$companyId]);
                        $outOfStockSamples = $outOfStockItemsQuery->fetchAll(\PDO::FETCH_ASSOC);
                        error_log("Actual out of stock items: " . json_encode($outOfStockSamples));
                        
                        // Check status values
                        if ($hasStatus) {
                            $statusQuery = $db->prepare("SELECT status, COUNT(*) as count FROM `{$tableName}` WHERE company_id = ? GROUP BY status");
                            $statusQuery->execute([$companyId]);
                            $statusCounts = $statusQuery->fetchAll(\PDO::FETCH_ASSOC);
                            error_log("Status distribution: " . json_encode($statusCounts));
                        }
                        
                        // Test the WHERE clause directly
                        $testQuery = $db->prepare("SELECT COUNT(*) as total FROM `{$tableName}` p {$joinClause} WHERE {$whereClause}");
                        $testQuery->execute([$companyId]);
                        $testCount = $testQuery->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                        error_log("Products matching WHERE clause: {$testCount}");
                        error_log("WHERE clause used: {$whereClause}");
                    }
            } catch (\PDOException $e) {
                    error_log("PDO Exception in inventory alerts query: " . $e->getMessage());
                    error_log("PDO Exception Code: " . $e->getCode());
                    if (isset($query) && $query) {
                        $errorInfo = $query->errorInfo();
                        error_log("PDO Error Info: " . print_r($errorInfo, true));
                    }
                    // Return empty results instead of throwing
                    error_log("Returning empty results due to query error");
                    $alerts = [];
                } catch (\Exception $e) {
                    error_log("Exception in inventory alerts query: " . $e->getMessage());
                    error_log("Exception trace: " . $e->getTraceAsString());
                    // Return empty results instead of throwing
                    error_log("Returning empty results due to exception");
                    $alerts = [];
                }
            }

            // Separate low stock and out of stock items
            $lowStockItems = [];
            $outOfStockItems = [];
            
            // Ensure $alerts is an array
            if (!is_array($alerts)) {
                error_log("Inventory alerts: \$alerts is not an array, type: " . gettype($alerts));
                $alerts = [];
            }
            
            foreach ($alerts as $alert) {
                if (!is_array($alert) || !isset($alert['name'])) {
                    error_log("Inventory alerts: Skipping invalid alert item: " . json_encode($alert));
                    continue;
                }
                
                $item = [
                    'name' => $alert['name'] ?? 'Unknown',
                    'quantity' => (int)($alert['quantity'] ?? 0),
                    'min_quantity' => (int)($alert['min_quantity'] ?? $minQtyDefault),
                    'brand' => $alert['brand'] ?? null,
                    'supplier' => $alert['supplier'] ?? null
                ];
                
                $alertType = $alert['alert_type'] ?? 'normal';
                if ($alertType === 'out_of_stock') {
                    $outOfStockItems[] = $item;
                } elseif ($alertType === 'low_stock') {
                    $lowStockItems[] = $item;
                }
            }

            $totalCount = count($lowStockItems) + count($outOfStockItems);

            // Clean output buffer and send response
            ob_end_clean();
            if (!headers_sent()) {
            header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => true,
                'low_stock' => $lowStockItems,
                'out_of_stock' => $outOfStockItems,
                'total_count' => $totalCount
            ]);
            exit;
        } catch (\Exception $e) {
            error_log("DashboardController inventoryAlerts error: " . $e->getMessage());
            error_log("DashboardController inventoryAlerts file: " . $e->getFile() . " line: " . $e->getLine());
            error_log("DashboardController inventoryAlerts trace: " . $e->getTraceAsString());
            
            // Return empty results instead of error to prevent dashboard breakage
            error_log("Returning empty results due to exception - dashboard will continue to function");
            
            // Clean any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
            header('Content-Type: application/json');
            }
            
            // Return success with empty results instead of error
            echo json_encode([
                'success' => true,
                'low_stock' => [],
                'out_of_stock' => [],
                'total_count' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Error $e) {
            error_log("DashboardController inventoryAlerts fatal error: " . $e->getMessage());
            error_log("DashboardController inventoryAlerts file: " . $e->getFile() . " line: " . $e->getLine());
            error_log("DashboardController inventoryAlerts trace: " . $e->getTraceAsString());
            
            // Return empty results instead of error to prevent dashboard breakage
            error_log("Returning empty results due to fatal error - dashboard will continue to function");
            
            // Clean any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
            header('Content-Type: application/json');
            }
            
            // Return success with empty results instead of error
            echo json_encode([
                'success' => true,
                'low_stock' => [],
                'out_of_stock' => [],
                'total_count' => 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    /**
     * Get staff performance for manager dashboard
     */
    public function staffPerformance() {
        $payload = AuthMiddleware::handle(['manager', 'admin']);
        $companyId = $payload->company_id;
        $db = \Database::getInstance()->getConnection();

        try {
            $query = $db->prepare("
            SELECT 
                    u.full_name as name,
                    u.role,
                    COUNT(ps.id) as sales
                FROM users u
                LEFT JOIN pos_sales ps ON u.id = ps.created_by_user_id AND DATE(ps.created_at) = CURDATE()
                WHERE u.company_id = ? AND u.role IN ('salesperson', 'technician')
                GROUP BY u.id, u.full_name, u.role
                ORDER BY sales DESC
                LIMIT 5
            ");
            $query->execute([$companyId]);
            $staff = $query->fetchAll();
        
        header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'staff' => $staff
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch staff performance',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get sales metrics for salesperson dashboard
     */
    public function salesMetrics() {
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set JSON header
        header('Content-Type: application/json');
        
        // Get user from session (already authenticated by WebAuthMiddleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $companyId = $user['company_id'] ?? null;
        $userId = $user['id'] ?? null;
        $userRole = $user['role'] ?? 'salesperson';
        
        if (!$companyId) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Company ID not found'
            ]);
            return;
        }
        
        $db = \Database::getInstance()->getConnection();
        
        try {
            // Managers and admins see all company sales, salespersons see only their own
            $filterByUser = !in_array($userRole, ['manager', 'admin', 'system_admin']);
            
            // Today's date
            $today = date('Y-m-d');
            
            // Build SQL and parameters based on user role
            if ($filterByUser && $userId) {
                // Salesperson - filter by user and exclude repair-related sales and swap transactions
                // Repair sales have notes like "Repair #X - Products sold by repairer"
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use swap_id IS NULL to exclude all swap-related sales
                $excludeRepairSales = " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))";
                $excludeSwapSales = " AND swap_id IS NULL";
                $todaySalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ?{$excludeRepairSales}{$excludeSwapSales}";
                $todayRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ?{$excludeRepairSales}{$excludeSwapSales}";
                $todayCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ? AND customer_id IS NOT NULL{$excludeRepairSales}{$excludeSwapSales}";
                $weekSalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) >= ?{$excludeRepairSales}{$excludeSwapSales}";
                $weekRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) >= ?{$excludeRepairSales}{$excludeSwapSales}";
                $totalCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND customer_id IS NOT NULL{$excludeRepairSales}{$excludeSwapSales}";
                
                $todayParams = [$companyId, $userId, $today];
                $weekParams = [$companyId, $userId, date('Y-m-d', strtotime('-7 days'))];
                $allParams = [$companyId, $userId];
            } else {
                // Manager/Admin - all company sales
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use swap_id IS NULL to exclude all swap-related sales
                $todaySalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ? AND swap_id IS NULL";
                $todayRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ? AND swap_id IS NULL";
                $todayCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ? AND customer_id IS NOT NULL AND swap_id IS NULL";
                $weekSalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) >= ? AND swap_id IS NULL";
                $weekRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND DATE(created_at) >= ? AND swap_id IS NULL";
                $totalCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND customer_id IS NOT NULL AND swap_id IS NULL";
                
                $todayParams = [$companyId, $today];
                $weekParams = [$companyId, date('Y-m-d', strtotime('-7 days'))];
                $allParams = [$companyId];
            }
            
            // Today's sales count
            $todaySalesQuery = $db->prepare($todaySalesSql);
            $todaySalesQuery->execute($todayParams);
            $todaySalesResult = $todaySalesQuery->fetch(\PDO::FETCH_ASSOC);
            $todaySales = $todaySalesResult ? (int)($todaySalesResult['count'] ?? 0) : 0;

            // Today's revenue
            $todayRevenueQuery = $db->prepare($todayRevenueSql);
            $todayRevenueQuery->execute($todayParams);
            $todayRevenueResult = $todayRevenueQuery->fetch(\PDO::FETCH_ASSOC);
            $todayRevenue = $todayRevenueResult ? (float)($todayRevenueResult['revenue'] ?? 0) : 0;

            // Today's customers
            $todayCustomersQuery = $db->prepare($todayCustomersSql);
            $todayCustomersQuery->execute($todayParams);
            $todayCustomersResult = $todayCustomersQuery->fetch(\PDO::FETCH_ASSOC);
            $todayCustomers = $todayCustomersResult ? (int)($todayCustomersResult['count'] ?? 0) : 0;

            // Week's sales count
            $weekSalesQuery = $db->prepare($weekSalesSql);
            $weekSalesQuery->execute($weekParams);
            $weekSalesResult = $weekSalesQuery->fetch(\PDO::FETCH_ASSOC);
            $weekSales = $weekSalesResult ? (int)($weekSalesResult['count'] ?? 0) : 0;

            // Week's revenue
            $weekRevenueQuery = $db->prepare($weekRevenueSql);
            $weekRevenueQuery->execute($weekParams);
            $weekRevenueResult = $weekRevenueQuery->fetch(\PDO::FETCH_ASSOC);
            $weekRevenue = $weekRevenueResult ? (float)($weekRevenueResult['revenue'] ?? 0) : 0;

            // Total customers
            $totalCustomersQuery = $db->prepare($totalCustomersSql);
            $totalCustomersQuery->execute($allParams);
            $totalCustomersResult = $totalCustomersQuery->fetch(\PDO::FETCH_ASSOC);
            $totalCustomers = $totalCustomersResult ? (int)($totalCustomersResult['count'] ?? 0) : 0;

            // Get monthly sold items count (current month)
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            if ($filterByUser && $userId) {
                $monthlyItemsSql = "SELECT COALESCE(SUM(psi.quantity), 0) as total_items
                                   FROM pos_sales ps
                                   INNER JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                                   WHERE ps.company_id = ? AND ps.created_by_user_id = ?
                                   AND DATE(ps.created_at) >= ? AND DATE(ps.created_at) <= ?
                                   AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))";
                $monthlyParams = [$companyId, $userId, $monthStart, $monthEnd];
            } else {
                $monthlyItemsSql = "SELECT COALESCE(SUM(psi.quantity), 0) as total_items
                                   FROM pos_sales ps
                                   INNER JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                                   WHERE ps.company_id = ?
                                   AND DATE(ps.created_at) >= ? AND DATE(ps.created_at) <= ?";
                $monthlyParams = [$companyId, $monthStart, $monthEnd];
            }
            
            $monthlyItemsQuery = $db->prepare($monthlyItemsSql);
            $monthlyItemsQuery->execute($monthlyParams);
            $monthlyItemsResult = $monthlyItemsQuery->fetch(\PDO::FETCH_ASSOC);
            $monthlySoldItems = $monthlyItemsResult ? (int)($monthlyItemsResult['total_items'] ?? 0) : 0;

            // Get payment statistics if partial payments module is enabled
            $paymentStats = null;
            $partialPaymentsCount = 0;
            $pendingPaymentsAmount = 0;
            
            if (CompanyModule::isEnabled($companyId, 'partial_payments')) {
                try {
                    $salePaymentModel = new \App\Models\SalePayment();
                    
                    // Get all partial and unpaid sales (not just today)
                    if ($filterByUser && $userId) {
                        $partialPaymentsSql = "SELECT ps.id, ps.final_amount, ps.payment_status
                                              FROM pos_sales ps
                                              WHERE ps.company_id = ? AND ps.created_by_user_id = ?
                                              AND (ps.payment_status = 'PARTIAL' OR ps.payment_status = 'UNPAID')
                                              AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))";
                        $partialParams = [$companyId, $userId];
                    } else {
                        $partialPaymentsSql = "SELECT ps.id, ps.final_amount, ps.payment_status
                                              FROM pos_sales ps
                                              WHERE ps.company_id = ?
                                              AND (ps.payment_status = 'PARTIAL' OR ps.payment_status = 'UNPAID')";
                        $partialParams = [$companyId];
                    }
                    
                    $partialPaymentsQuery = $db->prepare($partialPaymentsSql);
                    $partialPaymentsQuery->execute($partialParams);
                    $partialSales = $partialPaymentsQuery->fetchAll(\PDO::FETCH_ASSOC);
                    
                    $partialPaymentsCount = count($partialSales);
                    $pendingPaymentsAmount = 0;
                    
                    foreach ($partialSales as $sale) {
                        $paymentStatsForSale = $salePaymentModel->getPaymentStats($sale['id'], $companyId);
                        $pendingPaymentsAmount += $paymentStatsForSale['remaining'];
                    }
                    
                    // Get today's sales for payment stats
                    if ($filterByUser && $userId) {
                        $paymentStatsSql = "SELECT payment_status, COUNT(*) as count 
                                           FROM pos_sales 
                                           WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ?
                                           AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))
                                           GROUP BY payment_status";
                        $paymentParams = [$companyId, $userId, $today];
                    } else {
                        $paymentStatsSql = "SELECT payment_status, COUNT(*) as count 
                                           FROM pos_sales 
                                           WHERE company_id = ? AND DATE(created_at) = ?
                                           GROUP BY payment_status";
                        $paymentParams = [$companyId, $today];
                    }
                    
                    $paymentStatsQuery = $db->prepare($paymentStatsSql);
                    $paymentStatsQuery->execute($paymentParams);
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
                    
                    $paymentStats = [
                        'fully_paid' => $fullyPaid,
                        'partial' => $partial,
                        'unpaid' => $unpaid,
                        'total_partial_payments' => $partialPaymentsCount,
                        'pending_amount' => round($pendingPaymentsAmount, 2)
                    ];
                } catch (\Exception $e) {
                    error_log("DashboardController::salesMetrics - Error getting payment stats: " . $e->getMessage());
                }
            }
            
            $metrics = [
                'sales' => $todaySales,
                'revenue' => $todayRevenue,
                'customers' => $todayCustomers,
                'week_sales' => $weekSales,
                'week_revenue' => $weekRevenue,
                'total_customers' => $totalCustomers,
                'monthly_sold_items' => $monthlySoldItems
            ];
            
            if ($paymentStats) {
                $metrics['payment_stats'] = $paymentStats;
            }
        
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'metrics' => $metrics
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            ob_end_clean();
            error_log("DashboardController::salesMetrics error: " . $e->getMessage());
            error_log("DashboardController::salesMetrics trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch sales metrics',
                'message' => $e->getMessage()
            ]);
        } catch (\Error $e) {
            ob_end_clean();
            error_log("DashboardController::salesMetrics fatal error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch sales metrics',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get top products for salesperson dashboard
     */
    public function topProducts() {
        // Get user from session (already authenticated by WebAuthMiddleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $companyId = $user['company_id'];
        $userId = $user['id'];
        $db = \Database::getInstance()->getConnection();
        
        try {
            $query = $db->prepare("
                SELECT 
                    p.name,
                    c.name as category,
                    SUM(psi.quantity) as quantity,
                    SUM(psi.total_price) as revenue
                FROM pos_sale_items psi
                JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                JOIN products p ON psi.item_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE ps.company_id = ? AND ps.created_by_user_id = ? 
                AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY p.id, p.name, c.name
                ORDER BY quantity DESC
                LIMIT 5
            ");
            $query->execute([$companyId, $userId]);
            $products = $query->fetchAll();

            // Format revenue for display
            foreach ($products as &$product) {
                $product['revenue'] = number_format($product['revenue'], 2);
            }
        
        header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch top products',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * API endpoint: Get dashboard statistics
     */
    public function stats() {
        header('Content-Type: application/json');
        
        try {
            // Start session first for web requests
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Prioritize session-based auth for web requests
            $payload = null;
            $user = $_SESSION['user'] ?? null;
            
            if ($user && is_array($user) && !empty($user['company_id'])) {
                // User is authenticated via session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
            } else {
                // If no session, try JWT auth (for API calls)
                try {
                    $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin', 'salesperson', 'technician']);
                } catch (\Exception $e) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Authentication required',
                        'message' => 'Please login to access this resource'
                    ]);
                    return;
                }
            }
            
            $companyId = $payload->company_id ?? null;
            $role = $payload->role ?? 'salesperson';
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID not found'
                ]);
                return;
            }
            
            // Get basic stats based on user role
            if ($role === 'system_admin') {
                // System admin gets platform-wide stats
                $stats = $this->getPlatformStats();
            } elseif (in_array($role, ['manager', 'admin'])) {
                // Manager/Admin gets company-specific stats
                $stats = $this->getCompanyStats($companyId, $role);
            } else {
                // Salesperson/Technician gets limited stats (salesperson-specific metrics)
                $stats = $this->getSalespersonStats($companyId, $payload->id ?? null);
            }
        
            echo json_encode([
                'success' => true,
                'data' => $stats
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch dashboard statistics',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get salesperson-specific statistics
     */
    private function getSalespersonStats($companyId, $userId = null) {
        $db = \Database::getInstance()->getConnection();
        
        // Initialize default values
        $todayStats = ['count' => 0, 'revenue' => 0];
        $weekStats = ['count' => 0, 'revenue' => 0];
        $allTimeSalesStats = ['count' => 0, 'revenue' => 0];
        $swapStats = ['count' => 0, 'revenue' => 0];
        
        try {
            // Get user's own sales stats if userId provided (exclude repair-related sales and swap transactions)
            if ($userId) {
            // Exclude repair sales: notes like "Repair #X - Products sold by repairer"
            // EXCLUDE swap transactions - swaps should only be tracked on swap page
            // Use swap_id IS NULL to exclude all swap-related sales
            $excludeRepairSales = " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))";
            $excludeSwapSales = " AND swap_id IS NULL";
            
            // Today's sales
            $todaySalesQuery = $db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                FROM pos_sales 
                WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = CURDATE(){$excludeRepairSales}{$excludeSwapSales}
            ");
            $todaySalesQuery->execute([$companyId, $userId]);
            $todayStats = $todaySalesQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Week's sales
            $weekSalesQuery = $db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                FROM pos_sales 
                WHERE company_id = ? AND created_by_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY){$excludeRepairSales}{$excludeSwapSales}
            ");
            $weekSalesQuery->execute([$companyId, $userId]);
            $weekStats = $weekSalesQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Monthly sales (total sales and sales revenue for current month)
            $monthlySalesQuery = $db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                FROM pos_sales 
                WHERE company_id = ? AND created_by_user_id = ? 
                AND YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
                {$excludeRepairSales}{$excludeSwapSales}
            ");
            $monthlySalesQuery->execute([$companyId, $userId]);
            $monthlySalesStats = $monthlySalesQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Get swap stats - check which column exists for user reference
            $checkHandledBy = $db->query("SHOW COLUMNS FROM swaps LIKE 'handled_by'");
            $checkCreatedByUserId = $db->query("SHOW COLUMNS FROM swaps LIKE 'created_by_user_id'");
            $checkCreatedBy = $db->query("SHOW COLUMNS FROM swaps LIKE 'created_by'");
            $checkSalespersonId = $db->query("SHOW COLUMNS FROM swaps LIKE 'salesperson_id'");
            
            $hasHandledBy = $checkHandledBy && $checkHandledBy->rowCount() > 0;
            $hasCreatedByUserId = $checkCreatedByUserId && $checkCreatedByUserId->rowCount() > 0;
            $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->rowCount() > 0;
            $hasSalespersonId = $checkSalespersonId && $checkSalespersonId->rowCount() > 0;
            
            // Determine which column to use for user reference
            $userColumn = null;
            if ($hasSalespersonId) {
                $userColumn = 'salesperson_id';
            } elseif ($hasHandledBy) {
                $userColumn = 'handled_by';
            } elseif ($hasCreatedByUserId) {
                $userColumn = 'created_by_user_id';
            } elseif ($hasCreatedBy) {
                $userColumn = 'created_by';
            }
            
            // Get monthly swap stats if user column exists
            if ($userColumn) {
                // Check which columns exist for revenue calculation
                $checkFinalPrice = $db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
                $checkAddedCash = $db->query("SHOW COLUMNS FROM swaps LIKE 'added_cash'");
                $checkTotalValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
                $checkCashAdded = $db->query("SHOW COLUMNS FROM swaps LIKE 'cash_added'");
                
                $hasFinalPrice = $checkFinalPrice && $checkFinalPrice->rowCount() > 0;
                $hasAddedCash = $checkAddedCash && $checkAddedCash->rowCount() > 0;
                $hasTotalValue = $checkTotalValue && $checkTotalValue->rowCount() > 0;
                $hasCashAdded = $checkCashAdded && $checkCashAdded->rowCount() > 0;
                
                // Build revenue calculation for current month
                // Swap revenue should be:
                // - For non-resold swaps: cash top-up only (added_cash)
                // - For resold swaps: cash top-up + resale value (total_value, which includes both)
                $checkGivenPhoneValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'given_phone_value'");
                $hasGivenPhoneValue = $checkGivenPhoneValue && $checkGivenPhoneValue->rowCount() > 0;
                
                // Check if swapped_items table exists to check resale status
                $hasSwappedItems = false;
                try {
                    $check = $db->query("SHOW TABLES LIKE 'swapped_items'");
                    $hasSwappedItems = $check->rowCount() > 0;
                } catch (Exception $e) {
                    $hasSwappedItems = false;
                }
                
                if ($hasTotalValue) {
                    // Use total_value which should contain Cash Top-up + Resold Price
                    // total_value is calculated as: Cash Top-up (final_price - given_phone_value or added_cash) + Resold Price
                    $swapQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(total_value), 0) as revenue 
                        FROM swaps s
                        WHERE s.company_id = ? AND s.{$userColumn} = ?
                        AND YEAR(s.created_at) = YEAR(CURDATE()) 
                        AND MONTH(s.created_at) = MONTH(CURDATE())
                    ");
                } elseif ($hasFinalPrice && $hasGivenPhoneValue) {
                    // Calculate revenue as cash top-up: final_price - given_phone_value
                    $swapQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(GREATEST(0, final_price - COALESCE(given_phone_value, 0))), 0) as revenue 
                        FROM swaps 
                        WHERE company_id = ? AND {$userColumn} = ?
                        AND YEAR(created_at) = YEAR(CURDATE()) 
                        AND MONTH(created_at) = MONTH(CURDATE())
                    ");
                } elseif ($hasFinalPrice) {
                    // If given_phone_value doesn't exist, use final_price as fallback
                    // But this might include the full transaction value, not just cash top-up
                    $swapQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(final_price), 0) as revenue 
                        FROM swaps 
                        WHERE company_id = ? AND {$userColumn} = ?
                        AND YEAR(created_at) = YEAR(CURDATE()) 
                        AND MONTH(created_at) = MONTH(CURDATE())
                    ");
                } elseif ($hasAddedCash) {
                    // Fallback: Use added_cash directly (cash top-up only) if total_value doesn't exist
                    $swapQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(CASE WHEN added_cash > 0 THEN added_cash ELSE 0 END), 0) as revenue 
                        FROM swaps 
                        WHERE company_id = ? AND {$userColumn} = ?
                        AND YEAR(created_at) = YEAR(CURDATE()) 
                        AND MONTH(created_at) = MONTH(CURDATE())
                    ");
                } elseif ($hasCashAdded) {
                    // Use cash_added column
                    $swapQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(CASE WHEN cash_added > 0 THEN cash_added ELSE 0 END), 0) as revenue 
                        FROM swaps 
                        WHERE company_id = ? AND {$userColumn} = ?
                        AND YEAR(created_at) = YEAR(CURDATE()) 
                        AND MONTH(created_at) = MONTH(CURDATE())
                    ");
                } else {
                    // No revenue columns available
                    $swapQuery = $db->prepare("
                        SELECT COUNT(*) as count, 0 as revenue 
                        FROM swaps 
                        WHERE company_id = ? AND {$userColumn} = ?
                        AND YEAR(created_at) = YEAR(CURDATE()) 
                        AND MONTH(created_at) = MONTH(CURDATE())
                    ");
                }
                
                $swapQuery->execute([$companyId, $userId]);
                $swapStats = $swapQuery->fetch(\PDO::FETCH_ASSOC);
            }
        }
        } catch (\Exception $e) {
            error_log("DashboardController::getSalespersonStats - Error: " . $e->getMessage());
            error_log("DashboardController::getSalespersonStats - Trace: " . $e->getTraceAsString());
        }
        
        return [
            'total_revenue' => floatval($todayStats['revenue'] ?? 0),
            'total_sales' => intval($todayStats['count'] ?? 0),
            'week_revenue' => floatval($weekStats['revenue'] ?? 0),
            'week_sales' => intval($weekStats['count'] ?? 0),
            'total_customers' => 0,
            'total_phones' => 0,
            // Monthly metrics for dashboard cards (current month only)
            'all_time_total_sales' => intval($monthlySalesStats['count'] ?? 0),
            'all_time_sales_revenue' => floatval($monthlySalesStats['revenue'] ?? 0),
            'total_swaps' => intval($swapStats['count'] ?? 0),
            'swap_revenue' => floatval($swapStats['revenue'] ?? 0)
        ];
    }

    /**
     * Get platform-wide statistics
     */
    private function getPlatformStats() {
        $db = \Database::getInstance()->getConnection();
        
        // Get total companies
        $companiesQuery = $db->query("SELECT COUNT(*) as total FROM companies");
        $totalCompanies = $companiesQuery->fetch()['total'] ?? 0;
        
        // Get total sales
        $salesQuery = $db->query("SELECT COUNT(*) as total, SUM(final_amount) as revenue FROM pos_sales");
        $salesData = $salesQuery->fetch();
        $totalSales = $salesData['total'] ?? 0;
        $totalRevenue = $salesData['revenue'] ?? 0;
        
        // Get total repairs
        $repairsQuery = $db->query("SELECT COUNT(*) as total FROM repairs");
        $totalRepairs = $repairsQuery->fetch()['total'] ?? 0;
        
        return [
            'total_companies' => (int)$totalCompanies,
            'total_sales' => (int)$totalSales,
            'total_revenue' => (float)$totalRevenue,
            'total_repairs' => (int)$totalRepairs
        ];
    }

    /**
     * Get comprehensive manager dashboard statistics
     * Returns all stats for swaps, repairs, products, financial, and staff
     */
    public function managerOverview() {
        // Clean any existing output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Suppress PHP warnings for clean JSON output
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        ini_set('display_errors', 0);
        
        // Set JSON header early
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $companyId = null;
            $role = null;
            
            // Try Authorization header first (Bearer token)
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    
                    // Check role-based access
                    if (!in_array($payload->role, ['manager', 'admin', 'system_admin'], true)) {
                        throw new \Exception('Unauthorized role');
                    }
                    
                    $companyId = $payload->company_id ?? null;
                    $role = $payload->role;
                } catch (\Exception $e) {
                    // Token validation failed, try session fallback
                    error_log("Token validation failed in managerOverview: " . $e->getMessage());
                }
            }
            
            // Fallback to session-based user if header missing/invalid
            if ($companyId === null) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $user = $_SESSION['user'] ?? null;
                if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'admin', 'system_admin'], true)) {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    return;
                }
                
                $companyId = $user['company_id'] ?? null;
                $role = $user['role'] ?? 'manager';
            }
            
            if (!$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID is required']);
                return;
            }
            
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stats = $this->getComprehensiveManagerStats($companyId, $dateFrom, $dateTo);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => $stats
            ], JSON_NUMERIC_CHECK);
        } catch (\Exception $e) {
            ob_end_clean();
            error_log("DashboardController managerOverview error: " . $e->getMessage());
            error_log("DashboardController managerOverview trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch manager overview',
                'message' => $e->getMessage()
            ]);
        } catch (\Error $e) {
            ob_end_clean();
            error_log("DashboardController managerOverview fatal error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get comprehensive manager statistics
     */
    private function getComprehensiveManagerStats($companyId, $dateFrom = null, $dateTo = null) {
        if (!$companyId) {
            throw new \Exception('Company ID is required');
        }
        
        // Default to last 30 days if not specified
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-30 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        // 1. OVERVIEW METRICS
        try {
            $overview = $this->getOverviewMetrics($companyId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            error_log("Error getting overview metrics: " . $e->getMessage());
            $overview = [
                'total_products' => 0,
                'today_sales' => 0,
                'today_swaps' => 0,
                'today_repairs' => 0,
                'total_customers' => 0,
                'active_staff' => 0,
                'trends' => ['sales' => 0]
            ];
        }
        
        // 2. SWAP STATISTICS
        try {
            $swapStats = $this->getSwapStatistics($companyId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            error_log("Error getting swap statistics: " . $e->getMessage());
            $swapStats = [
                'total_swaps' => 0,
                'pending' => 0,
                'completed' => 0,
                'resold' => 0,
                'total_value' => 0,
                'total_profit' => 0,
                'estimated_profit' => 0,
                'in_stock_items' => 0,
                'sold_items' => 0,
                'recent_swaps' => [],
                'top_brands' => []
            ];
        }
        
        // 3. REPAIR STATISTICS
        try {
            $repairStats = $this->getRepairStatistics($companyId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            error_log("Error getting repair statistics: " . $e->getMessage());
            $repairStats = [
                'total' => 0,
                'pending' => 0,
                'ongoing' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'revenue' => 0,
                'recent_repairs' => []
            ];
        }
        
        // 4. PRODUCT/INVENTORY STATISTICS
        try {
            $productStats = $this->getProductStatistics($companyId);
        } catch (\Exception $e) {
            error_log("Error getting product statistics: " . $e->getMessage());
            $productStats = [
                'total_products' => 0,
                'available_products' => 0,
                'out_of_stock' => 0,
                'total_quantity' => 0,
                'inventory_value' => 0,
                'swap_available' => 0,
                'swapped_items_count' => 0,
                'best_products' => []
            ];
        }
        
        // 5. FINANCIAL SUMMARY
        try {
            // Use swap profit from swapStats (already calculated correctly)
            // getFinancialSummary() already calculates profit as Revenue - Cost, matching audit trail
            $financialStats = $this->getFinancialSummary($companyId, $dateFrom, $dateTo, $swapStats);
            
            error_log("Dashboard: FinancialStats from getFinancialSummary: " . json_encode($financialStats));
            error_log("Dashboard: Profit calculated as Revenue - Cost: ₵{$financialStats['total_revenue']} - ₵{$financialStats['total_cost']} = ₵{$financialStats['total_profit']}");
        } catch (\Exception $e) {
            error_log("Error getting financial summary: " . $e->getMessage());
            $financialStats = [
                'total_revenue' => 0,
                'sales_revenue' => 0,
                'swap_profit' => 0,
                'repair_revenue' => 0,
                'total_profit' => 0,
                'profit_margin' => 0
            ];
        }
        
        // 6. STAFF PERFORMANCE
        try {
            $staffStats = $this->getStaffPerformance($companyId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            error_log("Error getting staff performance: " . $e->getMessage());
            $staffStats = [];
        }
        
        // 7. SALES STATISTICS (for transaction count)
        try {
            $salesStats = $this->getSalesStatistics($companyId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            error_log("Error getting sales statistics: " . $e->getMessage());
            $salesStats = [
                'total_sales' => 0,
                'total_transactions' => 0,
                'sales_count' => 0
            ];
        }
        
        // 8. SMS BALANCE & USAGE
        try {
            $smsData = $this->getSMSBalanceData($companyId);
        } catch (\Exception $e) {
            error_log("Error getting SMS balance data: " . $e->getMessage());
            $smsData = [
                'total_sms' => 0,
                'sms_used' => 0,
                'sms_remaining' => 0,
                'usage_percent' => 0,
                'status' => 'unknown',
                'recent_logs' => []
            ];
        }
        
        return [
            'overview' => $overview,
            'swaps' => $swapStats,
            'repairs' => $repairStats,
            'products' => $productStats,
            'financial' => $financialStats,
            'sales' => $salesStats,
            'staff' => $staffStats,
            'sms' => $smsData,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Get sales statistics for the date range
     */
    private function getSalesStatistics($companyId, $dateFrom = null, $dateTo = null) {
        if (!$companyId) {
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'sales_count' => 0
            ];
        }
        
        $db = \Database::getInstance()->getConnection();
        
        // Default to last 30 days if not specified
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-30 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        try {
            // Count total sales transactions within date range
            $salesQuery = $db->prepare("
                SELECT COUNT(*) as total_sales
                FROM pos_sales 
                WHERE company_id = ? 
                AND DATE(created_at) BETWEEN ? AND ?
            ");
            $salesQuery->execute([$companyId, $dateFrom, $dateTo]);
            $salesData = $salesQuery->fetch(\PDO::FETCH_ASSOC);
            
            $totalSales = (int)($salesData['total_sales'] ?? 0);
            
            return [
                'total_sales' => $totalSales,
                'total_transactions' => $totalSales,
                'sales_count' => $totalSales
            ];
        } catch (\Exception $e) {
            error_log("Error in getSalesStatistics: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'sales_count' => 0
            ];
        }
    }
    
    /**
     * Get SMS balance and usage data for company
     */
    private function getSMSBalanceData($companyId) {
        try {
            $smsAccountModel = new \App\Models\CompanySMSAccount();
            $balance = $smsAccountModel->getSMSBalance($companyId);
            
            if (!$balance['success']) {
                return [
                    'total_sms' => 0,
                    'sms_used' => 0,
                    'sms_remaining' => 0,
                    'usage_percent' => 0,
                    'status' => 'unknown',
                    'recent_logs' => []
                ];
            }
            
            // Get recent SMS logs
            $db = \Database::getInstance()->getConnection();
            $logs = [];
            
            try {
                // Check if sms_logs table exists
                $checkTable = $db->query("SHOW TABLES LIKE 'sms_logs'");
                if ($checkTable->rowCount() > 0) {
                    $stmt = $db->prepare("
                        SELECT 
                            message_type,
                            recipient,
                            message,
                            status,
                            sender_id,
                            sent_at
                        FROM sms_logs 
                        WHERE company_id = ? 
                        ORDER BY sent_at DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$companyId]);
                    $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            } catch (\Exception $e) {
                error_log("Error fetching SMS logs: " . $e->getMessage());
            }
            
            // Calculate monetary balance based on remaining SMS
            // Balance = Remaining SMS * Rate per SMS
            $smsCreditRate = 0.05891; // Default rate (38 GHS / 645 messages)
            try {
                $rateStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_credit_rate'");
                $rateStmt->execute();
                $rateValue = $rateStmt->fetchColumn();
                if ($rateValue) {
                    $smsCreditRate = (float)$rateValue;
                }
            } catch (\Exception $e) {
                error_log("Error fetching SMS credit rate: " . $e->getMessage());
            }
            
            $monetaryBalance = $balance['sms_remaining'] * $smsCreditRate;
            
            return [
                'total_sms' => $balance['total_sms'],
                'sms_used' => $balance['sms_used'],
                'sms_remaining' => $balance['sms_remaining'],
                'usage_percent' => $balance['usage_percent'],
                'status' => $balance['status'],
                'recent_logs' => $logs,
                'sms_credit_rate' => $smsCreditRate,
                'monetary_balance' => $monetaryBalance
            ];
        } catch (\Exception $e) {
            error_log("Error getting SMS balance data: " . $e->getMessage());
            return [
                'total_sms' => 0,
                'sms_used' => 0,
                'sms_remaining' => 0,
                'usage_percent' => 0,
                'status' => 'unknown',
                'recent_logs' => []
            ];
        }
    }
    
    /**
     * Get overview metrics (top summary cards)
     */
    private function getOverviewMetrics($companyId, $dateFrom, $dateTo) {
        $db = \Database::getInstance()->getConnection();
        
        // Total Products - check if status column exists
        try {
            $checkStatusCol = $db->query("SHOW COLUMNS FROM products LIKE 'status'");
            $hasStatus = $checkStatusCol->rowCount() > 0;
            
            if ($hasStatus) {
                $productsQuery = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND status = 'available'");
            } else {
                $productsQuery = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
            }
            $productsQuery->execute([$companyId]);
            $totalProducts = $productsQuery->fetch()['total'] ?? 0;
        } catch (\Exception $e) {
            error_log("Error getting total products: " . $e->getMessage());
            $totalProducts = 0;
        }
        
        // Total Sales Today
        // EXCLUDE swap transactions - swaps should only be tracked on swap page
        // Use swap_id IS NULL to exclude all swap-related sales
        $todaySalesQuery = $db->prepare("
            SELECT COALESCE(SUM(final_amount), 0) as total 
            FROM pos_sales 
            WHERE company_id = ? AND DATE(created_at) = CURDATE()
            AND swap_id IS NULL
        ");
        $todaySalesQuery->execute([$companyId]);
        $todaySales = $todaySalesQuery->fetch()['total'] ?? 0;
        
        // Total Swaps Today
        $todaySwapsQuery = $db->prepare("
            SELECT COUNT(*) as total 
            FROM swaps 
            WHERE company_id = ? AND DATE(created_at) = CURDATE()
        ");
        $todaySwapsQuery->execute([$companyId]);
        $todaySwaps = $todaySwapsQuery->fetch()['total'] ?? 0;
        
        // Total Repairs Today
        try {
            $todayRepairsQuery = $db->prepare("
                SELECT COUNT(*) as total 
                FROM repairs_new 
                WHERE company_id = ? AND DATE(created_at) = CURDATE()
            ");
            $todayRepairsQuery->execute([$companyId]);
            $todayRepairs = $todayRepairsQuery->fetch()['total'] ?? 0;
        } catch (\Exception $e) {
            $todayRepairs = 0;
        }
        
        // Total Customers
        $customersQuery = $db->prepare("SELECT COUNT(DISTINCT id) as total FROM customers WHERE company_id = ?");
        $customersQuery->execute([$companyId]);
        $totalCustomers = $customersQuery->fetch()['total'] ?? 0;
        
        // Active Staff
        $staffQuery = $db->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE company_id = ? AND role IN ('salesperson', 'technician', 'manager')
        ");
        $staffQuery->execute([$companyId]);
        $activeStaff = $staffQuery->fetch()['total'] ?? 0;
        
        // Get previous day for trends
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // EXCLUDE swap transactions - swaps should only be tracked on swap page
        // Use swap_id IS NULL to exclude all swap-related sales
        $yesterdaySalesQuery = $db->prepare("
            SELECT COALESCE(SUM(final_amount), 0) as total 
            FROM pos_sales 
            WHERE company_id = ? AND DATE(created_at) = ?
            AND swap_id IS NULL
        ");
        $yesterdaySalesQuery->execute([$companyId, $yesterday]);
        $yesterdaySales = $yesterdaySalesQuery->fetch()['total'] ?? 0;
        
        return [
            'total_products' => (int)$totalProducts,
            'today_sales' => (float)$todaySales,
            'today_swaps' => (int)$todaySwaps,
            'today_repairs' => (int)$todayRepairs,
            'total_customers' => (int)$totalCustomers,
            'active_staff' => (int)$activeStaff,
            'trends' => [
                'sales' => $yesterdaySales > 0 ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 : 0
            ]
        ];
    }
    
    /**
     * Get swap statistics
     */
    private function getSwapStatistics($companyId, $dateFrom, $dateTo) {
        $db = \Database::getInstance()->getConnection();
        
        // Get swap counts by status - use same logic as SwapController
        // MUST match the logic in SwapController::index() and swaps_index.php view
        try {
            // Use Swap model to fetch all swaps with swapped_items info (same as SwapController)
            // Note: findByCompany doesn't filter by date, so we'll filter after fetching
            $swapModel = new \App\Models\Swap();
            $allSwapsRaw = $swapModel->findByCompany($companyId, 1000, null);
            
            // Note: We don't filter by date range for swap statistics
            // Swap profit is calculated based on when both items are sold (realized)
            // The date range filtering is applied only for display purposes, not for profit calculation
            // This ensures we capture all realized profits regardless of swap creation date
            
            // Deduplicate swaps by ID (same logic as SwapController)
            $allSwaps = [];
            $seenSwapIds = [];
            foreach ($allSwapsRaw as $swap) {
                $swapId = $swap['id'] ?? null;
                if ($swapId && !isset($seenSwapIds[$swapId])) {
                    $seenSwapIds[$swapId] = true;
                    $allSwaps[] = $swap;
                } elseif (!$swapId) {
                    $allSwaps[] = $swap;
                } else {
                    // Duplicate swap ID - merge resale_status
                    $existingIdx = array_search($swapId, array_column($allSwaps, 'id'));
                    if ($existingIdx !== false) {
                        $existingResaleStatus = $allSwaps[$existingIdx]['resale_status'] ?? null;
                        $newResaleStatus = $swap['resale_status'] ?? null;
                        if ($newResaleStatus === 'sold' && $existingResaleStatus !== 'sold') {
                            $allSwaps[$existingIdx]['resale_status'] = 'sold';
                        } elseif ($newResaleStatus === 'in_stock' && $existingResaleStatus === null) {
                            $allSwaps[$existingIdx]['resale_status'] = 'in_stock';
                        }
                    }
                }
            }
            
            // Count status using same logic as SwapController (matches view display)
            $statusStats = ['total' => count($allSwaps), 'pending' => 0, 'completed' => 0, 'resold' => 0];
            
            foreach ($allSwaps as $s) {
                $resaleStatus = $s['resale_status'] ?? null;
                // Check both status and swap_status columns
                $swapStatus = $s['swap_status'] ?? $s['status'] ?? 'pending';
                $swapStatusLower = strtolower($swapStatus);
                
                // Same logic as SwapController::index() - MUST MATCH VIEW DISPLAY
                if ($resaleStatus === 'sold') {
                    $statusStats['resold']++;
                } elseif ($resaleStatus === 'in_stock') {
                    $statusStats['completed']++;
                } elseif ($swapStatusLower === 'resold') {
                    $statusStats['resold']++;
                } elseif ($swapStatusLower === 'completed') {
                    $statusStats['completed']++;
                } else {
                    $statusStats['pending']++;
                }
            }
            
            // Calculate stats from all swaps - use same logic as SwapController
            // Calculate total_value, in_stock items, profit from the same $allSwaps array
            $totalValue = 0;
            $inStockItems = 0;
            $inStockValue = 0;
            $calculatedFinalProfit = 0;
            $calculatedEstimatedProfit = 0;
            $calculatedLoss = 0;
            $calculatedFinalCount = 0;
            $calculatedEstimatedCount = 0;
            
            foreach ($allSwaps as $s) {
                // Total value calculation - use same logic as SwapController
                // Get added_cash - check multiple possible column names
                $addedCash = 0;
                if (isset($s['added_cash']) && $s['added_cash'] !== null && $s['added_cash'] !== 'NULL' && floatval($s['added_cash']) > 0) {
                    $addedCash = floatval($s['added_cash']);
                } elseif (isset($s['cash_added']) && $s['cash_added'] !== null && $s['cash_added'] !== 'NULL' && floatval($s['cash_added']) > 0) {
                    $addedCash = floatval($s['cash_added']);
                }
                
                // If added_cash is still 0, calculate it from the difference (same as SwapController)
                if ($addedCash == 0 || $addedCash <= 0) {
                    $totalValueFromDb = floatval($s['total_value'] ?? 0);
                    $companyProductPrice = floatval($s['company_product_price'] ?? 0);
                    $customerProductValue = floatval($s['customer_product_value'] ?? 0);
                    
                    $baseValue = $totalValueFromDb > 0 ? $totalValueFromDb : $companyProductPrice;
                    
                    if ($baseValue > 0 && $customerProductValue > 0 && $baseValue > $customerProductValue) {
                        $calculatedAddedCash = $baseValue - $customerProductValue;
                        if ($calculatedAddedCash > 0) {
                            $addedCash = $calculatedAddedCash;
                        }
                    }
                }
                
                // Calculate total_value using same logic as SwapController
                $dbTotalValue = floatval($s['total_value'] ?? 0);
                $resaleStatus = $s['resale_status'] ?? null;
                $swapStatus = $s['swap_status'] ?? $s['status'] ?? 'pending';
                $isResold = ($resaleStatus === 'sold' || strtolower($swapStatus) === 'resold');
                
                // If swap is resold, total_value should include both added_cash and resale value
                // If swap is not resold, total_value should only be added_cash
                if ($isResold) {
                    // For resold swaps, use total_value as-is (it should already include resale value)
                    $totalValue += $dbTotalValue;
                } else {
                    // For non-resold swaps, use added_cash (cash top-up only)
                    // If total_value is much larger than added_cash, it's probably an old swap with wrong value
                    if ($dbTotalValue > 0 && $addedCash > 0 && $dbTotalValue > ($addedCash * 1.5)) {
                        // Old swap format - use added_cash instead
                        $totalValue += $addedCash;
                    } else {
                        // Use total_value if it seems correct, otherwise use added_cash
                        $totalValue += ($dbTotalValue > 0 ? $dbTotalValue : $addedCash);
                    }
                }
                
                // In stock items (from resale_status === 'in_stock')
                if ($resaleStatus === 'in_stock') {
                    $inStockItems++;
                    $inStockValue += floatval($s['resell_price'] ?? $s['customer_product_value'] ?? 0);
                }
                
                // Profit calculation - same logic as SwapController
                $profitEstimate = isset($s['profit_estimate']) && $s['profit_estimate'] !== null ? floatval($s['profit_estimate']) : null;
                $profitFinal = isset($s['final_profit']) && $s['final_profit'] !== null ? floatval($s['final_profit']) : null;
                $profitStatus = $s['profit_status'] ?? null;
                // Check both status and swap_status columns (already set above, but ensure consistency)
                $swapStatus = $s['swap_status'] ?? $s['status'] ?? 'pending';
                $swapStatusLower = strtolower($swapStatus);
                $isResold = ($resaleStatus === 'sold' || $swapStatusLower === 'resold');
                
                // Check if both sales are linked by querying swap_profit_links directly
                // (findByCompany doesn't include these fields)
                $hasCompanySaleId = false;
                $hasCustomerSaleId = false;
                try {
                    $checkSaleIds = $db->prepare("
                        SELECT company_item_sale_id, customer_item_sale_id, status, final_profit
                        FROM swap_profit_links 
                        WHERE swap_id = ?
                        LIMIT 1
                    ");
                    $checkSaleIds->execute([$s['id']]);
                    $saleIdsCheck = $checkSaleIds->fetch(\PDO::FETCH_ASSOC);
                    if ($saleIdsCheck) {
                        $hasCompanySaleId = !empty($saleIdsCheck['company_item_sale_id']);
                        $hasCustomerSaleId = !empty($saleIdsCheck['customer_item_sale_id']);
                        // Use status and final_profit from swap_profit_links if available (more accurate)
                        if (!empty($saleIdsCheck['status'])) {
                            $profitStatus = $saleIdsCheck['status'];
                        }
                        if (!empty($saleIdsCheck['final_profit']) && $profitFinal === null) {
                            $profitFinal = floatval($saleIdsCheck['final_profit']);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("DashboardController: Error checking sale IDs for swap #{$s['id']}: " . $e->getMessage());
                }
                
                $bothItemsSold = $hasCompanySaleId && $hasCustomerSaleId;
                
                // Debug logging for specific swap
                if ($s['id'] == 20252941 || $profitEstimate > 0) {
                    error_log("DashboardController getSwapStatistics: Swap #{$s['id']} - Estimate: " . ($profitEstimate ?? 'null') . ", Final: " . ($profitFinal ?? 'null') . ", Status: " . ($profitStatus ?? 'null') . ", BothSold: " . ($bothItemsSold ? 'yes' : 'no') . ", HasCustomerSale: " . ($hasCustomerSaleId ? 'yes' : 'no'));
                }
                
                if ($bothItemsSold) {
                    // Both items sold - profit is realized (customer_item_sale_id exists)
                    // Only count profit when customer item has been resold AND profit is finalized
                    if ($hasCustomerSaleId) {
                        if ($profitFinal === null || $profitStatus !== 'finalized') {
                            try {
                                $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                                $calculatedProfit = $swapProfitLinkModel->calculateSwapProfit($s['id']);
                                if ($calculatedProfit !== null) {
                                    $profitFinal = $calculatedProfit;
                                    $profitStatus = 'finalized';
                                }
                            } catch (\Exception $e) {
                                error_log("DashboardController: Error calculating profit for swap #{$s['id']}: " . $e->getMessage());
                                // Do not use estimate as fallback - only count finalized profits
                            }
                        }
                        
                        // Only count finalized profits - never use estimates as realized gains
                        if ($profitFinal !== null && $profitStatus === 'finalized') {
                            $profitToUse = $profitFinal;
                            $calculatedFinalProfit += $profitToUse;
                            $calculatedFinalCount++;
                            
                            if ($profitToUse < 0) {
                                $calculatedLoss += abs($profitToUse);
                            }
                        }
                        // If profit is not finalized, don't count it (even if both items are sold)
                    }
                    // If both items are not sold (customer_item_sale_id doesn't exist), don't count profit
                } elseif ($isResold) {
                    // Item is resold - profit is realized (matches view logic and SwapController)
                    // Check swap_profit_links table if final_profit column doesn't exist in swaps
                    if ($profitFinal === null) {
                        // Try to get profit from swap_profit_links table
                        try {
                            $profitLinkQuery = $db->prepare("
                                SELECT final_profit, profit_estimate, status as profit_status, 
                                       company_item_sale_id, customer_item_sale_id,
                                       company_product_cost, customer_phone_value, amount_added_by_customer
                                FROM swap_profit_links
                                WHERE swap_id = ?
                                LIMIT 1
                            ");
                            $profitLinkQuery->execute([$s['id']]);
                            $profitLink = $profitLinkQuery->fetch(\PDO::FETCH_ASSOC);
                            if ($profitLink) {
                                $profitFinal = isset($profitLink['final_profit']) && $profitLink['final_profit'] !== null ? floatval($profitLink['final_profit']) : null;
                                // If no final_profit but have profit_estimate, use it (matches view logic)
                                if ($profitFinal === null && isset($profitLink['profit_estimate']) && $profitLink['profit_estimate'] !== null) {
                                    $profitFinal = floatval($profitLink['profit_estimate']);
                                }
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
                                        $sale = $saleQuery->fetch(\PDO::FETCH_ASSOC);
                                        if ($sale) {
                                            $sellingPrice = floatval($sale['final_amount'] ?? 0);
                                            $costPrice = floatval($profitLink['company_product_cost'] ?? 0);
                                            // Profit = Selling Price - Cost Price
                                            $profitFinal = $sellingPrice - $costPrice;
                                            error_log("DashboardController getSwapStatistics: Calculated profit for swap #{$s['id']} from sale: Selling Price ₵{$sellingPrice} - Cost ₵{$costPrice} = ₵{$profitFinal}");
                                        }
                                    } catch (\Exception $e) {
                                        error_log("DashboardController getSwapStatistics: Error calculating profit from sale for swap #{$s['id']}: " . $e->getMessage());
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("DashboardController getSwapStatistics: Error fetching profit from swap_profit_links for swap #{$s['id']}: " . $e->getMessage());
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
                            error_log("DashboardController getSwapStatistics: Error calculating profit for swap #{$s['id']}: " . $e->getMessage());
                        }
                    }
                    
                    // If we have final profit (from swaps table, swap_profit_links, calculated, or estimate), count it
                    // Matches view logic: if resold and (profitFinal OR profitEstimate), show as realized
                    if ($profitFinal !== null) {
                        $profitToUse = $profitFinal;
                        $calculatedFinalProfit += $profitToUse;
                        $calculatedFinalCount++;
                        if ($profitToUse < 0) {
                            $calculatedLoss += abs($profitToUse);
                        }
                    } elseif ($profitEstimate !== null) {
                        // If no final profit but have estimate and item is resold, use estimate (matches view logic)
                        $profitToUse = $profitEstimate;
                        $calculatedFinalProfit += $profitToUse;
                        $calculatedFinalCount++;
                        if ($profitToUse < 0) {
                            $calculatedLoss += abs($profitToUse);
                        }
                        error_log("DashboardController getSwapStatistics: Using profit_estimate ₵{$profitEstimate} for resold swap #{$s['id']} (matches view logic)");
                    }
                } elseif ($profitStatus === 'finalized' && $hasCustomerSaleId) {
                    // Profit is finalized and customer item has been resold
                    if ($profitFinal !== null) {
                        $profitToUse = $profitFinal;
                        $calculatedFinalProfit += $profitToUse;
                        $calculatedFinalCount++;
                        if ($profitToUse < 0) {
                            $calculatedLoss += abs($profitToUse);
                        }
                    }
                }
            }
            
            // Get recent swaps - use the same $allSwaps array that we already have
            // This ensures we're using the exact same data source as the stats
            $recentSwaps = [];
            
            // Use the $allSwaps array we already fetched and processed
            // Sort by created_at or swap_date (most recent first) and take first 10
            if (!empty($allSwaps)) {
                // Determine sort key
                $sortKey = 'created_at';
                if (isset($allSwaps[0]['swap_date'])) {
                    $sortKey = 'swap_date';
                }
                
                // Sort swaps by date (most recent first)
                usort($allSwaps, function($a, $b) use ($sortKey) {
                    $dateA = strtotime($a[$sortKey] ?? $a['created_at'] ?? '1970-01-01');
                    $dateB = strtotime($b[$sortKey] ?? $b['created_at'] ?? '1970-01-01');
                    return $dateB - $dateA; // Descending order
                });
                
                // Take first 10 swaps
                $recentSwaps = array_slice($allSwaps, 0, 10);
                
                // Format for frontend - ensure all required fields are present
                foreach ($recentSwaps as &$swap) {
                    // Ensure transaction_code is set
                    if (empty($swap['transaction_code'])) {
                        $swap['transaction_code'] = 'SWP-' . ($swap['id'] ?? '');
                    }
                    
                    // Ensure customer_name is set
                    if (empty($swap['customer_name']) && empty($swap['customer_name_from_table'])) {
                        $swap['customer_name'] = 'Walk-in';
                    } elseif (!empty($swap['customer_name_from_table'])) {
                        $swap['customer_name'] = $swap['customer_name_from_table'];
                    }
                }
                unset($swap); // Break reference
                
                error_log("Recent swaps: Using {$statusStats['total']} total swaps, returning " . count($recentSwaps) . " recent swaps for company {$companyId}");
            } else {
                error_log("Recent swaps: No swaps found in \$allSwaps array for company {$companyId}");
                $recentSwaps = [];
            }
        } catch (\Exception $e) {
            error_log("Error getting swap status stats: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            $statusStats = ['total' => 0, 'pending' => 0, 'completed' => 0, 'resold' => 0];
            $totalValue = 0;
            $inStockItems = 0;
            $inStockValue = 0;
            $calculatedFinalProfit = 0;
            $calculatedEstimatedProfit = 0;
            $calculatedLoss = 0;
            $recentSwaps = [];
        }
        
        // Get swap model stats for total_cash_received
        $swapModelStats = ['total_cash_received' => 0];
        try {
            $swapModel = new \App\Models\Swap();
            $swapModelStats = $swapModel->getStats($companyId);
        } catch (\Exception $e) {
            error_log("Error getting swap model stats: " . $e->getMessage());
        }
        
        // Get swapped items stats for additional info
        $swappedItemsStats = ['total_items' => 0, 'sold_items' => 0];
        try {
            $swappedItemModel = new \App\Models\SwappedItem();
            $swappedItemsStats = $swappedItemModel->getStats($companyId);
        } catch (\Exception $e) {
            error_log("Error getting swapped items stats: " . $e->getMessage());
        }
            
        // Get top swapped brands - handle missing table
        $topBrands = [];
        try {
            $checkSwappedItemsTable = $db->query("SHOW TABLES LIKE 'swapped_items'");
            if ($checkSwappedItemsTable->rowCount() > 0) {
                // Check if brand column exists in swapped_items
                $checkBrandCol = $db->query("SHOW COLUMNS FROM swapped_items LIKE 'brand'");
                if ($checkBrandCol->rowCount() > 0) {
                    $brandsQuery = $db->prepare("
                        SELECT 
                            si.brand,
                            COUNT(*) as count
                        FROM swapped_items si
                        INNER JOIN swaps s ON si.swap_id = s.id
                        WHERE s.company_id = ? AND si.brand IS NOT NULL AND si.brand != ''
                        GROUP BY si.brand
                        ORDER BY count DESC
                        LIMIT 5
                    ");
                    $brandsQuery->execute([$companyId]);
                    $topBrands = $brandsQuery->fetchAll(\PDO::FETCH_ASSOC);
                }
            }
            
            // Fallback: Try getting brands from customer_products if swapped_items doesn't have them
            if (empty($topBrands)) {
                try {
                    $checkCustomerProductsTable = $db->query("SHOW TABLES LIKE 'customer_products'");
                    if ($checkCustomerProductsTable->rowCount() > 0) {
                        $checkCpBrandCol = $db->query("SHOW COLUMNS FROM customer_products LIKE 'brand'");
                        if ($checkCpBrandCol->rowCount() > 0) {
                            // Try through swapped_items -> customer_products
                            $brandsQuery2 = $db->prepare("
                                SELECT 
                                    cp.brand,
                                    COUNT(DISTINCT s.id) as count
                                FROM swaps s
                                LEFT JOIN swapped_items si ON si.swap_id = s.id
                                LEFT JOIN customer_products cp ON cp.id = si.customer_product_id OR cp.id = s.customer_product_id
                                WHERE s.company_id = ? AND cp.brand IS NOT NULL AND cp.brand != ''
                                GROUP BY cp.brand
                                ORDER BY count DESC
                                LIMIT 5
                            ");
                            $brandsQuery2->execute([$companyId]);
                            $topBrands = $brandsQuery2->fetchAll(\PDO::FETCH_ASSOC);
                        }
                    }
                } catch (\Exception $e2) {
                    error_log("Error getting brands from customer_products: " . $e2->getMessage());
                }
            }
            
            error_log("Top brands query returned " . count($topBrands) . " brands for company {$companyId}");
        } catch (\Exception $e) {
            error_log("Error getting top brands: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            $topBrands = [];
        }
        
        // Calculate final profit (positive only) and loss (positive value of losses)
        $totalProfit = max(0, $calculatedFinalProfit); // Only positive profits
        $totalLoss = $calculatedLoss; // Losses as positive value
        
        // Debug logging
        error_log("DashboardController getSwapStatistics: Calculated final profit: ₵{$calculatedFinalProfit}, Estimated profit: ₵{$calculatedEstimatedProfit}, Total profit (realized): ₵{$totalProfit}, Count: {$calculatedFinalCount}");
        
        return [
            'total_swaps' => (int)($statusStats['total'] ?? 0),
            'pending' => (int)($statusStats['pending'] ?? 0),
            'completed' => (int)($statusStats['completed'] ?? 0),
            'resold' => (int)($statusStats['resold'] ?? 0),
            'total_value' => (float)$totalValue,
            'total_profit' => (float)$totalProfit, // Realized gains (positive profits)
            'total_loss' => (float)$totalLoss, // Realized losses (positive value)
            'estimated_profit' => (float)$calculatedEstimatedProfit,
            'in_stock_items' => (int)$inStockItems,
            'in_stock_value' => (float)$inStockValue,
            'sold_items' => (int)($swappedItemsStats['sold_items'] ?? 0),
            'recent_swaps' => $recentSwaps,
            'top_brands' => $topBrands
        ];
    }
    
    /**
     * Get repair statistics
     */
    private function getRepairStatistics($companyId, $dateFrom, $dateTo) {
        $db = \Database::getInstance()->getConnection();
        
        try {
            // Try repairs_new first
            $repairsQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN repair_status IN ('pending', 'PENDING') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN repair_status IN ('in_progress', 'IN_PROGRESS', 'awaiting_parts') THEN 1 ELSE 0 END) as ongoing,
                    SUM(CASE WHEN repair_status IN ('completed', 'COMPLETED') THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN repair_status IN ('cancelled', 'CANCELLED') THEN 1 ELSE 0 END) as cancelled,
                    SUM(COALESCE(total_cost, 0)) as revenue
                FROM repairs_new 
                WHERE company_id = ?
            ");
            $repairsQuery->execute([$companyId]);
            $repairStats = $repairsQuery->fetch();
        } catch (\Exception $e) {
            // Fallback to repairs table
            $repairsQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    0 as pending,
                    0 as ongoing,
                    COUNT(*) as completed,
                    0 as cancelled,
                    SUM(COALESCE(repair_cost, 0)) as revenue
                FROM repairs 
                WHERE company_id = ?
            ");
            $repairsQuery->execute([$companyId]);
            $repairStats = $repairsQuery->fetch();
        }
        
        // Get recent repairs
        $recentRepairs = [];
        try {
            $checkRepairsTable = $db->query("SHOW TABLES LIKE 'repairs_new'");
            if ($checkRepairsTable->rowCount() > 0) {
                // Check which columns exist
                $checkTrackingCode = $db->query("SHOW COLUMNS FROM repairs_new LIKE 'tracking_code'");
                $checkRepairStatus = $db->query("SHOW COLUMNS FROM repairs_new LIKE 'repair_status'");
                $checkTotalCost = $db->query("SHOW COLUMNS FROM repairs_new LIKE 'total_cost'");
                $checkDeviceModel = $db->query("SHOW COLUMNS FROM repairs_new LIKE 'device_model'");
                $checkTechId = $db->query("SHOW COLUMNS FROM repairs_new LIKE 'assigned_technician_id'");
                
                $hasTrackingCode = $checkTrackingCode->rowCount() > 0;
                $hasRepairStatus = $checkRepairStatus->rowCount() > 0;
                $hasTotalCost = $checkTotalCost->rowCount() > 0;
                $hasDeviceModel = $checkDeviceModel->rowCount() > 0;
                $hasTechId = $checkTechId->rowCount() > 0;
                
                $statusCol = $hasRepairStatus ? 'r.repair_status' : "'pending'";
                $trackingCol = $hasTrackingCode ? 'r.tracking_code' : "CONCAT('REP-', r.id)";
                $costCol = $hasTotalCost ? 'r.total_cost' : '0';
                $deviceCol = $hasDeviceModel ? 'r.device_model' : "'N/A'";
                $techJoin = $hasTechId ? 'LEFT JOIN users u ON r.assigned_technician_id = u.id' : '';
                $techSelect = $hasTechId ? 'u.username as technician' : "'Unassigned' as technician";
                
                $recentRepairsQuery = $db->prepare("
                    SELECT 
                        r.id,
                        {$trackingCol} as tracking_code,
                        r.created_at,
                        {$statusCol} as status,
                        {$costCol} as total_cost,
                        COALESCE(c.full_name, r.customer_name, 'Walk-in') as customer_name,
                        {$deviceCol} as device,
                        {$techSelect}
                    FROM repairs_new r
                    LEFT JOIN customers c ON r.customer_id = c.id
                    {$techJoin}
                    WHERE r.company_id = ?
                    ORDER BY r.created_at DESC
                    LIMIT 10
                ");
                $recentRepairsQuery->execute([$companyId]);
                $recentRepairs = $recentRepairsQuery->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Exception $e) {
            error_log("Error getting recent repairs: " . $e->getMessage());
            $recentRepairs = [];
        }
        
        return [
            'total' => (int)($repairStats['total'] ?? 0),
            'pending' => (int)($repairStats['pending'] ?? 0),
            'ongoing' => (int)($repairStats['ongoing'] ?? 0),
            'completed' => (int)($repairStats['completed'] ?? 0),
            'cancelled' => (int)($repairStats['cancelled'] ?? 0),
            'revenue' => (float)($repairStats['revenue'] ?? 0),
            'recent_repairs' => $recentRepairs
        ];
    }
    
    /**
     * Get product/inventory statistics
     */
    private function getProductStatistics($companyId) {
        $db = \Database::getInstance()->getConnection();
        
        try {
            $productModel = new \App\Models\Product();
            $prodStats = $productModel->getStats($companyId);
            
            // Get products with sales data - show products with sales first, then recent inventory products
            $bestProducts = [];
            try {
                $db = \Database::getInstance()->getConnection();
                
                // Check column names dynamically
                $itemColStmt = $db->query("SHOW COLUMNS FROM pos_sale_items");
                $itemColumns = array_column($itemColStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $saleIdCol = in_array('pos_sale_id', $itemColumns) ? 'pos_sale_id' : 
                            (in_array('sale_id', $itemColumns) ? 'sale_id' : null);
                
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
                
                $productSalesMap = []; // Map product IDs to sales data
                
                // Get sales data if pos_sale_items table exists and has data
                if ($saleIdCol && $productsTable) {
                    $hasItemId = in_array('item_id', $itemColumns);
                    
                    if ($hasItemId) {
                        try {
                            // Query 1: Get sales data with item_id matches (linked products)
                            $salesStmt = $db->prepare("
                                SELECT 
                                    p.id,
                                    p.name as product_name,
                                    p.product_id as sku,
                                    COALESCE(b.name, p.brand, 'N/A') as brand,
                                    SUM(psi.quantity) as units_sold,
                                    SUM(psi.total_price) as revenue,
                                    COALESCE(SUM(psi.total_price) - SUM(psi.quantity * COALESCE(p.cost_price, p.cost, 0)), 0) as profit
                                FROM pos_sale_items psi
                                INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                                INNER JOIN {$productsTable} p ON psi.item_id = p.id
                                LEFT JOIN brands b ON p.brand_id = b.id
                                WHERE ps.company_id = ? AND psi.item_id IS NOT NULL AND psi.item_id != 0
                                GROUP BY p.id, p.name, p.product_id, COALESCE(b.name, p.brand, 'N/A')
                            ");
                            $salesStmt->execute([$companyId]);
                            $salesDataLinked = $salesStmt->fetchAll(\PDO::FETCH_ASSOC);
                            
                            foreach ($salesDataLinked as $sale) {
                                $productId = $sale['id'] ?? null;
                                $sku = $sale['sku'] ?? null;
                                if ($productId) {
                                    // Store by database ID
                                    if (isset($productSalesMap[$productId])) {
                                        $productSalesMap[$productId]['units_sold'] += intval($sale['units_sold'] ?? 0);
                                        $productSalesMap[$productId]['revenue'] += floatval($sale['revenue'] ?? 0);
                                        $productSalesMap[$productId]['profit'] += floatval($sale['profit'] ?? 0);
                                    } else {
                                        $productSalesMap[$productId] = [
                                            'units_sold' => intval($sale['units_sold'] ?? 0),
                                            'revenue' => floatval($sale['revenue'] ?? 0),
                                            'profit' => floatval($sale['profit'] ?? 0)
                                        ];
                                    }
                                    
                                    // Also store by SKU/product_id if available (for matching when item_id is NULL)
                                    if ($sku) {
                                        $skuKey = 'sku:' . strtolower(trim($sku));
                                        if (isset($productSalesMap[$skuKey])) {
                                            $productSalesMap[$skuKey]['units_sold'] += intval($sale['units_sold'] ?? 0);
                                            $productSalesMap[$skuKey]['revenue'] += floatval($sale['revenue'] ?? 0);
                                            $productSalesMap[$skuKey]['profit'] += floatval($sale['profit'] ?? 0);
                                        } else {
                                            $productSalesMap[$skuKey] = [
                                                'units_sold' => intval($sale['units_sold'] ?? 0),
                                                'revenue' => floatval($sale['revenue'] ?? 0),
                                                'profit' => floatval($sale['profit'] ?? 0)
                                            ];
                                        }
                                    }
                                }
                            }
                            
                            // Query 2: Get sales data by item_description (unlinked products) - match by name or SKU
                            // Also try to match items that have item_id but might be linked to products by name/SKU
                            $salesStmtUnlinked = $db->prepare("
                                SELECT 
                                    psi.item_description,
                                    psi.item_id,
                                    SUM(psi.quantity) as units_sold,
                                    SUM(psi.total_price) as revenue,
                                    0 as profit
                                FROM pos_sale_items psi
                                INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                                WHERE ps.company_id = ? AND (psi.item_id IS NULL OR psi.item_id = 0)
                                GROUP BY psi.item_description, psi.item_id
                            ");
                            $salesStmtUnlinked->execute([$companyId]);
                            $salesDataUnlinked = $salesStmtUnlinked->fetchAll(\PDO::FETCH_ASSOC);
                            
                            // Also try to get products by item_description that might match product names/SKUs
                            foreach ($salesDataUnlinked as $sale) {
                                $itemDesc = trim($sale['item_description'] ?? '');
                                if ($itemDesc) {
                                    $normalizedName = strtolower($itemDesc);
                                    // Store by normalized name
                                    if (isset($productSalesMap['name:' . $normalizedName])) {
                                        $productSalesMap['name:' . $normalizedName]['units_sold'] += intval($sale['units_sold'] ?? 0);
                                        $productSalesMap['name:' . $normalizedName]['revenue'] += floatval($sale['revenue'] ?? 0);
                                    } else {
                                        $productSalesMap['name:' . $normalizedName] = [
                                            'units_sold' => intval($sale['units_sold'] ?? 0),
                                            'revenue' => floatval($sale['revenue'] ?? 0),
                                            'profit' => 0 // Can't calculate profit without product cost
                                        ];
                                    }
                                    
                                    // Also try to extract SKU from item_description if it contains patterns like "GALAXYS27879" or "101"
                                    // Look for patterns that might be SKUs (alphanumeric sequences)
                                    if (preg_match('/\b([A-Z0-9]{3,20})\b/i', $itemDesc, $skuMatches)) {
                                        $possibleSku = strtolower(trim($skuMatches[1]));
                                        if ($possibleSku && !isset($productSalesMap['sku:' . $possibleSku])) {
                                            $productSalesMap['sku:' . $possibleSku] = [
                                                'units_sold' => intval($sale['units_sold'] ?? 0),
                                                'revenue' => floatval($sale['revenue'] ?? 0),
                                                'profit' => 0
                                            ];
                                        }
                                    }
                                }
                            }
                            
                            // Query 3: Try to match unlinked items to products by attempting to join even if item_id is NULL
                            // This catches cases where item_id might not be set but item_description matches product name
                            try {
                                $salesStmtByName = $db->prepare("
                                    SELECT 
                                        p.id,
                                        p.product_id as sku,
                                        p.name,
                                        SUM(psi.quantity) as units_sold,
                                        SUM(psi.total_price) as revenue,
                                        COALESCE(SUM(psi.total_price) - SUM(psi.quantity * COALESCE(p.cost_price, p.cost, 0)), 0) as profit
                                    FROM pos_sale_items psi
                                    INNER JOIN pos_sales ps ON psi.{$saleIdCol} = ps.id
                                    INNER JOIN {$productsTable} p ON (
                                        LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) OR
                                        LOWER(TRIM(psi.item_description)) LIKE CONCAT('%', LOWER(TRIM(p.product_id)), '%') OR
                                        LOWER(TRIM(psi.item_description)) LIKE CONCAT('%', LOWER(TRIM(p.name)), '%')
                                    )
                                    WHERE ps.company_id = ? 
                                    AND (psi.item_id IS NULL OR psi.item_id = 0)
                                    GROUP BY p.id, p.product_id, p.name
                                ");
                                $salesStmtByName->execute([$companyId]);
                                $salesDataByName = $salesStmtByName->fetchAll(\PDO::FETCH_ASSOC);
                                
                                foreach ($salesDataByName as $sale) {
                                    $productId = $sale['id'] ?? null;
                                    $sku = $sale['sku'] ?? null;
                                    
                                    if ($productId) {
                                        // Add to existing map if product ID already exists
                                        if (isset($productSalesMap[$productId])) {
                                            $productSalesMap[$productId]['units_sold'] += intval($sale['units_sold'] ?? 0);
                                            $productSalesMap[$productId]['revenue'] += floatval($sale['revenue'] ?? 0);
                                            $productSalesMap[$productId]['profit'] += floatval($sale['profit'] ?? 0);
                                        } else {
                                            $productSalesMap[$productId] = [
                                                'units_sold' => intval($sale['units_sold'] ?? 0),
                                                'revenue' => floatval($sale['revenue'] ?? 0),
                                                'profit' => floatval($sale['profit'] ?? 0)
                                            ];
                                        }
                                    }
                                    
                                    // Also add by SKU
                                    if ($sku) {
                                        $skuKey = 'sku:' . strtolower(trim($sku));
                                        if (isset($productSalesMap[$skuKey])) {
                                            $productSalesMap[$skuKey]['units_sold'] += intval($sale['units_sold'] ?? 0);
                                            $productSalesMap[$skuKey]['revenue'] += floatval($sale['revenue'] ?? 0);
                                            $productSalesMap[$skuKey]['profit'] += floatval($sale['profit'] ?? 0);
                                        } else {
                                            $productSalesMap[$skuKey] = [
                                                'units_sold' => intval($sale['units_sold'] ?? 0),
                                                'revenue' => floatval($sale['revenue'] ?? 0),
                                                'profit' => floatval($sale['profit'] ?? 0)
                                            ];
                                        }
                                    }
                                }
                                
                                error_log("DashboardController: Matched " . count($salesDataByName) . " unlinked items to products by name/SKU");
                            } catch (\Exception $nameMatchError) {
                                error_log("DashboardController: Error matching items by name: " . $nameMatchError->getMessage());
                            }
                            
                            error_log("DashboardController: Mapped " . count($salesDataLinked) . " linked products and " . count($salesDataUnlinked) . " unlinked item descriptions");
                            
                            // Debug: Log sample of sales map
                            if (!empty($productSalesMap)) {
                                $sampleKeys = array_slice(array_keys($productSalesMap), 0, 5);
                                error_log("DashboardController: Sample sales map keys: " . json_encode($sampleKeys));
                            } else {
                                error_log("DashboardController: WARNING - productSalesMap is empty! No sales found for company {$companyId}");
                            }
                            
                        } catch (\Exception $e) {
                            error_log("Error fetching sales data for products: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                        }
                    }
                }
                
                // Get products from inventory (get more to have options)
                $productModel = new \App\Models\Product();
                $recentProducts = $productModel->findByCompany($companyId, 20, null, 0, false, false);
                
                // Debug: Log sample products
                if (!empty($recentProducts)) {
                    $sampleProduct = $recentProducts[0];
                    error_log("DashboardController: Sample product - ID: " . ($sampleProduct['id'] ?? 'N/A') . ", Name: " . ($sampleProduct['name'] ?? 'N/A'));
                }
                
                // Combine inventory products with sales data
                foreach ($recentProducts as $product) {
                    $productId = $product['id'] ?? null;
                    $productName = trim($product['name'] ?? '');
                    $productNameLower = strtolower($productName);
                    $brandName = $product['brand_name'] ?? $product['brand'] ?? 'N/A';
                    $price = floatval($product['price'] ?? $product['display_price'] ?? $product['resell_price'] ?? 0);
                    $cost = floatval($product['cost'] ?? $product['cost_price'] ?? 0);
                    
                    // Start with zeros - only populate if we have actual sales data
                    $unitsSold = 0;
                    $revenue = 0;
                    $profit = 0;
                    
                    // Try to get sales data by database ID first
                    if ($productId && isset($productSalesMap[$productId])) {
                        $unitsSold = $productSalesMap[$productId]['units_sold'];
                        $revenue = $productSalesMap[$productId]['revenue'];
                        $profit = $productSalesMap[$productId]['profit'] ?? 0;
                        error_log("DashboardController: Matched product by DB ID {$productId} ({$productName}) - Units: {$unitsSold}, Revenue: {$revenue}");
                    }
                    // Try to match by SKU/product_id (like 'GALAXYS27879' or '101')
                    else {
                        $productSku = strtolower(trim($product['product_id'] ?? $product['sku'] ?? ''));
                        if ($productSku && isset($productSalesMap['sku:' . $productSku])) {
                            $unitsSold = $productSalesMap['sku:' . $productSku]['units_sold'];
                            $revenue = $productSalesMap['sku:' . $productSku]['revenue'];
                            $profit = $productSalesMap['sku:' . $productSku]['profit'] ?? 0;
                            error_log("DashboardController: Matched product by SKU '{$productSku}' ({$productName}) - Units: {$unitsSold}, Revenue: {$revenue}");
                        }
                        // Fallback: try to match by product name (exact match)
                        else if ($productNameLower && isset($productSalesMap['name:' . $productNameLower])) {
                            $unitsSold = $productSalesMap['name:' . $productNameLower]['units_sold'];
                            $revenue = $productSalesMap['name:' . $productNameLower]['revenue'];
                            // For unlinked sales, calculate profit from revenue and cost if we have units
                            if ($unitsSold > 0 && $cost > 0) {
                                $avgPricePerUnit = $revenue / $unitsSold;
                                $profit = ($avgPricePerUnit - $cost) * $unitsSold;
                            } else {
                                $profit = $productSalesMap['name:' . $productNameLower]['profit'] ?? 0;
                            }
                        }
                        // Additional fallback: try partial name matching
                        else if ($productNameLower) {
                        foreach ($productSalesMap as $key => $salesData) {
                            if (strpos($key, 'name:') === 0) {
                                $salesItemName = substr($key, 5); // Remove 'name:' prefix
                                // Check if product name contains sales item name or vice versa
                                if (strpos($productNameLower, $salesItemName) !== false || 
                                    strpos($salesItemName, $productNameLower) !== false) {
                                    $unitsSold = $salesData['units_sold'];
                                    $revenue = $salesData['revenue'];
                                    // Calculate profit if we have cost data
                                    if ($unitsSold > 0 && $cost > 0) {
                                        $avgPricePerUnit = $revenue / $unitsSold;
                                        $profit = ($avgPricePerUnit - $cost) * $unitsSold;
                                    } else {
                                        $profit = $salesData['profit'] ?? 0;
                                    }
                                    break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Debug log for first product to see why it's not matching
                    if (empty($bestProducts) && $productNameLower) {
                        error_log("DashboardController: First product - ID: {$productId}, Name: {$productNameLower}");
                        error_log("DashboardController: Checking sales map for key 'name:{$productNameLower}': " . (isset($productSalesMap['name:' . $productNameLower]) ? 'FOUND' : 'NOT FOUND'));
                    }
                    
                    $bestProducts[] = [
                        'name' => $productName ?: 'N/A',
                        'brand' => $brandName,
                        'units_sold' => $unitsSold,
                        'revenue' => $revenue,
                        'profit' => $profit
                    ];
                }
                
                // Sort: products with sales first (by units_sold DESC), then by profit DESC
                usort($bestProducts, function($a, $b) {
                    // If both have sales, sort by units_sold
                    if ($a['units_sold'] > 0 || $b['units_sold'] > 0) {
                        if ($b['units_sold'] == $a['units_sold']) {
                            return $b['revenue'] <=> $a['revenue'];
                        }
                        return $b['units_sold'] <=> $a['units_sold'];
                    }
                    // If neither has sales, sort by profit (potential profit)
                    return $b['profit'] <=> $a['profit'];
                });
                
                // Take top 4
                $bestProducts = array_slice($bestProducts, 0, 4);
                
                error_log("DashboardController: Fetched " . count($bestProducts) . " products with sales data for company {$companyId}");
                
                // Debug: log first product structure if available
                if (!empty($bestProducts) && isset($bestProducts[0])) {
                    error_log("DashboardController: Sample product structure: " . json_encode($bestProducts[0]));
                }
            } catch (\Exception $e) {
                error_log("Error getting recent products: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
                $bestProducts = [];
            }
            
            // Get swapped items for resale - check for column existence
            $swappedItemsCount = 0;
            try {
                // Check which columns exist
                $checkSource = $db->query("SHOW COLUMNS FROM products LIKE 'source'");
                $checkSwapItem = $db->query("SHOW COLUMNS FROM products LIKE 'is_swapped_item'");
                $checkStatus = $db->query("SHOW COLUMNS FROM products LIKE 'status'");
                
                $hasSource = $checkSource->rowCount() > 0;
                $hasSwapItem = $checkSwapItem->rowCount() > 0;
                $hasStatus = $checkStatus->rowCount() > 0;
                
                $whereClause = "company_id = ?";
                if ($hasSource && $hasSwapItem && $hasStatus) {
                    $whereClause .= " AND ((source = 'swap' OR is_swapped_item = 1) AND status = 'available')";
                } elseif ($hasSource && $hasStatus) {
                    $whereClause .= " AND (source = 'swap' AND status = 'available')";
                } elseif ($hasSwapItem && $hasStatus) {
                    $whereClause .= " AND (is_swapped_item = 1 AND status = 'available')";
                } elseif ($hasStatus) {
                    $whereClause .= " AND status = 'available'";
                }
                
                $swappedItemsQuery = $db->prepare("SELECT COUNT(*) as total FROM products WHERE {$whereClause}");
                $swappedItemsQuery->execute([$companyId]);
                $swappedItemsCount = $swappedItemsQuery->fetch()['total'] ?? 0;
            } catch (\Exception $e) {
                error_log("Error getting swapped items count: " . $e->getMessage());
                $swappedItemsCount = 0;
            }
            
            return [
                'total_products' => (int)($prodStats['total_products'] ?? 0),
                'available_products' => (int)($prodStats['available_products'] ?? 0),
                'out_of_stock' => (int)($prodStats['out_of_stock'] ?? 0),
                'total_quantity' => (int)($prodStats['total_quantity'] ?? 0),
                'inventory_value' => (float)($prodStats['total_value'] ?? 0),
                'swap_available' => (int)($prodStats['swap_available'] ?? 0),
                'swapped_items_count' => (int)$swappedItemsCount,
                'best_products' => $bestProducts
            ];
        } catch (\Exception $e) {
            error_log("DashboardController: Error loading product statistics - " . $e->getMessage());
            return [
                'total_products' => 0,
                'available_products' => 0,
                'out_of_stock' => 0,
                'total_quantity' => 0,
                'inventory_value' => 0,
                'swap_available' => 0,
                'swapped_items_count' => 0,
                'best_products' => []
            ];
        }
    }
    
    /**
     * Get financial summary
     * @param array $swapStats Optional swap statistics to use (for accurate swap profit)
     */
    private function getFinancialSummary($companyId, $dateFrom, $dateTo, $swapStats = null) {
        $db = \Database::getInstance()->getConnection();
        
        // Total Sales Revenue (from POS sales)
        // Use datetime comparison to match audit trail (includes full day)
        try {
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            // Calculate sales revenue from ALL sales (both technicians and salespersons)
            // No filtering by created_by_user_id or role - includes all company sales
            // EXCLUDE swap transactions - swaps should only be tracked on swap page
            // Use swap_id IS NULL to exclude all swap-related sales
            $salesRevenueQuery = $db->prepare("
                SELECT COALESCE(SUM(final_amount), 0) as total 
                FROM pos_sales 
                WHERE company_id = ? AND created_at >= ? AND created_at <= ?
                AND swap_id IS NULL
            ");
            $salesRevenueQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
            $salesRevenue = (float)($salesRevenueQuery->fetch()['total'] ?? 0);
            error_log("Financial Summary - Sales Revenue: ₵{$salesRevenue} (includes all sales from technicians and salespersons, excluding swap transactions)");
        } catch (\Exception $e) {
            error_log("Error getting sales revenue: " . $e->getMessage());
            $salesRevenue = 0;
        }
        
        // Swap Revenue and Profit
        // Swap revenue should be:
        // - For non-resold swaps: cash top-up only (added_cash)
        // - For resold swaps: cash top-up + resale value (total_value, which includes both)
        // The swapped item needs to be resold to realize its full value
        $swapRevenue = 0;
        $swapProfit = 0;
        
        // Get swap revenue - cash top-up for non-resold, total_value (top-up + resale) for resold swaps
        try {
            // Check which columns exist
            $checkAddedCash = $db->query("SHOW COLUMNS FROM swaps LIKE 'added_cash'");
            $hasAddedCash = $checkAddedCash->rowCount() > 0;
            $checkTotalValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
            $hasTotalValue = $checkTotalValue->rowCount() > 0;
            $checkFinalPrice = $db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
            $hasFinalPrice = $checkFinalPrice->rowCount() > 0;
            $checkCompanyProductId = $db->query("SHOW COLUMNS FROM swaps LIKE 'company_product_id'");
            $hasCompanyProductId = $checkCompanyProductId->rowCount() > 0;
            
            // Check if swapped_items table exists to check resale status
            $hasSwappedItems = false;
            try {
                $check = $db->query("SHOW TABLES LIKE 'swapped_items'");
                $hasSwappedItems = $check->rowCount() > 0;
            } catch (Exception $e) {
                $hasSwappedItems = false;
            }
            
            if ($hasTotalValue) {
                // Use total_value which should contain Cash Top-up + Resold Price
                // total_value is calculated as: Cash Top-up (final_price - given_phone_value or added_cash) + Resold Price
                $swapRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(total_value), 0) as total 
                    FROM swaps s
                    WHERE s.company_id = ? 
                    AND DATE(s.created_at) BETWEEN ? AND ?
                ");
                $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
            } elseif ($hasAddedCash) {
                // Fallback: Use added_cash directly (cash top-up only) if we can't check resale status
                $swapRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(added_cash), 0) as total 
                    FROM swaps 
                    WHERE company_id = ? 
                    AND DATE(created_at) BETWEEN ? AND ?
                ");
                $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
            } elseif ($hasCompanyProductId) {
                // Calculate added_cash from product prices: company_product_price - customer_product_estimated_value
                // Check if swapped_items table exists
                $hasSwappedItems = false;
                try {
                    $check = $db->query("SHOW TABLES LIKE 'swapped_items'");
                    $hasSwappedItems = $check->rowCount() > 0;
                } catch (Exception $e) {
                    $hasSwappedItems = false;
                }
                
                if ($hasSwappedItems) {
                    // Calculate cash top-up from: company_product_price - customer_product_estimated_value
                    // Use subquery to avoid double-counting when multiple swapped_items exist per swap
                    // Get the first swapped_item per swap to calculate cash top-up
                    $swapRevenueQuery = $db->prepare("
                        SELECT COALESCE(SUM(cash_topup), 0) as total
                        FROM (
                            SELECT s.id,
                                CASE 
                                    WHEN p.price > 0 AND si.estimated_value > 0 AND (p.price - si.estimated_value) > 0 
                                        THEN (p.price - si.estimated_value)
                                    ELSE 0
                                END as cash_topup
                            FROM swaps s
                            LEFT JOIN products p ON s.company_product_id = p.id AND p.company_id = s.company_id
                            LEFT JOIN (
                                SELECT swap_id, estimated_value, 
                                       ROW_NUMBER() OVER (PARTITION BY swap_id ORDER BY id) as rn
                                FROM swapped_items
                            ) si ON s.id = si.swap_id AND si.rn = 1
                            WHERE s.company_id = ? 
                            AND DATE(s.created_at) BETWEEN ? AND ?
                        ) as swap_calc
                    ");
                    $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                    $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
                    
                    // Fallback if ROW_NUMBER() is not supported (older MySQL)
                    if ($swapRevenue == 0) {
                        $swapRevenueQuery2 = $db->prepare("
                            SELECT COALESCE(SUM(cash_topup), 0) as total
                            FROM (
                                SELECT s.id,
                                    CASE 
                                        WHEN p.price > 0 AND si.estimated_value > 0 AND (p.price - si.estimated_value) > 0 
                                            THEN (p.price - si.estimated_value)
                                        ELSE 0
                                    END as cash_topup
                                FROM swaps s
                                LEFT JOIN products p ON s.company_product_id = p.id AND p.company_id = s.company_id
                                LEFT JOIN swapped_items si ON s.id = si.swap_id
                                WHERE s.company_id = ? 
                                AND DATE(s.created_at) BETWEEN ? AND ?
                                GROUP BY s.id
                            ) as swap_calc
                        ");
                        $swapRevenueQuery2->execute([$companyId, $dateFrom, $dateTo]);
                        $swapRevenue = (float)($swapRevenueQuery2->fetch()['total'] ?? 0);
                    }
                } else {
                    // Fallback: use total_value if it's reasonable (small amount, likely cash top-up)
                    if ($hasTotalValue) {
                        $swapRevenueQuery = $db->prepare("
                            SELECT COALESCE(SUM(
                                CASE 
                                    WHEN total_value > 0 AND total_value <= 500 THEN total_value
                                    ELSE 0
                                END
                            ), 0) as total 
                            FROM swaps 
                            WHERE company_id = ? 
                            AND DATE(created_at) BETWEEN ? AND ?
                        ");
                        $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                        $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
                    }
                }
            } elseif ($hasFinalPrice) {
                // final_price exists - calculate cash top-up from final_price - customer_product_value
                // Check if swapped_items table exists to get customer product value
                $hasSwappedItems = false;
                try {
                    $check = $db->query("SHOW TABLES LIKE 'swapped_items'");
                    $hasSwappedItems = $check->rowCount() > 0;
                } catch (Exception $e) {
                    $hasSwappedItems = false;
                }
                
                if ($hasSwappedItems) {
                    // Calculate cash top-up: final_price - estimated_value (customer product value)
                    // Use subquery to get first swapped_item per swap
                    try {
                        $swapRevenueQuery = $db->prepare("
                            SELECT COALESCE(SUM(cash_topup), 0) as total
                            FROM (
                                SELECT s.id,
                                    CASE 
                                        WHEN s.final_price > 0 AND si.estimated_value > 0 AND (s.final_price - si.estimated_value) > 0 
                                            THEN (s.final_price - si.estimated_value)
                                        ELSE 0
                                    END as cash_topup
                                FROM swaps s
                                LEFT JOIN (
                                    SELECT swap_id, estimated_value, 
                                           ROW_NUMBER() OVER (PARTITION BY swap_id ORDER BY id) as rn
                                    FROM swapped_items
                                ) si ON s.id = si.swap_id AND si.rn = 1
                                WHERE s.company_id = ? 
                                AND DATE(s.created_at) BETWEEN ? AND ?
                            ) as swap_calc
                        ");
                        $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                        $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
                    } catch (\Exception $e) {
                        // Fallback for older MySQL versions (no ROW_NUMBER)
                        error_log("DashboardController getFinancialSummary: ROW_NUMBER() not supported, using fallback: " . $e->getMessage());
                        $swapRevenueQuery = $db->prepare("
                            SELECT COALESCE(SUM(cash_topup), 0) as total
                            FROM (
                                SELECT s.id,
                                    CASE 
                                        WHEN s.final_price > 0 AND si.estimated_value > 0 AND (s.final_price - si.estimated_value) > 0 
                                            THEN (s.final_price - si.estimated_value)
                                        ELSE 0
                                    END as cash_topup
                                FROM swaps s
                                LEFT JOIN swapped_items si ON s.id = si.swap_id
                                WHERE s.company_id = ? 
                                AND DATE(s.created_at) BETWEEN ? AND ?
                                GROUP BY s.id
                            ) as swap_calc
                        ");
                        $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                        $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
                    }
                } else {
                    // No swapped_items table - can't calculate cash top-up, use 0
                    $swapRevenue = 0;
                }
            } elseif ($hasTotalValue) {
                // Fallback: use total_value if it's reasonable (small amount, likely cash top-up)
                $swapRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN total_value > 0 AND total_value <= 500 THEN total_value
                            ELSE 0
                        END
                    ), 0) as total 
                    FROM swaps 
                    WHERE company_id = ? 
                    AND DATE(created_at) BETWEEN ? AND ?
                ");
                $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
            } elseif ($hasCompanyProductId) {
                // Try to get from company_product price
                $swapRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(sp.price), 0) as total 
                    FROM swaps s
                    LEFT JOIN products sp ON s.company_product_id = sp.id
                    WHERE s.company_id = ? 
                    AND DATE(s.created_at) BETWEEN ? AND ?
                ");
                $swapRevenueQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapRevenue = (float)($swapRevenueQuery->fetch()['total'] ?? 0);
            } else {
                $swapRevenue = 0;
            }
        } catch (\Exception $e) {
            error_log("Error getting swap revenue: " . $e->getMessage());
            $swapRevenue = 0;
        }
        
        // Get swap profit - use from swapStats if provided (already calculated correctly)
        // Otherwise calculate from swap_profit_links table
        if ($swapStats && isset($swapStats['total_profit'])) {
            // Use the already-calculated swap profit from getSwapStatistics()
            // This uses the same logic as SwapController, ensuring consistency
            $swapProfit = (float)($swapStats['total_profit'] ?? 0);
            error_log("Financial Summary: Using swap profit from swapStats: ₵{$swapProfit}");
        } else {
            // Fallback: Calculate from database with date range filtering
            try {
                // Only count profit when customer item has been resold (customer_item_sale_id exists) AND profit is finalized
                // CRITICAL FIX: Filter by customer sale date (when item was resold), not swap creation date
                // Never count estimated profits as realized gains
                $swapProfitQuery = $db->prepare("
                    SELECT COALESCE(
                        SUM(CASE 
                            WHEN spl.customer_item_sale_id IS NOT NULL 
                            AND spl.status = 'finalized' 
                            AND spl.final_profit IS NOT NULL 
                            THEN spl.final_profit 
                            WHEN spl.customer_item_sale_id IS NOT NULL 
                            AND spl.final_profit IS NOT NULL 
                            THEN spl.final_profit
                            ELSE 0 
                        END), 0
                    ) as total 
                    FROM swap_profit_links spl
                    INNER JOIN swaps s ON spl.swap_id = s.id
                    LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                    WHERE s.company_id = ? 
                    AND spl.customer_item_sale_id IS NOT NULL
                    AND (
                        (customer_sale.id IS NOT NULL AND DATE(customer_sale.created_at) BETWEEN ? AND ?)
                        OR (customer_sale.id IS NULL AND DATE(s.created_at) BETWEEN ? AND ?)
                    )
                ");
                $swapProfitQuery->execute([$companyId, $dateFrom, $dateTo, $dateFrom, $dateTo]);
                $swapProfit = (float)($swapProfitQuery->fetch()['total'] ?? 0);
                error_log("Financial Summary: Calculated swap profit from DB (date filtered by customer sale): ₵{$swapProfit} for period {$dateFrom} to {$dateTo}");
            } catch (\Exception $e) {
                error_log("Error getting swap profit: " . $e->getMessage());
                $swapProfit = 0;
            }
        }
        
        // Repair Revenue and Parts Count
        // Use datetime comparison to match audit trail (includes full day)
        $repairRevenue = 0;
        $repairPartsCount = 0;
        try {
            // Use same datetime variables from sales revenue calculation
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            // Check which repairs table exists
            $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsNew->rowCount() > 0;
            
            if ($hasRepairsNew) {
                $repairRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(total_cost), 0) as total 
                    FROM repairs_new 
                    WHERE company_id = ? AND created_at >= ? AND created_at <= ?
                ");
                $repairRevenueQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                $repairRevenue = (float)($repairRevenueQuery->fetch()['total'] ?? 0);
                
                // Get repair parts count (products sold as spare parts during repairs)
                try {
                    $checkRepairAccessories = $db->query("SHOW TABLES LIKE 'repair_accessories'");
                    if ($checkRepairAccessories && $checkRepairAccessories->rowCount() > 0) {
                        $repairPartsQuery = $db->prepare("
                            SELECT COALESCE(SUM(ra.quantity), 0) as parts_count
                            FROM repair_accessories ra
                            INNER JOIN repairs_new r ON ra.repair_id = r.id
                            WHERE r.company_id = ? AND r.created_at >= ? AND r.created_at <= ?
                        ");
                        $repairPartsQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                        $repairPartsCount = (int)($repairPartsQuery->fetch()['parts_count'] ?? 0);
                    }
                } catch (\Exception $e2) {
                    error_log("Error getting repair parts count: " . $e2->getMessage());
                    $repairPartsCount = 0;
                }
            } else {
                // Try repairs table
                $repairRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(total_cost), 0) as total 
                    FROM repairs 
                    WHERE company_id = ? AND created_at >= ? AND created_at <= ?
                ");
                $repairRevenueQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                $repairRevenue = (float)($repairRevenueQuery->fetch()['total'] ?? 0);
                $repairPartsCount = 0; // Old repairs table doesn't have parts tracking
            }
        } catch (\Exception $e) {
            error_log("Error getting repair revenue: " . $e->getMessage());
            $repairRevenue = 0;
            $repairPartsCount = 0;
        }
        
        // Calculate Sales Profit (Revenue - Cost of Goods Sold)
        // For now, we'll use a rough estimate or calculate from pos_sales if cost is available
        $salesProfit = 0;
        try {
            // Check if pos_sales has cost column
            $checkCostCol = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'total_cost'");
            $hasCostCol = $checkCostCol->rowCount() > 0;
            
            if ($hasCostCol) {
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use swap_id IS NULL to exclude all swap-related sales
                $salesProfitQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount - total_cost), 0) as profit 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ? AND total_cost IS NOT NULL
                    AND swap_id IS NULL
                ");
                $salesProfitQuery->execute([$companyId, $dateFrom, $dateTo]);
                $salesProfit = (float)($salesProfitQuery->fetch()['profit'] ?? 0);
            } else {
                // Estimate: assume 20% margin on sales if no cost data
                $salesProfit = $salesRevenue * 0.20;
            }
        } catch (\Exception $e) {
            error_log("Error calculating sales profit: " . $e->getMessage());
            // Fallback: estimate 20% margin
            $salesProfit = $salesRevenue * 0.20;
        }
        
        // Calculate Repairer Profit (Workmanship + Parts Profit)
        // This matches the audit trail calculation for consistency
        $repairerProfit = 0;
        try {
            $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsNew->rowCount() > 0;
            
            if ($hasRepairsNew) {
                // Check if repair_accessories table exists
                $checkRepairAccessories = $db->query("SHOW TABLES LIKE 'repair_accessories'");
                $hasRepairAccessories = $checkRepairAccessories->rowCount() > 0;
                
                // Determine which products table to use
                $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
                
                // Check which cost column exists (prioritize cost_price, then cost)
                $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                $hasCostPrice = $checkCostPrice->rowCount() > 0;
                $hasCost = $checkCost->rowCount() > 0;
                
                // Determine cost column to use (prioritize cost_price if both exist)
                if ($hasCostPrice) {
                    $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
                } elseif ($hasCost) {
                    $costColumn = 'COALESCE(p.cost, 0)';
                } else {
                    $costColumn = '0'; // No cost column found
                }
                
                // Use datetime comparison to match audit trail (includes full day)
                $dateFromStart = $dateFrom . ' 00:00:00';
                $dateToEnd = $dateTo . ' 23:59:59';
                
                if ($hasRepairAccessories) {
                    // Calculate workmanship profit and parts profit
                    $repairerProfitQuery = $db->prepare("
                        SELECT 
                            -- Workmanship Profit: repair_cost (revenue) - labour_cost (cost)
                            COALESCE(SUM(r.repair_cost - COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as workmanship_profit,
                            -- Parts Profit: (selling_price - cost) * quantity
                            COALESCE(SUM((ra.price - {$costColumn}) * ra.quantity), 0) as parts_profit
                        FROM repairs_new r
                        LEFT JOIN repair_accessories ra ON r.id = ra.repair_id
                        LEFT JOIN {$productsTable} p ON ra.product_id = p.id AND p.company_id = r.company_id
                        WHERE r.company_id = ? AND r.created_at >= ? AND r.created_at <= ?
                    ");
                    $repairerProfitQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                    $repairerProfitResult = $repairerProfitQuery->fetch(\PDO::FETCH_ASSOC);
                    $workmanshipProfit = (float)($repairerProfitResult['workmanship_profit'] ?? 0);
                    $partsProfit = (float)($repairerProfitResult['parts_profit'] ?? 0);
                    $repairerProfit = $workmanshipProfit + $partsProfit;
                } else {
                    // If repair_accessories table doesn't exist, only calculate workmanship profit
                    $repairerProfitQuery = $db->prepare("
                        SELECT COALESCE(SUM(r.repair_cost - COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as workmanship_profit
                        FROM repairs_new r
                        WHERE r.company_id = ? AND r.created_at >= ? AND r.created_at <= ?
                    ");
                    $repairerProfitQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                    $repairerProfit = (float)($repairerProfitQuery->fetch()['workmanship_profit'] ?? 0);
                }
                
                error_log("Financial Summary - Repairer Profit: ₵{$repairerProfit} (Workmanship + Parts)");
            }
        } catch (\Exception $e) {
            error_log("Error calculating repairer profit: " . $e->getMessage());
            $repairerProfit = 0;
        }
        
        // Calculate total cost (sales cost + repairer cost) to match audit trail calculation
        // Use same datetime comparison and cost calculation as audit trail
        $salesCost = 0;
        try {
            $checkCostCol = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'total_cost'");
            $hasCostCol = $checkCostCol->rowCount() > 0;
            
            // Use datetime comparison to match audit trail (includes full day)
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            // ALWAYS calculate cost from products table to ensure accuracy
            // Don't use stored total_cost as it may be incorrect (e.g., 22.40 instead of 22.00)
            // Always use actual product cost from products table
            $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            
            // Check which cost column exists (prioritize cost_price, then cost)
            $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
            $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
            $hasCostPrice = $checkCostPrice->rowCount() > 0;
            $hasCost = $checkCost->rowCount() > 0;
            
            // Determine cost column to use (prioritize cost_price if both exist)
            if ($hasCostPrice) {
                $costColumn = 'COALESCE(p.cost_price, 0)';
            } elseif ($hasCost) {
                $costColumn = 'COALESCE(p.cost, 0)';
            } else {
                $costColumn = '0'; // No cost column found
            }
            
            // Calculate sales cost with improved matching (by item_id OR by description)
            // Includes ALL sales from both technicians and salespersons (no filtering by created_by_user_id or role)
            // EXCLUDE swap transactions - swaps should only be tracked on swap page
            // Use swap_id IS NULL to exclude all swap-related sales
            $salesCostQuery = $db->prepare("
                SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0) as cost
                FROM pos_sale_items psi
                INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                LEFT JOIN {$productsTable} p ON (
                    (psi.item_id = p.id AND p.company_id = ps.company_id)
                    OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                )
                WHERE ps.company_id = ? 
                AND ps.created_at >= ? 
                AND ps.created_at <= ?
                AND ps.swap_id IS NULL
                AND p.id IS NOT NULL
            ");
            $salesCostQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
            $salesCost = (float)($salesCostQuery->fetch()['cost'] ?? 0);
            
            // Log for debugging
            error_log("Financial Summary - Sales Cost calculated: ₵{$salesCost} for company {$companyId} from {$dateFrom} to {$dateTo} (includes all sales from technicians and salespersons)");
        } catch (\Exception $e) {
            error_log("Error calculating sales cost: " . $e->getMessage());
            // Fallback: estimate 80% cost
            $salesCost = $salesRevenue * 0.80;
        }
        
        // Calculate repairer cost (labour cost + parts cost)
        // Use datetime comparison to match audit trail (includes full day)
        $repairerCost = 0;
        try {
            // Use same datetime variables
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsNew->rowCount() > 0;
            
            if ($hasRepairsNew) {
                $checkRepairAccessories = $db->query("SHOW TABLES LIKE 'repair_accessories'");
                $hasRepairAccessories = $checkRepairAccessories->rowCount() > 0;
                
                $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
                
                // Check which cost column exists (prioritize cost_price, then cost)
                $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                $hasCostPrice = $checkCostPrice->rowCount() > 0;
                $hasCost = $checkCost->rowCount() > 0;
                
                // Determine cost column to use (prioritize cost_price if both exist)
                if ($hasCostPrice) {
                    $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
                } elseif ($hasCost) {
                    $costColumn = 'COALESCE(p.cost, 0)';
                } else {
                    $costColumn = '0'; // No cost column found
                }
                
                if ($hasRepairAccessories) {
                    // Calculate labour cost and parts cost
                    $repairerCostQuery = $db->prepare("
                        SELECT 
                            -- Labour Cost
                            COALESCE(SUM(COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as labour_cost,
                            -- Parts Cost: cost * quantity
                            COALESCE(SUM({$costColumn} * ra.quantity), 0) as parts_cost
                        FROM repairs_new r
                        LEFT JOIN repair_accessories ra ON r.id = ra.repair_id
                        LEFT JOIN {$productsTable} p ON ra.product_id = p.id AND p.company_id = r.company_id
                        WHERE r.company_id = ? AND r.created_at >= ? AND r.created_at <= ?
                    ");
                    $repairerCostQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                    $repairerCostResult = $repairerCostQuery->fetch(\PDO::FETCH_ASSOC);
                    $labourCost = (float)($repairerCostResult['labour_cost'] ?? 0);
                    $partsCost = (float)($repairerCostResult['parts_cost'] ?? 0);
                    $repairerCost = $labourCost + $partsCost;
                } else {
                    // Only labour cost if no repair_accessories table
                    $repairerCostQuery = $db->prepare("
                        SELECT COALESCE(SUM(COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as labour_cost
                        FROM repairs_new r
                        WHERE r.company_id = ? AND r.created_at >= ? AND r.created_at <= ?
                    ");
                    $repairerCostQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
                    $repairerCost = (float)($repairerCostQuery->fetch()['labour_cost'] ?? 0);
                }
            }
        } catch (\Exception $e) {
            error_log("Error calculating repairer cost: " . $e->getMessage());
            $repairerCost = 0;
        }
        
        // Calculate swap cost from actual cost price of swapped items (Selling Price - Cost Price = Profit)
        // Swap cost = Sum of cost prices of all company products given in swaps
        // This matches the audit trail calculation for consistency
        $swapCost = 0;
        if ($swapRevenue > 0) {
            try {
                // Check which products table exists
                $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
                
                // Check which cost columns exist
                $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                $checkPurchasePrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'purchase_price'");
                $hasCostPrice = $checkCostPrice->rowCount() > 0;
                $hasCost = $checkCost->rowCount() > 0;
                $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
                
                // Determine cost column to use (prioritize cost_price, then cost, then purchase_price)
                $costColumn = '0';
                if ($hasCostPrice) {
                    $costColumn = 'COALESCE(p.cost_price, 0)';
                } elseif ($hasCost) {
                    $costColumn = 'COALESCE(p.cost, 0)';
                } elseif ($hasPurchasePrice) {
                    $costColumn = 'COALESCE(p.purchase_price, 0)';
                }
                
                // Calculate swap cost: Sum of cost prices of company products given in swaps
                $swapCostQuery = $db->prepare("
                    SELECT COALESCE(SUM({$costColumn}), 0) as total_cost
                    FROM swaps s
                    LEFT JOIN {$productsTable} p ON s.company_product_id = p.id AND p.company_id = s.company_id
                    WHERE s.company_id = ?
                    AND DATE(s.created_at) >= ?
                    AND DATE(s.created_at) <= ?
                ");
                $swapCostQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapCostResult = $swapCostQuery->fetch(\PDO::FETCH_ASSOC);
                $swapCost = floatval($swapCostResult['total_cost'] ?? 0);
                
                error_log("Financial Summary: Swap cost calculated from products table: ₵{$swapCost} (using {$costColumn})");
            } catch (\Exception $e) {
                error_log("Financial Summary: Error calculating swap cost from products: " . $e->getMessage());
                // Fallback: calculate from revenue and profit if available
                if ($swapRevenue > 0 && $swapProfit > 0) {
                    $swapCost = $swapRevenue - $swapProfit;
                    error_log("Financial Summary: Using fallback swap cost calculation: ₵{$swapCost}");
                }
            }
        }
        
        if ($swapCost < 0) {
            error_log("Financial Summary WARNING: Negative swap cost detected (₵{$swapCost}), setting to 0");
            $swapCost = 0;
        }
        
        // Revenue = Sales Revenue (from POS sales by salesperson and technician) + Repair Revenue (from technicians)
        // Note: Resold swap items are already included in sales revenue as they are regular POS sales
        // Revenue should NOT include swap profit - profit is calculated separately
        $totalRevenue = $salesRevenue + $repairRevenue;
        
        // Total cost = Sales Cost + Repairer Cost (NO swap cost in total - swap profit already accounts for it)
        $totalCost = $salesCost + $repairerCost;
        
        // Validate and prevent anomalies
        // Ensure cost is not negative
        if ($totalCost < 0) {
            error_log("Financial Summary WARNING: Negative cost detected (₵{$totalCost}), setting to 0");
            $totalCost = 0;
        }
        
        // Ensure cost doesn't exceed revenue (unless it's a legitimate loss)
        // But log it as a warning
        if ($totalCost > $totalRevenue && $totalRevenue > 0) {
            error_log("Financial Summary WARNING: Cost (₵{$totalCost}) exceeds Revenue (₵{$totalRevenue}) - possible data issue or legitimate loss");
        }
        
        // Calculate profit as Selling Price - Cost Price (Revenue - Cost)
        // This is the standard profit formula: Profit = Revenue - Cost
        // ALWAYS calculate profit as Revenue - Cost (matching audit trail logic)
        // This is the unified formula for all items: Sales, Swaps, and Repairs
        $salesProfit = $salesRevenue - $salesCost;
        if ($salesProfit < 0) $salesProfit = 0;
        
        // Note: $repairerProfit is already calculated earlier in this function (around line 4660)
        // Ensure repairer profit is not negative
        if ($repairerProfit < 0) $repairerProfit = 0;
        
        // Calculate total profit = Sales Profit + Swap Profit + Repairer Profit
        // Swap profit is the realized gain from swap transactions (when customer item is resold)
        // This is separate from sales revenue - it's the additional profit from the swap transaction itself
        // Note: Resold swap items are included in sales revenue, but swap profit is the realized gain
        // from the swap transaction (difference between customer item value and resale price, minus costs)
        $totalProfit = $salesProfit + $swapProfit + $repairerProfit;
        
        // Profit cannot be negative
        if ($totalProfit < 0) {
            error_log("Financial Summary WARNING: Calculated profit is negative (₵{$totalProfit}). Sales Profit: ₵{$salesProfit}, Swap Profit: ₵{$swapProfit}, Repairer Profit: ₵{$repairerProfit}. Setting profit to 0.");
            $totalProfit = 0;
        }
        
        // Calculate profit margin based on total revenue (sales + repairs)
        // Note: Swap profit is separate and not included in revenue calculation
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
        
        // Round values to 2 decimal places to prevent floating point anomalies
        $totalRevenue = round($totalRevenue, 2);
        $totalCost = round($totalCost, 2);
        $totalProfit = round($totalProfit, 2);
        $profitMargin = round($profitMargin, 2);
        $salesProfit = round($salesProfit, 2);
        
        error_log("Financial Summary - Revenue: ₵{$totalRevenue} (Sales: ₵{$salesRevenue} + Repairs: ₵{$repairRevenue}), Cost: ₵{$totalCost} (Sales: ₵{$salesCost} + Repairer: ₵{$repairerCost}), Sales Profit: ₵{$salesProfit}, Swap Profit: ₵{$swapProfit}, Repairer Profit: ₵{$repairerProfit}, Total Profit: ₵{$totalProfit}, Margin: {$profitMargin}%");
        error_log("Financial Summary Breakdown - Sales Revenue: ₵{$salesRevenue}, Sales Cost: ₵{$salesCost}, Sales Profit: ₵{$salesProfit}, Repair Revenue: ₵{$repairRevenue}, Repairer Cost: ₵{$repairerCost}, Repairer Profit: ₵{$repairerProfit}, Swap Revenue (value given): ₵{$swapRevenue}, Swap Cost: ₵{$swapCost}, Swap Profit (realized): ₵{$swapProfit}");
        
        return [
            'total_revenue' => (float)$totalRevenue, // Already rounded
            'total_cost' => (float)$totalCost, // Already rounded
            'sales_revenue' => (float)round($salesRevenue, 2),
            'sales_cost' => (float)round($salesCost, 2),
            'sales_profit' => (float)$salesProfit, // Sales profit (revenue - cost) from inventory products
            'swap_revenue' => (float)round($swapRevenue, 2),
            'swap_total_value' => (float)round($swapRevenue, 2), // Alias for compatibility
            'swap_cost' => (float)round($swapCost, 2),
            'swap_profit' => (float)round($swapProfit, 2),
            'repair_revenue' => (float)round($repairRevenue, 2),
            'repair_parts_count' => (int)$repairPartsCount, // Number of products sold as spare parts
            'repairer_profit' => (float)round($repairerProfit, 2),
            'repairer_cost' => (float)round($repairerCost, 2),
            'total_profit' => (float)$totalProfit, // Total profit = Sales Profit + Swap Profit + Repairer Profit
            'profit_margin' => (float)$profitMargin // Already rounded above
        ];
    }
    
    /**
     * Get staff performance
     */
    private function getStaffPerformance($companyId, $dateFrom, $dateTo) {
        $db = \Database::getInstance()->getConnection();
        
        try {
            // Check if repairs_new table exists
            $checkRepairsTable = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsTable->rowCount() > 0;
            
            if ($hasRepairsNew) {
                $staffQuery = $db->prepare("
                    SELECT 
                        u.id,
                        u.username as name,
                        u.role,
                        COUNT(DISTINCT ps.id) as sales_count,
                        COUNT(DISTINCT s.id) as swaps_count,
                        COUNT(DISTINCT r.id) as repairs_count,
                        COALESCE(SUM(ps.final_amount), 0) as sales_revenue
                    FROM users u
                    LEFT JOIN pos_sales ps ON ps.created_by_user_id = u.id 
                        AND DATE(ps.created_at) BETWEEN ? AND ?
                        AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))
                    LEFT JOIN swaps s ON s.salesperson_id = u.id AND DATE(s.created_at) BETWEEN ? AND ?
                    LEFT JOIN repairs_new r ON r.assigned_technician_id = u.id AND DATE(r.created_at) BETWEEN ? AND ?
                    WHERE u.company_id = ? AND u.role IN ('salesperson', 'technician', 'manager')
                    GROUP BY u.id, u.username, u.role
                    ORDER BY sales_revenue DESC
                ");
            } else {
                $staffQuery = $db->prepare("
                    SELECT 
                        u.id,
                        u.username as name,
                        u.role,
                        COUNT(DISTINCT ps.id) as sales_count,
                        COUNT(DISTINCT s.id) as swaps_count,
                        0 as repairs_count,
                        COALESCE(SUM(ps.final_amount), 0) as sales_revenue
                    FROM users u
                    LEFT JOIN pos_sales ps ON ps.created_by_user_id = u.id 
                        AND DATE(ps.created_at) BETWEEN ? AND ?
                        AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))
                    LEFT JOIN swaps s ON s.salesperson_id = u.id AND DATE(s.created_at) BETWEEN ? AND ?
                    WHERE u.company_id = ? AND u.role IN ('salesperson', 'technician', 'manager')
                    GROUP BY u.id, u.username, u.role
                    ORDER BY sales_revenue DESC
                ");
            }
            
            if ($hasRepairsNew) {
                $staffQuery->execute([$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $companyId]);
            } else {
                $staffQuery->execute([$dateFrom, $dateTo, $dateFrom, $dateTo, $companyId]);
            }
            
            $staff = $staffQuery->fetchAll(\PDO::FETCH_ASSOC);
            return $staff;
        } catch (\Exception $e) {
            error_log("Error getting staff performance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get company-specific statistics
     */
    private function getCompanyStats($companyId, $role = null) {
        // Get enabled modules for this company (system admin sees all)
        $companyModule = new CompanyModule();
        $enabledModuleKeys = ($role === 'system_admin') 
            ? ['products_inventory', 'pos_sales', 'swap', 'repairs', 'customers', 'staff_management', 'reports_analytics']
            : $companyModule->getEnabledModules($companyId);
        
        $db = \Database::getInstance()->getConnection();
        
        $stats = [];
        
        // Get company sales stats (only if POS module enabled)
        // EXCLUDE swap transactions - swaps should only be tracked on swap page
        // Use swap_id IS NULL to exclude all swap-related sales
        if (in_array('pos_sales', $enabledModuleKeys)) {
            $salesQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total_sales,
                    SUM(final_amount) as total_revenue,
                    AVG(final_amount) as avg_sale
                FROM pos_sales 
                WHERE company_id = ?
                AND swap_id IS NULL
            ");
            $salesQuery->execute([$companyId]);
            $salesData = $salesQuery->fetch();
            
            $stats['total_sales'] = (int)($salesData['total_sales'] ?? 0);
            $stats['total_revenue'] = (float)($salesData['total_revenue'] ?? 0);
            $stats['avg_sale'] = (float)($salesData['avg_sale'] ?? 0);
        }
        
        // Get company repairs stats (only if repairs module enabled)
        if (in_array('repairs', $enabledModuleKeys)) {
            $repairsStats = null;
            try {
                $repairsQuery = $db->prepare("
                    SELECT 
                        COUNT(*) as total_repairs,
                        SUM(COALESCE(total_cost, 0)) as total_repair_revenue,
                        SUM(CASE WHEN repair_status = 'pending' OR repair_status = 'PENDING' THEN 1 ELSE 0 END) as pending_repairs,
                        SUM(CASE WHEN repair_status = 'in_progress' OR repair_status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_repairs,
                        SUM(CASE WHEN repair_status = 'completed' OR repair_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_repairs
                    FROM repairs_new 
                    WHERE company_id = ?
                ");
                $repairsQuery->execute([$companyId]);
                $repairsStats = $repairsQuery->fetch();
            } catch (\Exception $e) {
                // Fallback to repairs table
                try {
                    $repairsQuery = $db->prepare("
                        SELECT 
                            COUNT(*) as total_repairs,
                            SUM(COALESCE(repair_cost, 0)) as total_repair_revenue,
                            0 as pending_repairs,
                            0 as in_progress_repairs,
                            0 as completed_repairs
                        FROM repairs 
                        WHERE company_id = ?
                    ");
                    $repairsQuery->execute([$companyId]);
                    $repairsStats = $repairsQuery->fetch();
                } catch (\Exception $e2) {
                    $repairsStats = ['total_repairs' => 0, 'total_repair_revenue' => 0, 'pending_repairs' => 0, 'in_progress_repairs' => 0, 'completed_repairs' => 0];
                }
            }
            
            $stats['total_repairs'] = (int)($repairsStats['total_repairs'] ?? 0);
            $stats['repair_revenue'] = (float)($repairsStats['total_repair_revenue'] ?? 0);
            $stats['pending_repairs'] = (int)($repairsStats['pending_repairs'] ?? 0);
            $stats['in_progress_repairs'] = (int)($repairsStats['in_progress_repairs'] ?? 0);
            $stats['completed_repairs'] = (int)($repairsStats['completed_repairs'] ?? 0);
        }
        
        // Get swap stats using getSwapStatistics() - same method as swap page
        // This ensures consistency: profit is only realized when customer item is resold AND profit is finalized
        if (in_array('swap', $enabledModuleKeys)) {
            $swapStats = [];
            try {
                // Use getSwapStatistics() which uses the same logic as SwapController::index()
                // This ensures the profit calculation matches exactly what's shown on the swap page
                // Pass null for date range to get all-time stats (same as swap page)
                $swapStatsData = $this->getSwapStatistics($companyId, null, null);
                
                // Debug logging
                error_log("DashboardController getCompanyStats: Swap stats data - total_profit: " . ($swapStatsData['total_profit'] ?? 'null') . ", estimated_profit: " . ($swapStatsData['estimated_profit'] ?? 'null'));
                
                $swapStats = [
                    'total_swaps' => (int)($swapStatsData['total_swaps'] ?? 0),
                    'total_swap_value' => (float)($swapStatsData['total_value'] ?? 0),
                    'in_stock_items' => (int)($swapStatsData['in_stock_items'] ?? 0),
                    'sold_items' => (int)($swapStatsData['sold_items'] ?? 0),
                    'total_estimated_profit' => (float)($swapStatsData['estimated_profit'] ?? 0),
                    'total_final_profit' => (float)($swapStatsData['total_profit'] ?? 0), // Realized gains only - same as swap page
                    'realized_profit_count' => 0 // Not tracked in getSwapStatistics, but that's okay
                ];
                
                error_log("DashboardController getCompanyStats: Swap profit set to: ₵" . $swapStats['total_final_profit']);
                
                // Get cash received from swap model (not calculated in getSwapStatistics)
                try {
                    $swapModel = new \App\Models\Swap();
                    $swapModelStats = $swapModel->getStats($companyId);
                    $swapStats['total_cash_received'] = (float)($swapModelStats['total_cash_received'] ?? 0);
                } catch (\Exception $e) {
                    $swapStats['total_cash_received'] = 0;
                }
            } catch (\Exception $e) {
                error_log("DashboardController: Error loading swap stats - " . $e->getMessage());
                $swapStats = [
                    'total_swaps' => 0,
                    'total_swap_value' => 0,
                    'total_cash_received' => 0,
                    'in_stock_items' => 0,
                    'sold_items' => 0,
                    'total_estimated_profit' => 0,
                    'total_final_profit' => 0,
                    'realized_profit_count' => 0
                ];
            }
            
            $stats['swaps'] = $swapStats;
        }
        
        // Get product stats (only if products_inventory module enabled)
        if (in_array('products_inventory', $enabledModuleKeys)) {
            $productStats = [];
            try {
                $productModel = new \App\Models\Product();
                $prodStats = $productModel->getStats($companyId);
                
                $productStats = [
                    'total_products' => (int)($prodStats['total_products'] ?? 0),
                    'available_products' => (int)($prodStats['available_products'] ?? 0),
                    'out_of_stock' => (int)($prodStats['out_of_stock'] ?? 0),
                    'total_quantity' => (int)($prodStats['total_quantity'] ?? 0),
                    'inventory_value' => (float)($prodStats['total_value'] ?? 0),
                    'swap_available' => (int)($prodStats['swap_available'] ?? 0)
                ];
            } catch (\Exception $e) {
                error_log("DashboardController: Error loading product stats - " . $e->getMessage());
                $productStats = [
                    'total_products' => 0,
                    'available_products' => 0,
                    'out_of_stock' => 0,
                    'total_quantity' => 0,
                    'inventory_value' => 0,
                    'swap_available' => 0
                ];
            }
            
            $stats['products'] = $productStats;
        }
        
        // Calculate total profit (Sales profit + Swap profit)
        $totalProfit = 0;
        $salesProfit = 0;
        $swapProfit = 0;
        
        // Calculate sales profit (revenue - cost from sales history)
        if (in_array('pos_sales', $enabledModuleKeys)) {
            try {
                // Determine which products table exists
                $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
                
                // Check which cost column exists (prioritize cost_price, then cost)
                $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                $hasCostPrice = $checkCostPrice->rowCount() > 0;
                $hasCost = $checkCost->rowCount() > 0;
                
                // Determine cost column to use
                if ($hasCostPrice) {
                    $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
                } elseif ($hasCost) {
                    $costColumn = 'COALESCE(p.cost, 0)';
                } else {
                    $costColumn = '0';
                }
                
                // Calculate sales profit: revenue - cost
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                // Use same logic as sales history page (POSController::apiSales)
                // Check if is_swap_mode column exists
                $checkIsSwapMode = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
                $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
                
                // Build WHERE clause to exclude swap sales (same as sales history page)
                $whereClause = "ps.company_id = ?";
                if ($hasIsSwapMode) {
                    $whereClause .= " AND (ps.is_swap_mode = 0 OR ps.is_swap_mode IS NULL)";
                } else {
                    // Fallback: use swap_id IS NULL if is_swap_mode doesn't exist
                    $checkSwapId = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'swap_id'");
                    $hasSwapId = $checkSwapId->rowCount() > 0;
                    if ($hasSwapId) {
                        $whereClause .= " AND ps.swap_id IS NULL";
                    }
                }
                
                $salesProfitQuery = $db->prepare("
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
                $salesProfitQuery->execute([$companyId]);
                $salesProfitData = $salesProfitQuery->fetch(\PDO::FETCH_ASSOC);
                
                $salesRevenue = floatval($salesProfitData['revenue'] ?? 0);
                $salesCost = floatval($salesProfitData['cost'] ?? 0);
                $salesProfit = $salesRevenue - $salesCost;
                if ($salesProfit < 0) $salesProfit = 0;
                
                // Debug logging
                error_log("DashboardController getCompanyStats: Sales Profit Calculation - Revenue: ₵{$salesRevenue}, Cost: ₵{$salesCost}, Profit: ₵{$salesProfit}");
            } catch (\Exception $e) {
                error_log("DashboardController: Error calculating sales profit - " . $e->getMessage());
                $salesProfit = 0;
            }
        }
        
        // Get swap profit (realized gains from swaps)
        if (in_array('swap', $enabledModuleKeys) && isset($stats['swaps'])) {
            // Use the total_final_profit from swap stats (realized gains)
            $swapProfit = floatval($stats['swaps']['total_final_profit'] ?? 0);
            error_log("DashboardController getCompanyStats: Using swap profit from stats: ₵{$swapProfit}");
        } else {
            error_log("DashboardController getCompanyStats: Swap module not enabled or swap stats not found");
        }
        
        // Total profit = Sales profit + Swap profit
        $totalProfit = $salesProfit + $swapProfit;
        
        error_log("DashboardController getCompanyStats: Final profit calculation - Sales: ₵{$salesProfit}, Swap: ₵{$swapProfit}, Total: ₵{$totalProfit}");
        
        $stats['total_profit'] = round($totalProfit, 2);
        $stats['sales_profit'] = round($salesProfit, 2);
        $stats['swap_profit'] = round($swapProfit, 2);
        
        return $stats;
    }
    
    /**
     * Export dashboard summary report
     * Exports comprehensive dashboard metrics in CSV, Excel, or PDF format
     */
    public function exportDashboard() {
        // Clean any existing output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            $companyId = null;
            $role = null;
            
            // Try Authorization header first (Bearer token)
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    
                    if (!in_array($payload->role, ['manager', 'admin', 'system_admin'], true)) {
                        throw new \Exception('Unauthorized role');
                    }
                    
                    $companyId = $payload->company_id ?? null;
                    $role = $payload->role;
                } catch (\Exception $e) {
                    error_log("Token validation failed in exportDashboard: " . $e->getMessage());
                }
            }
            
            // Fallback to session-based user
            if ($companyId === null) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $user = $_SESSION['user'] ?? null;
                if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'admin', 'system_admin'], true)) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                
                $companyId = $user['company_id'] ?? null;
                $role = $user['role'] ?? 'manager';
            }
            
            if (!$companyId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Company ID is required']);
                exit;
            }
            
            // Get format and date range
            $format = $_GET['format'] ?? 'csv';
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Get comprehensive dashboard stats
            $stats = $this->getComprehensiveManagerStats($companyId, $dateFrom, $dateTo);
            
            // Format data for export
            $exportData = [];
            
            // 1. Overview Section
            $exportData[] = ['Section', 'Metric', 'Value'];
            $exportData[] = ['Overview', 'Total Products', $stats['overview']['total_products'] ?? 0];
            $exportData[] = ['Overview', 'Today Sales', '₵' . number_format($stats['overview']['today_sales'] ?? 0, 2)];
            $exportData[] = ['Overview', 'Today Swaps', $stats['overview']['today_swaps'] ?? 0];
            $exportData[] = ['Overview', 'Today Repairs', $stats['overview']['today_repairs'] ?? 0];
            $exportData[] = ['Overview', 'Total Customers', $stats['overview']['total_customers'] ?? 0];
            $exportData[] = ['Overview', 'Active Staff', $stats['overview']['active_staff'] ?? 0];
            $exportData[] = []; // Empty row
            
            // 2. Financial Summary Section
            $financial = $stats['financial'] ?? [];
            $exportData[] = ['Financial Summary', 'Metric', 'Value'];
            $exportData[] = ['Financial Summary', 'Total Revenue', '₵' . number_format($financial['total_revenue'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Sales Revenue', '₵' . number_format($financial['sales_revenue'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Repair Revenue', '₵' . number_format($financial['repair_revenue'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Repair Parts Sold', $financial['repair_parts_count'] ?? 0];
            $exportData[] = ['Financial Summary', 'Swap Profit', '₵' . number_format($financial['swap_profit'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Total Cost', '₵' . number_format($financial['total_cost'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Total Profit', '₵' . number_format($financial['total_profit'] ?? 0, 2)];
            $exportData[] = ['Financial Summary', 'Profit Margin', number_format($financial['profit_margin'] ?? 0, 2) . '%'];
            $exportData[] = []; // Empty row
            
            // 3. Sales Statistics
            $sales = $stats['sales'] ?? [];
            $exportData[] = ['Sales Statistics', 'Metric', 'Value'];
            $exportData[] = ['Sales Statistics', 'Total Sales', $sales['total_sales'] ?? 0];
            $exportData[] = ['Sales Statistics', 'Total Transactions', $sales['total_transactions'] ?? 0];
            $exportData[] = []; // Empty row
            
            // 4. Repairs Statistics
            $repairs = $stats['repairs'] ?? [];
            $exportData[] = ['Repairs Statistics', 'Metric', 'Value'];
            $exportData[] = ['Repairs Statistics', 'Total Repairs', $repairs['total'] ?? 0];
            $exportData[] = ['Repairs Statistics', 'Pending', $repairs['pending'] ?? 0];
            $exportData[] = ['Repairs Statistics', 'In Progress', $repairs['ongoing'] ?? 0];
            $exportData[] = ['Repairs Statistics', 'Completed', $repairs['completed'] ?? 0];
            $exportData[] = []; // Empty row
            
            // 5. Swaps Statistics
            $swaps = $stats['swaps'] ?? [];
            $exportData[] = ['Swaps Statistics', 'Metric', 'Value'];
            $exportData[] = ['Swaps Statistics', 'Total Swaps', $swaps['total'] ?? 0];
            $exportData[] = ['Swaps Statistics', 'Pending', $swaps['pending'] ?? 0];
            $exportData[] = ['Swaps Statistics', 'Completed', $swaps['completed'] ?? 0];
            $exportData[] = ['Swaps Statistics', 'Total Profit', '₵' . number_format($swaps['total_profit'] ?? 0, 2)];
            $exportData[] = []; // Empty row
            
            // 6. Products/Inventory Statistics
            $products = $stats['products'] ?? [];
            $exportData[] = ['Inventory Statistics', 'Metric', 'Value'];
            $exportData[] = ['Inventory Statistics', 'Total Products', $products['total_products'] ?? 0];
            $exportData[] = ['Inventory Statistics', 'Available Products', $products['available_products'] ?? 0];
            $exportData[] = ['Inventory Statistics', 'Out of Stock', $products['out_of_stock'] ?? 0];
            $exportData[] = ['Inventory Statistics', 'Total Quantity', $products['total_quantity'] ?? 0];
            $exportData[] = ['Inventory Statistics', 'Inventory Value', '₵' . number_format($products['inventory_value'] ?? 0, 2)];
            $exportData[] = []; // Empty row
            
            // 7. Date Range
            $exportData[] = ['Report Period', 'From', $dateFrom];
            $exportData[] = ['Report Period', 'To', $dateTo];
            $exportData[] = ['Report Generated', 'Date', date('Y-m-d H:i:s')];
            
            // Initialize ExportService
            $exportService = new ExportService();
            
            // Generate filename
            $filename = 'dashboard_report_' . date('Ymd_His') . '.' . ($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));
            $title = 'Manager Dashboard Report - ' . $dateFrom . ' to ' . $dateTo;
            
            // Export based on format
            if ($format === 'xlsx') {
                $exportService->exportExcel($exportData, $filename, $title);
            } elseif ($format === 'pdf') {
                $exportService->exportPDF($exportData, $filename, $title);
            } else {
                $exportService->exportCSV($exportData, $filename);
            }
            
        } catch (\Exception $e) {
            error_log("Dashboard export error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if a module is enabled for the company
     * GET /api/dashboard/check-module?module_key=dashboard_charts
     */
    public function checkModule() {
        // Start session first (for web dashboard calls)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $payload = null;
            
            // Try session-based auth first (for web dashboard)
            $user = $_SESSION['user'] ?? null;
            if ($user && is_array($user)) {
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
            } else {
                // If no session, try JWT auth (for API calls)
                $headers = function_exists('getallheaders') ? getallheaders() : [];
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                
                if (strpos($authHeader, 'Bearer ') === 0) {
                    try {
                        $token = substr($authHeader, 7);
                        $auth = new AuthService();
                        $payload = $auth->validateToken($token);
                        
                        // Check role-based access
                        $allowedRoles = ['manager', 'admin', 'system_admin', 'salesperson'];
                        if (!in_array($payload->role, $allowedRoles, true)) {
                            throw new \Exception('Unauthorized role');
                        }
                    } catch (\Exception $e) {
                        ob_end_clean();
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
                        return;
                    }
                } else {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    return;
                }
            }
            
            $companyId = $payload->company_id ?? null;
            $moduleKey = $_GET['module_key'] ?? null;
            
            if (!$companyId || !$moduleKey) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID and module key required']);
                return;
            }
            
            $enabled = CompanyModule::isEnabled($companyId, $moduleKey);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'enabled' => $enabled
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Toggle module status for the company
     * POST /api/dashboard/toggle-module
     */
    public function toggleModule() {
        // Start session first (for web dashboard calls)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $payload = null;
            
            // Try session-based auth first (for web dashboard)
            $user = $_SESSION['user'] ?? null;
            if ($user && is_array($user)) {
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
            } else {
                // If no session, try JWT auth (for API calls)
                $headers = function_exists('getallheaders') ? getallheaders() : [];
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                
                if (strpos($authHeader, 'Bearer ') === 0) {
                    try {
                        $token = substr($authHeader, 7);
                        $auth = new AuthService();
                        $payload = $auth->validateToken($token);
                        
                        // Check role-based access
                        $allowedRoles = ['manager', 'admin', 'system_admin'];
                        if (!in_array($payload->role, $allowedRoles, true)) {
                            throw new \Exception('Unauthorized role');
                        }
                    } catch (\Exception $e) {
                        ob_end_clean();
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
                        return;
                    }
                } else {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    return;
                }
            }
            
            $companyId = $payload->company_id ?? null;
            $input = json_decode(file_get_contents('php://input'), true);
            $moduleKey = $input['module_key'] ?? null;
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : null;
            
            if (!$companyId || !$moduleKey || $enabled === null) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID, module key, and enabled status required']);
                return;
            }
            
            $moduleModel = new CompanyModule();
            $result = $moduleModel->setModuleStatus($companyId, $moduleKey, $enabled);
            
            if ($result) {
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Module status updated successfully',
                    'enabled' => $enabled
                ]);
            } else {
                throw new \Exception('Failed to update module status');
            }
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get charts data for dashboard
     * GET /api/dashboard/charts-data
     */
    public function chartsData() {
        // Start session FIRST before any output buffering (session params set in config/app.php)
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start(); // Suppress warnings
            }
        } catch (\Exception $e) {
            error_log("Charts data: Session start error: " . $e->getMessage());
        }
        
        // Clean any output buffers (after session is started)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set headers first
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            // Get user from session
            $user = $_SESSION['user'] ?? null;
            
            if (!$user) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                return;
            }
            
            $companyId = $user['company_id'] ?? null;
            
            if (!$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID required']);
                return;
            }
            
            // Get database connection
            try {
                // Ensure Database class is loaded
                if (!class_exists('Database')) {
                    require_once __DIR__ . '/../../config/database.php';
                }
                
                $db = \Database::getInstance()->getConnection();
                if (!$db) {
                    throw new \Exception('Database connection failed');
                }
            } catch (\Exception $e) {
                ob_end_clean();
                http_response_code(500);
                error_log("Charts data: Database connection error: " . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database connection failed',
                    'debug' => [
                        'message' => $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ]
                ]);
                return;
            }
            
            // Sales Trends - Last 7 days
            $salesTrends = [];
            $labels = [];
            $revenue = [];
            
            try {
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dayName = date('D', strtotime($date));
                    $labels[] = $dayName;
                    
                    try {
                        $query = $db->prepare("
                            SELECT COALESCE(SUM(final_amount), 0) as total
                            FROM pos_sales
                            WHERE company_id = ? AND DATE(created_at) = ?
                        ");
                        $query->execute([$companyId, $date]);
                        $result = $query->fetch(\PDO::FETCH_ASSOC);
                        $revenue[] = floatval($result['total'] ?? 0);
                    } catch (\Exception $e) {
                        error_log("Charts data: Error getting revenue for date {$date}: " . $e->getMessage());
                        $revenue[] = 0;
                    }
                }
            } catch (\Exception $e) {
                error_log("Charts data: Error building sales trends: " . $e->getMessage());
                // Set default values
                $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                $revenue = [0, 0, 0, 0, 0, 0, 0];
            }
            
            $salesTrends = [
                'labels' => $labels,
                'revenue' => $revenue
            ];
            
            // Activity Distribution - Count of different activities
            $activityDistribution = [];
            
            // Sales count
            $salesCount = 0;
            try {
                $salesQuery = $db->prepare("SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ?");
                $salesQuery->execute([$companyId]);
                $salesCount = (int)($salesQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Charts data: Error getting sales count: " . $e->getMessage());
            }
            
            // Repairs count - try repairs_new first, fallback to repairs
            $repairsCount = 0;
            try {
                // Try repairs_new first
                try {
                    $repairsQuery = $db->prepare("SELECT COUNT(*) as count FROM repairs_new WHERE company_id = ?");
                    $repairsQuery->execute([$companyId]);
                    $repairsCount = (int)($repairsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
                } catch (\Exception $e) {
                    // repairs_new doesn't exist or failed, try repairs table
                    try {
                        $repairsQuery = $db->prepare("SELECT COUNT(*) as count FROM repairs WHERE company_id = ?");
                        $repairsQuery->execute([$companyId]);
                        $repairsCount = (int)($repairsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
                    } catch (\Exception $e2) {
                        error_log("Charts data: Error getting repairs count from both tables: " . $e2->getMessage());
                        $repairsCount = 0;
                    }
                }
            } catch (\Exception $e) {
                error_log("Charts data: Error getting repairs count: " . $e->getMessage());
            }
            
            // Swaps count
            $swapsCount = 0;
            try {
                $swapsQuery = $db->prepare("SELECT COUNT(*) as count FROM swaps WHERE company_id = ?");
                $swapsQuery->execute([$companyId]);
                $swapsCount = (int)($swapsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Charts data: Error getting swaps count: " . $e->getMessage());
            }
            
            // Customers count
            $customersCount = 0;
            try {
                $customersQuery = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
                $customersQuery->execute([$companyId]);
                $customersCount = (int)($customersQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Charts data: Error getting customers count: " . $e->getMessage());
            }
            
            // Products count - try products_new first, fallback to products
            $productsCount = 0;
            try {
                // Try products_new first
                try {
                    $productsQuery = $db->prepare("SELECT COUNT(*) as count FROM products_new WHERE company_id = ?");
                    $productsQuery->execute([$companyId]);
                    $productsCount = (int)($productsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
                } catch (\Exception $e) {
                    // products_new doesn't exist or failed, try products table
                    try {
                        $productsQuery = $db->prepare("SELECT COUNT(*) as count FROM products WHERE company_id = ?");
                        $productsQuery->execute([$companyId]);
                        $productsCount = (int)($productsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
                    } catch (\Exception $e2) {
                        error_log("Charts data: Error getting products count from both tables: " . $e2->getMessage());
                        $productsCount = 0;
                    }
                }
            } catch (\Exception $e) {
                error_log("Charts data: Error getting products count: " . $e->getMessage());
            }
            
            // Technicians count
            $techniciansCount = 0;
            try {
                $techniciansQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM users 
                    WHERE company_id = ? AND role = 'technician'
                ");
                $techniciansQuery->execute([$companyId]);
                $techniciansCount = (int)($techniciansQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Charts data: Error getting technicians count: " . $e->getMessage());
            }
            
            $activityDistribution = [
                'labels' => ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'],
                'values' => [
                    (int)$salesCount,
                    (int)$repairsCount,
                    (int)$swapsCount,
                    (int)$customersCount,
                    (int)$productsCount,
                    (int)$techniciansCount
                ]
            ];
            
            ob_end_clean();
            
            // Ensure all values are JSON-serializable
            $response = [
                'success' => true,
                'data' => [
                    'sales_trends' => [
                        'labels' => array_map('strval', $labels),
                        'revenue' => array_map('floatval', $revenue)
                    ],
                    'activity_distribution' => [
                        'labels' => array_map('strval', $activityDistribution['labels']),
                        'values' => array_map('intval', $activityDistribution['values'])
                    ]
                ]
            ];
            
            $json = json_encode($response, JSON_NUMERIC_CHECK);
            if ($json === false) {
                throw new \Exception('JSON encoding failed: ' . json_last_error_msg());
            }
            
            echo $json;
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            $errorMsg = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            error_log("Charts data error: $errorMsg in $errorFile:$errorLine");
            error_log("Charts data stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false, 
                'error' => 'Internal server error',
                'debug' => [
                    'message' => $errorMsg,
                    'file' => basename($errorFile),
                    'line' => $errorLine,
                    'trace' => explode("\n", $e->getTraceAsString())
                ]
            ]);
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            $errorMsg = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            error_log("Charts data fatal error: $errorMsg in $errorFile:$errorLine");
            error_log("Charts data fatal stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false, 
                'error' => 'Fatal error',
                'debug' => [
                    'message' => $errorMsg,
                    'file' => basename($errorFile),
                    'line' => $errorLine,
                    'trace' => explode("\n", $e->getTraceAsString())
                ]
            ]);
        }
    }
}