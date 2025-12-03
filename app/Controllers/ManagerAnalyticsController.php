<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use App\Services\AuditService;
use App\Services\AlertService;
use App\Services\AnomalyDetectionService;
use App\Services\ForecastService;
use App\Services\BackupService;
use App\Models\CompanyModule;
use App\Models\SmartRecommendation;

class ManagerAnalyticsController {
    private $analyticsService;
    private $exportService;
    private $auditService;
    private $alertService;
    private $anomalyService;
    private $forecastService;
    private $backupService;

    public function __construct() {
        $this->analyticsService = new AnalyticsService();
        $this->exportService = new ExportService();
        $this->auditService = new AuditService();
        $this->alertService = new AlertService();
        $this->anomalyService = new AnomalyDetectionService();
        $this->forecastService = new ForecastService();
        $this->backupService = new BackupService();
    }

    /**
     * Get authenticated user from session
     */
    private function getAuthenticatedUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        
        return null;
    }

    /**
     * Check if module is enabled
     */
    private function checkModuleEnabled($companyId, $moduleKey, $userRole) {
        if ($userRole === 'system_admin') {
            return true;
        }
        
        if (!$companyId) {
            return false;
        }
        
        return CompanyModule::isEnabled($companyId, $moduleKey);
    }

    /**
     * Display the Audit Trail (Manager Analytics) page
     * GET /dashboard/audit-trail
     */
    public function index() {
        // Handle web authentication - managers and admins only
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }

        $companyId = $user['company_id'] ?? null;
        $userRole = $user['role'] ?? 'manager';
        
        if (!$companyId) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('Company association required'));
            exit;
        }

        $title = 'Audit Trail - Manager Analytics';
        $page = 'audit-trail';
        
        // Get enabled modules to filter UI
        $enabledModules = [];
        if ($userRole !== 'system_admin') {
            $moduleModel = new CompanyModule();
            $enabledModules = $moduleModel->getEnabledModules($companyId);
        }
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/manager_analytics.php';
        $content = ob_get_clean();
        
        // Set global variables for layout
        $GLOBALS['currentPage'] = 'audit-trail';
        $GLOBALS['user_data'] = $user;
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        $GLOBALS['pageTitle'] = $title;
        
        // Render layout
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Get overview analytics data (JSON API)
     * GET /api/analytics/overview
     */
    public function overview() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get date filters
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            // Get enabled modules
            $enabledModules = [];
            if ($userRole !== 'system_admin') {
                $moduleModel = new CompanyModule();
                $enabledModules = $moduleModel->getEnabledModules($companyId);
            }

            $metrics = [];

            // Sales stats (only if pos_sales module enabled)
            if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                $metrics['sales'] = $this->analyticsService->getSalesStats($companyId, $dateFrom, $dateTo);
            }

            // Repair stats (only if repairs module enabled)
            if ($userRole === 'system_admin' || in_array('repairs', $enabledModules)) {
                $metrics['repairs'] = $this->analyticsService->getRepairStats($companyId, $dateFrom, $dateTo);
            }

            // Swap stats (only if swap module enabled)
            if ($userRole === 'system_admin' || in_array('swap', $enabledModules)) {
                $metrics['swaps'] = $this->analyticsService->getSwapStats($companyId, $dateFrom, $dateTo);
            }

            // Inventory stats (always available if products_inventory module enabled)
            if ($userRole === 'system_admin' || in_array('products_inventory', $enabledModules)) {
                $metrics['inventory'] = $this->analyticsService->getInventoryStats($companyId);
            }

            // Profit stats (only if pos_sales module enabled)
            if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                $metrics['profit'] = $this->analyticsService->getProfitStats($companyId, $dateFrom, $dateTo);
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'enabled_modules' => $enabledModules
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics overview error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trace items across all modules
     * GET /api/analytics/trace
     */
    public function trace() {
        // Register shutdown function to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                    http_response_code(500);
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Fatal error: ' . $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ]);
                exit;
            }
        });
        
        // Suppress any error output
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set JSON header immediately
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $query = $_GET['q'] ?? $_GET['query'] ?? '';
            if (empty($query)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Search query required']);
                exit;
            }

            error_log("Trace API: Searching for query: {$query}, company_id: {$companyId}");
            
            // Test if swap exists directly
            $debugInfo = [];
            if (preg_match('/^SWAP-(\d+)$/i', $query, $matches)) {
                $swapId = (int)$matches[1];
                try {
                    $db = \Database::getInstance()->getConnection();
                    $testQuery = $db->prepare("SELECT id, company_id FROM swaps WHERE id = :swap_id");
                    $testQuery->execute(['swap_id' => $swapId]);
                    $swapTest = $testQuery->fetch(\PDO::FETCH_ASSOC);
                    if ($swapTest) {
                        $debugInfo['swap_exists'] = true;
                        $debugInfo['swap_company_id'] = $swapTest['company_id'] ?? 'NULL';
                        $debugInfo['searched_company_id'] = $companyId;
                        $debugInfo['company_match'] = ($swapTest['company_id'] == $companyId);
                    } else {
                        $debugInfo['swap_exists'] = false;
                    }
                } catch (\Exception $e) {
                    $debugInfo['test_error'] = $e->getMessage();
                    error_log("Trace API: Test query error: " . $e->getMessage());
                }
            }
            
            $results = $this->analyticsService->traceItem($companyId, $query);
            error_log("Trace API: Found " . count($results) . " results");

            ob_end_clean();
            
            // Include debug info in development
            $isDevelopment = (defined('APP_ENV') && APP_ENV === 'development') || 
                           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
            
            $response = [
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ];
            
            if ($isDevelopment && count($results) === 0) {
                // Add debug info when no results found
                $response['debug'] = array_merge([
                    'query' => $query,
                    'company_id' => $companyId,
                    'message' => 'Check PHP error log for detailed TraceItem logs'
                ], $debugInfo);
            }
            
            echo json_encode($response);
            exit;
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics trace error: " . $e->getMessage());
            error_log("Analytics trace error trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            $isDevelopment = (defined('APP_ENV') && APP_ENV === 'development') || 
                           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $isDevelopment ? $e->getTraceAsString() : null
            ]);
            exit;
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics trace fatal error: " . $e->getMessage());
            error_log("Analytics trace fatal error trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false,
                'error' => 'Fatal error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    /**
     * Get metrics totals for each active module
     * GET /api/analytics/metrics
     */
    public function metrics() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get date filters
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of month
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            // Get enabled modules
            $enabledModules = [];
            if ($userRole !== 'system_admin') {
                $moduleModel = new CompanyModule();
                $enabledModules = $moduleModel->getEnabledModules($companyId);
            }

            $metrics = [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'sales' => null,
                'repairs' => null,
                'swaps' => null,
                'inventory' => null,
                'profit' => null
            ];

            // Sales metrics
            if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                $salesStats = $this->analyticsService->getSalesStats($companyId, $dateFrom, $dateTo);
                $profitStats = $this->analyticsService->getProfitStats($companyId, $dateFrom, $dateTo);
                
                $metrics['sales'] = [
                    'today' => $salesStats['today'],
                    'monthly' => $salesStats['monthly'],
                    'period' => $salesStats['period'] ?? $salesStats['filtered'] ?? [
                        'count' => 0,
                        'revenue' => 0,
                        'avg_sale' => 0
                    ]
                ];
                
                $metrics['profit'] = $profitStats;
            }

            // Repair metrics
            if ($userRole === 'system_admin' || in_array('repairs', $enabledModules)) {
                $repairStats = $this->analyticsService->getRepairStats($companyId, $dateFrom, $dateTo);
                $metrics['repairs'] = [
                    'active' => $repairStats['active'],
                    'monthly' => $repairStats['monthly'],
                    'period' => $repairStats['period'] ?? $repairStats['filtered'] ?? [
                        'count' => 0,
                        'revenue' => 0
                    ]
                ];
            }

            // Swap metrics
            if ($userRole === 'system_admin' || in_array('swap', $enabledModules)) {
                $swapStats = $this->analyticsService->getSwapStats($companyId, $dateFrom, $dateTo);
                $metrics['swaps'] = [
                    'pending' => $swapStats['pending'],
                    'monthly' => $swapStats['monthly'],
                    'profit' => $swapStats['profit'],
                    'period' => $swapStats['period'] ?? $swapStats['filtered'] ?? [
                        'count' => 0,
                        'revenue' => 0,
                        'profit' => 0
                    ]
                ];
            }

            // Inventory metrics
            if ($userRole === 'system_admin' || in_array('products_inventory', $enabledModules)) {
                $inventoryStats = $this->analyticsService->getInventoryStats($companyId);
                $metrics['inventory'] = $inventoryStats;
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'enabled_modules' => $enabledModules
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics metrics error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get chart data for visualization
     * GET /api/analytics/charts
     */
    public function charts() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get chart type and date range
            $chartType = $_GET['type'] ?? 'all'; // revenue, profit, products, customers, all
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            // Get enabled modules
            $enabledModules = [];
            if ($userRole !== 'system_admin') {
                $moduleModel = new CompanyModule();
                $enabledModules = $moduleModel->getEnabledModules($companyId);
            }

            $charts = [];

            // Revenue/Time series chart
            if ($chartType === 'revenue' || $chartType === 'all') {
                $timeSeriesData = $this->analyticsService->getTimeSeriesData($companyId, $dateFrom, $dateTo, 'all');
                
                // Ensure we always have labels - generate if missing
                $labels = !empty($timeSeriesData['labels']) ? $timeSeriesData['labels'] : [];
                
                // If no labels, generate them from date range
                if (empty($labels)) {
                    $start = new \DateTime($dateFrom);
                    $end = new \DateTime($dateTo);
                    $end->modify('+1 day'); // Include end date
                    $interval = new \DateInterval('P1D');
                    $dateRange = new \DatePeriod($start, $interval, $end);
                    
                    foreach ($dateRange as $date) {
                        $labels[] = $date->format('Y-m-d');
                    }
                }
                
                // Ensure labels are sorted chronologically (oldest to newest) - left to right
                usort($labels, function($a, $b) {
                    return strcmp($a, $b); // Ascending order: oldest first
                });
                
                error_log("Charts API: Revenue labels count: " . count($labels) . ", date range: {$dateFrom} to {$dateTo}");
                error_log("Charts API: TimeSeriesData structure: " . json_encode([
                    'labels_count' => count($labels),
                    'sales_count' => count($timeSeriesData['sales'] ?? []),
                    'labels_sample' => array_slice($labels, 0, 5),
                    'labels_last5' => array_slice($labels, -5),
                    'sales_sample' => array_slice($timeSeriesData['sales'] ?? [], 0, 3),
                    'sales_last5' => array_slice($timeSeriesData['sales'] ?? [], -5)
                ]));
                
                $charts['revenue'] = [
                    'labels' => $labels,
                    'datasets' => []
                ];

                if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                    // Debug: Check raw sales data structure before extraction
                    error_log("Charts API: Raw sales data (first 5): " . json_encode(array_slice($timeSeriesData['sales'] ?? [], 0, 5)));
                    error_log("Charts API: Raw sales data (last 5): " . json_encode(array_slice($timeSeriesData['sales'] ?? [], -5)));
                    
                    $salesData = array_column($timeSeriesData['sales'] ?? [], 'revenue');
                    
                    error_log("Charts API: Sales data count: " . count($salesData) . ", labels count: " . count($labels));
                    if (count($salesData) > 0) {
                        $nonZeroCount = count(array_filter($salesData, function($val) { return $val > 0; }));
                        error_log("Charts API: Sales data has " . $nonZeroCount . " non-zero values");
                        error_log("Charts API: Sales data sample (first 10): " . json_encode(array_slice($salesData, 0, 10)));
                        error_log("Charts API: Sales data sample (last 10): " . json_encode(array_slice($salesData, -10)));
                        
                        // Check specifically for Nov 5 and Nov 6 (indices 88 and 89 if 91 labels)
                        if (count($labels) >= 90) {
                            $nov5Index = null;
                            $nov6Index = null;
                            foreach ($labels as $idx => $label) {
                                if ($label === '2025-11-05') $nov5Index = $idx;
                                if ($label === '2025-11-06') $nov6Index = $idx;
                            }
                            if ($nov5Index !== null) {
                                error_log("Charts API: Nov 5 at index {$nov5Index}, value: " . ($salesData[$nov5Index] ?? 'NOT SET'));
                            }
                            if ($nov6Index !== null) {
                                error_log("Charts API: Nov 6 at index {$nov6Index}, value: " . ($salesData[$nov6Index] ?? 'NOT SET'));
                            }
                        }
                    }
                    
                    // Ensure salesData matches labels length
                    if (count($salesData) !== count($labels)) {
                        error_log("Charts API: Mismatch - salesData: " . count($salesData) . ", labels: " . count($labels));
                        // Pad or trim salesData to match labels
                        if (count($salesData) < count($labels)) {
                            $salesData = array_pad($salesData, count($labels), 0);
                        } else {
                            $salesData = array_slice($salesData, 0, count($labels));
                        }
                    }
                    
                    if (!empty($salesData) || !empty($labels)) {
                        $charts['revenue']['datasets'][] = [
                            'label' => 'Sales',
                            'data' => $salesData,
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                            'tension' => 0.4
                        ];
                        error_log("Charts API: Added Sales dataset with " . count($salesData) . " data points");
                    }
                }

                if ($userRole === 'system_admin' || in_array('repairs', $enabledModules)) {
                    $repairsData = array_column($timeSeriesData['repairs'] ?? [], 'revenue');
                    if (!empty($repairsData) || !empty($labels)) {
                        $charts['revenue']['datasets'][] = [
                            'label' => 'Repairs',
                            'data' => $repairsData,
                            'borderColor' => 'rgb(234, 179, 8)',
                            'backgroundColor' => 'rgba(234, 179, 8, 0.6)',
                            'tension' => 0.4
                        ];
                    }
                }

                if ($userRole === 'system_admin' || in_array('swap', $enabledModules)) {
                    $swapsData = array_column($timeSeriesData['swaps'] ?? [], 'revenue');
                    if (!empty($swapsData) || !empty($labels)) {
                        $charts['revenue']['datasets'][] = [
                            'label' => 'Swaps',
                            'data' => $swapsData,
                            'borderColor' => 'rgb(34, 197, 94)',
                            'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                            'tension' => 0.4
                        ];
                    }
                }
                
                // If no datasets were added, add an empty one to prevent chart errors
                if (empty($charts['revenue']['datasets'])) {
                    $charts['revenue']['datasets'][] = [
                        'label' => 'Sales',
                        'data' => array_fill(0, count($labels), 0),
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                        'tension' => 0.4
                    ];
                }
            }

            // Profit breakdown chart
            if ($chartType === 'profit' || $chartType === 'all') {
                if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                    // Generate labels for full date range (same as revenue chart)
                    $profitLabels = [];
                    try {
                        $start = new \DateTime($dateFrom);
                        $end = new \DateTime($dateTo);
                        $end->modify('+1 day');
                        $interval = new \DateInterval('P1D');
                        $dateRange = new \DatePeriod($start, $interval, $end);
                        
                        foreach ($dateRange as $date) {
                            $profitLabels[] = $date->format('Y-m-d');
                        }
                    } catch (\Exception $e) {
                        error_log("Charts API: Error generating profit labels: " . $e->getMessage());
                        // Fallback
                        $current = strtotime($dateFrom);
                        $endTime = strtotime($dateTo);
                        while ($current <= $endTime) {
                            $profitLabels[] = date('Y-m-d', $current);
                            $current = strtotime('+1 day', $current);
                        }
                    }
                    
                    // Ensure profit labels are sorted chronologically (oldest to newest) - left to right
                    usort($profitLabels, function($a, $b) {
                        return strcmp($a, $b); // Ascending order: oldest first
                    });
                    
                    // Use date range from request for profit breakdown
                    $profitBreakdown = $this->getProfitBreakdownByDateRange($companyId, $dateFrom, $dateTo);
                    
                    error_log("Charts API: Profit breakdown count: " . count($profitBreakdown) . ", date range: {$dateFrom} to {$dateTo}");
                    error_log("Charts API: Profit labels count: " . count($profitLabels));
                    
                    // Create a map of profit data by date
                    $profitMap = [];
                    error_log("Charts API: Profit breakdown raw data: " . json_encode($profitBreakdown));
                    
                    // If profit breakdown is empty, try to get revenue data and calculate profit
                    if (empty($profitBreakdown)) {
                        error_log("Charts API: Profit breakdown is empty, trying to use revenue data from timeSeriesData");
                        // Get revenue data from timeSeriesData (which we know works)
                        $timeSeriesData = $this->analyticsService->getTimeSeriesData($companyId, $dateFrom, $dateTo, 'sales');
                        $salesData = $timeSeriesData['sales'] ?? [];
                        
                        // Create profit map from sales data (estimate cost as 70% of revenue)
                        foreach ($salesData as $index => $sale) {
                            if (isset($profitLabels[$index])) {
                                $dateKey = $profitLabels[$index];
                                // Normalize date
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                                    $dateKey = date('Y-m-d', strtotime($dateKey));
                                }
                                $revenue = (float)($sale['revenue'] ?? 0);
                                $cost = $revenue * 0.7; // Estimate cost as 70% of revenue
                                $profit = $revenue - $cost;
                                
                                if ($revenue > 0) {
                                    $profitMap[$dateKey] = [
                                        'revenue' => $revenue,
                                        'cost' => $cost,
                                        'profit' => $profit
                                    ];
                                }
                            }
                        }
                        error_log("Charts API: Created profit map from revenue data, count: " . count($profitMap));
                    } else {
                        foreach ($profitBreakdown as $row) {
                            $dateKey = $row['period'];
                            // Normalize date to Y-m-d format - DATE() function should return Y-m-d but handle edge cases
                            if (is_string($dateKey)) {
                                // If it contains time, extract just the date part
                                if (strpos($dateKey, ' ') !== false) {
                                    $dateKey = substr($dateKey, 0, 10);
                                }
                                // Only normalize if not already in Y-m-d format
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                                    $dateKey = date('Y-m-d', strtotime($dateKey));
                                }
                            } else {
                                $dateKey = date('Y-m-d', is_numeric($dateKey) ? $dateKey : strtotime($dateKey));
                            }
                            error_log("Charts API: Profit mapping date '{$row['period']}' -> '{$dateKey}' with revenue: " . $row['revenue'] . ", profit: " . $row['profit']);
                            $profitMap[$dateKey] = [
                                'revenue' => (float)$row['revenue'],
                                'cost' => (float)$row['cost'],
                                'profit' => (float)$row['profit']
                            ];
                        }
                    }
                    error_log("Charts API: Profit map keys: " . implode(', ', array_keys($profitMap)));
                    error_log("Charts API: Profit map sample: " . json_encode(array_slice($profitMap, 0, 3, true)));
                    
                    // Map profit data to all labels
                    $revenueData = [];
                    $costData = [];
                    $profitData = [];
                    
                    foreach ($profitLabels as $index => $label) {
                        // Normalize label to Y-m-d format - same as revenue chart
                        $normalizedLabel = $label;
                        if (is_string($label) && strpos($label, ' ') !== false) {
                            $normalizedLabel = substr($label, 0, 10);
                        }
                        // Only normalize if not already in Y-m-d format
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedLabel)) {
                            $normalizedLabel = date('Y-m-d', strtotime($normalizedLabel));
                        }
                        
                        // Try exact match first
                        $profitRow = $profitMap[$normalizedLabel] ?? null;
                        
                        // If no exact match, try date comparison (in case of timezone issues)
                        if (!$profitRow) {
                            foreach ($profitMap as $mapDate => $mapData) {
                                $normalizedMapDate = $mapDate; // Already normalized
                                if ($normalizedMapDate === $normalizedLabel) {
                                    $profitRow = $mapData;
                                    break;
                                }
                            }
                        }
                        
                        $revenueData[] = $profitRow ? $profitRow['revenue'] : 0;
                        $costData[] = $profitRow ? $profitRow['cost'] : 0;
                        $profitData[] = $profitRow ? $profitRow['profit'] : 0;
                        
                        // Log Nov 5 and Nov 6 specifically
                        if ($normalizedLabel === '2025-11-05' || $normalizedLabel === '2025-11-06') {
                            $mapKeys = array_keys($profitMap);
                            error_log("Charts API: Profit - Label '{$label}' -> normalized '{$normalizedLabel}' (index {$index})");
                            error_log("Charts API: Profit - Looking for '{$normalizedLabel}' in map, found: " . (isset($profitMap[$normalizedLabel]) ? 'YES' : 'NO'));
                            error_log("Charts API: Profit - Map keys available: " . implode(', ', $mapKeys));
                            error_log("Charts API: Profit - Mapped to revenue: " . ($profitRow ? $profitRow['revenue'] : 0) . ", cost: " . ($profitRow ? $profitRow['cost'] : 0) . ", profit: " . ($profitRow ? $profitRow['profit'] : 0));
                        }
                    }
                    
                    error_log("Charts API: Profit data mapped - revenue: " . count($revenueData) . ", cost: " . count($costData) . ", profit: " . count($profitData));
                    error_log("Charts API: Profit data sample (last 5): " . json_encode([
                        'revenue' => array_slice($revenueData, -5),
                        'cost' => array_slice($costData, -5),
                        'profit' => array_slice($profitData, -5)
                    ]));
                    
                    $charts['profit'] = [
                        'labels' => $profitLabels,
                        'datasets' => [
                            [
                                'label' => 'Revenue',
                                'data' => $revenueData,
                                'borderColor' => 'rgb(34, 197, 94)',
                                'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                                'tension' => 0.4
                            ],
                            [
                                'label' => 'Cost',
                                'data' => $costData,
                                'borderColor' => 'rgb(234, 179, 8)',
                                'backgroundColor' => 'rgba(234, 179, 8, 0.6)',
                                'tension' => 0.4
                            ],
                            [
                                'label' => 'Profit',
                                'data' => $profitData,
                                'borderColor' => 'rgb(59, 130, 246)',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                                'tension' => 0.4
                            ]
                        ]
                    ];
                } else {
                    // If no permission, generate empty chart
                    $profitLabels = [];
                    try {
                        $start = new \DateTime($dateFrom);
                        $end = new \DateTime($dateTo);
                        $end->modify('+1 day');
                        $interval = new \DateInterval('P1D');
                        $dateRange = new \DatePeriod($start, $interval, $end);
                        
                        foreach ($dateRange as $date) {
                            $profitLabels[] = $date->format('Y-m-d');
                        }
                    } catch (\Exception $e) {
                        error_log("Charts API: Error generating profit labels: " . $e->getMessage());
                        // Fallback
                        $current = strtotime($dateFrom);
                        $endTime = strtotime($dateTo);
                        while ($current <= $endTime) {
                            $profitLabels[] = date('Y-m-d', $current);
                            $current = strtotime('+1 day', $current);
                        }
                    }
                    
                    // Generate empty data arrays matching labels length
                    $emptyData = array_fill(0, count($profitLabels), 0);
                    
                    // Return empty chart structure if no data - but still initialize chart
                    $charts['profit'] = [
                        'labels' => $profitLabels,
                        'datasets' => [
                            [
                                'label' => 'Revenue',
                                'data' => $emptyData,
                                'borderColor' => 'rgb(34, 197, 94)',
                                'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                                'tension' => 0.4
                            ],
                            [
                                'label' => 'Cost',
                                'data' => $emptyData,
                                'borderColor' => 'rgb(234, 179, 8)',
                                'backgroundColor' => 'rgba(234, 179, 8, 0.6)',
                                'tension' => 0.4
                            ],
                            [
                                'label' => 'Profit',
                                'data' => $emptyData,
                                'borderColor' => 'rgb(59, 130, 246)',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                                'tension' => 0.4
                            ]
                        ]
                    ];
                    error_log("Charts API: Profit chart - no data, generated " . count($profitLabels) . " empty labels");
                }
            }

            // Top products chart
            if ($chartType === 'products' || $chartType === 'all') {
                // Always generate Top Products chart (remove module restriction for now)
                // Use date range from request for top products
                $topProducts = $this->analyticsService->getTopProducts($companyId, 10, $dateFrom, $dateTo);
                
                error_log("Charts API: Top Products - userRole: {$userRole}, enabledModules: " . json_encode($enabledModules));
                error_log("Charts API: Top Products count: " . count($topProducts) . ", date range: {$dateFrom} to {$dateTo}");
                if (count($topProducts) > 0) {
                    error_log("Charts API: Top Products sample: " . json_encode(array_slice($topProducts, 0, 3)));
                }
                
                if (!empty($topProducts)) {
                    // Generate soft pastel colors for each product
                    $softColors = [
                        ['bg' => 'rgba(147, 197, 253, 0.7)', 'border' => 'rgb(96, 165, 250)'], // Soft blue
                        ['bg' => 'rgba(196, 181, 253, 0.7)', 'border' => 'rgb(167, 139, 250)'], // Soft purple
                        ['bg' => 'rgba(252, 211, 77, 0.7)', 'border' => 'rgb(251, 191, 36)'], // Soft yellow
                        ['bg' => 'rgba(134, 239, 172, 0.7)', 'border' => 'rgb(74, 222, 128)'], // Soft green
                        ['bg' => 'rgba(251, 146, 60, 0.7)', 'border' => 'rgb(249, 115, 22)'], // Soft orange
                        ['bg' => 'rgba(244, 114, 182, 0.7)', 'border' => 'rgb(236, 72, 153)'], // Soft pink
                        ['bg' => 'rgba(129, 140, 248, 0.7)', 'border' => 'rgb(99, 102, 241)'], // Soft indigo
                        ['bg' => 'rgba(94, 234, 212, 0.7)', 'border' => 'rgb(45, 212, 191)'], // Soft teal
                        ['bg' => 'rgba(253, 164, 175, 0.7)', 'border' => 'rgb(251, 113, 133)'], // Soft rose
                        ['bg' => 'rgba(165, 180, 252, 0.7)', 'border' => 'rgb(129, 140, 248)'], // Soft lavender
                    ];
                    
                    $productCount = count($topProducts);
                    $backgroundColors = [];
                    $borderColors = [];
                    
                    for ($i = 0; $i < $productCount; $i++) {
                        $colorIndex = $i % count($softColors);
                        $backgroundColors[] = $softColors[$colorIndex]['bg'];
                        $borderColors[] = $softColors[$colorIndex]['border'];
                    }
                    
                    $charts['topProducts'] = [
                        'labels' => array_column($topProducts, 'name'),
                        'datasets' => [
                            [
                                'label' => 'Units Sold',
                                'data' => array_column($topProducts, 'units_sold'),
                                'backgroundColor' => $backgroundColors,
                                'borderColor' => $borderColors,
                                'borderWidth' => 1
                            ]
                        ]
                    ];
                } else {
                    // Return empty chart structure if no data
                    $charts['topProducts'] = [
                        'labels' => [],
                        'datasets' => [
                            [
                                'label' => 'Units Sold',
                                'data' => [],
                                'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                                'borderColor' => 'rgb(34, 197, 94)',
                                'borderWidth' => 1
                            ]
                        ]
                    ];
                }
            }

            // Top customers chart
            if ($chartType === 'customers' || $chartType === 'all') {
                if ($userRole === 'system_admin' || in_array('pos_sales', $enabledModules)) {
                    // Use date range from request for top customers (same as top products)
                    $topCustomers = $this->analyticsService->getTopCustomers($companyId, 10, $dateFrom, $dateTo);
                    
                    if (!empty($topCustomers)) {
                        $charts['topCustomers'] = [
                            'labels' => array_column($topCustomers, 'name'),
                            'datasets' => [
                                [
                                    'label' => 'Revenue',
                                    'data' => array_column($topCustomers, 'total_revenue'),
                                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                                    'borderColor' => 'rgb(59, 130, 246)',
                                    'borderWidth' => 1
                                ]
                            ]
                        ];
                    } else {
                        // Return empty chart structure if no data
                        $charts['topCustomers'] = [
                            'labels' => [],
                            'datasets' => [
                                [
                                    'label' => 'Revenue',
                                    'data' => [],
                                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                                    'borderColor' => 'rgb(59, 130, 246)',
                                    'borderWidth' => 1
                                ]
                            ]
                        ];
                    }
                }
            }

            // Log chart data for debugging
            error_log("Charts API: Returning charts for company {$companyId}, date range: {$dateFrom} to {$dateTo}");
            error_log("Charts API: Revenue chart - labels: " . (isset($charts['revenue']) ? count($charts['revenue']['labels'] ?? []) : 0) . ", datasets: " . (isset($charts['revenue']) ? count($charts['revenue']['datasets'] ?? []) : 0));
            error_log("Charts API: Profit chart - labels: " . (isset($charts['profit']) ? count($charts['profit']['labels'] ?? []) : 0) . ", datasets: " . (isset($charts['profit']) ? count($charts['profit']['datasets'] ?? []) : 0));
            error_log("Charts API: Top Products chart - labels: " . (isset($charts['topProducts']) ? count($charts['topProducts']['labels'] ?? []) : 0) . ", datasets: " . (isset($charts['topProducts']) ? count($charts['topProducts']['datasets'] ?? []) : 0));
            if (isset($charts['topProducts']) && !empty($charts['topProducts']['labels'])) {
                error_log("Charts API: Top Products sample: " . json_encode(array_slice($charts['topProducts']['labels'], 0, 5)));
            }
            
            // Ensure charts always have structure even if empty
            if (!isset($charts['revenue'])) {
                $charts['revenue'] = ['labels' => [], 'datasets' => []];
            }
            if (!isset($charts['profit'])) {
                $charts['profit'] = ['labels' => [], 'datasets' => []];
            }
            if (!isset($charts['topProducts'])) {
                $charts['topProducts'] = ['labels' => [], 'datasets' => [[
                    'label' => 'Units Sold',
                    'data' => [],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1
                ]]];
            }
            if (!isset($charts['topCustomers'])) {
                $charts['topCustomers'] = ['labels' => [], 'datasets' => []];
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'charts' => $charts
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics charts error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export analytics data
     * GET /api/analytics/export/{type}
     */
    public function export($type = null) {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get format (csv, pdf, xlsx)
            $format = $_GET['format'] ?? 'csv';
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $staffId = $_GET['staff_id'] ?? null; // Staff filter for export

            // Route to appropriate export handler
            switch ($type) {
                case 'sales':
                    $this->exportSales($companyId, $format, $dateFrom, $dateTo, $staffId);
                    break;
                case 'repairs':
                    $this->exportRepairs($companyId, $format, $dateFrom, $dateTo, $staffId);
                    break;
                case 'swaps':
                    $this->exportSwaps($companyId, $format, $dateFrom, $dateTo, $staffId);
                    break;
                case 'inventory':
                    $this->exportInventory($companyId, $format);
                    break;
                default:
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Invalid export type']);
                    exit;
            }
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Export error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export sales data
     * @param int|null $staffId Optional staff member filter
     */
    private function exportSales($companyId, $format, $dateFrom, $dateTo, $staffId = null) {
        // Default date range if not provided
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01'); // Start of month
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        $data = $this->analyticsService->getSalesByDateRange($companyId, $dateFrom, $dateTo, $staffId);
        
        // Get authenticated user for role check
        $user = $this->getAuthenticatedUser();
        $userRole = $user['role'] ?? 'manager';
        
        // Get staff name for filename if staff is selected
        $staffName = '';
        $staff = null;
        $title = 'Sales Export';
        if ($staffId) {
            try {
                $db = \Database::getInstance()->getConnection();
                $staffQuery = $db->prepare("SELECT full_name, username FROM users WHERE id = ? AND company_id = ?");
                $staffQuery->execute([$staffId, $companyId]);
                $staff = $staffQuery->fetch(\PDO::FETCH_ASSOC);
                if ($staff) {
                    $staffName = '_' . str_replace(' ', '_', strtolower($staff['full_name'] ?? $staff['username'] ?? 'staff'));
                    $title = 'Sales Export - ' . ($staff['full_name'] ?? 'Staff');
                }
            } catch (\Exception $e) {
                error_log("Error getting staff name for export: " . $e->getMessage());
            }
        }
        
        // Check if partial payments module is enabled
        $partialPaymentsEnabled = $this->checkModuleEnabled($companyId, 'partial_payments', $userRole);
        
        // Format data for export
        $formattedData = [];
        $hasPartialPayments = false;
        $salePaymentModel = null;
        
        if ($partialPaymentsEnabled) {
            $salePaymentModel = new \App\Models\SalePayment();
        }
        
        foreach ($data as $row) {
            $exportRow = [
                'ID' => $row['id'],
                'Unique ID' => $row['unique_id'],
                'Date' => $row['created_at'],
                'Customer' => $row['customer_name'] ?? '',
                'Customer Phone' => $row['customer_phone'] ?? '',
                'Amount' => number_format($row['final_amount'], 2),
                'Payment Method' => $row['payment_method'] ?? '',
                'Cashier' => $row['cashier_name'] ?? '',
                'Item Count' => $row['item_count'],
                'Items' => $row['items'] ?? ''
            ];
            
            // Add payment information if partial payments module is enabled
            if ($partialPaymentsEnabled && $salePaymentModel) {
                try {
                    $paymentStats = $salePaymentModel->getPaymentStats($row['id'], $companyId);
                    $totalPaid = floatval($paymentStats['total_paid'] ?? 0);
                    $remaining = floatval($paymentStats['remaining'] ?? 0);
                    $paymentStatus = $paymentStats['payment_status'] ?? 'PAID';
                    
                    $exportRow['Payment Status'] = $paymentStatus;
                    $exportRow['Total Paid'] = number_format($totalPaid, 2);
                    $exportRow['Remaining'] = number_format($remaining, 2);
                    
                    if ($paymentStatus === 'PARTIAL' || $paymentStatus === 'UNPAID' || $remaining > 0) {
                        $hasPartialPayments = true;
                    }
                } catch (\Exception $e) {
                    // If payment tracking fails, assume full payment
                    $exportRow['Payment Status'] = 'PAID';
                    $exportRow['Total Paid'] = number_format($row['final_amount'], 2);
                    $exportRow['Remaining'] = '0.00';
                }
            }
            
            $formattedData[] = $exportRow;
        }

        $filename = 'sales_export' . $staffName . '_' . date('Ymd') . '.' . ($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));
        
        // Add payment stats summary to title if there are partial payments
        if ($hasPartialPayments && $partialPaymentsEnabled) {
            $title .= ' (Includes Partial Payments)';
        }
        
        if ($format === 'xlsx') {
            $this->exportService->exportExcel($formattedData, $filename, $title, null, $hasPartialPayments && $partialPaymentsEnabled);
        } elseif ($format === 'pdf') {
            $this->exportService->exportPDF($formattedData, $filename, $title, null, $hasPartialPayments && $partialPaymentsEnabled);
        } else {
            $this->exportService->exportCSV($formattedData, $filename);
        }
    }

    /**
     * Export repairs data
     * @param int|null $staffId Optional staff member filter (technician)
     */
    private function exportRepairs($companyId, $format, $dateFrom, $dateTo, $staffId = null) {
        // Default date range if not provided
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01'); // Start of month
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        $data = $this->analyticsService->getRepairsByDateRange($companyId, $dateFrom, $dateTo, $staffId);
        
        // Get staff name for filename if staff is selected
        $staffName = '';
        $title = 'Repairs Export';
        if ($staffId) {
            try {
                $db = \Database::getInstance()->getConnection();
                $staffQuery = $db->prepare("SELECT full_name, username FROM users WHERE id = ? AND company_id = ?");
                $staffQuery->execute([$staffId, $companyId]);
                $staff = $staffQuery->fetch(\PDO::FETCH_ASSOC);
                if ($staff) {
                    $staffName = '_' . str_replace(' ', '_', strtolower($staff['full_name'] ?? $staff['username'] ?? 'staff'));
                    $title = 'Repairs Export - ' . ($staff['full_name'] ?? 'Staff');
                }
            } catch (\Exception $e) {
                error_log("Error getting staff name for export: " . $e->getMessage());
            }
        }
        
        // Format data for export
        $formattedData = [];
        foreach ($data as $row) {
            $formattedData[] = [
                'ID' => $row['id'],
                'Unique ID' => $row['unique_id'],
                'Date' => $row['created_at'],
                'Customer' => $row['customer_name'] ?? '',
                'Customer Phone' => $row['customer_phone'] ?? '',
                'Phone Description' => $row['phone_description'] ?? '',
                'IMEI' => $row['imei'] ?? '',
                'Total Cost' => number_format($row['total_cost'], 2),
                'Status' => $row['repair_status'] ?? '',
                'Payment Status' => $row['payment_status'] ?? ''
            ];
        }

        $filename = 'repairs_export' . $staffName . '_' . date('Ymd') . '.' . ($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));
        
        if ($format === 'xlsx') {
            $this->exportService->exportExcel($formattedData, $filename, $title);
        } elseif ($format === 'pdf') {
            $this->exportService->exportPDF($formattedData, $filename, $title);
        } else {
            $this->exportService->exportCSV($formattedData, $filename);
        }
    }

    /**
     * Export swaps data
     * @param int|null $staffId Optional staff member filter (salesperson)
     */
    private function exportSwaps($companyId, $format, $dateFrom, $dateTo, $staffId = null) {
        // Default date range if not provided
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01'); // Start of month
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        $data = $this->analyticsService->getSwapsByDateRange($companyId, $dateFrom, $dateTo, $staffId);
        
        // Get staff name for filename if staff is selected
        $staffName = '';
        $title = 'Swaps Export';
        if ($staffId) {
            try {
                $db = \Database::getInstance()->getConnection();
                $staffQuery = $db->prepare("SELECT full_name, username FROM users WHERE id = ? AND company_id = ?");
                $staffQuery->execute([$staffId, $companyId]);
                $staff = $staffQuery->fetch(\PDO::FETCH_ASSOC);
                if ($staff) {
                    $staffName = '_' . str_replace(' ', '_', strtolower($staff['full_name'] ?? $staff['username'] ?? 'staff'));
                    $title = 'Swaps Export - ' . ($staff['full_name'] ?? 'Staff');
                }
            } catch (\Exception $e) {
                error_log("Error getting staff name for export: " . $e->getMessage());
            }
        }
        
        // Format data for export
        $formattedData = [];
        foreach ($data as $row) {
            $formattedData[] = [
                'ID' => $row['id'] ?? '',
                'Unique ID' => $row['unique_id'] ?? '',
                'Date' => $row['created_at'] ?? '',
                'Customer' => $row['customer_name'] ?? '',
                'Customer Phone' => $row['customer_phone'] ?? '',
                'Item' => $row['item_description'] ?? '',
                'Brand' => $row['brand'] ?? '',
                'Model' => $row['model'] ?? '',
                'Total Value' => number_format(floatval($row['total_value'] ?? 0), 2),
                'Status' => $row['swap_status'] ?? ''
            ];
        }

        $filename = 'swaps_export' . $staffName . '_' . date('Ymd') . '.' . ($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));
        
        if ($format === 'xlsx') {
            $this->exportService->exportExcel($formattedData, $filename, $title);
        } elseif ($format === 'pdf') {
            $this->exportService->exportPDF($formattedData, $filename, $title);
        } else {
            $this->exportService->exportCSV($formattedData, $filename);
        }
    }

    /**
     * Export inventory data
     */
    private function exportInventory($companyId, $format) {
        $db = \Database::getInstance()->getConnection();
        $query = $db->prepare("
            SELECT 
                id,
                product_id as sku,
                name,
                category,
                brand,
                price,
                cost,
                COALESCE(quantity, qty, 0) as quantity,
                status
            FROM products
            WHERE company_id = :company_id
            ORDER BY name ASC
        ");
        $query->execute(['company_id' => $companyId]);
        $data = $query->fetchAll(\PDO::FETCH_ASSOC);

        // Format data for export
        $formattedData = [];
        foreach ($data as $row) {
            $formattedData[] = [
                'ID' => $row['id'],
                'SKU' => $row['sku'] ?? '',
                'Name' => $row['name'] ?? '',
                'Category' => $row['category'] ?? '',
                'Brand' => $row['brand'] ?? '',
                'Price' => number_format($row['price'] ?? 0, 2),
                'Cost' => number_format($row['cost'] ?? 0, 2),
                'Quantity' => $row['quantity'] ?? 0,
                'Status' => $row['status'] ?? ''
            ];
        }

        $filename = 'inventory_export_' . date('Ymd') . '.' . ($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));
        
        if ($format === 'xlsx') {
            $this->exportService->exportExcel($formattedData, $filename, 'Inventory Export');
        } elseif ($format === 'pdf') {
            $this->exportService->exportPDF($formattedData, $filename, 'Inventory Export');
        } else {
            $this->exportService->exportCSV($formattedData, $filename);
        }
    }

    /**
     * Show audit trail details
     * GET /dashboard/audit-trail/{id}
     */
    public function show($id) {
        // Handle web authentication - managers and admins only
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }

        $companyId = $user['company_id'] ?? null;
        $userRole = $user['role'] ?? 'manager';
        
        if (!$companyId) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('Company association required'));
            exit;
        }

        // Get the audit log
        $filters = ['id' => $id];
        $logs = $this->auditService->getLogs($companyId, $filters, 1, 0);
        
        if (empty($logs)) {
            $_SESSION['flash_error'] = 'Audit log not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/audit-trail');
            exit;
        }
        
        $log = $logs[0];
        
        $title = 'Audit Trail Details';
        $page = 'audit-trail';
        
        // Set global variables BEFORE including the view so the view can access them
        $GLOBALS['log'] = $log;
        $GLOBALS['currentPage'] = 'audit-trail';
        $GLOBALS['user_data'] = $user;
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/audit_trail_show.php';
        $content = ob_get_clean();
        
        // Set remaining global variables for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        $GLOBALS['pageTitle'] = $title;
        
        // Render layout
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Get audit logs (live feed)
     * GET /api/analytics/audit-logs
     */
    public function auditLogs() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get filters
            $filters = [
                'event_type' => $_GET['event_type'] ?? null,
                'user_id' => $_GET['user_id'] ?? null,
                'entity_type' => $_GET['entity_type'] ?? null,
                'entity_id' => $_GET['entity_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            
            // Remove null filters
            $filters = array_filter($filters, function($v) { return $v !== null; });
            
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = (int)($_GET['offset'] ?? 0);
            $page = (int)($_GET['page'] ?? 1);
            
            // Calculate offset from page if provided
            if ($page > 1 && $offset === 0) {
                $offset = ($page - 1) * $limit;
            }

            // Get total count for pagination
            $totalCount = $this->auditService->getLogsCount($companyId, $filters);
            $logs = $this->auditService->getLogs($companyId, $filters, $limit, $offset);
            $totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 0;

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Audit logs error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get alert notifications
     * GET /api/analytics/alerts
     */
    public function alerts() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $unhandledOnly = isset($_GET['unhandled_only']) ? (bool)$_GET['unhandled_only'] : true;
            $limit = (int)($_GET['limit'] ?? 50);

            $notifications = $this->alertService->getNotifications($companyId, $unhandledOnly, $limit);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Alerts error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Acknowledge alert notification
     * POST /api/analytics/alerts/{id}/acknowledge
     */
    public function acknowledgeAlert($notificationId) {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $userId = $user['id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                exit;
            }

            $success = $this->alertService->acknowledgeNotification($notificationId, $userId);

            ob_end_clean();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Alert acknowledged' : 'Failed to acknowledge alert'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Acknowledge alert error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get anomalies
     * GET /api/analytics/anomalies
     */
    public function anomalies() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $anomalies = $this->anomalyService->runAllChecks($companyId);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'anomalies' => $anomalies,
                'count' => count($anomalies)
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Anomalies error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test alert (for system_admin / manager)
     * POST /api/analytics/alerts/test
     */
    public function testAlert() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $userRole = $user['role'] ?? 'manager';
            if (!in_array($userRole, ['system_admin', 'manager', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Manually trigger alert check
            $triggered = $this->alertService->checkAndTrigger($companyId);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'triggered' => $triggered,
                'count' => count($triggered),
                'message' => 'Alert check completed'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Test alert error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get sales forecast
     * GET /api/analytics/forecast/sales
     */
    public function forecastSales() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $timeframe = $_GET['timeframe'] ?? 'weekly';
            $daysAhead = (int)($_GET['days_ahead'] ?? 7);

            $forecast = $this->forecastService->predictSales($companyId, $timeframe, $daysAhead);

            ob_end_clean();
            echo json_encode($forecast);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Forecast sales error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get restock forecast
     * GET /api/analytics/forecast/restock
     */
    public function forecastRestock() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $daysAhead = (int)($_GET['days_ahead'] ?? 14);

            $forecast = $this->forecastService->forecastRestockNeeds($companyId, $daysAhead);

            ob_end_clean();
            echo json_encode($forecast);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Forecast restock error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get profit forecast
     * GET /api/analytics/forecast/profit
     */
    public function forecastProfit() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $interval = $_GET['interval'] ?? 'weekly';

            $forecast = $this->forecastService->forecastProfit($companyId, $interval);

            ob_end_clean();
            echo json_encode($forecast);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Forecast profit error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get smart recommendations
     * GET /api/analytics/recommendations
     */
    public function recommendations() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $unreadOnly = isset($_GET['unread_only']) ? (bool)$_GET['unread_only'] : false;
            $type = $_GET['type'] ?? null;
            $limit = (int)($_GET['limit'] ?? 20);

            $recommendationModel = new SmartRecommendation();
            $recommendations = $recommendationModel->getForCompany($companyId, $unreadOnly, $type, $limit);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'recommendations' => $recommendations,
                'count' => count($recommendations)
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Recommendations error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark recommendation as read
     * POST /api/analytics/recommendations/{id}/read
     */
    public function markRecommendationRead($id) {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $recommendationModel = new SmartRecommendation();
            $success = $recommendationModel->markAsRead($id, $companyId);

            ob_end_clean();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Recommendation marked as read' : 'Failed to mark as read'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Mark recommendation read error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate recommendations
     * POST /api/analytics/recommendations/generate
     */
    public function generateRecommendations() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $userRole = $user['role'] ?? 'manager';
            if (!in_array($userRole, ['system_admin', 'manager', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $recommendations = $this->analyticsService->generateRecommendations($companyId, true);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'recommendations' => $recommendations,
                'count' => count($recommendations),
                'message' => 'Recommendations generated successfully'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Generate recommendations error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get profit optimization suggestions
     * GET /api/analytics/profit-optimization
     */
    public function profitOptimization() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $suggestions = $this->analyticsService->getProfitOptimizationSuggestions($companyId);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'count' => count($suggestions)
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Profit optimization error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export company backup
     * POST /api/analytics/backup/export
     */
    public function exportBackup() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $format = $_POST['format'] ?? 'json';

            $result = $this->backupService->exportCompanyData($companyId, $format);

            if ($result['success']) {
                // Log audit event
                AuditService::log(
                    $companyId,
                    $user['id'] ?? null,
                    'backup.exported',
                    'backup',
                    null,
                    [
                        'filename' => $result['filename'],
                        'format' => $format,
                        'size' => $result['size'],
                        'record_count' => $result['record_count']
                    ]
                );
            }

            ob_end_clean();
            echo json_encode($result);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Export backup error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get company backups list
     * GET /api/analytics/backups
     */
    public function getBackups() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $backups = $this->backupService->getCompanyBackups($companyId);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'backups' => $backups,
                'count' => count($backups)
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Get backups error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get integrity dashboard data
     * GET /api/analytics/integrity
     */
    public function integrity() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $backups = $this->backupService->getCompanyBackups($companyId);
            $lastBackup = !empty($backups) ? $backups[0] : null;

            // Get audit log count (restorable records)
            $auditCount = $this->auditService->getEventStats($companyId);
            $totalAuditRecords = array_sum(array_column($auditCount, 'count'));

            // Get scheduled reports status (handle if table doesn't exist)
            $reportStatus = ['total' => 0, 'enabled' => 0];
            try {
                $db = \Database::getInstance()->getConnection();
                $checkTable = $db->query("SHOW TABLES LIKE 'scheduled_reports'");
                if ($checkTable->rowCount() > 0) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total, 
                               SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled
                        FROM scheduled_reports
                        WHERE company_id = ?
                    ");
                    $stmt->execute([$companyId]);
                    $reportStatus = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['total' => 0, 'enabled' => 0];
                }
            } catch (\Exception $e) {
                error_log("Integrity dashboard - scheduled_reports query error: " . $e->getMessage());
            }

            // Verify last backup integrity if exists
            $backupIntegrity = null;
            if ($lastBackup && file_exists($lastBackup['filepath'])) {
                // Extract and verify
                $zip = new \ZipArchive();
                if ($zip->open($lastBackup['filepath']) === TRUE) {
                    $tempDir = sys_get_temp_dir() . '/verify_' . uniqid();
                    mkdir($tempDir, 0755, true);
                    $zip->extractTo($tempDir);
                    
                    $files = scandir($tempDir);
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                            $backupData = json_decode(file_get_contents($tempDir . '/' . $file), true);
                            $integrityCheck = $this->backupService->verifyBackupIntegrity($backupData);
                            $backupIntegrity = $integrityCheck['valid'] ? 'passed' : 'failed';
                            break;
                        }
                    }
                    
                    $zip->close();
                    // Cleanup
                    array_map('unlink', glob($tempDir . '/*'));
                    rmdir($tempDir);
                }
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'integrity' => [
                    'last_backup_date' => $lastBackup['created_at'] ?? null,
                    'backup_count' => count($backups),
                    'backup_integrity' => $backupIntegrity ?? 'unknown',
                    'restorable_records' => $totalAuditRecords,
                    'scheduled_reports' => [
                        'total' => (int)($reportStatus['total'] ?? 0),
                        'enabled' => (int)($reportStatus['enabled'] ?? 0)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Integrity dashboard error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch live data - unified endpoint for real-time analytics
     * GET /api/audit-trail/data
     */
    public function fetchLiveData() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $companyId = $user['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            // Get parameters
            $dateRange = $_GET['date_range'] ?? 'last_90_days';
            $module = $_GET['module'] ?? 'all';
            $staffId = !empty($_GET['staff_id']) ? (int)$_GET['staff_id'] : null; // Filter by specific staff member (cast to int)
            $customerId = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null; // Filter by customer
            $productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null; // Filter by product
            $imei = !empty($_GET['imei']) ? trim($_GET['imei']) : null; // Filter by IMEI
            
            error_log("fetchLiveData: staff_id=" . ($staffId ?? 'null') . ", customer_id=" . ($customerId ?? 'null') . ", product_id=" . ($productId ?? 'null') . ", imei=" . ($imei ?? 'null'));

            // Prioritize explicit date_from and date_to over date_range
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $today = date('Y-m-d');
            
            // Only calculate from date_range if explicit dates not provided
            if (!$dateFrom || !$dateTo) {
                // Calculate date range from label
                list($dateFrom, $dateTo) = $this->calculateDateRange($dateRange);
            }
            
            // IMPORTANT: Do NOT override explicit dates - respect what the user selected
            // Only apply defaults if dates were calculated from a range label
            // If explicit dates were provided, use them exactly as specified
            
            // Ensure we have valid dates (only if they weren't explicitly provided)
            if (!$dateFrom || !$dateTo) {
                // Default to last 90 days if still no dates
                $dateTo = $today;
                $dateFrom = date('Y-m-d', strtotime('-90 days'));
            }
            
            // Only auto-update dateTo to today if it's a relative range (not explicit dates)
            // Check if dates were explicitly provided by checking if they're in the request
            $explicitDatesProvided = isset($_GET['date_from']) && isset($_GET['date_to']);
            
            if (!$explicitDatesProvided && $dateTo < $today) {
                // Only update if dates were calculated from a range, not if explicitly provided
                error_log("fetchLiveData: dateTo ({$dateTo}) is before today ({$today}), updating to today (calculated range)");
                $dateTo = $today;
            }
            
            error_log("fetchLiveData: Final date range - from: {$dateFrom}, to: {$dateTo}, today: {$today}, explicit: " . ($explicitDatesProvided ? 'yes' : 'no'));

            // Check enabled modules
            $moduleModel = new CompanyModule();
            $enabledModules = $moduleModel->getEnabledModules($companyId);
            $userRole = $user['role'] ?? 'manager';

            $response = [
                'success' => true,
                'company_id' => $companyId,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'label' => $dateRange
                ],
                'enabled_modules' => $enabledModules,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Fetch sales data (if pos_sales enabled)
            if ($module === 'all' || $module === 'sales') {
                if (in_array('pos_sales', $enabledModules) || $userRole === 'system_admin') {
                    $response['sales'] = $this->analyticsService->getSalesStats($companyId, $dateFrom, $dateTo, $staffId);
                    
                    // Add payment stats if partial payments module is enabled
                    if (CompanyModule::isEnabled($companyId, 'partial_payments')) {
                        try {
                            $db = \Database::getInstance()->getConnection();
                            
                            // Check if is_swap_mode column exists
                            $checkIsSwapMode = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
                            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
                            $excludeSwapSales = $hasIsSwapMode ? " AND (is_swap_mode = 0 OR is_swap_mode IS NULL)" : "";
                            
                            $paymentStatsSql = "SELECT payment_status, COUNT(*) as count 
                                               FROM pos_sales 
                                               WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ?{$excludeSwapSales}
                                               GROUP BY payment_status";
                            $paymentStatsQuery = $db->prepare($paymentStatsSql);
                            $paymentStatsQuery->execute([$companyId, $dateFrom, $dateTo]);
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
                            
                            $response['payment_stats'] = [
                                'fully_paid' => $fullyPaid,
                                'partial' => $partial,
                                'unpaid' => $unpaid
                            ];
                        } catch (\Exception $e) {
                            error_log("ManagerAnalyticsController::fetchLiveData - Error getting payment stats: " . $e->getMessage());
                        }
                    }
                }
            }

            // Fetch swaps data
            if ($module === 'all' || $module === 'swaps') {
                // Check for both 'swap' (singular) and 'swaps' (plural) module keys
                $swapModuleEnabled = in_array('swap', $enabledModules) || in_array('swaps', $enabledModules) || $userRole === 'system_admin';
                error_log("fetchLiveData: Swap module check - enabled: " . ($swapModuleEnabled ? 'yes' : 'no') . ", enabled modules: " . implode(', ', $enabledModules) . ", user role: {$userRole}");
                
                if ($swapModuleEnabled) {
                    try {
                        // Use getSwapStatistics for consistency with swap page and dashboard
                        // This ensures the audit trail shows the same values as other pages
                        $dashboardController = new \App\Controllers\DashboardController();
                        $reflection = new \ReflectionClass($dashboardController);
                        $method = $reflection->getMethod('getSwapStatistics');
                        $method->setAccessible(true);
                        $swapStatsFromDashboard = $method->invoke($dashboardController, $companyId, $dateFrom, $dateTo);
                        
                        error_log("fetchLiveData: Swap stats from getSwapStatistics (matches swap page): " . json_encode($swapStatsFromDashboard));
                        
                        // Use the values from getSwapStatistics which matches swap page
                        $totalSwaps = (int)($swapStatsFromDashboard['total_swaps'] ?? 0);
                        $totalValue = (float)($swapStatsFromDashboard['total_value'] ?? 0);
                        $totalProfit = (float)($swapStatsFromDashboard['total_profit'] ?? 0);
                        $pendingSwaps = (int)($swapStatsFromDashboard['pending'] ?? 0);
                        
                        // Ensure all required fields are present - use getSwapStatistics values for consistency
                        $response['swaps'] = [
                            'pending' => $pendingSwaps,
                            'monthly' => [
                                'count' => $totalSwaps,
                                'revenue' => $totalValue,
                                'profit' => $totalProfit
                            ],
                            'profit' => $totalProfit,
                            'period' => [
                                'count' => $totalSwaps,
                                'revenue' => $totalValue,
                                'profit' => $totalProfit
                            ],
                            'filtered' => null,
                            'total_value' => $totalValue  // Add explicit total_value for dashboard consistency
                        ];
                        error_log("fetchLiveData: Swap stats formatted successfully for company {$companyId}: " . json_encode($response['swaps']));
                    } catch (\Exception $e) {
                        error_log("fetchLiveData: Error fetching swap stats: " . $e->getMessage());
                        error_log("fetchLiveData: Stack trace: " . $e->getTraceAsString());
                        // Return empty swap stats on error
                        $response['swaps'] = [
                            'pending' => 0,
                            'monthly' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                            'profit' => 0,
                            'period' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                            'filtered' => null
                        ];
                    }
                } else {
                    error_log("fetchLiveData: Swap module not enabled. Enabled modules: " . implode(', ', $enabledModules) . ", User role: {$userRole}");
                    // Still return empty swap stats structure so frontend doesn't break
                    $response['swaps'] = [
                        'pending' => 0,
                        'monthly' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                        'profit' => 0,
                        'period' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                        'filtered' => null
                    ];
                }
            } else {
                // Module not requested, but still return empty structure
                $response['swaps'] = [
                    'pending' => 0,
                    'monthly' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                    'profit' => 0,
                    'period' => ['count' => 0, 'revenue' => 0, 'profit' => 0],
                    'filtered' => null
                ];
            }

            // Fetch repairs data (always fetch if repairs module enabled, needed for profit calculation)
            if (in_array('repairs', $enabledModules) || $userRole === 'system_admin') {
                try {
                    $repairStats = $this->analyticsService->getRepairStats($companyId, $dateFrom, $dateTo, $staffId);
                    $response['repairs'] = $repairStats;
                    error_log("fetchLiveData: Repair stats fetched - " . json_encode($repairStats));
                } catch (\Exception $e) {
                    error_log("fetchLiveData: Error fetching repair stats: " . $e->getMessage());
                    $response['repairs'] = [
                        'active' => 0,
                        'monthly' => ['count' => 0, 'revenue' => 0],
                        'period' => ['count' => 0, 'revenue' => 0],
                        'filtered' => null
                    ];
                }
            } else {
                // Return empty structure if repairs module not enabled
                $response['repairs'] = [
                    'active' => 0,
                    'monthly' => ['count' => 0, 'revenue' => 0],
                    'period' => ['count' => 0, 'revenue' => 0],
                    'filtered' => null
                ];
            }

            // Fetch inventory data
            if ($module === 'all' || $module === 'inventory') {
                if (in_array('products_inventory', $enabledModules) || 
                    in_array('pos_sales', $enabledModules) || 
                    $userRole === 'system_admin') {
                    $response['inventory'] = $this->analyticsService->getInventoryStats($companyId);
                }
            }

            // Fetch profit data (always include if pos_sales enabled or system admin)
            // Use the same date range as sales for consistency
            if ($module === 'all' || $module === 'profit') {
                if (in_array('pos_sales', $enabledModules) || $userRole === 'system_admin') {
                    // Always use the same date range as sales
                    $response['profit'] = $this->analyticsService->getProfitStats($companyId, $dateFrom, $dateTo, $staffId);
                } else {
                    // Still return zero profit if module not enabled
                    $response['profit'] = [
                        'revenue' => 0,
                        'cost' => 0,
                        'profit' => 0,
                        'margin' => 0
                    ];
                }
            }

            // Fetch customer activity (if customers enabled)
            $response['customer_activity'] = $this->getCustomerActivity($companyId, $dateFrom, $dateTo);

            // Fetch staff activity (salesperson and repairer/technician)
            // If staff_id is provided, filter to show only that staff member's activity
            try {
                $staffActivity = $this->getStaffActivity($companyId, $dateFrom, $dateTo, $staffId);
                $response['staff_activity'] = $staffActivity;
                error_log("fetchLiveData: Staff activity returned - Salespersons: " . count($staffActivity['salespersons']) . ", Technicians: " . count($staffActivity['technicians']));
            } catch (\Exception $e) {
                error_log("fetchLiveData: Error fetching staff activity: " . $e->getMessage());
                error_log("fetchLiveData: Stack trace: " . $e->getTraceAsString());
                $response['staff_activity'] = [
                    'salespersons' => [],
                    'technicians' => [],
                    'total_salespersons' => 0,
                    'total_technicians' => 0
                ];
            }

            // Fetch items sold by repairers (parts/accessories from repairs)
            if (in_array('repairs', $enabledModules) || $userRole === 'system_admin') {
                $response['repairer_parts_sales'] = $this->getRepairerPartsSales($companyId, $dateFrom, $dateTo, $staffId);
            }

            // Fetch comprehensive activity logs (with filters if provided)
            $activityLogs = $this->getActivityLogs($companyId, $dateFrom, $dateTo, $staffId, $customerId, $productId, $imei);
            // Ensure activity_logs is always an array
            $response['activity_logs'] = is_array($activityLogs) ? $activityLogs : [];
            
            // Log activity logs count for debugging
            error_log("fetchLiveData: Returning " . count($response['activity_logs']) . " activity logs for company {$companyId} (date range: {$dateFrom} to {$dateTo})");
            
            // CRITICAL DEBUG: If activity_logs is empty but we know there are sales, log detailed info
            if (count($response['activity_logs']) === 0) {
                error_log("fetchLiveData: WARNING - activity_logs is empty! Checking if sales exist...");
                $db = \Database::getInstance()->getConnection();
                $checkSales = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id");
                $checkSales->execute(['company_id' => $companyId]);
                $salesCount = $checkSales->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
                error_log("fetchLiveData: Sales count for company {$companyId}: {$salesCount}");
                
                if ($salesCount > 0) {
                    // Try a simple direct query to see if we can get sales
                    $directQuery = $db->prepare("
                        SELECT id, unique_id, final_amount, created_at 
                        FROM pos_sales 
                        WHERE company_id = :company_id 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $directQuery->execute(['company_id' => $companyId]);
                    $sampleSales = $directQuery->fetchAll(\PDO::FETCH_ASSOC);
                    error_log("fetchLiveData: Sample sales from direct query: " . json_encode($sampleSales));
                }
            }

            // Fetch profit/loss breakdown by period (with staff filter if provided)
            $response['profit_loss_breakdown'] = $this->getProfitLossBreakdown($companyId, $dateFrom, $dateTo, $staffId);

            // Recalculate Net Profit from breakdown to ensure consistency
            // This ensures the Net Profit matches the sum of weekly/monthly profits
            // Also include swap profit and repair revenue to match main dashboard
            if (isset($response['profit'])) {
                $breakdown = $response['profit_loss_breakdown'] ?? null;
                
                // Calculate totals from monthly breakdown (preferred) or weekly if monthly is empty
                $salesRevenue = 0;
                $salesCost = 0;
                $salesProfit = 0;
                
                if ($breakdown && !empty($breakdown['monthly'])) {
                    // Sum from monthly breakdown
                    foreach ($breakdown['monthly'] as $month) {
                        $salesRevenue += floatval($month['revenue'] ?? 0);
                        $salesCost += floatval($month['cost'] ?? 0);
                        $salesProfit += floatval($month['profit'] ?? 0);
                    }
                } elseif ($breakdown && !empty($breakdown['weekly'])) {
                    // Fallback to weekly breakdown if monthly is empty
                    foreach ($breakdown['weekly'] as $week) {
                        $salesRevenue += floatval($week['revenue'] ?? 0);
                        $salesCost += floatval($week['cost'] ?? 0);
                        $salesProfit += floatval($week['profit'] ?? 0);
                    }
                } elseif ($breakdown && !empty($breakdown['daily'])) {
                    // Fallback to daily breakdown if weekly is also empty
                    foreach ($breakdown['daily'] as $day) {
                        $salesRevenue += floatval($day['revenue'] ?? 0);
                        $salesCost += floatval($day['cost'] ?? 0);
                        $salesProfit += floatval($day['profit'] ?? 0);
                    }
                } else {
                    // If no breakdown, use the initial profit stats (sales only)
                    // These should already have the correct values from AnalyticsService::getProfitStats()
                    $salesRevenue = floatval($response['profit']['revenue'] ?? 0);
                    $salesCost = floatval($response['profit']['cost'] ?? 0);
                    $salesProfit = floatval($response['profit']['profit'] ?? 0);
                    error_log("Audit Trail: No breakdown data, using initial profit stats - Revenue: ₵{$salesRevenue}, Cost: ₵{$salesCost}, Profit: ₵{$salesProfit}");
                    
                    // If we have revenue but cost is 0, there might be a matching issue
                    if ($salesRevenue > 0 && $salesCost == 0) {
                        error_log("Audit Trail WARNING: Revenue is ₵{$salesRevenue} but cost is 0. This might indicate product matching issues.");
                    }
                }
                
                // Get swap profit - use filtered response data when staff_id is provided
                // Otherwise use getSwapStatistics for consistency with swap page
                $swapProfit = 0;
                $swapRevenue = 0;
                
                if ($staffId) {
                    // When staff is selected, use the filtered data from response (already filtered by staff_id)
                    $swapProfit = floatval($response['swaps']['period']['profit'] ?? $response['swaps']['profit'] ?? 0);
                    $swapRevenue = floatval($response['swaps']['period']['revenue'] ?? $response['swaps']['period']['total_value'] ?? $response['swaps']['revenue'] ?? $response['swaps']['total_value'] ?? 0);
                    error_log("Audit Trail: Using filtered swap data for staff_id={$staffId} - Revenue: ₵{$swapRevenue}, Profit: ₵{$swapProfit}");
                } else {
                    // When no staff filter, use getSwapStatistics for consistency with swap page
                    try {
                        // Suppress any potential output from DashboardController instantiation
                        ob_start();
                        $dashboardController = new \App\Controllers\DashboardController();
                        $reflection = new \ReflectionClass($dashboardController);
                        $method = $reflection->getMethod('getSwapStatistics');
                        $method->setAccessible(true);
                        
                        // Get swap statistics using the same logic as swap page
                        $swapStats = $method->invoke($dashboardController, $companyId, $dateFrom, $dateTo);
                        $swapProfit = floatval($swapStats['total_profit'] ?? 0); // Realized gains only
                        // Use total_value from getSwapStatistics - this matches the swap page "Total Value" card
                        $swapRevenue = floatval($swapStats['total_value'] ?? 0); // Same as swap page
                        
                        // Discard any output that might have been generated
                        ob_end_clean();
                        
                        error_log("Audit Trail: Swap revenue from getSwapStatistics: ₵{$swapRevenue} (same as swap page Total Value)");
                        error_log("Audit Trail: Swap profit from getSwapStatistics: ₵{$swapProfit} (same as swap page)");
                    } catch (\Throwable $e) {
                        // Discard any output that might have been generated
                        if (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        error_log("Audit Trail: Error getting swap stats from getSwapStatistics: " . $e->getMessage());
                        error_log("Audit Trail: Exception trace: " . $e->getTraceAsString());
                        // Fallback: try to get from response
                        $swapProfit = floatval($response['swaps']['period']['profit'] ?? $response['swaps']['profit'] ?? 0);
                        $swapRevenue = floatval($response['swaps']['period']['revenue'] ?? $response['swaps']['period']['total_value'] ?? $response['swaps']['revenue'] ?? $response['swaps']['total_value'] ?? 0);
                    }
                }
                // Repair revenue from period (date range) or filtered or monthly
                $repairRevenue = floatval($response['repairs']['period']['revenue'] ?? $response['repairs']['filtered']['revenue'] ?? $response['repairs']['monthly']['revenue'] ?? 0);
                
                // Calculate repairer profit (workmanship + parts profit) to match main dashboard
                $repairerProfit = 0;
                if (isset($response['staff_activity']) && isset($response['staff_activity']['technicians'])) {
                    foreach ($response['staff_activity']['technicians'] as $tech) {
                        $workmanshipProfit = floatval($tech['workmanship_profit'] ?? 0);
                        $partsProfit = floatval($tech['parts_profit'] ?? 0);
                        $repairerProfit += $workmanshipProfit + $partsProfit;
                    }
                }
                
                error_log("Audit Trail: Swap revenue: ₵{$swapRevenue}, Swap profit: ₵{$swapProfit}, Repair revenue: ₵{$repairRevenue}, Repairer profit: ₵{$repairerProfit}");
                error_log("Audit Trail: Swaps data structure: " . json_encode($response['swaps'] ?? []));
                error_log("Audit Trail: Repairs data structure: " . json_encode($response['repairs'] ?? []));
                
                // Always recalculate to include swap profit and repair revenue
                // This ensures Net Profit matches the sum of sales + repairs + swap profit
                // Recalculate even if breakdown is empty (will use initial profit stats for sales)
                {
                    // Calculate total cost (sales cost + repairer cost + swap cost)
                    // Only include parts cost, NOT labour cost (workmanship) for Net Profit
                    $repairerCost = 0;
                    $totalPartsRevenue = 0; // Sum of parts revenue from all technicians
                    if (isset($response['staff_activity']) && isset($response['staff_activity']['technicians'])) {
                        foreach ($response['staff_activity']['technicians'] as $tech) {
                            $labourCost = floatval($tech['labour_cost'] ?? 0);
                            $partsRevenue = floatval($tech['parts_revenue'] ?? 0);
                            $partsProfit = floatval($tech['parts_profit'] ?? 0);
                            $partsCost = $partsRevenue - $partsProfit;
                            // Only include parts cost, exclude labour cost (workmanship)
                            $repairerCost += $partsCost;
                            $totalPartsRevenue += $partsRevenue;
                        }
                    }
                    
                    // Calculate swap cost from actual cost price of swapped items (Selling Price - Cost Price = Profit)
                    // Swap cost = Sum of cost prices of all company products given in swaps
                    $swapCost = 0;
                    if ($swapRevenue > 0) {
                        try {
                            $db = \Database::getInstance()->getConnection();
                            
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
                            
                            // Use the same date range as the function (already extracted from request)
                            // $dateFrom and $dateTo are already available in the function scope
                            
                            // Calculate swap cost: Sum of cost prices of company products given in swaps
                            $swapCostQuery = $db->prepare("
                                SELECT COALESCE(SUM({$costColumn}), 0) as total_cost
                                FROM swaps s
                                LEFT JOIN {$productsTable} p ON s.company_product_id = p.id AND p.company_id = s.company_id
                                WHERE s.company_id = :company_id
                                AND DATE(s.created_at) >= :date_from
                                AND DATE(s.created_at) <= :date_to
                            ");
                            $swapCostQuery->execute([
                                'company_id' => $companyId,
                                'date_from' => $dateFrom,
                                'date_to' => $dateTo
                            ]);
                            $swapCostResult = $swapCostQuery->fetch(\PDO::FETCH_ASSOC);
                            $swapCost = floatval($swapCostResult['total_cost'] ?? 0);
                            
                            error_log("Audit Trail: Swap cost calculated from products table: ₵{$swapCost} (using {$costColumn})");
                        } catch (\Exception $e) {
                            error_log("Audit Trail: Error calculating swap cost from products: " . $e->getMessage());
                            // Fallback: calculate from revenue and profit if available
                            if ($swapRevenue > 0 && $swapProfit > 0) {
                                $swapCost = $swapRevenue - $swapProfit;
                                error_log("Audit Trail: Using fallback swap cost calculation: ₵{$swapCost}");
                            }
                        }
                    }
                    
                    if ($swapCost < 0) {
                        error_log("Audit Trail WARNING: Negative swap cost detected (₵{$swapCost}), setting to 0");
                        $swapCost = 0;
                    }
                    
                    // Net Profit = Sales Profit (excluding swap transactions) + Swap Profit (realized only)
                    // This matches the calculation on both the swap page and manager dashboard
                    // Sales profit excludes swap transactions, swap profit is from swap page (realized only)
                    $totalRevenue = $salesRevenue;
                    $totalCost = $salesCost;
                    
                    // Total Profit = Sales Profit + Swap Profit (same as manager dashboard)
                    // Sales profit excludes swap transactions, swap profit is realized gains only
                    $totalProfit = $salesProfit + $swapProfit;
                    
                    // Validate and prevent anomalies
                    if ($totalCost < 0) {
                        error_log("Audit Trail WARNING: Negative cost detected (₵{$totalCost}), setting to 0");
                        $totalCost = 0;
                    }
                    
                    // Profit cannot be negative - if cost exceeds revenue, profit is 0
                    if ($totalProfit < 0) {
                        error_log("Audit Trail WARNING: Calculated profit is negative (₵{$totalProfit}). Revenue: ₵{$totalRevenue}, Cost: ₵{$totalCost}. Setting profit to 0.");
                        $totalProfit = 0;
                    }
                    
                    // Calculate margin
                    $totalMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
                    
                    // Round values to prevent floating point anomalies
                    $totalRevenue = round($totalRevenue, 2);
                    $totalCost = round($totalCost, 2);
                    $totalProfit = round($totalProfit, 2);
                    $totalMargin = round($totalMargin, 2);
                    
                    // Update profit response - includes Sales Profit + Swap Profit (realized only)
                    $response['profit'] = [
                        'revenue' => $totalRevenue, // Revenue: Sales only (excluding swap transactions)
                        'cost' => $totalCost, // Sales Cost only (excluding swap transactions)
                        'profit' => $totalProfit, // Total Profit = Sales Profit + Swap Profit (realized only)
                        'sales_profit' => $salesProfit, // Sales Profit (excluding swap transactions)
                        'swap_profit' => $swapProfit, // Swap Profit (realized gains only, from swap page)
                        'margin' => $totalMargin
                    ];
                    
                    error_log("Net Profit recalculated - Revenue: ₵{$totalRevenue} (Sales only, excluding swaps), Cost: ₵{$totalCost} (Sales only), Sales Profit: ₵{$salesProfit}, Swap Profit: ₵{$swapProfit}, Net Profit: ₵{$totalProfit}, Margin: {$totalMargin}%");
                    
                    // Additional debug: Log if profit is 0 but revenue exists
                    if ($totalRevenue > 0 && $totalProfit == 0) {
                        error_log("Audit Trail DEBUG: Profit is 0 but revenue is ₵{$totalRevenue}. Sales Cost: ₵{$salesCost}, Repairer Cost: ₵{$repairerCost}, Total Cost: ₵{$totalCost}");
                    }
                }
            }

            // Enhanced inventory stats with detailed breakdown
            if (isset($response['inventory'])) {
                $response['inventory'] = $this->getEnhancedInventoryStats($companyId);
            }

            // Ensure no output before JSON
            ob_end_clean();
            
            // Set proper headers
            header('Content-Type: application/json; charset=utf-8');
            
            // Encode response with error handling
            $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if ($jsonResponse === false) {
                // JSON encoding failed
                http_response_code(500);
                $errorResponse = [
                    'success' => false,
                    'error' => 'Failed to encode response',
                    'json_error' => json_last_error_msg()
                ];
                echo json_encode($errorResponse);
            } else {
                echo $jsonResponse;
            }
            exit;
        } catch (\Exception $e) {
            // Ensure no output before JSON
            ob_end_clean();
            
            http_response_code(500);
            error_log("Fetch live data error: " . $e->getMessage());
            
            // Set proper headers
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /**
     * Calculate date range from label
     */
    private function calculateDateRange($range) {
        $today = date('Y-m-d');
        
        switch ($range) {
            case 'today':
                return [$today, $today];
            
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return [$yesterday, $yesterday];
            
            case 'this_week':
                $start = date('Y-m-d', strtotime('monday this week'));
                return [$start, $today];
            
            case 'last_week':
                $start = date('Y-m-d', strtotime('monday last week'));
                $end = date('Y-m-d', strtotime('sunday last week'));
                return [$start, $end];
            
            case 'this_month':
                $start = date('Y-m-01');
                return [$start, $today];
            
            case 'last_month':
                $start = date('Y-m-01', strtotime('first day of last month'));
                $end = date('Y-m-t', strtotime('last month'));
                return [$start, $end];
            
            case 'last_7_days':
                $start = date('Y-m-d', strtotime('-7 days'));
                return [$start, $today];
            
            case 'last_30_days':
                $start = date('Y-m-d', strtotime('-30 days'));
                return [$start, $today];
            
            case 'last_90_days':
                $start = date('Y-m-d', strtotime('-90 days'));
                return [$start, $today];
            
            case 'this_year':
                $start = date('Y-01-01');
                return [$start, $today];
            
            case 'last_year':
                $start = date('Y-01-01', strtotime('-1 year'));
                $end = date('Y-12-31', strtotime('-1 year'));
                return [$start, $end];
            
            case 'yearly':
                $start = date('Y-01-01');
                return [$start, $today];
            
            default:
                // Default to last 90 days instead of just today
                $start = date('Y-m-d', strtotime('-90 days'));
                return [$start, $today];
        }
    }

    /**
     * Get customer activity metrics
     */
    private function getCustomerActivity($companyId, $dateFrom, $dateTo) {
        try {
            $db = \Database::getInstance()->getConnection();

            // New customers in range
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM customers
                WHERE company_id = :company_id
                AND DATE(created_at) BETWEEN :date_from AND :date_to
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            $newCustomers = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Active customers (made a purchase)
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT customer_id) as count
                FROM pos_sales
                WHERE company_id = :company_id
                AND DATE(created_at) BETWEEN :date_from AND :date_to
                AND customer_id IS NOT NULL
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            $activeCustomers = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Total customers
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM customers
                WHERE company_id = :company_id
            ");
            $stmt->execute(['company_id' => $companyId]);
            $totalCustomers = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'new_customers' => (int)($newCustomers['count'] ?? 0),
                'active_customers' => (int)($activeCustomers['count'] ?? 0),
                'total_customers' => (int)($totalCustomers['count'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log("Get customer activity error: " . $e->getMessage());
            return [
                'new_customers' => 0,
                'active_customers' => 0,
                'total_customers' => 0
            ];
        }
    }

    /**
     * Get staff activity (salesperson and repairer/technician)
     * @param int|null $staffId If provided, only return data for this staff member
     */
    private function getStaffActivity($companyId, $dateFrom, $dateTo, $staffId = null) {
        try {
            error_log("GetStaffActivity: Called with companyId={$companyId}, dateFrom={$dateFrom}, dateTo={$dateTo}, staffId=" . ($staffId ?? 'null'));
            $db = \Database::getInstance()->getConnection();

            // Check if repairs_new table exists
            $checkRepairsTable = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsTable->rowCount() > 0;

            // Salesperson activity
            $salespersonWhere = "u.company_id = :company_id AND u.role = 'salesperson'";
            $salespersonParams = [
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ];
            
            if ($staffId) {
                $salespersonWhere .= " AND u.id = :staff_id";
                $salespersonParams['staff_id'] = $staffId;
            }
            
            // Check which columns exist in swaps table
            $checkSwapStatus = $db->query("SHOW COLUMNS FROM swaps LIKE 'status'");
            $hasSwapStatus = $checkSwapStatus && $checkSwapStatus->rowCount() > 0;
            $checkSwapStatusCol = $db->query("SHOW COLUMNS FROM swaps LIKE 'swap_status'");
            $hasSwapStatusCol = $checkSwapStatusCol && $checkSwapStatusCol->rowCount() > 0;
            $checkSalespersonId = $db->query("SHOW COLUMNS FROM swaps LIKE 'salesperson_id'");
            $hasSalespersonId = $checkSalespersonId && $checkSalespersonId->rowCount() > 0;
            $checkHandledBy = $db->query("SHOW COLUMNS FROM swaps LIKE 'handled_by'");
            $hasHandledBy = $checkHandledBy && $checkHandledBy->rowCount() > 0;
            $checkCreatedBy = $db->query("SHOW COLUMNS FROM swaps LIKE 'created_by'");
            $hasCreatedBy = $checkCreatedBy && $checkCreatedBy->rowCount() > 0;
            $checkCreatedByUserId = $db->query("SHOW COLUMNS FROM swaps LIKE 'created_by_user_id'");
            $hasCreatedByUserId = $checkCreatedByUserId && $checkCreatedByUserId->rowCount() > 0;
            
            // Determine status column name
            $statusColumn = $hasSwapStatus ? 's.status' : ($hasSwapStatusCol ? 's.swap_status' : 'NULL');
            
            // Determine salesperson/user column name (check in order of preference)
            $salespersonColumn = $hasSalespersonId ? 's.salesperson_id' : 
                                ($hasHandledBy ? 's.handled_by' : 
                                ($hasCreatedBy ? 's.created_by' : 
                                ($hasCreatedByUserId ? 's.created_by_user_id' : 'NULL')));
            
            // Check for final_profit column
            $checkFinalProfit = $db->query("SHOW COLUMNS FROM swaps LIKE 'final_profit'");
            $hasFinalProfit = $checkFinalProfit && $checkFinalProfit->rowCount() > 0;
            $profitColumn = $hasFinalProfit ? 's.final_profit' : '0';
            
            // Build swap profit calculation
            if ($statusColumn !== 'NULL') {
                $swapProfitCalc = "COALESCE(SUM(CASE WHEN {$statusColumn} = 'completed' OR {$statusColumn} = 'COMPLETED' THEN {$profitColumn} ELSE 0 END), 0)";
            } else {
                $swapProfitCalc = "0";
            }
            
            // Build swap JOIN condition
            if ($salespersonColumn !== 'NULL') {
                $swapJoinCondition = "{$salespersonColumn} = u.id AND DATE(s.created_at) BETWEEN :date_from AND :date_to";
            } else {
                // If no salesperson column exists, skip the swap join
                $swapJoinCondition = "1=0"; // This will prevent any swaps from being joined
            }
            
            // Check if swap exclusion columns exist
            $checkIsSwapMode = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode && $checkIsSwapMode->rowCount() > 0;
            $checkSwapId = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'swap_id'");
            $hasSwapId = $checkSwapId && $checkSwapId->rowCount() > 0;
            
            // Exclude swap sales from salesperson revenue (swaps should only appear on swap page)
            $excludeSwapSales = "";
            if ($hasIsSwapMode) {
                $excludeSwapSales = " AND (ps.is_swap_mode = 0 OR ps.is_swap_mode IS NULL)";
            }
            if ($hasSwapId) {
                $excludeSwapSales .= " AND ps.swap_id IS NULL";
            }
            
            $salespersonQuery = $db->prepare("
                SELECT 
                    u.id,
                    u.full_name as name,
                    u.username,
                    u.role,
                    COUNT(DISTINCT ps.id) as sales_count,
                    COALESCE(SUM(ps.final_amount), 0) as sales_revenue,
                    COUNT(DISTINCT s.id) as swaps_handled,
                    {$swapProfitCalc} as swap_profit
                FROM users u
                LEFT JOIN pos_sales ps ON ps.created_by_user_id = u.id 
                    AND DATE(ps.created_at) BETWEEN :date_from AND :date_to
                    AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))
                    {$excludeSwapSales}
                LEFT JOIN swaps s ON {$swapJoinCondition}
                WHERE {$salespersonWhere}
                GROUP BY u.id, u.full_name, u.username, u.role
                ORDER BY sales_revenue DESC
            ");
            $salespersonQuery->execute($salespersonParams);
            $salespersons = $salespersonQuery->fetchAll(\PDO::FETCH_ASSOC);

            // Repairer/Technician activity
            // Find all users who have repairs assigned to them (regardless of role)
            // This ensures we capture all repairers, even if their role isn't 'technician'
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            $technicianParams = [
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ];
            
            $technicianWhere = "u.company_id = :company_id";
            if ($staffId) {
                $technicianWhere .= " AND u.id = :staff_id";
                $technicianParams['staff_id'] = $staffId;
            }
            
            if ($hasRepairsNew) {
                // Check which products table exists
                $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
                $hasProductsNew = $checkProductsNew && $checkProductsNew->rowCount() > 0;
                $productsTable = $hasProductsNew ? 'products_new' : 'products';
                
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
                
                $technicianQuery = $db->prepare("
                    SELECT 
                        u.id,
                        u.full_name as name,
                        u.username,
                        u.role,
                        COUNT(DISTINCT r.id) as repairs_count,
                        COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_repairs,
                        COUNT(DISTINCT CASE WHEN r.status = 'in_progress' THEN r.id END) as in_progress_repairs,
                        -- Workmanship Revenue (repair charges - separate from parts)
                        COALESCE(SUM(r.repair_cost), 0) as workmanship_revenue,
                        -- Labour Cost (actual cost of providing repair service)
                        COALESCE(SUM(COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as labour_cost,
                        -- Workmanship Profit: repair_cost (revenue) - labour_cost (cost)
                        COALESCE(SUM(r.repair_cost - COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as workmanship_profit,
                        -- Parts & Accessories Revenue (from repair_accessories table)
                        COALESCE(SUM(ra.price * ra.quantity), 0) as parts_revenue,
                        -- Parts & Accessories Cost (from products table)
                        COALESCE(SUM({$costColumn} * ra.quantity), 0) as parts_cost,
                        -- Parts & Accessories Profit: (selling_price - cost) * quantity
                        COALESCE(SUM((ra.price - {$costColumn}) * ra.quantity), 0) as parts_profit,
                        -- Parts Count: Total number of products sold as spare parts
                        COALESCE(SUM(ra.quantity), 0) as parts_count,
                        -- Total Revenue (workmanship + parts)
                        COALESCE(SUM(r.repair_cost), 0) + COALESCE(SUM(ra.price * ra.quantity), 0) as total_revenue,
                        AVG(CASE WHEN r.status = 'completed' 
                            THEN DATEDIFF(COALESCE(r.updated_at, r.created_at), r.created_at) ELSE NULL END) as avg_repair_days
                    FROM repairs_new r
                    INNER JOIN users u ON r.technician_id = u.id AND u.company_id = :company_id
                    LEFT JOIN repair_accessories ra ON ra.repair_id = r.id
                    LEFT JOIN {$productsTable} p ON ra.product_id = p.id AND p.company_id = :company_id
                    WHERE r.company_id = :company_id 
                        AND r.technician_id IS NOT NULL
                        AND r.created_at >= :date_from_start 
                        AND r.created_at <= :date_to_end
                        " . ($staffId ? "AND u.id = :staff_id" : "") . "
                    GROUP BY u.id, u.full_name, u.username, u.role
                    HAVING repairs_count > 0
                    ORDER BY total_revenue DESC, parts_profit DESC, workmanship_revenue DESC
                ");
            } else {
                $technicianQuery = $db->prepare("
                    SELECT 
                        u.id,
                        u.full_name as name,
                        u.username,
                        u.role,
                        0 as repairs_count,
                        0 as completed_repairs,
                        0 as in_progress_repairs,
                        0 as repair_revenue,
                        0 as avg_repair_days
                    FROM users u
                    WHERE {$technicianWhere}
                    GROUP BY u.id, u.full_name, u.username, u.role
                ");
            }
            error_log("GetStaffActivity: Executing query for company {$companyId}, date range: {$dateFrom} to {$dateTo}, hasRepairsNew: " . ($hasRepairsNew ? 'yes' : 'no'));
            error_log("GetStaffActivity: Query params: " . json_encode($technicianParams));
            try {
                $technicianQuery->execute($technicianParams);
                $technicians = $technicianQuery->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log("GetStaffActivity: SQL Error: " . $e->getMessage());
                error_log("GetStaffActivity: SQL Error Code: " . $e->getCode());
                throw $e;
            }
            
            // Debug logging
            error_log("GetStaffActivity: Found " . count($technicians) . " technicians for company {$companyId} from {$dateFrom} to {$dateTo}");
            if (count($technicians) > 0) {
                error_log("GetStaffActivity: First technician: " . json_encode($technicians[0]));
            }
            if (count($technicians) === 0) {
                // Check if there are any repairs at all
                $checkRepairs = $db->prepare("
                    SELECT COUNT(*) as total, 
                           COUNT(DISTINCT technician_id) as unique_technicians,
                           MIN(created_at) as earliest,
                           MAX(created_at) as latest
                    FROM repairs_new 
                    WHERE company_id = :company_id
                ");
                $checkRepairs->execute(['company_id' => $companyId]);
                $repairCheck = $checkRepairs->fetch(\PDO::FETCH_ASSOC);
                error_log("GetStaffActivity: Total repairs in company: {$repairCheck['total']}, Unique technicians: {$repairCheck['unique_technicians']}, Date range: {$repairCheck['earliest']} to {$repairCheck['latest']}");
                
                // Check repairs in date range
                $checkDateRange = $db->prepare("
                    SELECT COUNT(*) as count
                    FROM repairs_new 
                    WHERE company_id = :company_id 
                    AND created_at >= :date_from_start 
                    AND created_at <= :date_to_end
                ");
                $checkDateRange->execute([
                    'company_id' => $companyId,
                    'date_from_start' => $dateFromStart,
                    'date_to_end' => $dateToEnd
                ]);
                $dateRangeCheck = $checkDateRange->fetch(\PDO::FETCH_ASSOC);
                error_log("GetStaffActivity: Repairs in date range ({$dateFrom} to {$dateTo}): {$dateRangeCheck['count']}");
            }
            
            // Calculate profit margin and totals for each technician
            foreach ($technicians as &$tech) {
                $workmanshipRevenue = floatval($tech['workmanship_revenue'] ?? 0);
                $labourCost = floatval($tech['labour_cost'] ?? 0);
                $workmanshipProfit = floatval($tech['workmanship_profit'] ?? ($workmanshipRevenue - $labourCost));
                $partsRevenue = floatval($tech['parts_revenue'] ?? 0);
                $partsProfit = floatval($tech['parts_profit'] ?? 0);
                $totalRevenue = floatval($tech['total_revenue'] ?? ($workmanshipRevenue + $partsRevenue));
                
                $tech['parts_profit_margin'] = $partsRevenue > 0 ? ($partsProfit / $partsRevenue) * 100 : 0;
                $tech['workmanship_profit_margin'] = $workmanshipRevenue > 0 ? ($workmanshipProfit / $workmanshipRevenue) * 100 : 0;
                $tech['repair_revenue'] = $totalRevenue; // Total repair revenue (workmanship + parts) for backward compatibility
                $tech['total_revenue'] = $totalRevenue; // Ensure total_revenue is set
                $tech['labour_cost'] = $labourCost; // Ensure labour_cost is set
                $tech['workmanship_profit'] = $workmanshipProfit; // Ensure workmanship_profit is set
            }
            unset($tech);

            return [
                'salespersons' => $salespersons,
                'technicians' => $technicians,
                'total_salespersons' => count($salespersons),
                'total_technicians' => count($technicians)
            ];
        } catch (\Exception $e) {
            error_log("Get staff activity error: " . $e->getMessage());
            return [
                'salespersons' => [],
                'technicians' => [],
                'total_salespersons' => 0,
                'total_technicians' => 0
            ];
        }
    }

    /**
     * Get items sold by repairers (parts/accessories from repairs)
     * @param int $companyId
     * @param string $dateFrom
     * @param string $dateTo
     * @param int|null $staffId Optional staff member filter
     * @return array
     */
    private function getRepairerPartsSales($companyId, $dateFrom, $dateTo, $staffId = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check which tables exist
            $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
            $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
            $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
            $hasProductsNew = $checkProductsNew && $checkProductsNew->rowCount() > 0;
            $productsTable = $hasProductsNew ? 'products_new' : 'products';
            
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
            
            if (!$hasRepairsNew) {
                return [
                    'total_revenue' => 0,
                    'total_cost' => 0,
                    'total_profit' => 0,
                    'items' => [],
                    'count' => 0
                ];
            }
            
            $where = "r.company_id = :company_id AND DATE(r.created_at) BETWEEN :date_from AND :date_to";
            $params = [
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ];
            
            if ($staffId) {
                $where .= " AND r.technician_id = :staff_id";
                $params['staff_id'] = $staffId;
            }
            
            $query = $db->prepare("
                SELECT 
                    ra.id,
                    ra.repair_id,
                    r.tracking_code,
                    r.customer_name,
                    r.customer_contact,
                    u.full_name as repairer_name,
                    p.name as product_name,
                    p.sku,
                    ra.quantity,
                    ra.price as selling_price,
                        {$costColumn} as cost_price,
                        (ra.price * ra.quantity) as revenue,
                        ({$costColumn} * ra.quantity) as cost,
                        ((ra.price - {$costColumn}) * ra.quantity) as profit,
                    r.created_at as sold_date
                FROM repair_accessories ra
                INNER JOIN repairs_new r ON ra.repair_id = r.id
                LEFT JOIN users u ON r.technician_id = u.id
                LEFT JOIN {$productsTable} p ON ra.product_id = p.id AND p.company_id = :company_id
                WHERE {$where}
                ORDER BY r.created_at DESC, ra.id DESC
            ");
            $query->execute($params);
            $items = $query->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calculate totals
            $totalRevenue = 0;
            $totalCost = 0;
            $totalProfit = 0;
            
            foreach ($items as $item) {
                $totalRevenue += floatval($item['revenue'] ?? 0);
                $totalCost += floatval($item['cost'] ?? 0);
                $totalProfit += floatval($item['profit'] ?? 0);
            }
            
            return [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0,
                'items' => $items,
                'count' => count($items)
            ];
        } catch (\Exception $e) {
            error_log("Get repairer parts sales error: " . $e->getMessage());
            return [
                'total_revenue' => 0,
                'total_cost' => 0,
                'total_profit' => 0,
                'profit_margin' => 0,
                'items' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get comprehensive activity logs
     */
    /**
     * Get activity logs for company
     * @param int $companyId
     * @param string $dateFrom
     * @param string $dateTo
     * @param int|null $staffId Optional staff member filter
     * @param int|null $customerId Optional customer filter
     * @param int|null $productId Optional product filter
     * @param string|null $imei Optional IMEI filter
     * @return array
     */
    private function getActivityLogs($companyId, $dateFrom, $dateTo, $staffId = null, $customerId = null, $productId = null, $imei = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            $logs = [];
            
            // Validate date range
            if (!$dateFrom || !$dateTo) {
                error_log("getActivityLogs: Invalid date range - dateFrom: {$dateFrom}, dateTo: {$dateTo}");
                // Default to last 90 days if invalid
                $dateTo = date('Y-m-d');
                $dateFrom = date('Y-m-d', strtotime('-90 days'));
            }
            
            // Ensure dateTo includes the full day (add time component to include all of today)
            $dateToEnd = $dateTo . ' 23:59:59';
            $dateFromStart = $dateFrom . ' 00:00:00';

            // First, check if there are any sales for this company at all (for debugging)
            $checkSalesQuery = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id");
            $checkSalesQuery->execute(['company_id' => $companyId]);
            $totalSales = $checkSalesQuery->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
            error_log("getActivityLogs: Total sales for company {$companyId}: {$totalSales}");
            
            // Check sales in date range using both DATE() and datetime comparison
            $checkDateRangeQuery = $db->prepare("
                SELECT COUNT(*) as cnt 
                FROM pos_sales 
                WHERE company_id = :company_id 
                AND created_at >= :date_from_start 
                AND created_at <= :date_to_end
            ");
            $checkDateRangeQuery->execute([
                'company_id' => $companyId,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ]);
            $salesInRange = $checkDateRangeQuery->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
            error_log("getActivityLogs: Sales in date range {$dateFrom} to {$dateTo}: {$salesInRange}");
            
            // Check today's sales specifically
            $today = date('Y-m-d');
            $checkTodayQuery = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id AND DATE(created_at) = :today");
            $checkTodayQuery->execute([
                'company_id' => $companyId,
                'today' => $today
            ]);
            $todaySales = $checkTodayQuery->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
            error_log("getActivityLogs: Sales today ({$today}): {$todaySales}");

            // Sales activities - use datetime comparison to be more accurate
            $salesWhere = "ps.company_id = :company_id AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end";
            $salesParams = [
                'company_id' => $companyId,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ];
            
            if ($staffId) {
                $salesWhere .= " AND ps.created_by_user_id = :staff_id";
                $salesParams['staff_id'] = $staffId;
            }
            
            if ($customerId) {
                $salesWhere .= " AND ps.customer_id = :customer_id";
                $salesParams['customer_id'] = $customerId;
            }
            
            // For product/IMEI filtering in sales, we need to join with pos_sale_items and products
            $productJoin = "";
            if ($productId || $imei) {
                $productJoin = "LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id LEFT JOIN products p ON psi.item_id = p.id";
                if ($productId) {
                    $salesWhere .= " AND p.id = :product_id";
                    $salesParams['product_id'] = $productId;
                }
                if ($imei) {
                    $salesWhere .= " AND p.imei LIKE :imei";
                    $salesParams['imei'] = '%' . $imei . '%';
                }
            }
            
            // Include repair parts sales in general sales (for manager view, not filtered by staff)
            // When viewing all sales (no staff filter), include repair parts
            // When viewing specific staff, exclude repair parts from their stats (already handled in getSalesStats)
            // Get item descriptions from pos_sale_items using subquery
            $salesQuery = $db->prepare("
                SELECT DISTINCT
                    ps.id,
                    'sale' as activity_type,
                    ps.created_at as timestamp,
                    u.full_name as user_name,
                    u.role as user_role,
                    ps.final_amount as amount,
                    COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)) as reference,
                    COALESCE(c.full_name, 'Walk-in Customer') as customer_name,
                    c.phone_number as customer_phone,
                    COALESCE(ps.payment_status, 'PAID') as status,
                    CASE 
                        WHEN ps.notes LIKE '%Repair #%' OR ps.notes LIKE '%Products sold by repairer%' 
                        THEN CONCAT('Part Sold by Repairer: ', COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)))
                        ELSE CONCAT('Sale: ', COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)))
                    END as description,
                    CASE 
                        WHEN ps.notes LIKE '%Repair #%' OR ps.notes LIKE '%Products sold by repairer%' 
                        THEN 'repair_part_sale'
                        ELSE 'sale'
                    END as sale_type,
                    (SELECT GROUP_CONCAT(DISTINCT COALESCE(psi.item_description, psi.item_name, 'Product') SEPARATOR ', ')
                     FROM pos_sale_items psi 
                     WHERE psi.pos_sale_id = ps.id 
                     LIMIT 3) as item_description
                FROM pos_sales ps
                LEFT JOIN users u ON ps.created_by_user_id = u.id
                LEFT JOIN customers c ON ps.customer_id = c.id
                {$productJoin}
                WHERE {$salesWhere}
                GROUP BY ps.id, ps.created_at, u.full_name, u.role, ps.final_amount, ps.unique_id, c.full_name, c.phone_number, ps.payment_status, ps.notes
                ORDER BY ps.created_at DESC
                LIMIT 50
            ");
            $salesQuery->execute($salesParams);
            $salesLogs = $salesQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("getActivityLogs: Sales query returned " . count($salesLogs) . " records");
            if (count($salesLogs) > 0) {
                error_log("getActivityLogs: Sample sale: " . json_encode($salesLogs[0]));
            } else {
                error_log("getActivityLogs: No sales found with params: " . json_encode($salesParams));
            }

            // Repair activities
            $checkRepairsTable = $db->query("SHOW TABLES LIKE 'repairs_new'");
            if ($checkRepairsTable->rowCount() > 0) {
                $repairsWhere = "r.company_id = :company_id AND r.created_at >= :date_from_start AND r.created_at <= :date_to_end";
                $repairsParams = [
                    'company_id' => $companyId,
                    'date_from_start' => $dateFromStart,
                    'date_to_end' => $dateToEnd
                ];
                
                if ($staffId) {
                    $repairsWhere .= " AND r.assigned_technician_id = :staff_id";
                    $repairsParams['staff_id'] = $staffId;
                }
                
                if ($customerId) {
                    $repairsWhere .= " AND r.customer_id = :customer_id";
                    $repairsParams['customer_id'] = $customerId;
                }
                
                if ($imei) {
                    $repairsWhere .= " AND r.imei LIKE :imei";
                    $repairsParams['imei'] = '%' . $imei . '%';
                }
                
                $repairsQuery = $db->prepare("
                    SELECT 
                        r.id,
                        'repair' as activity_type,
                        r.created_at as timestamp,
                        u.full_name as user_name,
                        u.role as user_role,
                        r.repair_cost as amount,
                        r.tracking_code as reference,
                        COALESCE(c.full_name, 'Walk-in Customer') as customer_name,
                        c.phone_number as customer_phone,
                        r.status,
                        COALESCE(r.phone_description, r.issue, CONCAT('Repair: ', r.tracking_code)) as item_description,
                        CONCAT('Repair: ', r.tracking_code, ' - ', r.status) as description
                    FROM repairs_new r
                    LEFT JOIN users u ON r.assigned_technician_id = u.id
                    LEFT JOIN customers c ON r.customer_id = c.id
                    WHERE {$repairsWhere}
                    ORDER BY r.created_at DESC
                    LIMIT 50
                ");
                $repairsQuery->execute($repairsParams);
                $repairsLogs = $repairsQuery->fetchAll(\PDO::FETCH_ASSOC);
                error_log("getActivityLogs: Repairs query returned " . count($repairsLogs) . " records");
                if (count($repairsLogs) > 0) {
                    error_log("getActivityLogs: Sample repair: " . json_encode($repairsLogs[0]));
                    error_log("getActivityLogs: Repair activity_type: " . ($repairsLogs[0]['activity_type'] ?? 'missing'));
                } else {
                    error_log("getActivityLogs: No repairs found with params: " . json_encode($repairsParams));
                }
            } else {
                $repairsLogs = [];
                error_log("getActivityLogs: repairs_new table does not exist, skipping repairs");
            }

            // Swap activities - check if swaps table exists first
            $swapsLogs = [];
            $totalSwaps = 0;
            $checkSwapsTable = $db->query("SHOW TABLES LIKE 'swaps'");
            if ($checkSwapsTable->rowCount() > 0) {
                // First check total swaps count for debugging
                $checkSwapsCount = $db->prepare("SELECT COUNT(*) as cnt FROM swaps WHERE company_id = :company_id");
                $checkSwapsCount->execute(['company_id' => $companyId]);
                $totalSwaps = $checkSwapsCount->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
                error_log("getActivityLogs: Total swaps in database for company {$companyId}: {$totalSwaps}");
                
                $swapsWhere = "s.company_id = :company_id AND s.created_at >= :date_from_start AND s.created_at <= :date_to_end";
                $swapsParams = [
                    'company_id' => $companyId,
                    'date_from_start' => $dateFromStart,
                    'date_to_end' => $dateToEnd
                ];
                
                if ($staffId) {
                    // Check if column is salesperson_id or handled_by
                    $swapsColumns = $db->query("SHOW COLUMNS FROM swaps")->fetchAll(\PDO::FETCH_COLUMN);
                    if (in_array('salesperson_id', $swapsColumns)) {
                        $swapsWhere .= " AND s.salesperson_id = :staff_id";
                    } elseif (in_array('handled_by', $swapsColumns)) {
                        $swapsWhere .= " AND s.handled_by = :staff_id";
                    }
                    $swapsParams['staff_id'] = $staffId;
                }
                
                if ($customerId) {
                    $swapsWhere .= " AND s.customer_id = :customer_id";
                    $swapsParams['customer_id'] = $customerId;
                }
                
                // For swaps, IMEI filtering would be on customer_products
                if ($imei) {
                    $swapsWhere .= " AND EXISTS (
                        SELECT 1 FROM customer_products cp 
                        WHERE cp.id = s.customer_product_id 
                        AND cp.imei LIKE :imei
                    )";
                    $swapsParams['imei'] = '%' . $imei . '%';
                }
                
                // Check which columns exist in swaps table
                $swapsColumns = $db->query("SHOW COLUMNS FROM swaps")->fetchAll(\PDO::FETCH_COLUMN);
                $hasSwapCode = in_array('swap_code', $swapsColumns);
                $hasTransactionCode = in_array('transaction_code', $swapsColumns);
                $hasFinalProfit = in_array('final_profit', $swapsColumns);
                $hasTotalValue = in_array('total_value', $swapsColumns);
                $hasSalespersonId = in_array('salesperson_id', $swapsColumns);
                $hasHandledBy = in_array('handled_by', $swapsColumns);
                $hasCustomerName = in_array('customer_name', $swapsColumns);
                $hasCustomerPhone = in_array('customer_phone', $swapsColumns);
                
                // Build the SELECT fields based on what columns exist
                $referenceSelect = $hasSwapCode ? 's.swap_code' : ($hasTransactionCode ? 's.transaction_code' : "CONCAT('SWAP-', s.id)");
                // CRITICAL FIX: Get profit from swap_profit_links instead of swaps.final_profit
                // Check if swap_profit_links table exists
                $checkProfitLinksTable = $db->query("SHOW TABLES LIKE 'swap_profit_links'");
                $hasProfitLinksTable = $checkProfitLinksTable->rowCount() > 0;
                
                if ($hasProfitLinksTable) {
                    // Check if sale ID columns exist
                    $hasSaleIdColumns = false;
                    try {
                        $checkSaleIdCols = $db->query("SHOW COLUMNS FROM swap_profit_links LIKE 'company_item_sale_id'");
                        $hasSaleIdColumns = $checkSaleIdCols->rowCount() > 0;
                    } catch (\Exception $e) {
                        $hasSaleIdColumns = false;
                    }
                    
                    if ($hasSaleIdColumns) {
                        // Use profit from swap_profit_links - same logic as swaps page
                        // Profit is realized when customer item is resold (customer_item_sale_id exists)
                        // Company sale ID may be NULL for old swaps, but profit can still be calculated
                        $amountSelect = "CASE 
                            WHEN spl.customer_item_sale_id IS NOT NULL 
                            AND spl.status = 'finalized' 
                            AND spl.final_profit IS NOT NULL 
                            THEN spl.final_profit 
                            WHEN spl.customer_item_sale_id IS NOT NULL 
                            AND spl.final_profit IS NOT NULL 
                            THEN spl.final_profit
                            WHEN spl.customer_item_sale_id IS NOT NULL 
                            AND spl.profit_estimate IS NOT NULL 
                            THEN spl.profit_estimate 
                            ELSE 0 
                        END";
                    } else {
                        // Fallback: use final_profit or profit_estimate if sale ID columns don't exist
                        $amountSelect = "COALESCE(spl.final_profit, spl.profit_estimate, 0)";
                    }
                } else {
                    // Fallback to swaps.final_profit if swap_profit_links doesn't exist
                    $amountSelect = $hasFinalProfit ? 's.final_profit' : ($hasTotalValue ? 's.total_value' : '0');
                }
                
                $customerNameSelect = $hasCustomerName ? 's.customer_name' : 'NULL';
                $customerPhoneSelect = $hasCustomerPhone ? 's.customer_phone' : 'NULL';
                
                $userJoinCondition = $hasSalespersonId ? 's.salesperson_id = u.id' : ($hasHandledBy ? 's.handled_by = u.id' : '1=0');
                
                error_log("getActivityLogs: Swaps table structure - swap_code: " . ($hasSwapCode ? 'yes' : 'no') . ", transaction_code: " . ($hasTransactionCode ? 'yes' : 'no') . ", final_profit: " . ($hasFinalProfit ? 'yes' : 'no') . ", total_value: " . ($hasTotalValue ? 'yes' : 'no') . ", swap_profit_links: " . ($hasProfitLinksTable ? 'yes' : 'no'));
                
                // Build JOIN clause for swap_profit_links if table exists - same as Swap model (no condition, just LEFT JOIN)
                $profitLinksJoin = $hasProfitLinksTable ? 'LEFT JOIN swap_profit_links spl ON s.id = spl.swap_id' : '';
                
                $swapsQuery = $db->prepare("
                    SELECT 
                        s.id,
                        'swap' as activity_type,
                        s.created_at as timestamp,
                        u.full_name as user_name,
                        u.role as user_role,
                        {$amountSelect} as amount,
                        COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)) as reference,
                        COALESCE(c.full_name, {$customerNameSelect}, 'Walk-in Customer') as customer_name,
                        COALESCE(c.phone_number, {$customerPhoneSelect}, '') as customer_phone,
                        COALESCE(s.status, 'completed') as status,
                        COALESCE(
                            CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(cp.brand, ''), ' ', COALESCE(cp.model, '')),
                            CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(s.status, 'completed'))
                        ) as item_description,
                        CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(s.status, 'completed')) as description
                    FROM swaps s
                    LEFT JOIN users u ON {$userJoinCondition}
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                    {$profitLinksJoin}
                    WHERE {$swapsWhere}
                    ORDER BY s.created_at DESC
                    LIMIT 50
                ");
                try {
                    error_log("getActivityLogs: Executing swaps query with params: " . json_encode($swapsParams));
                    error_log("getActivityLogs: Swaps WHERE clause: {$swapsWhere}");
                    $swapsQuery->execute($swapsParams);
                    $swapsLogs = $swapsQuery->fetchAll(\PDO::FETCH_ASSOC);
                    error_log("getActivityLogs: Swaps query returned " . count($swapsLogs) . " records");
                    if (count($swapsLogs) > 0) {
                        error_log("getActivityLogs: Sample swap record: " . json_encode($swapsLogs[0]));
                        // Verify activity_type is set correctly
                        foreach ($swapsLogs as $swap) {
                            if (($swap['activity_type'] ?? '') !== 'swap') {
                                error_log("getActivityLogs: WARNING - Swap record has incorrect activity_type: " . ($swap['activity_type'] ?? 'missing'));
                            }
                        }
                    } else {
                        // Check swaps in date range
                        $checkSwapsDateRange = $db->prepare("SELECT COUNT(*) as cnt FROM swaps WHERE company_id = :company_id AND created_at >= :date_from_start AND created_at <= :date_to_end");
                        $checkSwapsDateRange->execute([
                            'company_id' => $companyId,
                            'date_from_start' => $dateFromStart,
                            'date_to_end' => $dateToEnd
                        ]);
                        $swapsInRange = $checkSwapsDateRange->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
                        error_log("getActivityLogs: Swaps in date range {$dateFrom} to {$dateTo}: {$swapsInRange}");
                        
                        // If swaps exist but none in date range, fetch most recent swaps as fallback
                        if ($totalSwaps > 0 && $swapsInRange === 0) {
                            error_log("getActivityLogs: Swaps exist but none in date range, fetching most recent swaps...");
                            $swapsFallbackWhere = "s.company_id = :company_id";
                            $swapsFallbackParams = ['company_id' => $companyId];
                            if ($staffId) {
                                if ($hasSalespersonId) {
                                    $swapsFallbackWhere .= " AND s.salesperson_id = :staff_id";
                                    $swapsFallbackParams['staff_id'] = $staffId;
                                } elseif ($hasHandledBy) {
                                    $swapsFallbackWhere .= " AND s.handled_by = :staff_id";
                                    $swapsFallbackParams['staff_id'] = $staffId;
                                }
                            }
                            $swapsFallbackQuery = $db->prepare("
                                SELECT 
                                    s.id,
                                    'swap' as activity_type,
                                    s.created_at as timestamp,
                                    u.full_name as user_name,
                                    u.role as user_role,
                                    COALESCE({$amountSelect}, 0) as amount,
                                    COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)) as reference,
                                    COALESCE(c.full_name, {$customerNameSelect}, 'Walk-in Customer') as customer_name,
                                    COALESCE(c.phone_number, {$customerPhoneSelect}, '') as customer_phone,
                                    COALESCE(s.status, 'completed') as status,
                                    COALESCE(
                                        CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(cp.brand, ''), ' ', COALESCE(cp.model, '')),
                                        CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(s.status, 'completed'))
                                    ) as item_description,
                                    CONCAT('Swap: ', COALESCE({$referenceSelect}, CONCAT('SWAP-', s.id)), ' - ', COALESCE(s.status, 'completed')) as description
                                FROM swaps s
                                LEFT JOIN users u ON {$userJoinCondition}
                                LEFT JOIN customers c ON s.customer_id = c.id
                                LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                                WHERE {$swapsFallbackWhere}
                                ORDER BY s.created_at DESC
                                LIMIT 50
                            ");
                            try {
                                $swapsFallbackQuery->execute($swapsFallbackParams);
                                $swapsLogs = $swapsFallbackQuery->fetchAll(\PDO::FETCH_ASSOC);
                                error_log("getActivityLogs: Swaps fallback query returned " . count($swapsLogs) . " records");
                            } catch (\Exception $e2) {
                                error_log("getActivityLogs: Swaps fallback query failed: " . $e2->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("getActivityLogs: ERROR executing swaps query: " . $e->getMessage());
                    error_log("getActivityLogs: Swaps query error trace: " . $e->getTraceAsString());
                    // Try fallback query even on error
                    if ($totalSwaps > 0) {
                        error_log("getActivityLogs: Attempting swaps fallback query after error...");
                        try {
                            $swapsFallbackQuery = $db->prepare("
                                SELECT 
                                    s.id,
                                    'swap' as activity_type,
                                    s.created_at as timestamp,
                                    NULL as user_name,
                                    NULL as user_role,
                                    0 as amount,
                                    CONCAT('SWAP-', s.id) as reference,
                                    'Walk-in Customer' as customer_name,
                                    '' as customer_phone,
                                    'completed' as status,
                                    CONCAT('Swap: SWAP-', s.id) as item_description,
                                    CONCAT('Swap: SWAP-', s.id) as description
                                FROM swaps s
                                WHERE s.company_id = :company_id
                                ORDER BY s.created_at DESC
                                LIMIT 50
                            ");
                            $swapsFallbackQuery->execute(['company_id' => $companyId]);
                            $swapsLogs = $swapsFallbackQuery->fetchAll(\PDO::FETCH_ASSOC);
                            error_log("getActivityLogs: Swaps emergency fallback returned " . count($swapsLogs) . " records");
                        } catch (\Exception $e3) {
                            error_log("getActivityLogs: Swaps emergency fallback also failed: " . $e3->getMessage());
                        }
                    }
                }
            } else {
                error_log("getActivityLogs: Swaps table does not exist, skipping swaps");
            }

            // Combine and sort by timestamp
            $allLogs = array_merge($salesLogs, $repairsLogs, $swapsLogs);
            usort($allLogs, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            // Log for debugging
            error_log("getActivityLogs: Found " . count($allLogs) . " logs for company {$companyId} from {$dateFrom} to {$dateTo}");
            error_log("getActivityLogs: Breakdown - Sales: " . count($salesLogs) . ", Repairs: " . count($repairsLogs ?? []) . ", Swaps: " . count($swapsLogs));
            
            // Log swap details for debugging
            if (count($swapsLogs) > 0) {
                error_log("getActivityLogs: Swaps found - First swap: " . json_encode($swapsLogs[0]));
                error_log("getActivityLogs: Swaps found - All swap activity_types: " . implode(', ', array_column($swapsLogs, 'activity_type')));
            } else {
                error_log("getActivityLogs: No swaps found in results");
            }
            
            // CRITICAL: If we have sales in the database but got 0 results, ALWAYS use fallback
            // This ensures we always return transactions if they exist, regardless of date range issues
            $shouldUseFallback = false;
            if ($totalSales > 0 && count($allLogs) === 0) {
                $shouldUseFallback = true;
                error_log("getActivityLogs: CRITICAL - Total sales ({$totalSales}) exist but got 0 results. Forcing fallback.");
            } elseif (count($allLogs) === 0 && $totalSales > 0) {
                $shouldUseFallback = true;
                error_log("getActivityLogs: No logs found in date range but total sales exist ({$totalSales}). Using fallback.");
            } elseif ($todaySales > 0 && count($salesLogs) === 0) {
                $shouldUseFallback = true;
                error_log("getActivityLogs: Today has {$todaySales} sales but query returned 0, using fallback");
            } elseif ($totalSales > 0 && count($salesLogs) === 0) {
                $shouldUseFallback = true;
                error_log("getActivityLogs: Total sales exist ({$totalSales}) but date range query returned 0, using fallback");
            }
            
            if ($shouldUseFallback) {
                // If no logs found in date range, try to get the most recent transactions regardless of date
                error_log("getActivityLogs: Fetching most recent transactions (fallback)...");
                
                // Try pos_sales table first
                $fallbackQuery = $db->prepare("
                    SELECT 
                        ps.id,
                        'sale' as activity_type,
                        ps.created_at as timestamp,
                        u.full_name as user_name,
                        u.role as user_role,
                        ps.final_amount as amount,
                        COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)) as reference,
                        COALESCE(c.full_name, 'Walk-in Customer') as customer_name,
                        c.phone_number as customer_phone,
                        COALESCE(ps.payment_status, 'PAID') as status,
                        CONCAT('Sale: ', COALESCE(ps.unique_id, CONCAT('SALE-', ps.id))) as description
                    FROM pos_sales ps
                    LEFT JOIN users u ON ps.created_by_user_id = u.id
                    LEFT JOIN customers c ON ps.customer_id = c.id
                    WHERE ps.company_id = :company_id
                    ORDER BY ps.created_at DESC
                    LIMIT 50
                ");
                $fallbackQuery->execute(['company_id' => $companyId]);
                $fallbackLogs = $fallbackQuery->fetchAll(\PDO::FETCH_ASSOC);
                error_log("getActivityLogs: Fallback query (pos_sales) returned " . count($fallbackLogs) . " records");
                
                // If still no results, try sales_new table as alternative
                if (count($fallbackLogs) === 0) {
                    error_log("getActivityLogs: Trying sales_new table as alternative...");
                    $checkSalesNew = $db->query("SHOW TABLES LIKE 'sales_new'");
                    if ($checkSalesNew->rowCount() > 0) {
                        $fallbackQuery2 = $db->prepare("
                            SELECT 
                                s.id,
                                'sale' as activity_type,
                                s.created_at as timestamp,
                                u.full_name as user_name,
                                u.role as user_role,
                                s.total as amount,
                                COALESCE(s.unique_id, CONCAT('SALE-', s.id)) as reference,
                                COALESCE(c.full_name, s.customer_name, 'Walk-in Customer') as customer_name,
                                c.phone_number as customer_phone,
                                COALESCE(s.payment_status, 'completed') as status,
                                CONCAT('Sale: ', COALESCE(s.unique_id, CONCAT('SALE-', s.id))) as description
                            FROM sales_new s
                            LEFT JOIN users u ON s.cashier_id = u.id
                            LEFT JOIN customers c ON s.customer_id = c.id
                            WHERE s.company_id = :company_id
                            ORDER BY s.created_at DESC
                            LIMIT 50
                        ");
                        $fallbackQuery2->execute(['company_id' => $companyId]);
                        $fallbackLogs = $fallbackQuery2->fetchAll(\PDO::FETCH_ASSOC);
                        error_log("getActivityLogs: Fallback query (sales_new) returned " . count($fallbackLogs) . " records");
                    }
                }
                
                if (count($fallbackLogs) > 0) {
                    // Merge fallback logs with swaps and repairs (fallback only gets sales)
                    $allLogs = array_merge($fallbackLogs, $repairsLogs, $swapsLogs);
                    usort($allLogs, function($a, $b) {
                        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                    });
                    error_log("getActivityLogs: Using fallback results - found " . count($fallbackLogs) . " sales, " . count($repairsLogs ?? []) . " repairs, " . count($swapsLogs) . " swaps. Total: " . count($allLogs) . " transactions");
                } else {
                    error_log("getActivityLogs: Fallback also returned no results. Total sales in DB: {$totalSales}, Today sales: {$todaySales}");
                    // Last resort: try without joins to see if table has any data
                    $simpleQuery = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id");
                    $simpleQuery->execute(['company_id' => $companyId]);
                    $simpleCount = $simpleQuery->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
                    error_log("getActivityLogs: Simple count query shows {$simpleCount} sales for company {$companyId}");
                }
            } else if (count($allLogs) > 0) {
                error_log("getActivityLogs: Successfully found " . count($allLogs) . " logs. Sample: " . json_encode($allLogs[0]));
            }

            $result = array_slice($allLogs, 0, 100); // Return top 100 most recent
            error_log("getActivityLogs: FINAL RESULT - Returning " . count($result) . " activity logs");
            
            // Count swaps in final result
            $swapsInResult = array_filter($result, function($log) {
                return ($log['activity_type'] ?? '') === 'swap';
            });
            error_log("getActivityLogs: Swaps in final result: " . count($swapsInResult));
            if (count($swapsInResult) > 0) {
                error_log("getActivityLogs: Sample swap in final result: " . json_encode(reset($swapsInResult)));
            }
            
            // FINAL SAFETY CHECK: If we have sales but result is empty, force a simple query
            if (count($result) === 0 && ($totalSales > 0 || $totalSwaps > 0)) {
                error_log("getActivityLogs: FINAL SAFETY - Result is empty but transactions exist. Running emergency query...");
                $emergencyLogs = [];
                try {
                    if ($totalSales > 0) {
                        $emergencyQuery = $db->prepare("
                            SELECT 
                                ps.id,
                                'sale' as activity_type,
                                ps.created_at as timestamp,
                                COALESCE(u.full_name, 'System') as user_name,
                                COALESCE(u.role, 'user') as user_role,
                                ps.final_amount as amount,
                                COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)) as reference,
                                COALESCE(c.full_name, 'Walk-in Customer') as customer_name,
                                COALESCE(c.phone_number, '') as customer_phone,
                                COALESCE(ps.payment_status, 'PAID') as status,
                                CONCAT('Sale: ', COALESCE(ps.unique_id, CONCAT('SALE-', ps.id))) as description
                            FROM pos_sales ps
                            LEFT JOIN users u ON ps.created_by_user_id = u.id
                            LEFT JOIN customers c ON ps.customer_id = c.id
                            WHERE ps.company_id = :company_id
                            ORDER BY ps.created_at DESC
                            LIMIT 50
                        ");
                        $emergencyQuery->execute(['company_id' => $companyId]);
                        $emergencySales = $emergencyQuery->fetchAll(\PDO::FETCH_ASSOC);
                        $emergencyLogs = array_merge($emergencyLogs, $emergencySales);
                        error_log("getActivityLogs: Emergency sales query returned " . count($emergencySales) . " records");
                    }
                    
                    // Also include swaps in emergency query
                    $checkSwapsTableEmergency = $db->query("SHOW TABLES LIKE 'swaps'");
                    if ($totalSwaps > 0 && $checkSwapsTableEmergency->rowCount() > 0) {
                        $emergencySwapsQuery = $db->prepare("
                            SELECT 
                                s.id,
                                'swap' as activity_type,
                                s.created_at as timestamp,
                                NULL as user_name,
                                NULL as user_role,
                                0 as amount,
                                CONCAT('SWAP-', s.id) as reference,
                                'Walk-in Customer' as customer_name,
                                '' as customer_phone,
                                'completed' as status,
                                CONCAT('Swap: SWAP-', s.id) as item_description,
                                CONCAT('Swap: SWAP-', s.id) as description
                            FROM swaps s
                            WHERE s.company_id = :company_id
                            ORDER BY s.created_at DESC
                            LIMIT 50
                        ");
                        $emergencySwapsQuery->execute(['company_id' => $companyId]);
                        $emergencySwaps = $emergencySwapsQuery->fetchAll(\PDO::FETCH_ASSOC);
                        $emergencyLogs = array_merge($emergencyLogs, $emergencySwaps);
                        error_log("getActivityLogs: Emergency swaps query returned " . count($emergencySwaps) . " records");
                    }
                    
                    if (count($emergencyLogs) > 0) {
                        usort($emergencyLogs, function($a, $b) {
                            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                        });
                        $result = array_slice($emergencyLogs, 0, 100);
                        error_log("getActivityLogs: Using emergency results - " . count($result) . " transactions (sales + swaps)");
                    }
                } catch (\Exception $e3) {
                    error_log("getActivityLogs: Emergency query failed: " . $e3->getMessage());
                }
            }
            
            if (count($result) > 0) {
                error_log("getActivityLogs: First result sample: " . json_encode($result[0]));
            } else {
                error_log("getActivityLogs: WARNING - Returning empty array despite total sales: {$totalSales}");
            }
            return $result;
        } catch (\Exception $e) {
            error_log("Get activity logs error: " . $e->getMessage());
            error_log("Get activity logs trace: " . $e->getTraceAsString());
            // Even on error, try to return recent transactions as fallback
            try {
                $db = \Database::getInstance()->getConnection();
                $emergencyLogs = [];
                
                // Get sales
                $emergencyQuery = $db->prepare("
                    SELECT 
                        ps.id,
                        'sale' as activity_type,
                        ps.created_at as timestamp,
                        u.full_name as user_name,
                        u.role as user_role,
                        ps.final_amount as amount,
                        COALESCE(ps.unique_id, CONCAT('SALE-', ps.id)) as reference,
                        COALESCE(c.full_name, 'Walk-in Customer') as customer_name,
                        c.phone_number as customer_phone,
                        COALESCE(ps.payment_status, 'PAID') as status,
                        CONCAT('Sale: ', COALESCE(ps.unique_id, CONCAT('SALE-', ps.id))) as description
                    FROM pos_sales ps
                    LEFT JOIN users u ON ps.created_by_user_id = u.id
                    LEFT JOIN customers c ON ps.customer_id = c.id
                    WHERE ps.company_id = :company_id
                    ORDER BY ps.created_at DESC
                    LIMIT 50
                ");
                $emergencyQuery->execute(['company_id' => $companyId]);
                $emergencySales = $emergencyQuery->fetchAll(\PDO::FETCH_ASSOC);
                $emergencyLogs = array_merge($emergencyLogs, $emergencySales);
                
                // Get swaps
                $checkSwapsTable = $db->query("SHOW TABLES LIKE 'swaps'");
                if ($checkSwapsTable->rowCount() > 0) {
                    $emergencySwapsQuery = $db->prepare("
                        SELECT 
                            s.id,
                            'swap' as activity_type,
                            s.created_at as timestamp,
                            NULL as user_name,
                            NULL as user_role,
                            0 as amount,
                            CONCAT('SWAP-', s.id) as reference,
                            'Walk-in Customer' as customer_name,
                            '' as customer_phone,
                            'completed' as status,
                            CONCAT('Swap: SWAP-', s.id) as item_description,
                            CONCAT('Swap: SWAP-', s.id) as description
                        FROM swaps s
                        WHERE s.company_id = :company_id
                        ORDER BY s.created_at DESC
                        LIMIT 50
                    ");
                    $emergencySwapsQuery->execute(['company_id' => $companyId]);
                    $emergencySwaps = $emergencySwapsQuery->fetchAll(\PDO::FETCH_ASSOC);
                    $emergencyLogs = array_merge($emergencyLogs, $emergencySwaps);
                }
                
                usort($emergencyLogs, function($a, $b) {
                    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                });
                error_log("getActivityLogs: Emergency fallback returned " . count($emergencyLogs) . " records (sales + swaps)");
                return array_slice($emergencyLogs, 0, 100);
            } catch (\Exception $e2) {
                error_log("getActivityLogs: Emergency fallback also failed: " . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * Get profit breakdown by date range for charts
     * @param int $companyId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    private function getProfitBreakdownByDateRange($companyId, $dateFrom, $dateTo) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if pos_sales has total_cost column
            $checkCostCol = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'total_cost'");
            $hasCostCol = $checkCostCol && $checkCostCol->rowCount() > 0;
            
            // Determine which products table to use
            $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            
            // Build cost calculation
            if ($hasCostCol) {
                $costCalculation = "COALESCE(SUM(ps.total_cost), 0)";
            } else {
                $costCalculation = "COALESCE(SUM(
                    COALESCE(
                        (SELECT COALESCE(SUM(psi.quantity * COALESCE(p.cost, psi.unit_price * 0.7, 0)), 0)
                         FROM pos_sale_items psi 
                         LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                         WHERE psi.pos_sale_id = ps.id),
                        ps.final_amount * 0.7
                    )
                ), 0)";
            }
            
            // Daily breakdown for the date range - use datetime comparison for accuracy
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            $query = $db->prepare("
                SELECT 
                    DATE(ps.created_at) as period,
                    COALESCE(SUM(ps.final_amount), 0) as revenue,
                    {$costCalculation} as cost
                FROM pos_sales ps
                WHERE ps.company_id = :company_id
                AND ps.created_at >= :date_from_start
                AND ps.created_at <= :date_to_end
                GROUP BY DATE(ps.created_at)
                ORDER BY period ASC
            ");
            
            $query->execute([
                'company_id' => $companyId,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ]);
            
            $results = $query->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("getProfitBreakdownByDateRange: Query returned " . count($results) . " records for {$dateFrom} to {$dateTo}");
            if (count($results) > 0) {
                error_log("getProfitBreakdownByDateRange: Sample record: " . json_encode($results[0]));
            }
            
            return array_map(function($row) {
                $revenue = (float)$row['revenue'];
                $cost = (float)$row['cost'];
                $profit = $revenue - $cost;
                
                return [
                    'period' => $row['period'],
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit
                ];
            }, $results);
        } catch (\Exception $e) {
            error_log("ManagerAnalyticsController::getProfitBreakdownByDateRange error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get profit/loss breakdown by period
     * @param int|null $staffId Optional staff member filter
     */
    private function getProfitLossBreakdown($companyId, $dateFrom, $dateTo, $staffId = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Log input parameters
            error_log("getProfitLossBreakdown called: companyId={$companyId}, dateFrom={$dateFrom}, dateTo={$dateTo}, staffId=" . ($staffId ?? 'null'));
            
            // Check if pos_sales has total_cost column
            $checkCostCol = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'total_cost'");
            $hasCostCol = $checkCostCol && $checkCostCol->rowCount() > 0;
            
            // Use datetime comparison for accuracy - include full day
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';
            
            // First, check if there are any sales in the date range at all
            $checkSales = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id AND created_at >= :date_from_start AND created_at <= :date_to_end");
            $checkSales->execute([
                'company_id' => $companyId,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ]);
            $salesCount = $checkSales->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
            error_log("Total sales in date range {$dateFrom} to {$dateTo}: {$salesCount}");
            
            // If staff filter is applied, check sales for that staff (all sales, no exclusions)
            if ($staffId) {
                $checkStaffSales = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id AND created_at >= :date_from_start AND created_at <= :date_to_end AND created_by_user_id = :staff_id");
                $checkStaffSales->execute([
                    'company_id' => $companyId,
                    'date_from_start' => $dateFromStart,
                    'date_to_end' => $dateToEnd,
                    'staff_id' => (int)$staffId
                ]);
                $staffSalesCount = $checkStaffSales->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
                error_log("Sales for staff_id={$staffId} in date range {$dateFrom} to {$dateTo}: {$staffSalesCount}");
            }
            
            // Also check total sales for this company (all time) for debugging
            $checkAllSales = $db->prepare("SELECT COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id");
            $checkAllSales->execute(['company_id' => $companyId]);
            $allSalesCount = $checkAllSales->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
            error_log("Total sales for company {$companyId} (all time): {$allSalesCount}");
            
            // Build WHERE clause with optional staff filter - use datetime comparison
            // EXCLUDE swap transactions - swaps should only be tracked on swap page
            $whereClause = "ps.company_id = :company_id AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end AND (ps.swap_id IS NULL OR ps.is_swap_mode = FALSE OR ps.is_swap_mode = 0)";
            $params = [
                'company_id' => $companyId,
                'date_from_start' => $dateFromStart,
                'date_to_end' => $dateToEnd
            ];
            
            if ($staffId) {
                $whereClause .= " AND ps.created_by_user_id = :staff_id";
                // Don't exclude repair sales in breakdown - show all sales for the staff member
                // This matches the "all staff" behavior where all sales are shown
                $params['staff_id'] = (int)$staffId; // Ensure it's an integer
                error_log("getProfitLossBreakdown: Adding staff filter - staff_id=" . $params['staff_id'] . " (original: " . var_export($staffId, true) . ")");
            }
            
            // Determine which products table to use
            $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            
            // ALWAYS calculate cost from products table to ensure accuracy
            // Don't use stored total_cost as it may be incorrect or outdated
            // Calculate cost from pos_sale_items joined with products table
            // MUST include company_id check to get correct product cost
            
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
            
            $costCalculation = "COALESCE(SUM(
                (SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0)
                 FROM pos_sale_items psi 
                 LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                 WHERE psi.pos_sale_id = ps.id)
            ), 0)";
            $profitCalculation = "COALESCE(SUM(ps.final_amount - (
                SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0)
                FROM pos_sale_items psi 
                LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                WHERE psi.pos_sale_id = ps.id
            )), 0)";
            
            // Daily breakdown - simplified approach (no complex JOINs)
            // First get revenue and count, then calculate cost separately if needed
            $dailySql = "
                SELECT 
                    DATE(ps.created_at) as date,
                    COUNT(ps.id) as sales_count,
                    COALESCE(SUM(ps.final_amount), 0) as revenue
                FROM pos_sales ps
                WHERE {$whereClause}
                GROUP BY DATE(ps.created_at)
                ORDER BY date DESC
            ";
            
            $dailyQuery = $db->prepare($dailySql);
            $dailyQuery->execute($params);
            $dailyRaw = $dailyQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("getProfitLossBreakdown: Daily query returned " . count($dailyRaw) . " rows");
            if ($staffId) {
                error_log("getProfitLossBreakdown: WITH staff_id={$staffId}, found " . count($dailyRaw) . " daily records");
            } else {
                error_log("getProfitLossBreakdown: WITHOUT staff filter, found " . count($dailyRaw) . " daily records");
            }
            if (count($dailyRaw) > 0) {
                error_log("getProfitLossBreakdown: Sample daily record: " . json_encode($dailyRaw[0]));
            }
            
            // Calculate cost and profit for each day
            $daily = [];
            foreach ($dailyRaw as $row) {
                $dateStr = $row['date'];
                $revenue = floatval($row['revenue']);
                
                // ALWAYS calculate cost from products table (don't use stored total_cost)
                // This ensures we use the actual product cost price (22.00) not a stored value
                // Calculate actual cost from pos_sale_items for this day - MUST include company_id check
                // Use the same cost column determined above (cost_price or cost)
                // Use improved matching: by item_id OR by item_description = product.name
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                $costQuery = $db->prepare("
                    SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0) as cost
                    FROM pos_sales ps
                    INNER JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = :company_id 
                    AND ps.created_at >= :date_start AND ps.created_at <= :date_end
                    AND (ps.swap_id IS NULL OR ps.is_swap_mode = FALSE OR ps.is_swap_mode = 0)
                    AND p.id IS NOT NULL
                    " . ($staffId ? "AND ps.created_by_user_id = :staff_id" : "") . "
                ");
                $costParams = [
                    'company_id' => $companyId, 
                    'date_start' => $dateStr . ' 00:00:00',
                    'date_end' => $dateStr . ' 23:59:59'
                ];
                if ($staffId) {
                    $costParams['staff_id'] = (int)$staffId; // Ensure it's an integer
                }
                $costQuery->execute($costParams);
                $costResult = $costQuery->fetch(\PDO::FETCH_ASSOC);
                $cost = floatval($costResult['cost'] ?? 0);
                
                // If still 0, use fallback (should rarely happen)
                if ($cost == 0 && $revenue > 0) {
                    $cost = $revenue * 0.7;
                }
                
                $profit = $revenue - $cost;
                
                // Add swap profit for this day (when customer item was resold)
                $swapProfit = 0;
                try {
                    $checkProfitTable = $db->query("SHOW TABLES LIKE 'swap_profit_links'");
                    if ($checkProfitTable->rowCount() > 0) {
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
                            ) as profit
                            FROM swap_profit_links spl
                            INNER JOIN swaps s ON spl.swap_id = s.id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE s.company_id = :company_id 
                            AND spl.customer_item_sale_id IS NOT NULL
                            AND DATE(customer_sale.created_at) = :date
                        ");
                        $swapProfitQuery->execute(['company_id' => $companyId, 'date' => $dateStr]);
                        $swapProfitResult = $swapProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $swapProfit = floatval($swapProfitResult['profit'] ?? 0);
                    }
                } catch (\Exception $e) {
                    error_log("Error getting swap profit for date {$dateStr}: " . $e->getMessage());
                }
                
                $daily[] = [
                    'date' => $dateStr,
                    'sales_count' => intval($row['sales_count']),
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit + $swapProfit
                ];
            }
            
            error_log("Daily breakdown: " . count($daily) . " records found for date range {$dateFrom} to {$dateTo}");
            if (count($daily) > 0) {
                error_log("Sample daily record: " . json_encode($daily[0]));
            } else {
                error_log("WARNING: No daily records found. Checking if sales exist...");
                // Debug query to check recent sales
                $debugQuery = $db->prepare("SELECT COUNT(*) as cnt, MAX(DATE(created_at)) as latest_date FROM pos_sales WHERE company_id = :company_id");
                $debugQuery->execute(['company_id' => $companyId]);
                $debugResult = $debugQuery->fetch(\PDO::FETCH_ASSOC);
                error_log("Debug: Total sales for company: " . ($debugResult['cnt'] ?? 0) . ", Latest sale date: " . ($debugResult['latest_date'] ?? 'none'));
            }

            // Weekly breakdown - simplified approach
            $weeklySql = "
                SELECT 
                    YEARWEEK(ps.created_at, 1) as week,
                    COUNT(ps.id) as sales_count,
                    COALESCE(SUM(ps.final_amount), 0) as revenue
                FROM pos_sales ps
                WHERE {$whereClause}
                GROUP BY YEARWEEK(ps.created_at, 1)
                ORDER BY week DESC
            ";
            
            $weeklyQuery = $db->prepare($weeklySql);
            $weeklyQuery->execute($params);
            $weeklyRaw = $weeklyQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("getProfitLossBreakdown: Weekly query returned " . count($weeklyRaw) . " rows");
            if ($staffId) {
                error_log("getProfitLossBreakdown: WITH staff_id={$staffId}, found " . count($weeklyRaw) . " weekly records");
            }
            
            // Calculate cost and profit for each week
            $weekly = [];
            foreach ($weeklyRaw as $row) {
                $weekNum = $row['week'];
                $revenue = floatval($row['revenue']);
                
                // ALWAYS calculate cost from products table (don't use stored total_cost)
                // This ensures we use the actual product cost price (22.00) not a stored value
                // Calculate actual cost from pos_sale_items for this week - MUST include company_id check
                // Use improved matching: by item_id OR by item_description = product.name
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                $costQuery = $db->prepare("
                    SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0) as cost
                    FROM pos_sales ps
                    INNER JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = :company_id 
                    AND YEARWEEK(ps.created_at, 1) = :week
                    AND (ps.swap_id IS NULL OR ps.is_swap_mode = FALSE OR ps.is_swap_mode = 0)
                    AND p.id IS NOT NULL
                    " . ($staffId ? "AND ps.created_by_user_id = :staff_id" : "") . "
                ");
                $costParams = ['company_id' => $companyId, 'week' => $weekNum];
                if ($staffId) {
                    $costParams['staff_id'] = (int)$staffId; // Ensure it's an integer
                }
                $costQuery->execute($costParams);
                $costResult = $costQuery->fetch(\PDO::FETCH_ASSOC);
                $cost = floatval($costResult['cost'] ?? 0);
                
                // If still 0, use fallback (should rarely happen)
                if ($cost == 0 && $revenue > 0) {
                    $cost = $revenue * 0.7;
                }
                
                $profit = $revenue - $cost;
                
                // Add swap profit for this week (when customer item was resold)
                $swapProfit = 0;
                try {
                    $checkProfitTable = $db->query("SHOW TABLES LIKE 'swap_profit_links'");
                    if ($checkProfitTable->rowCount() > 0) {
                        // Get year and week from week number
                        $year = substr($weekNum, 0, 4);
                        $week = substr($weekNum, 4);
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
                            ) as profit
                            FROM swap_profit_links spl
                            INNER JOIN swaps s ON spl.swap_id = s.id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE s.company_id = :company_id 
                            AND spl.customer_item_sale_id IS NOT NULL
                            AND YEARWEEK(customer_sale.created_at, 1) = :week
                        ");
                        $swapProfitQuery->execute(['company_id' => $companyId, 'week' => $weekNum]);
                        $swapProfitResult = $swapProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $swapProfit = floatval($swapProfitResult['profit'] ?? 0);
                    }
                } catch (\Exception $e) {
                    error_log("Error getting swap profit for week {$weekNum}: " . $e->getMessage());
                }
                
                $weekly[] = [
                    'week' => $weekNum,
                    'sales_count' => intval($row['sales_count']),
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit + $swapProfit
                ];
            }
            
            error_log("Weekly breakdown: " . count($weekly) . " records found");

            // Monthly breakdown - start from the first sale date, not the date range start
            // Find the earliest sale date for this company (with staff filter if provided)
            $earliestSaleWhere = "company_id = :company_id";
            $earliestSaleParams = ['company_id' => $companyId];
            if ($staffId) {
                $earliestSaleWhere .= " AND created_by_user_id = :staff_id";
                $earliestSaleParams['staff_id'] = (int)$staffId;
            }
            $earliestSaleQuery = $db->prepare("
                SELECT MIN(DATE(created_at)) as earliest_date 
                FROM pos_sales 
                WHERE {$earliestSaleWhere}
            ");
            $earliestSaleQuery->execute($earliestSaleParams);
            $earliestSale = $earliestSaleQuery->fetch(\PDO::FETCH_ASSOC);
            $earliestDate = $earliestSale['earliest_date'] ?? $dateFrom;
            
            error_log("Monthly breakdown - Earliest sale date: {$earliestDate}, Date range: {$dateFrom} to {$dateTo}");
            
            // Use the earliest sale date as the start, or the date range start if no sales found
            $startDate = new \DateTime($earliestDate);
            $endDate = new \DateTime($dateTo);
            $allMonths = [];
            
            // Generate all months from first sale to end date
            $currentMonth = clone $startDate;
            $currentMonth->modify('first day of this month');
            $endMonth = clone $endDate;
            $endMonth->modify('first day of this month');
            
            while ($currentMonth <= $endMonth) {
                $monthStr = $currentMonth->format('Y-m');
                $allMonths[$monthStr] = [
                    'month' => $monthStr,
                    'sales_count' => 0,
                    'revenue' => 0,
                    'cost' => 0,
                    'profit' => 0
                ];
                $currentMonth->modify('+1 month');
            }
            
            error_log("Monthly breakdown - Generated " . count($allMonths) . " months from {$earliestDate} to {$dateTo}");
            
            // Now get actual sales data for months that have sales
            // Query without date restriction first to see all sales, then filter by date range
            // EXCLUDE swap transactions - swaps should only be tracked on swap page
            $monthlySql = "
                SELECT 
                    DATE_FORMAT(ps.created_at, '%Y-%m') as month,
                    COUNT(ps.id) as sales_count,
                    COALESCE(SUM(ps.final_amount), 0) as revenue
                FROM pos_sales ps
                WHERE ps.company_id = :company_id
                AND ps.created_at >= :date_from_start 
                AND ps.created_at <= :date_to_end
                AND (ps.swap_id IS NULL OR ps.is_swap_mode = FALSE OR ps.is_swap_mode = 0)
                " . ($staffId ? "AND ps.created_by_user_id = :staff_id" : "") . "
                GROUP BY DATE_FORMAT(ps.created_at, '%Y-%m')
                ORDER BY month DESC
            ";
            
            error_log("Monthly breakdown SQL: " . $monthlySql);
            error_log("Monthly breakdown params: " . json_encode($params));
            error_log("Monthly breakdown date range: {$dateFrom} to {$dateTo}");
            
            // Debug: Check total sales in date range
            $debugQuery = $db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as cnt,
                    SUM(final_amount) as total
                FROM pos_sales 
                WHERE company_id = :company_id
                AND created_at >= :date_from_start 
                AND created_at <= :date_to_end
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
            ");
            $debugQuery->execute($params);
            $debugResults = $debugQuery->fetchAll(\PDO::FETCH_ASSOC);
            error_log("Monthly breakdown DEBUG - All sales by month: " . json_encode($debugResults));
            
            $monthlyQuery = $db->prepare($monthlySql);
            $monthlyQuery->execute($params);
            $monthlyRaw = $monthlyQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("Monthly breakdown raw results: " . count($monthlyRaw) . " months with sales found");
            if (count($monthlyRaw) > 0) {
                error_log("Monthly breakdown raw data: " . json_encode($monthlyRaw));
            } else {
                error_log("WARNING: No monthly sales found! Checking if there are any sales at all...");
                $checkAllSales = $db->prepare("SELECT COUNT(*) as cnt, MIN(DATE(created_at)) as earliest, MAX(DATE(created_at)) as latest FROM pos_sales WHERE company_id = :company_id");
                $checkAllSales->execute(['company_id' => $companyId]);
                $allSalesInfo = $checkAllSales->fetch(\PDO::FETCH_ASSOC);
                error_log("All sales info: " . json_encode($allSalesInfo));
            }
            
            // Fill in sales data for months that have sales
            foreach ($monthlyRaw as $row) {
                $monthStr = $row['month'];
                $revenue = floatval($row['revenue']);
                $salesCount = intval($row['sales_count']);
                
                error_log("Processing month: {$monthStr}, sales_count: {$salesCount}, revenue: {$revenue}");
                
                // ALWAYS calculate cost from products table (don't use stored total_cost)
                // This ensures we use the actual product cost price (22.00) not a stored value
                // Calculate actual cost from pos_sale_items for this month - MUST include company_id check
                // Use improved matching: by item_id OR by item_description = product.name
                $monthStart = $monthStr . '-01 00:00:00';
                $monthEnd = date('Y-m-t', strtotime($monthStr . '-01')) . ' 23:59:59';
                
                // EXCLUDE swap transactions - swaps should only be tracked on swap page
                $costQuery = $db->prepare("
                    SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0) as cost
                    FROM pos_sales ps
                    INNER JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                    LEFT JOIN {$productsTable} p ON (
                        (psi.item_id = p.id AND p.company_id = ps.company_id)
                        OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                    )
                    WHERE ps.company_id = :company_id 
                    AND ps.created_at >= :month_start AND ps.created_at <= :month_end
                    AND (ps.swap_id IS NULL OR ps.is_swap_mode = FALSE OR ps.is_swap_mode = 0)
                    AND p.id IS NOT NULL
                    " . ($staffId ? "AND ps.created_by_user_id = :staff_id" : "") . "
                ");
                $costParams = [
                    'company_id' => $companyId, 
                    'month_start' => $monthStart,
                    'month_end' => $monthEnd
                ];
                if ($staffId) {
                    $costParams['staff_id'] = (int)$staffId; // Ensure it's an integer
                }
                $costQuery->execute($costParams);
                $costResult = $costQuery->fetch(\PDO::FETCH_ASSOC);
                $cost = floatval($costResult['cost'] ?? 0);
                
                // If still 0, use fallback (should rarely happen)
                if ($cost == 0 && $revenue > 0) {
                    $cost = $revenue * 0.7;
                }
                
                $profit = $revenue - $cost;
                
                // Add swap profit for this month (when customer item was resold)
                $swapProfit = 0;
                try {
                    $checkProfitTable = $db->query("SHOW TABLES LIKE 'swap_profit_links'");
                    if ($checkProfitTable->rowCount() > 0) {
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
                            ) as profit
                            FROM swap_profit_links spl
                            INNER JOIN swaps s ON spl.swap_id = s.id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE s.company_id = :company_id 
                            AND spl.customer_item_sale_id IS NOT NULL
                            AND DATE_FORMAT(customer_sale.created_at, '%Y-%m') = :month
                        ");
                        $swapProfitQuery->execute(['company_id' => $companyId, 'month' => $monthStr]);
                        $swapProfitResult = $swapProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $swapProfit = floatval($swapProfitResult['profit'] ?? 0);
                    }
                } catch (\Exception $e) {
                    error_log("Error getting swap profit for month {$monthStr}: " . $e->getMessage());
                }
                
                // Update the month in allMonths array
                if (isset($allMonths[$monthStr])) {
                    $allMonths[$monthStr] = [
                        'month' => $monthStr,
                        'sales_count' => $salesCount,
                        'revenue' => $revenue,
                        'cost' => $cost,
                        'profit' => $profit + $swapProfit
                    ];
                } else {
                    // Month not in range, but add it anyway
                    $allMonths[$monthStr] = [
                        'month' => $monthStr,
                        'sales_count' => $salesCount,
                        'revenue' => $revenue,
                        'cost' => $cost,
                        'profit' => $profit + $swapProfit
                    ];
                }
                
                error_log("Added month {$monthStr}: {$salesCount} sales, revenue: {$revenue}, cost: {$cost}, profit: {$profit} (sales: " . ($profit - $swapProfit) . ", swap: {$swapProfit})");
            }
            
            // Convert to array and sort by month (descending)
            $monthly = array_values($allMonths);
            usort($monthly, function($a, $b) {
                return strcmp($b['month'], $a['month']); // Descending order
            });
            
            // Log detailed results
            error_log("Monthly breakdown: " . count($monthly) . " records found");
            if (count($monthly) > 0) {
                error_log("Sample monthly record: " . json_encode($monthly[0]));
            } else {
                error_log("WARNING: Monthly breakdown is empty! Date range: {$dateFrom} to {$dateTo}");
            }
            error_log("Profit/Loss Breakdown - Date range: {$dateFrom} to {$dateTo}, Daily: " . count($daily) . ", Weekly: " . count($weekly) . ", Monthly: " . count($monthly));
            
            // If no data found, try without date restriction to see if there's any data at all
            if (count($daily) == 0 && count($weekly) == 0 && count($monthly) == 0 && $salesCount > 0) {
                error_log("WARNING: Sales exist but breakdown returned empty. Checking query...");
                // Try a simple query to see what's happening
                $testQuery = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt FROM pos_sales WHERE company_id = :company_id GROUP BY DATE_FORMAT(created_at, '%Y-%m') LIMIT 5");
                $testQuery->execute(['company_id' => $companyId]);
                $testResults = $testQuery->fetchAll(\PDO::FETCH_ASSOC);
                error_log("Test query results (all time): " . json_encode($testResults));
            }

            $result = [
                'daily' => $daily,
                'weekly' => $weekly,
                'monthly' => $monthly
            ];
            
            error_log("getProfitLossBreakdown: Returning result - Daily: " . count($daily) . ", Weekly: " . count($weekly) . ", Monthly: " . count($monthly));
            if ($staffId && count($daily) == 0 && count($weekly) == 0 && count($monthly) == 0) {
                error_log("WARNING: getProfitLossBreakdown returned empty arrays with staff_id={$staffId}. Date range: {$dateFrom} to {$dateTo}");
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("Get profit/loss breakdown error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'daily' => [],
                'weekly' => [],
                'monthly' => []
            ];
        }
    }

    /**
     * Get enhanced inventory stats with detailed breakdown
     */
    private function getEnhancedInventoryStats($companyId) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Determine which products table to use
            $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            
            // Check column names
            $columns = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'quantity'");
            $qtyColumn = ($columns && $columns->rowCount() > 0) ? 'quantity' : 'qty';
            
            // Get inventory breakdown
            $inventoryQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN ({$qtyColumn} > 5 AND status = 'available') THEN 1 ELSE 0 END) as in_stock,
                    SUM(CASE WHEN ({$qtyColumn} > 0 AND {$qtyColumn} <= 5 AND status = 'available') THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN ({$qtyColumn} <= 0 OR status IN ('out_of_stock', 'sold')) THEN 1 ELSE 0 END) as out_of_stock,
                    COALESCE(SUM(price * {$qtyColumn}), 0) as total_value,
                    COALESCE(SUM({$qtyColumn}), 0) as total_quantity
                FROM {$productsTable}
                WHERE company_id = :company_id
            ");
            $inventoryQuery->execute(['company_id' => $companyId]);
            $stats = $inventoryQuery->fetch(\PDO::FETCH_ASSOC);

            // Get low stock items
            $lowStockQuery = $db->prepare("
                SELECT 
                    id, name, {$qtyColumn} as quantity, price, status
                FROM {$productsTable}
                WHERE company_id = :company_id
                    AND {$qtyColumn} > 0 
                    AND {$qtyColumn} <= 5 
                    AND status = 'available'
                ORDER BY {$qtyColumn} ASC
                LIMIT 10
            ");
            $lowStockQuery->execute(['company_id' => $companyId]);
            $lowStockItems = $lowStockQuery->fetchAll(\PDO::FETCH_ASSOC);

            // Get out of stock items
            $outStockQuery = $db->prepare("
                SELECT 
                    id, name, {$qtyColumn} as quantity, price, status
                FROM {$productsTable}
                WHERE company_id = :company_id
                    AND ({$qtyColumn} <= 0 OR status IN ('out_of_stock', 'sold'))
                ORDER BY name ASC
                LIMIT 10
            ");
            $outStockQuery->execute(['company_id' => $companyId]);
            $outStockItems = $outStockQuery->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'total_products' => (int)($stats['total_products'] ?? 0),
                'in_stock' => (int)($stats['in_stock'] ?? 0),
                'low_stock' => (int)($stats['low_stock'] ?? 0),
                'out_of_stock' => (int)($stats['out_of_stock'] ?? 0),
                'total_value' => (float)($stats['total_value'] ?? 0),
                'total_quantity' => (int)($stats['total_quantity'] ?? 0),
                'low_stock_items' => $lowStockItems,
                'out_of_stock_items' => $outStockItems
            ];
        } catch (\Exception $e) {
            error_log("Get enhanced inventory stats error: " . $e->getMessage());
            return [
                'total_products' => 0,
                'in_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'total_value' => 0,
                'total_quantity' => 0,
                'low_stock_items' => [],
                'out_of_stock_items' => []
            ];
        }
    }
}

