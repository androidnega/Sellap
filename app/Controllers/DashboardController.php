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
        // Use comprehensive dashboard if it exists, otherwise fallback to basic
        $comprehensiveDashboard = APP_PATH . '/Views/manager-dashboard-comprehensive.php';
        if (file_exists($comprehensiveDashboard)) {
            include $comprehensiveDashboard;
        } else {
            include APP_PATH . '/Views/manager-dashboard.php';
        }
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
        $userId = $userData['id'] ?? null;
        
        // Get technician's repairs
        $repairModel = new \App\Models\Repair();
        $pendingRepairs = $repairModel->findByTechnician($userId, $companyId, 'pending');
        $inProgressRepairs = $repairModel->findByTechnician($userId, $companyId, 'in_progress');
        $completedRepairs = $repairModel->findByTechnician($userId, $companyId, 'completed');
        
        // Get stats
        $allRepairs = $repairModel->findByTechnician($userId, $companyId, null);
        $totalRepairs = count($allRepairs);
        $totalRevenue = array_sum(array_column($allRepairs, 'total_cost'));
        $completedCount = count(array_filter($allRepairs, fn($r) => $r['status'] === 'completed'));
        
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                $todayRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                ");
                $todayRevenueQuery->execute([$companyId]);
                $todayRevenue = $todayRevenueQuery->fetch()['revenue'] ?? 0;

                $todaySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = CURDATE()
                ");
                $todaySalesQuery->execute([$companyId]);
                $todaySales = $todaySalesQuery->fetch()['count'] ?? 0;

                $monthlyRevenueQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $monthlyRevenueQuery->execute([$companyId]);
                $monthlyRevenue = $monthlyRevenueQuery->fetch()['revenue'] ?? 0;

                $monthlySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $monthlySalesQuery->execute([$companyId]);
                $monthlySales = $monthlySalesQuery->fetch()['count'] ?? 0;
                
                $metrics['today_revenue'] = (float)$todayRevenue;
                $metrics['today_sales'] = (int)$todaySales;
                $metrics['monthly_revenue'] = (float)$monthlyRevenue;
                $metrics['monthly_sales'] = (int)$monthlySales;
                
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
            // Out of stock: quantity <= 0 OR status IN ('out_of_stock', 'OUT_OF_STOCK', 'sold')
            // Low stock: quantity > 0 AND quantity <= minQtyDefault AND status = 'available'
            
            // Build WHERE conditions - items that are either out of stock OR low stock
            $alertConditions = [];
            
            // Out of stock conditions
            $outOfStockConditions = ["COALESCE(p.{$quantityCol}, 0) <= 0"];
            if ($hasStatus) {
                // Check for various status values that indicate out of stock
                $outOfStockConditions[] = "(LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold')";
            }
            $alertConditions[] = "(" . implode(' OR ', $outOfStockConditions) . ")";
            
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
            $outOfStockCase = "COALESCE(p.{$quantityCol}, 0) <= 0";
            if ($hasStatus) {
                $outOfStockCase = "({$outOfStockCase} OR LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold')";
            }
            
            // Build ORDER BY clause
            $orderByOutOfStock = "COALESCE(p.{$quantityCol}, 0) <= 0";
            if ($hasStatus) {
                $orderByOutOfStock = "({$orderByOutOfStock} OR LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold')";
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
                // Salesperson - filter by user and exclude repair-related sales
                // Repair sales have notes like "Repair #X - Products sold by repairer"
                $excludeRepairSales = " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))";
                $todaySalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ?{$excludeRepairSales}";
                $todayRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ?{$excludeRepairSales}";
                $todayCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ? AND customer_id IS NOT NULL{$excludeRepairSales}";
                $weekSalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) >= ?{$excludeRepairSales}";
                $weekRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) >= ?{$excludeRepairSales}";
                $totalCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND created_by_user_id = ? AND customer_id IS NOT NULL{$excludeRepairSales}";
                
                $todayParams = [$companyId, $userId, $today];
                $weekParams = [$companyId, $userId, date('Y-m-d', strtotime('-7 days'))];
                $allParams = [$companyId, $userId];
            } else {
                // Manager/Admin - all company sales
                $todaySalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ?";
                $todayRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ?";
                $todayCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) = ? AND customer_id IS NOT NULL";
                $weekSalesSql = "SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ? AND DATE(created_at) >= ?";
                $weekRevenueSql = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM pos_sales WHERE company_id = ? AND DATE(created_at) >= ?";
                $totalCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count FROM pos_sales WHERE company_id = ? AND customer_id IS NOT NULL";
                
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

            // Get payment statistics if partial payments module is enabled
            $paymentStats = null;
            if (CompanyModule::isEnabled($companyId, 'partial_payments')) {
                try {
                    $salePaymentModel = new \App\Models\SalePayment();
                    
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
                        'unpaid' => $unpaid
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
                'total_customers' => $totalCustomers
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
            // Try JWT auth first (for API calls)
            $payload = null;
            try {
                $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin', 'salesperson', 'technician']);
            } catch (\Exception $e) {
                // If JWT fails, try session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Authentication required'
                    ]);
                    return;
                }
                
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
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
        
            // Get user's own sales stats if userId provided (exclude repair-related sales)
            if ($userId) {
                // Exclude repair sales: notes like "Repair #X - Products sold by repairer"
                $excludeRepairSales = " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))";
                $todaySalesQuery = $db->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = CURDATE(){$excludeRepairSales}
                ");
                $todaySalesQuery->execute([$companyId, $userId]);
                $todayStats = $todaySalesQuery->fetch(\PDO::FETCH_ASSOC);
                
                $weekSalesQuery = $db->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                    FROM pos_sales 
                    WHERE company_id = ? AND created_by_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY){$excludeRepairSales}
                ");
                $weekSalesQuery->execute([$companyId, $userId]);
                $weekStats = $weekSalesQuery->fetch(\PDO::FETCH_ASSOC);
        } else {
            $todayStats = ['count' => 0, 'revenue' => 0];
            $weekStats = ['count' => 0, 'revenue' => 0];
        }
        
        return [
            'total_revenue' => floatval($todayStats['revenue'] ?? 0),
            'total_sales' => intval($todayStats['count'] ?? 0),
            'week_revenue' => floatval($weekStats['revenue'] ?? 0),
            'week_sales' => intval($weekStats['count'] ?? 0),
            'total_customers' => 0,
            'total_phones' => 0
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
        $todaySalesQuery = $db->prepare("
            SELECT COALESCE(SUM(final_amount), 0) as total 
            FROM pos_sales 
            WHERE company_id = ? AND DATE(created_at) = CURDATE()
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
        
        $yesterdaySalesQuery = $db->prepare("
            SELECT COALESCE(SUM(final_amount), 0) as total 
            FROM pos_sales 
            WHERE company_id = ? AND DATE(created_at) = ?
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
                $swapStatus = $s['status'] ?? 'pending';
                
                // Same logic as SwapController::index() - MUST MATCH VIEW DISPLAY
                if ($resaleStatus === 'sold') {
                    $statusStats['resold']++;
                } elseif ($resaleStatus === 'in_stock') {
                    $statusStats['completed']++;
                } elseif ($swapStatus === 'resold') {
                    $statusStats['resold']++;
                } elseif ($swapStatus === 'completed') {
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
                // Total value
                $totalValue += floatval($s['total_value'] ?? 0);
                
                // In stock items (from resale_status === 'in_stock')
                $resaleStatus = $s['resale_status'] ?? null;
                if ($resaleStatus === 'in_stock') {
                    $inStockItems++;
                    $inStockValue += floatval($s['resell_price'] ?? $s['customer_product_value'] ?? 0);
                }
                
                // Profit calculation - same logic as SwapController
                $profitEstimate = isset($s['profit_estimate']) && $s['profit_estimate'] !== null ? floatval($s['profit_estimate']) : null;
                $profitFinal = isset($s['final_profit']) && $s['final_profit'] !== null ? floatval($s['final_profit']) : null;
                $profitStatus = $s['profit_status'] ?? null;
                $swapStatus = $s['status'] ?? 'pending';
                $isResold = ($resaleStatus === 'sold' || $swapStatus === 'resold');
                
                // Check if both sales are linked
                $hasCompanySaleId = !empty($s['company_item_sale_id']);
                $hasCustomerSaleId = !empty($s['customer_item_sale_id']);
                $bothItemsSold = $hasCompanySaleId && $hasCustomerSaleId;
                
                if ($bothItemsSold) {
                    // Both items sold - profit is realized
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
                            if ($profitEstimate !== null) {
                                $profitFinal = $profitEstimate;
                            }
                        }
                    }
                    
                    $profitToUse = $profitFinal !== null ? $profitFinal : ($profitEstimate !== null ? $profitEstimate : 0);
                    $calculatedFinalProfit += $profitToUse;
                    $calculatedFinalCount++;
                    
                    if ($profitToUse < 0) {
                        $calculatedLoss += abs($profitToUse);
                    }
                } elseif ($isResold || $profitStatus === 'finalized') {
                    // Legacy: resold or finalized
                    $profitToUse = $profitFinal !== null ? $profitFinal : ($profitEstimate !== null ? $profitEstimate : 0);
                    if ($profitToUse != 0 || $profitFinal !== null) {
                        $calculatedFinalProfit += $profitToUse;
                        $calculatedFinalCount++;
                        if ($profitToUse < 0) {
                            $calculatedLoss += abs($profitToUse);
                        }
                    }
                } elseif ($profitEstimate !== null) {
                    // Estimated profit
                    $calculatedEstimatedProfit += $profitEstimate;
                    $calculatedEstimatedCount++;
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
            
            $salesRevenueQuery = $db->prepare("
                SELECT COALESCE(SUM(final_amount), 0) as total 
                FROM pos_sales 
                WHERE company_id = ? AND created_at >= ? AND created_at <= ?
            ");
            $salesRevenueQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
            $salesRevenue = (float)($salesRevenueQuery->fetch()['total'] ?? 0);
        } catch (\Exception $e) {
            error_log("Error getting sales revenue: " . $e->getMessage());
            $salesRevenue = 0;
        }
        
        // Swap Profit - use from swapStats if provided (already calculated correctly)
        // Otherwise calculate from swap_profit_links table
        $swapProfit = 0;
        if ($swapStats && isset($swapStats['total_profit'])) {
            // Use the already-calculated swap profit from getSwapStatistics()
            // This uses the same logic as SwapController, ensuring consistency
            $swapProfit = (float)($swapStats['total_profit'] ?? 0);
            error_log("Financial Summary: Using swap profit from swapStats: ₵{$swapProfit}");
        } else {
            // Fallback: Calculate from database with date range filtering
            try {
                $swapProfitQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_profit), 0) as total 
                    FROM swap_profit_links spl
                    INNER JOIN swaps s ON spl.swap_id = s.id
                    WHERE s.company_id = ? 
                    AND spl.final_profit IS NOT NULL 
                    AND spl.status = 'finalized'
                    AND DATE(s.created_at) BETWEEN ? AND ?
                ");
                $swapProfitQuery->execute([$companyId, $dateFrom, $dateTo]);
                $swapProfit = (float)($swapProfitQuery->fetch()['total'] ?? 0);
                error_log("Financial Summary: Calculated swap profit from DB (date filtered): ₵{$swapProfit} for period {$dateFrom} to {$dateTo}");
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
                $salesProfitQuery = $db->prepare("
                    SELECT COALESCE(SUM(final_amount - total_cost), 0) as profit 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ? AND total_cost IS NOT NULL
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
                            COALESCE(SUM((ra.price - COALESCE(p.cost, 0)) * ra.quantity), 0) as parts_profit
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
            
            $salesCostQuery = $db->prepare("
                SELECT COALESCE(SUM(
                    (SELECT COALESCE(SUM(psi.quantity * COALESCE(p.cost, 0)), 0)
                     FROM pos_sale_items psi 
                     LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                     WHERE psi.pos_sale_id = ps.id)
                ), 0) as cost
                FROM pos_sales ps
                WHERE ps.company_id = ? AND ps.created_at >= ? AND ps.created_at <= ?
            ");
            $salesCostQuery->execute([$companyId, $dateFromStart, $dateToEnd]);
            $salesCost = (float)($salesCostQuery->fetch()['cost'] ?? 0);
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
                
                if ($hasRepairAccessories) {
                    // Calculate labour cost and parts cost
                    $repairerCostQuery = $db->prepare("
                        SELECT 
                            -- Labour Cost
                            COALESCE(SUM(COALESCE(r.labour_cost, r.repair_cost * 0.5, 0)), 0) as labour_cost,
                            -- Parts Cost: cost * quantity
                            COALESCE(SUM(COALESCE(p.cost, 0) * ra.quantity), 0) as parts_cost
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
        
        $totalRevenue = $salesRevenue + $repairRevenue;
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
        $totalProfit = $totalRevenue - $totalCost;
        
        // Validate profit calculation
        // Profit can be negative (loss), but log if it seems anomalous
        if ($totalProfit < 0 && $totalRevenue > 100) {
            // Large loss might indicate data issue, but allow it (could be legitimate)
            error_log("Financial Summary INFO: Loss detected - Revenue: ₵{$totalRevenue}, Cost: ₵{$totalCost}, Profit: ₵{$totalProfit}");
        }
        
        // Calculate profit margin (can be negative for losses)
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
        
        // Round values to 2 decimal places to prevent floating point anomalies
        $totalRevenue = round($totalRevenue, 2);
        $totalCost = round($totalCost, 2);
        $totalProfit = round($totalProfit, 2);
        $profitMargin = round($profitMargin, 2);
        
        error_log("Financial Summary - Revenue: ₵{$totalRevenue}, Cost: ₵{$totalCost} (Sales: ₵{$salesCost}, Repairer: ₵{$repairerCost}), Profit: ₵{$totalProfit}, Margin: {$profitMargin}%");
        error_log("Financial Summary Breakdown - Sales Revenue: ₵{$salesRevenue}, Sales Profit: ₵{$salesProfit}, Swap Profit: ₵{$swapProfit}, Repairer Profit: ₵{$repairerProfit}");
        
        return [
            'total_revenue' => (float)$totalRevenue, // Already rounded
            'total_cost' => (float)$totalCost, // Already rounded
            'sales_revenue' => (float)round($salesRevenue, 2),
            'sales_cost' => (float)round($salesCost, 2),
            'swap_profit' => (float)round($swapProfit, 2),
            'repair_revenue' => (float)round($repairRevenue, 2),
            'repair_parts_count' => (int)$repairPartsCount, // Number of products sold as spare parts
            'repairer_profit' => (float)round($repairerProfit, 2),
            'repairer_cost' => (float)round($repairerCost, 2),
            'total_profit' => (float)$totalProfit, // Already rounded above
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
        if (in_array('pos_sales', $enabledModuleKeys)) {
            $salesQuery = $db->prepare("
                SELECT 
                    COUNT(*) as total_sales,
                    SUM(final_amount) as total_revenue,
                    AVG(final_amount) as avg_sale
                FROM pos_sales 
                WHERE company_id = ?
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
        
        // Get swap stats using models (only if swap module enabled)
        if (in_array('swap', $enabledModuleKeys)) {
            $swapStats = [];
            try {
                $swapModel = new \App\Models\Swap();
                $swapModelStats = $swapModel->getStats($companyId);
                
                // Get swapped items stats
                $swappedItemModel = new \App\Models\SwappedItem();
                $swappedItemsStats = $swappedItemModel->getStats($companyId);
                
                // Get profit stats
                $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                $profitStats = $swapProfitLinkModel->getStats($companyId);
                
                $swapStats = [
                    'total_swaps' => (int)($swapModelStats['total_swaps'] ?? 0),
                    'total_swap_value' => (float)($swapModelStats['total_value'] ?? 0),
                    'total_cash_received' => (float)($swapModelStats['total_cash_received'] ?? 0),
                    'in_stock_items' => (int)($swappedItemsStats['in_stock_items'] ?? 0),
                    'sold_items' => (int)($swappedItemsStats['sold_items'] ?? 0),
                    'total_estimated_profit' => (float)($profitStats['total_estimated_profit'] ?? 0),
                    'total_final_profit' => (float)($profitStats['total_final_profit'] ?? 0),
                    'realized_profit_count' => (int)($profitStats['finalized_links'] ?? 0)
                ];
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
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Try JWT auth first (for API calls)
            $payload = null;
            try {
                $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin', 'salesperson']);
            } catch (\Exception $e) {
                // If JWT fails, try session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    return;
                }
                
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
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
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Try JWT auth first (for API calls)
            $payload = null;
            try {
                $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin']);
            } catch (\Exception $e) {
                // If JWT fails, try session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    return;
                }
                
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
            }
            
            $companyId = $payload->company_id ?? null;
            $input = json_decode(file_get_contents('php://input'), true);
            $moduleKey = $input['module_key'] ?? null;
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : null;
            
            if (!$companyId || !$moduleKey || $enabled === null) {
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
        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Try JWT auth first (for API calls)
            $payload = null;
            try {
                $payload = AuthMiddleware::handle(['manager', 'admin', 'system_admin']);
            } catch (\Exception $e) {
                // If JWT fails, try session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    return;
                }
                
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null
                ];
            }
            
            $companyId = $payload->company_id ?? null;
            
            if (!$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID required']);
                return;
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Sales Trends - Last 7 days
            $salesTrends = [];
            $labels = [];
            $revenue = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayName = date('D', strtotime($date));
                $labels[] = $dayName;
                
                $query = $db->prepare("
                    SELECT COALESCE(SUM(final_amount), 0) as total
                    FROM pos_sales
                    WHERE company_id = ? AND DATE(created_at) = ?
                ");
                $query->execute([$companyId, $date]);
                $result = $query->fetch(\PDO::FETCH_ASSOC);
                $revenue[] = floatval($result['total'] ?? 0);
            }
            
            $salesTrends = [
                'labels' => $labels,
                'revenue' => $revenue
            ];
            
            // Activity Distribution - Count of different activities
            $activityDistribution = [];
            
            // Sales count
            $salesQuery = $db->prepare("SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ?");
            $salesQuery->execute([$companyId]);
            $salesCount = $salesQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Repairs count
            $repairsQuery = $db->prepare("SELECT COUNT(*) as count FROM repairs WHERE company_id = ?");
            $repairsQuery->execute([$companyId]);
            $repairsCount = $repairsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Swaps count
            $swapsQuery = $db->prepare("SELECT COUNT(*) as count FROM swaps WHERE company_id = ?");
            $swapsQuery->execute([$companyId]);
            $swapsCount = $swapsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Customers count
            $customersQuery = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
            $customersQuery->execute([$companyId]);
            $customersCount = $customersQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Products count
            $productsQuery = $db->prepare("SELECT COUNT(*) as count FROM products WHERE company_id = ?");
            $productsQuery->execute([$companyId]);
            $productsCount = $productsQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Technicians count
            $techniciansQuery = $db->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE company_id = ? AND role = 'technician'
            ");
            $techniciansQuery->execute([$companyId]);
            $techniciansCount = $techniciansQuery->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
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
            echo json_encode([
                'success' => true,
                'data' => [
                    'sales_trends' => $salesTrends,
                    'activity_distribution' => $activityDistribution
                ]
            ], JSON_NUMERIC_CHECK);
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}