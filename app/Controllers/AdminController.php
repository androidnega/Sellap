<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

/**
 * Admin Controller
 * Handles system administrator operations and platform-wide metrics
 */
class AdminController {
    
    /**
     * Get platform-wide statistics (System Admin only)
     */
    public function stats() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            // Get total companies
            $companiesQuery = $db->query("SELECT COUNT(*) as total FROM companies");
            $totalCompanies = $companiesQuery->fetch()['total'] ?? 0;

            // Get total managers
            $managersQuery = $db->query("SELECT COUNT(*) as total FROM users WHERE role='manager'");
            $totalManagers = $managersQuery->fetch()['total'] ?? 0;

            // Get total users across all companies
            $usersQuery = $db->query("SELECT COUNT(*) as total FROM users");
            $totalUsers = $usersQuery->fetch()['total'] ?? 0;

            // Get aggregated sales volume across all companies - EXCLUDE swap transactions
            $salesVolumeQuery = $db->query("
                SELECT COALESCE(SUM(final_amount), 0) as total 
                FROM pos_sales 
                WHERE swap_id IS NULL
            ");
            $salesVolume = $salesVolumeQuery->fetch()['total'] ?? 0;

            // Get total repairs revenue across all companies - check which repairs table exists
            $repairsVolume = 0;
            try {
                $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                
                if ($hasRepairsNew) {
                    $repairsVolumeQuery = $db->query("SELECT COALESCE(SUM(total_cost), 0) as total FROM repairs_new");
                } else {
                    $repairsVolumeQuery = $db->query("SELECT COALESCE(SUM(total_cost), 0) as total FROM repairs WHERE payment_status = 'PAID'");
                }
                $repairsVolume = $repairsVolumeQuery->fetch()['total'] ?? 0;
            } catch (\Exception $e) {
                error_log("Error getting repairs volume: " . $e->getMessage());
                $repairsVolume = 0;
            }

            // Get total transactions count - Only count sales transactions (excluding swap transactions)
            // Repairs and swaps are tracked separately, not as "transactions"
            $totalTransactions = 0;
            try {
                $salesCountQuery = $db->query("SELECT COUNT(*) as total FROM pos_sales WHERE swap_id IS NULL");
                $totalTransactions = (int)($salesCountQuery->fetch()['total'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error calculating total transactions: " . $e->getMessage());
                $totalTransactions = 0;
            }

            $stats = [
                'companies' => (int)$totalCompanies,
                'managers' => (int)$totalManagers,
                'users' => (int)$totalUsers,
                'sales_volume' => (float)$salesVolume,
                'repairs_volume' => (float)$repairsVolume,
                'total_revenue' => (float)($salesVolume + $repairsVolume),
                'total_transactions' => (int)$totalTransactions
            ];

            header('Content-Type: application/json');
            echo json_encode($stats);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to fetch admin statistics', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get all companies (System Admin only)
     */
    public function companies() {
        try {
        $payload = AuthMiddleware::handle(['system_admin']);
        } catch (\Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([]); // Return empty array instead of error object
            return;
        }
        
        $db = \Database::getInstance()->getConnection();

        try {
            $query = $db->query("
                SELECT 
                    id, 
                    name, 
                    email, 
                    phone_number, 
                    address,
                    created_at 
                FROM companies 
                ORDER BY created_at DESC
            ");
            $companies = $query ? $query->fetchAll(\PDO::FETCH_ASSOC) : [];

            // Always return an array, even if empty
            header('Content-Type: application/json');
            echo json_encode($companies);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            // Return empty array on error instead of error object
            echo json_encode([]);
            error_log("Error fetching companies: " . $e->getMessage());
        }
    }

    /**
     * Get all managers (System Admin only)
     */
    public function managers() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            $query = $db->query("
                SELECT 
                    u.id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role,
                    u.company_id,
                    c.name as company_name,
                    u.created_at
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE u.role = 'manager'
                ORDER BY u.created_at DESC
            ");
            $managers = $query->fetchAll();

            header('Content-Type: application/json');
            echo json_encode($managers);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to fetch managers', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get all users (System Admin only)
     */
    public function users() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            $query = $db->query("
                SELECT 
                    u.id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role,
                    u.company_id,
                    c.name as company_name,
                    u.created_at
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                ORDER BY u.created_at DESC
            ");
            $users = $query->fetchAll();

            header('Content-Type: application/json');
            echo json_encode($users);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to fetch users', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get system health status (System Admin only)
     */
    public function health() {
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            $health = [
                'status' => 'operational',
                'database' => 'connected',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ];

            header('Content-Type: application/json');
            echo json_encode($health);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'System health check failed', 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get platform administration dashboard data (System Admin only)
     * Focuses on platform oversight, not business transactions
     */
    public function dashboard() {
        // Clean any existing output to ensure clean JSON response
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Set JSON header
        header('Content-Type: application/json');
        
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
                error_log("Admin Dashboard Fatal Shutdown Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
                echo json_encode([
                    'success' => false,
                    'error' => 'Internal server error',
                    'message' => 'A fatal error occurred while loading dashboard data',
                    'debug' => [
                        'error' => $error['message'],
                        'file' => basename($error['file']),
                        'line' => $error['line']
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        });
        
        try {
            // Check session auth first (for web requests), then JWT
            $authenticated = false;
            
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Try session-based auth first (for web dashboard)
            $userData = $_SESSION['user'] ?? null;
            
            if ($userData && is_array($userData) && isset($userData['role']) && $userData['role'] === 'system_admin') {
                $authenticated = true;
            } else {
                // Try JWT auth if session not available (for API calls)
                // Check if Authorization header exists
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
                
                if ($authHeader) {
                    try {
                        if (class_exists('\App\Middleware\AuthMiddleware') && method_exists('\App\Middleware\AuthMiddleware', 'handle')) {
                            $payload = AuthMiddleware::handle(['system_admin']);
                            $authenticated = true;
                        } else {
                            $authenticated = false;
                        }
                    } catch (\Exception $e) {
                        // JWT auth failed
                        error_log("Admin Dashboard Auth Error: " . $e->getMessage());
                        $authenticated = false;
                    } catch (\Error $e) {
                        // Fatal error in auth
                        error_log("Admin Dashboard Auth Fatal Error: " . $e->getMessage());
                        $authenticated = false;
                    }
                } else {
                    // No auth header and no valid session
                    $authenticated = false;
                }
            }
            
            if (!$authenticated) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Unauthorized',
                    'message' => 'Please log in as system administrator to access this resource'
                ]);
                return;
            }
            
            // Get database connection with error handling
            try {
                $db = \Database::getInstance()->getConnection();
                if (!$db) {
                    throw new \Exception("Database connection is null");
                }
            } catch (\Exception $e) {
                error_log("Admin Dashboard Database Connection Error: " . $e->getMessage());
                ob_end_clean();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database connection failed',
                    'message' => 'Unable to connect to database: ' . $e->getMessage()
                ]);
                return;
            } catch (\Error $e) {
                error_log("Admin Dashboard Database Fatal Error: " . $e->getMessage());
                ob_end_clean();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database connection failed',
                    'message' => 'A fatal error occurred while connecting to database'
                ]);
                return;
            }
            
            // Helper function to safely query
            $safeQuery = function($sql, $default = 0) use ($db) {
                try {
                    $result = $db->query($sql);
                    if ($result !== false) {
                        $row = $result->fetch(\PDO::FETCH_ASSOC);
                        if ($row !== false && isset($row['total'])) {
                            return (int)$row['total'];
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Admin Dashboard Query Error: " . $e->getMessage() . " - SQL: " . $sql);
                } catch (\PDOException $e) {
                    error_log("Admin Dashboard PDO Error: " . $e->getMessage() . " - SQL: " . $sql);
                }
                return $default;
            };
            
            // Helper function to safely query array
            $safeQueryAll = function($sql, $default = []) use ($db) {
                try {
                    if (!$db) {
                        error_log("Admin Dashboard Query Error: Database connection is null");
                        return $default;
                    }
                    $result = $db->query($sql);
                    if ($result !== false && $result instanceof \PDOStatement) {
                        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
                        // Ensure all items in the array are arrays themselves
                        if (is_array($data)) {
                            // Filter and re-index to ensure sequential array
                            $filtered = array_filter($data, function($item) {
                                return is_array($item);
                            });
                            return array_values($filtered); // Re-index to ensure sequential array
                        }
                        return $default;
                    }
                } catch (\Exception $e) {
                    error_log("Admin Dashboard Query Error: " . $e->getMessage() . " - SQL: " . $sql);
                } catch (\PDOException $e) {
                    error_log("Admin Dashboard PDO Error: " . $e->getMessage() . " - SQL: " . $sql);
                } catch (\Error $e) {
                    error_log("Admin Dashboard Fatal Query Error: " . $e->getMessage() . " - SQL: " . $sql);
                }
                return $default;
            };
            
            // ==================== SUMMARY CARDS ====================
            
            // Test database connection first
            try {
                $testQuery = $db->query("SELECT 1");
                if ($testQuery === false) {
                    throw new \Exception("Database connection failed");
                }
            } catch (\Exception $e) {
                error_log("Database connection test failed: " . $e->getMessage());
            }
            
            // Total Companies - using fetchColumn for COUNT queries (more reliable)
            try {
                $companiesResult = $db->query("SELECT COUNT(*) FROM companies");
                $companiesCount = $companiesResult ? (int)$companiesResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                error_log("Companies count error: " . $e->getMessage());
                $companiesCount = 0;
            }
            
            // Active Companies (all companies are active by default since no status field)
            $activeCompaniesCount = $companiesCount;
            
            // Inactive Companies (currently 0 since no status field)
            $inactiveCompaniesCount = 0;
            
            // New Companies This Month
            try {
                $newCompaniesResult = $db->query("
                    SELECT COUNT(*) FROM companies 
                    WHERE YEAR(created_at) = YEAR(CURDATE()) 
                    AND MONTH(created_at) = MONTH(CURDATE())
                ");
                $newCompaniesThisMonth = $newCompaniesResult ? (int)$newCompaniesResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                error_log("New companies count error: " . $e->getMessage());
                $newCompaniesThisMonth = 0;
            }
            
            // Total Managers - using fetchColumn
            try {
                $managersResult = $db->query("SELECT COUNT(*) FROM users WHERE role='manager'");
                $managersCount = $managersResult ? (int)$managersResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                error_log("Managers count error: " . $e->getMessage());
                $managersCount = 0;
            }
            
            // Total Users - using fetchColumn
            try {
                $usersResult = $db->query("SELECT COUNT(*) FROM users");
                $usersCount = $usersResult ? (int)$usersResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                error_log("Users count error: " . $e->getMessage());
                $usersCount = 0;
            }
            
            // API Requests Today - Count SMS sends from sms_logs table (instant tracking)
            $apiRequestsToday = 0;
            try {
                // First, check for sms_logs table (primary source for SMS API calls)
                $smsLogsCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
                if ($smsLogsCheck && $smsLogsCheck->rowCount() > 0) {
                    $smsLogsResult = $db->query("
                        SELECT COUNT(*) FROM sms_logs 
                        WHERE DATE(sent_at) = CURDATE() AND status = 'sent'
                    ");
                    $apiRequestsToday = $smsLogsResult ? (int)$smsLogsResult->fetchColumn() : 0;
                    error_log("AdminController: API calls today from sms_logs: {$apiRequestsToday}");
                    
                    // Diagnostic: Check total records today (both sent and failed)
                    $totalTodayResult = $db->query("
                        SELECT COUNT(*) FROM sms_logs 
                        WHERE DATE(sent_at) = CURDATE()
                    ");
                    $totalToday = $totalTodayResult ? (int)$totalTodayResult->fetchColumn() : 0;
                    error_log("AdminController: Total SMS logs today (sent + failed): {$totalToday}");
                } else {
                    error_log("AdminController: sms_logs table does not exist for API calls count");
                }
                
                // Also count from notification_logs if it exists (for other notifications)
                $notificationLogsCheck = $db->query("SHOW TABLES LIKE 'notification_logs'");
                if ($notificationLogsCheck && $notificationLogsCheck->rowCount() > 0) {
                    $notificationResult = $db->query("
                        SELECT COUNT(*) FROM notification_logs 
                        WHERE DATE(created_at) = CURDATE() AND success = 1
                    ");
                    $notificationCount = $notificationResult ? (int)$notificationResult->fetchColumn() : 0;
                    // Add to API calls (SMS logs are primary, notifications are additional)
                    $apiRequestsToday += $notificationCount;
                    error_log("AdminController: Added {$notificationCount} notifications to API calls count");
                }
            } catch (\Exception $e) {
                // Table might not exist, that's okay - use 0
                error_log("API requests check error: " . $e->getMessage());
            }
            
            // Total SMS Sent (from sms_logs table - primary source for SMS tracking)
            $smsCountTotal = 0;
            try {
                // First check sms_logs table (primary source)
                $smsLogsCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
                if ($smsLogsCheck && $smsLogsCheck->rowCount() > 0) {
                    $smsTotalResult = $db->query("
                        SELECT COUNT(*) FROM sms_logs 
                        WHERE status = 'sent'
                    ");
                    if ($smsTotalResult) {
                        $smsCountTotal = (int)$smsTotalResult->fetchColumn();
                    }
                    error_log("AdminController: Total SMS count from sms_logs: {$smsCountTotal}");
                } else {
                    error_log("AdminController: sms_logs table does not exist");
                }
                
                // DO NOT fallback to notification_logs for total count - only use sms_logs
                // notification_logs is only used for API calls today count as additional source
            } catch (\Exception $e) {
                // Table might not exist, that's okay
                error_log("SMS count check error: " . $e->getMessage());
            }
            
            // SMS Balance from Arkasel API (SMS credits/units, not money)
            $smsBalance = [
                'credits' => null,
                'formatted' => 'N/A',
                'status' => 'unknown'
            ];
            try {
                // Load SMS settings from database
                $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = ($settingsQuery && $settingsQuery instanceof \PDOStatement) ? $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
                $settings = is_array($settings) ? $settings : [];
                
                if (!empty($settings['sms_api_key'])) {
                    if (class_exists('\App\Services\SMSService')) {
                        try {
                            $smsService = new \App\Services\SMSService();
                            if (method_exists($smsService, 'loadFromSettings')) {
                                $smsService->loadFromSettings($settings);
                            }
                            
                            if (method_exists($smsService, 'getBalance')) {
                                $balanceResult = $smsService->getBalance();
                                
                                if (is_array($balanceResult) && ($balanceResult['success'] ?? false)) {
                                    $smsBalance = [
                                        'credits' => (float)($balanceResult['balance'] ?? 0),
                                        'formatted' => isset($balanceResult['formatted']) ? (string)$balanceResult['formatted'] : '0 SMS',
                                        'status' => 'active'
                                    ];
                                } else {
                                    $smsBalance['status'] = 'error';
                                    $errorMsg = isset($balanceResult['error']) ? (string)$balanceResult['error'] : 'Unable to fetch balance';
                                    
                                    // Make timeout errors more user-friendly
                                    if (isset($balanceResult['timeout']) || strpos($errorMsg, 'timeout') !== false) {
                                        $smsBalance['error'] = 'Connection timeout. Please check your internet connection.';
                                    } elseif (isset($balanceResult['dns_error']) || strpos($errorMsg, 'DNS') !== false) {
                                        $smsBalance['error'] = 'Network error. Unable to reach SMS API server.';
                                    } else {
                                        $smsBalance['error'] = $errorMsg;
                                    }
                                }
                            } else {
                                $smsBalance['status'] = 'error';
                                $smsBalance['error'] = 'getBalance method not found';
                            }
                        } catch (\Throwable $e) {
                            error_log("SMS Service instantiation error: " . $e->getMessage());
                            $smsBalance['status'] = 'error';
                            $smsBalance['error'] = 'Failed to initialize SMS service';
                        }
                    } else {
                        $smsBalance['status'] = 'error';
                        $smsBalance['error'] = 'SMS service class not found';
                    }
                } else {
                    $smsBalance['status'] = 'not_configured';
                    $smsBalance['error'] = 'SMS API key not configured';
                }
            } catch (\Exception $e) {
                error_log("SMS balance check error: " . $e->getMessage());
                $smsBalance['status'] = 'error';
                $smsBalance['error'] = 'Failed to fetch balance: ' . $e->getMessage();
            } catch (\Error $e) {
                error_log("SMS balance check fatal error: " . $e->getMessage());
                $smsBalance['status'] = 'error';
                $smsBalance['error'] = 'Failed to fetch balance';
            }
            
            // System Storage Usage (estimate - sum of file sizes if you track uploads)
            // For now, return placeholder or calculate from actual storage
            $storageUsedMB = 0; // Placeholder - implement based on your file storage system
            
            // ==================== ANALYTICS CHARTS ====================
            
            // Company Growth Chart (last 12 months)
            $companyGrowthData = $safeQueryAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM companies
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $companyGrowthChart = [
                'labels' => is_array($companyGrowthData) ? array_map(function($item) {
                    if (!is_array($item) || !isset($item['month'])) {
                        return '';
                    }
                    $timestamp = strtotime($item['month'] . '-01');
                    return $timestamp !== false ? date('M Y', $timestamp) : '';
                }, $companyGrowthData) : [],
                'values' => is_array($companyGrowthData) ? array_map(function($item) {
                    return (is_array($item) && isset($item['count'])) ? (int)$item['count'] : 0;
                }, $companyGrowthData) : []
            ];
            
            // User Growth Chart (last 12 months)
            $userGrowthData = $safeQueryAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $userGrowthChart = [
                'labels' => is_array($userGrowthData) ? array_map(function($item) {
                    if (!is_array($item) || !isset($item['month'])) {
                        return '';
                    }
                    $timestamp = strtotime($item['month'] . '-01');
                    return $timestamp !== false ? date('M Y', $timestamp) : '';
                }, $userGrowthData) : [],
                'values' => is_array($userGrowthData) ? array_map(function($item) {
                    return (is_array($item) && isset($item['count'])) ? (int)$item['count'] : 0;
                }, $userGrowthData) : []
            ];
            
            // SMS Usage Trend (last 30 days)
            $smsTrendData = [];
            try {
                // First try sms_logs table (primary source for SMS tracking)
                $smsLogsCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
                if ($smsLogsCheck && $smsLogsCheck->rowCount() > 0) {
                    $smsTrendResult = $db->query("
                        SELECT 
                            DATE(sent_at) as date,
                            COUNT(*) as count
                        FROM sms_logs
                        WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        AND status = 'sent'
                        GROUP BY DATE(sent_at)
                        ORDER BY date ASC
                    ");
                    $smsTrendData = $smsTrendResult ? $smsTrendResult->fetchAll(\PDO::FETCH_ASSOC) : [];
                    error_log("SMS trend data from sms_logs: " . count($smsTrendData) . " records");
                } else {
                    // Fallback to notification_logs if sms_logs doesn't exist
                    $tableCheck = $db->query("SHOW TABLES LIKE 'notification_logs'");
                    if ($tableCheck && $tableCheck->rowCount() > 0) {
                        $smsTrendResult = $db->query("
                            SELECT 
                                DATE(created_at) as date,
                                COUNT(*) as count
                            FROM notification_logs
                            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                            AND success = 1
                            GROUP BY DATE(created_at)
                            ORDER BY date ASC
                        ");
                        $smsTrendData = $smsTrendResult ? $smsTrendResult->fetchAll(\PDO::FETCH_ASSOC) : [];
                        error_log("SMS trend data from notification_logs (fallback): " . count($smsTrendData) . " records");
                    }
                }
            } catch (\Exception $e) {
                error_log("SMS trend query error: " . $e->getMessage());
                error_log("SMS trend query trace: " . $e->getTraceAsString());
            }
            $smsChart = [
                'labels' => is_array($smsTrendData) ? array_map(function($item) {
                    if (!is_array($item) || !isset($item['date'])) {
                        return '';
                    }
                    $timestamp = strtotime($item['date']);
                    return $timestamp !== false ? date('M d', $timestamp) : '';
                }, $smsTrendData) : [],
                'values' => is_array($smsTrendData) ? array_map(function($item) {
                    return (is_array($item) && isset($item['count'])) ? (int)$item['count'] : 0;
                }, $smsTrendData) : []
            ];
            
            // Activity Heatmap - Active Companies Daily (last 30 days)
            // Count companies with any activity (sales, repairs, swaps, or new users)
            $activityData = $safeQueryAll("
                SELECT 
                    DATE(activity_date) as date,
                    COUNT(DISTINCT company_id) as active_count
                FROM (
                    SELECT DATE(created_at) as activity_date, company_id FROM pos_sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    UNION
                    SELECT DATE(created_at) as activity_date, company_id FROM repairs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    UNION
                    SELECT DATE(created_at) as activity_date, company_id FROM swaps WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    UNION
                    SELECT DATE(created_at) as activity_date, company_id FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND company_id IS NOT NULL
                ) activity_data
                GROUP BY DATE(activity_date)
                ORDER BY date ASC
            ");
            $activityHeatmap = [
                'labels' => is_array($activityData) ? array_map(function($item) {
                    if (!is_array($item) || !isset($item['date'])) {
                        return '';
                    }
                    $timestamp = strtotime($item['date']);
                    return $timestamp !== false ? date('M d', $timestamp) : '';
                }, $activityData) : [],
                'values' => is_array($activityData) ? array_map(function($item) {
                    return (is_array($item) && isset($item['active_count'])) ? (int)$item['active_count'] : 0;
                }, $activityData) : []
            ];
            
            // System Transaction Summary (high-level count only)
            try {
                $salesResult = $db->query("SELECT COUNT(*) FROM pos_sales");
                $salesCount = $salesResult ? (int)$salesResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                $salesCount = 0;
            }
            
            try {
                $repairsResult = $db->query("SELECT COUNT(*) FROM repairs");
                $repairsCount = $repairsResult ? (int)$repairsResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                $repairsCount = 0;
            }
            
            try {
                $swapsResult = $db->query("SELECT COUNT(*) FROM swaps");
                $swapsCount = $swapsResult ? (int)$swapsResult->fetchColumn() : 0;
            } catch (\Exception $e) {
                $swapsCount = 0;
            }
            
            $transactionSummary = [
                'sales_count' => $salesCount,
                'repairs_count' => $repairsCount,
                'swaps_count' => $swapsCount
            ];
            
            // Top 3 Most Active Companies (by transaction volume)
            $topCompanies = $safeQueryAll("
                SELECT 
                    c.id,
                    c.name,
                    COALESCE((
                        SELECT COUNT(*) FROM pos_sales ps 
                        WHERE ps.company_id = c.id
                    ), 0) +
                    COALESCE((
                        SELECT COUNT(*) FROM repairs r 
                        WHERE r.company_id = c.id
                    ), 0) +
                    COALESCE((
                        SELECT COUNT(*) FROM swaps s 
                        WHERE s.company_id = c.id
                    ), 0) as transaction_count
                FROM companies c
                HAVING transaction_count > 0
                ORDER BY transaction_count DESC
                LIMIT 3
            ");
            
            // ==================== COMPANY SMS SUMMARY ====================
            
            // Get Company SMS Summary data
            $companySMSSummary = [
                'total_sms_used' => 0,
                'companies_with_low_balance' => 0,
                'low_balance_companies' => [],
                'top_senders' => [],
                'top_sender' => null
            ];
            
            try {
                $tableCheck = $db->query("SHOW TABLES LIKE 'company_sms_accounts'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    // Get total SMS used across all companies
                    $totalUsedResult = $db->query("
                        SELECT SUM(sms_used) as total_used 
                        FROM company_sms_accounts
                    ");
                    $totalUsed = $totalUsedResult ? (float)($totalUsedResult->fetchColumn() ?: 0) : 0;
                    $companySMSSummary['total_sms_used'] = $totalUsed;
                    
                    // Get companies with low balance (< 10% remaining)
                    $lowBalanceCompanies = $safeQueryAll("
                        SELECT 
                            csa.company_id,
                            c.name as company_name,
                            csa.total_sms,
                            csa.sms_used,
                            (csa.total_sms - csa.sms_used) as sms_remaining,
                            CASE 
                                WHEN csa.total_sms > 0 
                                THEN ROUND((csa.sms_used / csa.total_sms) * 100, 2)
                                ELSE 0 
                            END as usage_percent,
                            csa.status
                        FROM company_sms_accounts csa
                        INNER JOIN companies c ON csa.company_id = c.id
                        WHERE csa.status = 'active'
                        AND csa.total_sms > 0
                        HAVING usage_percent >= 90 OR sms_remaining <= 10
                        ORDER BY usage_percent DESC
                        LIMIT 10
                    ");
                    $companySMSSummary['companies_with_low_balance'] = is_array($lowBalanceCompanies) ? count($lowBalanceCompanies) : 0;
                    $companySMSSummary['low_balance_companies'] = is_array($lowBalanceCompanies) ? array_map(function($item) {
                        if (!is_array($item)) {
                            return [
                                'company_id' => 0,
                                'company_name' => '',
                                'total_sms' => 0,
                                'sms_used' => 0,
                                'sms_remaining' => 0,
                                'usage_percent' => 0
                            ];
                        }
                        return [
                            'company_id' => isset($item['company_id']) ? (int)$item['company_id'] : 0,
                            'company_name' => isset($item['company_name']) ? (string)$item['company_name'] : '',
                            'total_sms' => isset($item['total_sms']) ? (int)$item['total_sms'] : 0,
                            'sms_used' => isset($item['sms_used']) ? (int)$item['sms_used'] : 0,
                            'sms_remaining' => isset($item['sms_remaining']) ? (int)$item['sms_remaining'] : 0,
                            'usage_percent' => isset($item['usage_percent']) ? (float)$item['usage_percent'] : 0
                        ];
                    }, $lowBalanceCompanies) : [];
                    
                    // Get top SMS senders (most SMS sent)
                    $topSenders = $safeQueryAll("
                        SELECT 
                            csa.company_id,
                            c.name as company_name,
                            csa.sms_used as sms_sent,
                            (csa.total_sms - csa.sms_used) as sms_remaining,
                            csa.status
                        FROM company_sms_accounts csa
                        INNER JOIN companies c ON csa.company_id = c.id
                        WHERE csa.sms_used > 0
                        ORDER BY csa.sms_used DESC
                        LIMIT 5
                    ");
                    $companySMSSummary['top_senders'] = is_array($topSenders) ? array_map(function($item) {
                        if (!is_array($item)) {
                            return [
                                'company_id' => 0,
                                'company_name' => '',
                                'sms_sent' => 0,
                                'sms_remaining' => 0,
                                'status' => ''
                            ];
                        }
                        return [
                            'company_id' => isset($item['company_id']) ? (int)$item['company_id'] : 0,
                            'company_name' => isset($item['company_name']) ? (string)$item['company_name'] : '',
                            'sms_sent' => isset($item['sms_sent']) ? (int)$item['sms_sent'] : 0,
                            'sms_remaining' => isset($item['sms_remaining']) ? (int)$item['sms_remaining'] : 0,
                            'status' => isset($item['status']) ? (string)$item['status'] : ''
                        ];
                    }, $topSenders) : [];
                    
                    // Get top sender (single)
                    if (is_array($topSenders) && !empty($topSenders) && isset($topSenders[0]) && is_array($topSenders[0])) {
                        $companySMSSummary['top_sender'] = [
                            'company_id' => isset($topSenders[0]['company_id']) ? (int)$topSenders[0]['company_id'] : 0,
                            'company_name' => isset($topSenders[0]['company_name']) ? (string)$topSenders[0]['company_name'] : '',
                            'sms_sent' => isset($topSenders[0]['sms_sent']) ? (int)$topSenders[0]['sms_sent'] : 0
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching company SMS summary: " . $e->getMessage());
            }
            
            // ==================== SYSTEM INFO ====================
            
            // System Health Check
            $healthCheck = [
                'status' => 'operational',
                'database' => 'connected',
                'php_version' => phpversion() ?: 'Unknown',
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ];
            
            // Version Info (from config or constant)
            $versionInfo = [
                'current_version' => '2.0.0',
                'last_update_check' => date('Y-m-d')
            ];
            
            // Backup Status (placeholder - implement if you have backup tracking)
            $backupStatus = [
                'last_backup' => null, // Implement if you track backups
                'backup_enabled' => false
            ];
            
            // System Alerts (check for recent errors)
            $recentErrors = 0;
            try {
                $tableCheck = $db->query("SHOW TABLES LIKE 'notification_logs'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    $errorsResult = $db->query("
                        SELECT COUNT(*) FROM notification_logs 
                        WHERE success = 0 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ");
                    $recentErrors = $errorsResult ? (int)$errorsResult->fetchColumn() : 0;
                }
            } catch (\Exception $e) {
                // Table might not exist, that's okay
            }
            
            // Ensure all variables are defined before building response
            $response = [
                'success' => true,
                'companies' => isset($companiesCount) ? (int)$companiesCount : 0,
                'active_companies' => isset($activeCompaniesCount) ? (int)$activeCompaniesCount : 0,
                'inactive_companies' => isset($inactiveCompaniesCount) ? (int)$inactiveCompaniesCount : 0,
                'new_companies_this_month' => isset($newCompaniesThisMonth) ? (int)$newCompaniesThisMonth : 0,
                'managers' => isset($managersCount) ? (int)$managersCount : 0,
                'users' => isset($usersCount) ? (int)$usersCount : 0,
                'api_requests_today' => isset($apiRequestsToday) ? (int)$apiRequestsToday : 0,
                'sms_sent_total' => isset($smsCountTotal) ? (int)$smsCountTotal : 0,
                'sms_balance' => isset($smsBalance) && is_array($smsBalance) ? $smsBalance : [
                    'credits' => null,
                    'formatted' => 'N/A',
                    'status' => 'unknown'
                ],
                'storage_used_mb' => isset($storageUsedMB) ? (float)$storageUsedMB : 0,
                'growth_chart' => isset($companyGrowthChart) && is_array($companyGrowthChart) ? $companyGrowthChart : ['labels' => [], 'values' => []],
                'user_chart' => isset($userGrowthChart) && is_array($userGrowthChart) ? $userGrowthChart : ['labels' => [], 'values' => []],
                'sms_chart' => isset($smsChart) && is_array($smsChart) ? $smsChart : ['labels' => [], 'values' => []],
                'activity_heatmap' => isset($activityHeatmap) && is_array($activityHeatmap) ? $activityHeatmap : ['labels' => [], 'values' => []],
                'transaction_summary' => [
                    'sales' => isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['sales_count'] ?? 0) : 0,
                    'repairs' => isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['repairs_count'] ?? 0) : 0,
                    'swaps' => isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['swaps_count'] ?? 0) : 0,
                    'total' => (isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['sales_count'] ?? 0) : 0) + 
                              (isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['repairs_count'] ?? 0) : 0) + 
                              (isset($transactionSummary) && is_array($transactionSummary) ? ($transactionSummary['swaps_count'] ?? 0) : 0)
                ],
                'top_companies' => isset($topCompanies) && is_array($topCompanies) ? array_map(function($item) {
                    if (!is_array($item)) {
                        return [
                            'id' => 0,
                            'name' => '',
                            'transaction_count' => 0
                        ];
                    }
                    return [
                        'id' => isset($item['id']) ? (int)$item['id'] : 0,
                        'name' => isset($item['name']) ? (string)$item['name'] : '',
                        'transaction_count' => isset($item['transaction_count']) ? (int)$item['transaction_count'] : 0
                    ];
                }, $topCompanies) : [],
                'system_health' => isset($healthCheck) && is_array($healthCheck) ? $healthCheck : [
                    'status' => 'operational',
                    'database' => 'connected',
                    'php_version' => phpversion() ?: 'Unknown',
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
                ],
                'version_info' => isset($versionInfo) && is_array($versionInfo) ? $versionInfo : [
                    'current_version' => '2.0.0',
                    'last_update_check' => date('Y-m-d')
                ],
                'backup_status' => isset($backupStatus) && is_array($backupStatus) ? $backupStatus : [
                    'last_backup' => null,
                    'backup_enabled' => false
                ],
                'system_alerts' => [
                    'recent_errors' => isset($recentErrors) ? (int)$recentErrors : 0,
                    'has_alerts' => (isset($recentErrors) ? (int)$recentErrors : 0) > 0
                ],
                'company_sms_summary' => isset($companySMSSummary) && is_array($companySMSSummary) ? $companySMSSummary : [
                    'total_sms_used' => 0,
                    'companies_with_low_balance' => 0,
                    'low_balance_companies' => [],
                    'top_senders' => [],
                    'top_sender' => null
                ]
            ];

            // Clean output buffer and send JSON
            ob_end_clean();
            
            // Validate response before encoding
            $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            if ($jsonResponse === false) {
                $error = json_last_error_msg();
                error_log("JSON encoding error: " . $error);
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to encode response',
                    'message' => 'Data encoding error'
                ]);
                return;
            }
            
            echo $jsonResponse;
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Admin Dashboard Error: " . $e->getMessage() . " - Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch dashboard data',
                'message' => $e->getMessage()
            ]);
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Admin Dashboard Fatal Error: " . $e->getMessage() . " - Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'A fatal error occurred while loading dashboard data'
            ]);
        }
    }
    
    /**
     * Get detailed analytics data (System Admin only)
     */
    public function analytics() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Check authentication
            $authenticated = false;
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if ($userData && is_array($userData) && isset($userData['role']) && $userData['role'] === 'system_admin') {
                $authenticated = true;
            } else {
                try {
        $payload = AuthMiddleware::handle(['system_admin']);
                    $authenticated = true;
                } catch (\Exception $e) {
                    $authenticated = false;
                }
            }
            
            if (!$authenticated) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'System administrator access required'
                ]);
                return;
            }
            
            // Ensure Database class is loaded
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            
        $db = \Database::getInstance()->getConnection();
            
            if (!$db) {
                throw new \Exception("Database connection is null");
            }

        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics Error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch analytics data',
                'message' => $e->getMessage()
            ]);
            return;
        }

        try {
            // Get sales analytics - EXCLUDE swap transactions
            try {
            $salesQuery = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(final_amount), 0) as revenue,
                    COALESCE(AVG(final_amount), 0) as avg_order_value,
                    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as monthly
                FROM pos_sales
                WHERE swap_id IS NULL
            ");
                $salesData = $salesQuery ? $salesQuery->fetch(\PDO::FETCH_ASSOC) : ['total' => 0, 'revenue' => 0, 'avg_order_value' => 0, 'monthly' => 0];
            } catch (\Exception $e) {
                error_log("Sales analytics query error: " . $e->getMessage());
                $salesData = ['total' => 0, 'revenue' => 0, 'avg_order_value' => 0, 'monthly' => 0];
            }
            
            // Get repairs analytics - check which repairs table exists
            try {
                $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                
                if ($hasRepairsNew) {
                    $repairsQuery = $db->query("
                        SELECT 
                            COUNT(*) as total,
                            COALESCE(SUM(total_cost), 0) as revenue
                        FROM repairs_new
                    ");
                } else {
                    $repairsQuery = $db->query("
                        SELECT 
                            COUNT(*) as total,
                            COALESCE(SUM(CASE WHEN payment_status = 'PAID' THEN total_cost ELSE 0 END), 0) as revenue
                        FROM repairs
                    ");
                }
                $repairsData = $repairsQuery ? $repairsQuery->fetch(\PDO::FETCH_ASSOC) : ['total' => 0, 'revenue' => 0];
            } catch (\Exception $e) {
                error_log("Repairs analytics query error: " . $e->getMessage());
                $repairsData = ['total' => 0, 'revenue' => 0];
            }
            
            // Get swaps analytics - check which status column exists and calculate revenue
            try {
                // Check which columns exist
                $checkStatusCol = $db->query("SHOW COLUMNS FROM swaps LIKE 'status'");
                $checkSwapStatusCol = $db->query("SHOW COLUMNS FROM swaps LIKE 'swap_status'");
                $checkTotalValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
                $checkAddedCash = $db->query("SHOW COLUMNS FROM swaps LIKE 'added_cash'");
                $checkCashAdded = $db->query("SHOW COLUMNS FROM swaps LIKE 'cash_added'");
                $checkFinalPrice = $db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
                
                $hasStatus = $checkStatusCol && $checkStatusCol->rowCount() > 0;
                $hasSwapStatus = $checkSwapStatusCol && $checkSwapStatusCol->rowCount() > 0;
                $hasTotalValue = $checkTotalValue && $checkTotalValue->rowCount() > 0;
                $hasAddedCash = $checkAddedCash && $checkAddedCash->rowCount() > 0;
                $hasCashAdded = $checkCashAdded && $checkCashAdded->rowCount() > 0;
                $hasFinalPrice = $checkFinalPrice && $checkFinalPrice->rowCount() > 0;
                
                // Determine revenue column
                $revenueColumn = '0';
                if ($hasTotalValue) {
                    $revenueColumn = 'COALESCE(SUM(total_value), 0)';
                } elseif ($hasAddedCash) {
                    $revenueColumn = 'COALESCE(SUM(added_cash), 0)';
                } elseif ($hasCashAdded) {
                    $revenueColumn = 'COALESCE(SUM(cash_added), 0)';
                } elseif ($hasFinalPrice) {
                    $revenueColumn = 'COALESCE(SUM(final_price), 0)';
                }
                
                // Build status condition
                $statusCondition = '';
                if ($hasStatus) {
                    $statusCondition = "COUNT(CASE WHEN status IN ('pending', 'completed') THEN 1 END)";
                } elseif ($hasSwapStatus) {
                    $statusCondition = "COUNT(CASE WHEN swap_status IN ('PENDING', 'COMPLETED') THEN 1 END)";
                } else {
                    $statusCondition = "COUNT(*)";
                }
                
                // Build query
                if ($hasStatus) {
                    $swapsQuery = $db->query("
                        SELECT 
                            COUNT(*) as total,
                            {$statusCondition} as active,
                            {$revenueColumn} as revenue
                        FROM swaps
                    ");
                } elseif ($hasSwapStatus) {
                    $swapsQuery = $db->query("
                        SELECT 
                            COUNT(*) as total,
                            {$statusCondition} as active,
                            {$revenueColumn} as revenue
                        FROM swaps
                    ");
                } else {
                    $swapsQuery = $db->query("
                        SELECT 
                            COUNT(*) as total,
                            COUNT(*) as active,
                            {$revenueColumn} as revenue
                        FROM swaps
                    ");
                }
                $swapsData = $swapsQuery ? $swapsQuery->fetch(\PDO::FETCH_ASSOC) : ['total' => 0, 'active' => 0, 'revenue' => 0];
            } catch (\Exception $e) {
                error_log("Swaps analytics query error: " . $e->getMessage());
                $swapsData = ['total' => 0, 'active' => 0, 'revenue' => 0];
            }
            
            // Get revenue timeline (last 30 days) - Include sales (excluding swap transactions) and repairs revenue
            try {
                // Check which repairs table exists
                $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                
                // Get sales revenue by date (excluding swap transactions)
                $salesTimelineQuery = $db->query("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(final_amount), 0) as revenue
                    FROM pos_sales
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND swap_id IS NULL
                    GROUP BY DATE(created_at)
                ");
                $salesTimeline = $salesTimelineQuery ? $salesTimelineQuery->fetchAll(\PDO::FETCH_ASSOC) : [];
                
                // Get repairs revenue by date
                if ($hasRepairsNew) {
                    $repairsTimelineQuery = $db->query("
                        SELECT 
                            DATE(created_at) as date,
                            COALESCE(SUM(total_cost), 0) as revenue
                        FROM repairs_new
                        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                    ");
                } else {
                    $repairsTimelineQuery = $db->query("
                        SELECT 
                            DATE(created_at) as date,
                            COALESCE(SUM(CASE WHEN payment_status = 'PAID' THEN total_cost ELSE 0 END), 0) as revenue
                        FROM repairs
                        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                    ");
                }
                $repairsTimeline = $repairsTimelineQuery ? $repairsTimelineQuery->fetchAll(\PDO::FETCH_ASSOC) : [];
                
                // Combine sales and repairs revenue by date
                $revenueByDate = [];
                
                // Add sales revenue
                foreach ($salesTimeline as $item) {
                    $date = $item['date'];
                    if (!isset($revenueByDate[$date])) {
                        $revenueByDate[$date] = 0;
                    }
                    $revenueByDate[$date] += (float)($item['revenue'] ?? 0);
                }
                
                // Add repairs revenue
                foreach ($repairsTimeline as $item) {
                    $date = $item['date'];
                    if (!isset($revenueByDate[$date])) {
                        $revenueByDate[$date] = 0;
                    }
                    $revenueByDate[$date] += (float)($item['revenue'] ?? 0);
                }
                
                // Convert to array format and sort by date
                $revenueTimeline = [];
                foreach ($revenueByDate as $date => $revenue) {
                    $revenueTimeline[] = [
                        'date' => $date,
                        'revenue' => $revenue
                    ];
                }
                
                // Sort by date
                usort($revenueTimeline, function($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });
                
            } catch (\Exception $e) {
                error_log("Revenue timeline query error: " . $e->getMessage());
                $revenueTimeline = [];
            }
            
            // Get transaction types count - EXCLUDE swap transactions from sales, check both repairs tables
            try {
                // Check which repairs table exists
                $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                
                if ($hasRepairsNew) {
                    $transactionTypesQuery = $db->query("
                        SELECT 
                            (SELECT COUNT(*) FROM pos_sales WHERE swap_id IS NULL) as sales,
                            (SELECT COUNT(*) FROM repairs_new) as repairs,
                            (SELECT COUNT(*) FROM swaps) as swaps
                    ");
                } else {
                    $transactionTypesQuery = $db->query("
                        SELECT 
                            (SELECT COUNT(*) FROM pos_sales WHERE swap_id IS NULL) as sales,
                            (SELECT COUNT(*) FROM repairs) as repairs,
                            (SELECT COUNT(*) FROM swaps) as swaps
                    ");
                }
                $transactionTypesData = $transactionTypesQuery ? $transactionTypesQuery->fetch(\PDO::FETCH_ASSOC) : ['sales' => 0, 'repairs' => 0, 'swaps' => 0];
            } catch (\Exception $e) {
                error_log("Transaction types query error: " . $e->getMessage());
                $transactionTypesData = ['sales' => 0, 'repairs' => 0, 'swaps' => 0];
            }
            
            // Get user growth (last 90 days, grouped by week)
            try {
            $userGrowthQuery = $db->query("
                SELECT 
                    YEAR(created_at) as year,
                        WEEK(created_at, 1) as week,
                    COUNT(*) as count
                FROM users
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    GROUP BY YEAR(created_at), WEEK(created_at, 1)
                ORDER BY year ASC, week ASC
            ");
                $userGrowthData = $userGrowthQuery ? $userGrowthQuery->fetchAll(\PDO::FETCH_ASSOC) : [];
            } catch (\Exception $e) {
                error_log("User growth query error: " . $e->getMessage());
                $userGrowthData = [];
            }
            
            // Prepare response
            $analytics = [
                'success' => true,
                'sales' => [
                    'total' => (int)($salesData['total'] ?? 0),
                    'revenue' => (float)($salesData['revenue'] ?? 0),
                    'avg_order_value' => (float)($salesData['avg_order_value'] ?? 0),
                    'monthly' => (int)($salesData['monthly'] ?? 0)
                ],
                'repairs' => [
                    'total' => (int)($repairsData['total'] ?? 0),
                    'revenue' => (float)($repairsData['revenue'] ?? 0)
                ],
                'swaps' => [
                    'total' => (int)($swapsData['total'] ?? 0),
                    'active' => (int)($swapsData['active'] ?? 0),
                    'revenue' => (float)($swapsData['revenue'] ?? 0)
                ],
                'revenue_timeline' => [
                    'labels' => array_map(function($item) {
                        if (!isset($item['date']) || empty($item['date'])) {
                            return '';
                        }
                        $timestamp = strtotime($item['date']);
                        return $timestamp !== false ? date('M d', $timestamp) : '';
                    }, $revenueTimeline),
                    'values' => array_map(function($item) {
                        return (float)($item['revenue'] ?? 0);
                    }, $revenueTimeline)
                ],
                'transaction_types' => [
                    'sales' => (int)($transactionTypesData['sales'] ?? 0),
                    'repairs' => (int)($transactionTypesData['repairs'] ?? 0),
                    'swaps' => (int)($transactionTypesData['swaps'] ?? 0)
                ],
                'user_growth' => [
                    'labels' => array_map(function($item) {
                        try {
                            if (!isset($item['year']) || !isset($item['week'])) {
                                return '';
                            }
                        $date = new \DateTime();
                            $date->setISODate((int)$item['year'], (int)$item['week']);
                        return $date->format('M d');
                        } catch (\Exception $e) {
                            // Fallback to simple date format
                            return date('M d', strtotime($item['year'] . '-W' . str_pad($item['week'], 2, '0', STR_PAD_LEFT)));
                        }
                    }, $userGrowthData),
                    'values' => array_map(function($item) {
                        return (int)($item['count'] ?? 0);
                    }, $userGrowthData)
                ]
            ];

            ob_end_clean();
            echo json_encode($analytics);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics Query Error: " . $e->getMessage());
            error_log("Analytics Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch analytics data',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Analytics Fatal Error: " . $e->getMessage());
            error_log("Analytics Fatal Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    
    /**
     * Get company performance metrics (revenue and profit per company)
     * GET /api/admin/company-performance
     */
    public function companyPerformance() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Check authentication
            $authenticated = false;
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if ($userData && is_array($userData) && isset($userData['role']) && $userData['role'] === 'system_admin') {
                $authenticated = true;
            } else {
                try {
                    $payload = AuthMiddleware::handle(['system_admin']);
                    $authenticated = true;
                } catch (\Exception $e) {
                    $authenticated = false;
                }
            }
            
            if (!$authenticated) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'System administrator access required'
                ]);
                return;
            }
            
            // Ensure Database class is loaded
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            
            $db = \Database::getInstance()->getConnection();
            
            if (!$db) {
                throw new \Exception("Database connection is null");
            }
            
            // Get date range filters (optional)
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            // Determine which products table to use
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'products_new'");
                $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            } catch (\Exception $e) {
                error_log("Error checking products table: " . $e->getMessage());
                $productsTable = 'products'; // Default fallback
            }
            
            // Get all companies first
            try {
                $companiesQuery = $db->query("SELECT id, name, email, phone_number, created_at FROM companies ORDER BY id");
                $allCompanies = $companiesQuery ? $companiesQuery->fetchAll(\PDO::FETCH_ASSOC) : [];
            } catch (\Exception $e) {
                error_log("Error fetching companies: " . $e->getMessage());
                $allCompanies = [];
            }
            
            // Calculate metrics for each company
            $companies = [];
            foreach ($allCompanies as $company) {
                try {
                    $companyId = $company['id'];
                    
                    // Build WHERE clauses with proper parameter binding
                    $salesWhereClause = "ps.company_id = :company_id";
                    $repairsWhereClause = "r.company_id = :company_id";
                    $swapsWhereClause = "s.company_id = :company_id";
                    
                    $salesParams = ['company_id' => $companyId];
                    $repairsParams = ['company_id' => $companyId];
                    $swapsParams = ['company_id' => $companyId];
                    
                    // Use datetime comparison to match getFinancialSummary (includes full day)
                    if ($dateFrom && $dateTo) {
                        $salesWhereClause .= " AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end";
                        $repairsWhereClause .= " AND r.created_at >= :date_from_start AND r.created_at <= :date_to_end";
                        $swapsWhereClause .= " AND s.created_at >= :date_from_start AND s.created_at <= :date_to_end";
                        $salesParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        $salesParams['date_to_end'] = $dateTo . ' 23:59:59';
                        $repairsParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        $repairsParams['date_to_end'] = $dateTo . ' 23:59:59';
                        $swapsParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        $swapsParams['date_to_end'] = $dateTo . ' 23:59:59';
                    } elseif ($dateFrom) {
                        $salesWhereClause .= " AND ps.created_at >= :date_from_start";
                        $repairsWhereClause .= " AND r.created_at >= :date_from_start";
                        $swapsWhereClause .= " AND s.created_at >= :date_from_start";
                        $salesParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        $repairsParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        $swapsParams['date_from_start'] = $dateFrom . ' 00:00:00';
                    } elseif ($dateTo) {
                        $salesWhereClause .= " AND ps.created_at <= :date_to_end";
                        $repairsWhereClause .= " AND r.created_at <= :date_to_end";
                        $swapsWhereClause .= " AND s.created_at <= :date_to_end";
                        $salesParams['date_to_end'] = $dateTo . ' 23:59:59';
                        $repairsParams['date_to_end'] = $dateTo . ' 23:59:59';
                        $swapsParams['date_to_end'] = $dateTo . ' 23:59:59';
                    }
                    
                    // Get sales metrics - EXCLUDE swap transactions
                    try {
                        $salesQuery = $db->prepare("
                            SELECT 
                                COUNT(DISTINCT ps.id) as total_sales,
                                COALESCE(SUM(ps.final_amount), 0) as sales_revenue
                            FROM pos_sales ps
                            WHERE {$salesWhereClause}
                            AND ps.swap_id IS NULL
                        ");
                        $salesQuery->execute($salesParams);
                        $salesData = $salesQuery->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        error_log("Company {$companyId} sales query error: " . $e->getMessage());
                        $salesData = ['total_sales' => 0, 'sales_revenue' => 0];
                    }
                    
                    // Get sales cost - EXCLUDE swap transactions
                    // Use datetime comparison to match getFinancialSummary (includes full day)
                    try {
                        // Build datetime parameters
                        $costParams = ['company_id' => $companyId];
                        $costWhereClause = "ps.company_id = :company_id AND ps.swap_id IS NULL";
                        
                        if ($dateFrom && $dateTo) {
                            $costWhereClause .= " AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end";
                            $costParams['date_from_start'] = $dateFrom . ' 00:00:00';
                            $costParams['date_to_end'] = $dateTo . ' 23:59:59';
                        } elseif ($dateFrom) {
                            $costWhereClause .= " AND ps.created_at >= :date_from_start";
                            $costParams['date_from_start'] = $dateFrom . ' 00:00:00';
                        } elseif ($dateTo) {
                            $costWhereClause .= " AND ps.created_at <= :date_to_end";
                            $costParams['date_to_end'] = $dateTo . ' 23:59:59';
                        }
                        
                        // Check which cost column exists (prioritize cost_price, then cost)
                        $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
                        $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
                        $hasCostPrice = $checkCostPrice->rowCount() > 0;
                        $hasCost = $checkCost->rowCount() > 0;
                        
                        if ($hasCostPrice) {
                            $costColumn = 'COALESCE(p.cost_price, 0)';
                        } elseif ($hasCost) {
                            $costColumn = 'COALESCE(p.cost, 0)';
                        } else {
                            $costColumn = '0';
                        }
                        
                        // Calculate sales cost with improved matching (by item_id OR by description) - matches getFinancialSummary
                        $costQuery = $db->prepare("
                            SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0) as sales_cost
                            FROM pos_sale_items psi
                            INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                            LEFT JOIN {$productsTable} p ON (
                                (psi.item_id = p.id AND p.company_id = ps.company_id)
                                OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps.company_id)
                            )
                            WHERE {$costWhereClause}
                            AND p.id IS NOT NULL
                        ");
                        $costQuery->execute($costParams);
                        $costData = $costQuery->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        error_log("Company {$companyId} cost query error: " . $e->getMessage());
                        $costData = ['sales_cost' => 0];
                    }
                    
                    // Get repairs metrics - check which repairs table exists
                    try {
                        $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                        
                        if ($hasRepairsNew) {
                            $repairsQuery = $db->prepare("
                                SELECT 
                                    COUNT(DISTINCT r.id) as total_repairs,
                                    COALESCE(SUM(r.total_cost), 0) as repairs_revenue
                                FROM repairs_new r
                                WHERE {$repairsWhereClause}
                            ");
                        } else {
                            $repairsQuery = $db->prepare("
                                SELECT 
                                    COUNT(DISTINCT r.id) as total_repairs,
                                    COALESCE(SUM(r.total_cost), 0) as repairs_revenue
                                FROM repairs r
                                WHERE {$repairsWhereClause}
                            ");
                        }
                        $repairsQuery->execute($repairsParams);
                        $repairsData = $repairsQuery->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        error_log("Company {$companyId} repairs query error: " . $e->getMessage());
                        $repairsData = ['total_repairs' => 0, 'repairs_revenue' => 0];
                    }
                    
                    // Get swaps count
                    try {
                        $swapsQuery = $db->prepare("
                            SELECT COUNT(DISTINCT s.id) as total_swaps
                            FROM swaps s
                            WHERE {$swapsWhereClause}
                        ");
                        $swapsQuery->execute($swapsParams);
                        $swapsData = $swapsQuery->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        error_log("Company {$companyId} swaps query error: " . $e->getMessage());
                        $swapsData = ['total_swaps' => 0];
                    }
                    
                    // Get swap profit (realized gains only - when customer item is resold and profit is finalized)
                    // NOTE: This is calculated separately but getFinancialSummary will recalculate it with proper date filtering
                    // We keep this for the fallback calculation only
                    $swapProfit = 0;
                    // Don't calculate swap profit here - let getFinancialSummary handle it with proper date filtering
                    
                    // Get users count (no date filter)
                    try {
                        $usersQuery = $db->prepare("
                            SELECT COUNT(DISTINCT u.id) as total_users
                            FROM users u
                            WHERE u.company_id = :company_id
                        ");
                        $usersQuery->execute(['company_id' => $companyId]);
                        $usersData = $usersQuery->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        error_log("Company {$companyId} users query error: " . $e->getMessage());
                        $usersData = ['total_users' => 0];
                    }
                    
                    $companies[] = [
                        'id' => $company['id'],
                        'name' => $company['name'],
                        'email' => $company['email'],
                        'phone_number' => $company['phone_number'],
                        'company_created_at' => $company['created_at'],
                        'total_sales' => (int)($salesData['total_sales'] ?? 0),
                        'sales_revenue' => (float)($salesData['sales_revenue'] ?? 0),
                        'sales_cost' => (float)($costData['sales_cost'] ?? 0),
                        'repairs_revenue' => (float)($repairsData['repairs_revenue'] ?? 0),
                        'total_repairs' => (int)($repairsData['total_repairs'] ?? 0),
                        'total_swaps' => (int)($swapsData['total_swaps'] ?? 0),
                        'total_users' => (int)($usersData['total_users'] ?? 0),
                        'swap_profit' => (float)$swapProfit
                    ];
                } catch (\Exception $e) {
                    error_log("Error processing company {$company['id']}: " . $e->getMessage());
                    // Continue with next company even if one fails
                    continue;
                }
            }
            
            // Sort by total revenue
            usort($companies, function($a, $b) {
                $revenueA = ($a['sales_revenue'] ?? 0) + ($a['repairs_revenue'] ?? 0);
                $revenueB = ($b['sales_revenue'] ?? 0) + ($b['repairs_revenue'] ?? 0);
                return $revenueB <=> $revenueA;
            });
            
            // Use DashboardController's getFinancialSummary to get EXACT same values as company dashboard
            // This ensures admin analytics shows exactly what the company sees
            $companyPerformance = [];
            $dashboardController = new \App\Controllers\DashboardController();
            
            foreach ($companies as $company) {
                $companyId = $company['id'];
                
                // Use the provided date range, or all-time if no dates provided
                // If no dates, use a very early date to present date to get all-time data
                $financialDateFrom = $dateFrom ?? '1970-01-01';
                $financialDateTo = $dateTo ?? date('Y-m-d');
                
                try {
                    // Use reflection to call the private getFinancialSummary method
                    $reflection = new \ReflectionClass($dashboardController);
                    $method = $reflection->getMethod('getFinancialSummary');
                    $method->setAccessible(true);
                    
                    // Get swap stats first (needed for getFinancialSummary)
                    $swapStats = null;
                    try {
                        $swapStatsMethod = $reflection->getMethod('getSwapStatistics');
                        $swapStatsMethod->setAccessible(true);
                        $swapStats = $swapStatsMethod->invoke($dashboardController, $companyId, $financialDateFrom, $financialDateTo);
                    } catch (\Exception $e) {
                        error_log("Error getting swap stats for company {$companyId}: " . $e->getMessage());
                    }
                    
                    // Get financial summary - this is what the company dashboard uses
                    // This will calculate everything with proper date filtering
                    $financialSummary = $method->invoke($dashboardController, $companyId, $financialDateFrom, $financialDateTo, $swapStats);
                    
                    // Use the exact values from getFinancialSummary
                    $salesRevenue = (float)($financialSummary['sales_revenue'] ?? 0);
                    $repairsRevenue = (float)($financialSummary['repair_revenue'] ?? 0);
                    $totalRevenue = (float)($financialSummary['total_revenue'] ?? 0);
                    $salesCost = (float)($financialSummary['sales_cost'] ?? 0);
                    $totalCost = (float)($financialSummary['total_cost'] ?? 0);
                    
                    // Profit should match manager dashboard: Sales Profit + Swap Profit (NOT including repairer profit)
                    // This matches what the manager dashboard shows: sales_profit + swap_profit
                    $salesProfit = (float)($financialSummary['sales_profit'] ?? 0);
                    $swapProfit = (float)($financialSummary['swap_profit'] ?? 0);
                    $profit = $salesProfit + $swapProfit;
                    
                    // Ensure profit is 0 if there's no revenue in the date range
                    // This prevents showing profit when there are no sales/transactions in the selected date range
                    if ($totalRevenue == 0) {
                        $profit = 0;
                        $salesProfit = 0;
                        $swapProfit = 0;
                        $salesRevenue = 0;
                        $repairsRevenue = 0;
                        $salesCost = 0;
                        $totalCost = 0;
                    }
                    
                    // Calculate profit margin based on the profit (without repairer profit)
                    $profitMargin = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;
                    
                } catch (\Exception $e) {
                    error_log("Error getting financial summary for company {$companyId}: " . $e->getMessage());
                    // Fallback to simple calculation if reflection fails
                    $salesRevenue = (float)($company['sales_revenue'] ?? 0);
                    $salesCost = (float)($company['sales_cost'] ?? 0);
                    $repairsRevenue = (float)($company['repairs_revenue'] ?? 0);
                    $swapProfit = 0; // Don't use swap profit from separate calculation - it may not respect date range
                    
                    $totalRevenue = $salesRevenue + $repairsRevenue;
                    $totalCost = $salesCost;
                    $salesProfit = $salesRevenue - $salesCost;
                    if ($salesProfit < 0) $salesProfit = 0;
                    $profit = $salesProfit + $swapProfit;
                    
                    // Ensure profit is 0 if there's no revenue in the date range
                    if ($totalRevenue == 0) {
                        $profit = 0;
                        $salesProfit = 0;
                    }
                    
                    if ($profit < 0) $profit = 0;
                    $profitMargin = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;
                }
                
                $companyPerformance[] = [
                    'id' => (int)$company['id'],
                    'name' => $company['name'] ?? 'Unknown',
                    'email' => $company['email'] ?? '',
                    'phone_number' => $company['phone_number'] ?? '',
                    'created_at' => $company['company_created_at'] ?? '',
                    'metrics' => [
                        'total_sales' => (int)($company['total_sales'] ?? 0),
                        'total_repairs' => (int)($company['total_repairs'] ?? 0),
                        'total_swaps' => (int)($company['total_swaps'] ?? 0),
                        'total_users' => (int)($company['total_users'] ?? 0),
                        'sales_revenue' => round($salesRevenue, 2),
                        'repairs_revenue' => round($repairsRevenue, 2),
                        'total_revenue' => round($totalRevenue, 2),
                        'sales_cost' => round($salesCost, 2),
                        'total_cost' => round($totalCost, 2),
                        'profit' => round($profit, 2),
                        'profit_margin' => round($profitMargin, 2)
                    ]
                ];
            }
            
            // Calculate summary totals
            $totalRevenue = 0;
            $totalProfit = 0;
            foreach ($companyPerformance as $company) {
                $totalRevenue += (float)($company['metrics']['total_revenue'] ?? 0);
                $totalProfit += (float)($company['metrics']['profit'] ?? 0);
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'companies' => $companyPerformance,
                'summary' => [
                    'total_companies' => count($companyPerformance),
                    'total_revenue' => round($totalRevenue, 2),
                    'total_profit' => round($totalProfit, 2)
                ],
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Company Performance Error: " . $e->getMessage());
            error_log("Company Performance Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch company performance data',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Company Performance Fatal Error: " . $e->getMessage());
            error_log("Company Performance Fatal Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    
    /**
     * Get company audit records with date range filters
     * GET /api/admin/company-audit
     */
    public function companyAudit() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Check authentication
            $authenticated = false;
            
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Try session-based auth first (for web dashboard)
            $userData = $_SESSION['user'] ?? null;
            
            if ($userData && is_array($userData) && isset($userData['role']) && $userData['role'] === 'system_admin') {
                $authenticated = true;
            } else {
                // Try JWT auth if session not available (for API calls)
                // Check if Authorization header exists
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
                
                if ($authHeader) {
                    try {
                        if (class_exists('\App\Middleware\AuthMiddleware') && method_exists('\App\Middleware\AuthMiddleware', 'handle')) {
                            $payload = AuthMiddleware::handle(['system_admin']);
                            $authenticated = true;
                        } else {
                            $authenticated = false;
                        }
                    } catch (\Exception $e) {
                        // JWT auth failed
                        error_log("Company Audit Auth Error: " . $e->getMessage());
                        $authenticated = false;
                    } catch (\Error $e) {
                        // Fatal error in auth
                        error_log("Company Audit Auth Fatal Error: " . $e->getMessage());
                        $authenticated = false;
                    }
                } else {
                    // No auth header - check if we have a valid session token
                    if (isset($_SESSION['token'])) {
                        try {
                            $auth = new \App\Services\AuthService();
                            $payload = $auth->validateToken($_SESSION['token']);
                            if ($payload && $payload->role === 'system_admin') {
                                // Update session user data from validated token
                                $_SESSION['user'] = [
                                    'id' => $payload->sub,
                                    'username' => $payload->username,
                                    'role' => $payload->role,
                                    'company_id' => $payload->company_id ?? null
                                ];
                                $authenticated = true;
                            }
                        } catch (\Exception $e) {
                            // Token validation failed
                            error_log("Company Audit Session Token Error: " . $e->getMessage());
                            $authenticated = false;
                        }
                    }
                }
            }
            
            if (!$authenticated) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'System administrator access required. Please log in as system administrator.'
                ]);
                return;
            }
            
            // Ensure Database class is loaded
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            
            $db = \Database::getInstance()->getConnection();
            
            if (!$db) {
                throw new \Exception("Database connection is null");
            }
            
            // Get filters
            $companyId = $_GET['company_id'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $period = $_GET['period'] ?? 'all'; // daily, weekly, monthly, yearly, all, custom
            $recordType = $_GET['record_type'] ?? 'all'; // sales, repairs, swaps, all
            
            // Calculate date range based on period
            if ($period === 'daily') {
                $dateFrom = date('Y-m-d');
                $dateTo = date('Y-m-d');
            } elseif ($period === 'weekly') {
                $dateFrom = date('Y-m-d', strtotime('monday this week'));
                $dateTo = date('Y-m-d', strtotime('sunday this week'));
            } elseif ($period === 'monthly') {
                $dateFrom = date('Y-m-01');
                $dateTo = date('Y-m-t');
            } elseif ($period === 'yearly') {
                $dateFrom = date('Y-01-01');
                $dateTo = date('Y-12-31');
            } elseif ($period === 'custom' && $dateFrom && $dateTo) {
                // Use provided custom dates
            } else {
                // 'all' - no date filter
                $dateFrom = null;
                $dateTo = null;
            }
            
            // Build filters for each query type
            $salesParams = [];
            $repairsParams = [];
            $swapsParams = [];
            
            // Build WHERE clauses for each query
            $salesWhere = [];
            $repairsWhere = [];
            $swapsWhere = [];
            
            if ($companyId) {
                $salesWhere[] = "ps.company_id = :company_id";
                $repairsWhere[] = "r.company_id = :company_id";
                $swapsWhere[] = "s.company_id = :company_id";
                $salesParams['company_id'] = (int)$companyId;
                $repairsParams['company_id'] = (int)$companyId;
                $swapsParams['company_id'] = (int)$companyId;
            }
            
            // Build date filters
            if ($dateFrom && $dateTo) {
                $salesWhere[] = "DATE(ps.created_at) BETWEEN :date_from AND :date_to";
                $repairsWhere[] = "DATE(r.created_at) BETWEEN :date_from AND :date_to";
                $swapsWhere[] = "DATE(s.created_at) BETWEEN :date_from AND :date_to";
                $salesParams['date_from'] = $dateFrom;
                $salesParams['date_to'] = $dateTo;
                $repairsParams['date_from'] = $dateFrom;
                $repairsParams['date_to'] = $dateTo;
                $swapsParams['date_from'] = $dateFrom;
                $swapsParams['date_to'] = $dateTo;
            } elseif ($dateFrom) {
                $salesWhere[] = "DATE(ps.created_at) >= :date_from";
                $repairsWhere[] = "DATE(r.created_at) >= :date_from";
                $swapsWhere[] = "DATE(s.created_at) >= :date_from";
                $salesParams['date_from'] = $dateFrom;
                $repairsParams['date_from'] = $dateFrom;
                $swapsParams['date_from'] = $dateFrom;
            } elseif ($dateTo) {
                $salesWhere[] = "DATE(ps.created_at) <= :date_to";
                $repairsWhere[] = "DATE(r.created_at) <= :date_to";
                $swapsWhere[] = "DATE(s.created_at) <= :date_to";
                $salesParams['date_to'] = $dateTo;
                $repairsParams['date_to'] = $dateTo;
                $swapsParams['date_to'] = $dateTo;
            }
            
            $salesWhereClause = !empty($salesWhere) ? 'WHERE ' . implode(' AND ', $salesWhere) : '';
            $repairsWhereClause = !empty($repairsWhere) ? 'WHERE ' . implode(' AND ', $repairsWhere) : '';
            $swapsWhereClause = !empty($swapsWhere) ? 'WHERE ' . implode(' AND ', $swapsWhere) : '';
            
            // Get sales records - EXCLUDE swap transactions
            $salesRecords = [];
            if ($recordType === 'all' || $recordType === 'sales') {
                try {
                    // Add swap_id IS NULL to exclude swap transactions
                    $swapFilter = " AND ps.swap_id IS NULL";
                    $salesWhereClauseWithSwap = $salesWhereClause ? $salesWhereClause . $swapFilter : 'WHERE ps.swap_id IS NULL';
                    
                    $salesQuery = $db->prepare("
                        SELECT 
                            ps.id,
                            ps.company_id,
                            c.name as company_name,
                            'sale' as record_type,
                            ps.final_amount as amount,
                            ps.created_at as record_date,
                            ps.payment_status,
                            ps.customer_id,
                            cust.full_name as customer_name,
                            cust.phone_number as customer_phone,
                            u.full_name as created_by,
                            COUNT(psi.id) as item_count
                        FROM pos_sales ps
                        INNER JOIN companies c ON ps.company_id = c.id
                        LEFT JOIN customers cust ON ps.customer_id = cust.id
                        LEFT JOIN users u ON ps.created_by_user_id = u.id
                        LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                        {$salesWhereClauseWithSwap}
                        GROUP BY ps.id, ps.company_id, c.name, ps.final_amount, ps.created_at, ps.payment_status, ps.customer_id, cust.full_name, cust.phone_number, u.full_name
                        ORDER BY ps.created_at DESC
                        LIMIT 1000
                    ");
                    $salesQuery->execute($salesParams);
                    $salesRecords = $salesQuery->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("Sales audit query error: " . $e->getMessage());
                    $salesRecords = [];
                }
            }
            
            // Get repairs records - check which repairs table exists
            $repairsRecords = [];
            if ($recordType === 'all' || $recordType === 'repairs') {
                try {
                    $checkRepairsNew = $db->query("SHOW TABLES LIKE 'repairs_new'");
                    $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
                    
                    if ($hasRepairsNew) {
                        $repairsQuery = $db->prepare("
                            SELECT 
                                r.id,
                                r.company_id,
                                c.name as company_name,
                                'repair' as record_type,
                                r.total_cost as amount,
                                r.created_at as record_date,
                                r.payment_status,
                                r.customer_name,
                                r.customer_contact,
                                r.issue_description,
                                u.full_name as created_by,
                                r.status as repair_status
                            FROM repairs_new r
                            INNER JOIN companies c ON r.company_id = c.id
                            LEFT JOIN users u ON r.technician_id = u.id
                            {$repairsWhereClause}
                            ORDER BY r.created_at DESC
                            LIMIT 1000
                        ");
                    } else {
                        $repairsQuery = $db->prepare("
                            SELECT 
                                r.id,
                                r.company_id,
                                c.name as company_name,
                                'repair' as record_type,
                                r.total_cost as amount,
                                r.created_at as record_date,
                                r.payment_status,
                                r.customer_name,
                                r.customer_contact,
                                r.issue_description,
                                u.full_name as created_by,
                                r.status as repair_status
                            FROM repairs r
                            INNER JOIN companies c ON r.company_id = c.id
                            LEFT JOIN users u ON r.created_by_user_id = u.id
                            {$repairsWhereClause}
                            ORDER BY r.created_at DESC
                            LIMIT 1000
                        ");
                    }
                    $repairsQuery->execute($repairsParams);
                    $repairsRecords = $repairsQuery->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("Repairs audit query error: " . $e->getMessage());
                    $repairsRecords = [];
                }
            }
            
            // Get swaps records
            $swapsRecords = [];
            if ($recordType === 'all' || $recordType === 'swaps') {
                try {
                    // Use added_cash as the amount for swaps (cash top-up from customer)
                    // Don't include estimated profit - only cash received during swap
                    $swapsQuery = $db->prepare("
                        SELECT 
                            s.id,
                            s.company_id,
                            c.name as company_name,
                            'swap' as record_type,
                            COALESCE(s.added_cash, 0) as amount,
                            s.created_at as record_date,
                            s.status as swap_status,
                            u.full_name as created_by
                        FROM swaps s
                        INNER JOIN companies c ON s.company_id = c.id
                        LEFT JOIN users u ON s.created_by_user_id = u.id
                        {$swapsWhereClause}
                        ORDER BY s.created_at DESC
                        LIMIT 1000
                    ");
                    $swapsQuery->execute($swapsParams);
                    $swapsRecords = $swapsQuery->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("Swaps audit query error: " . $e->getMessage());
                    $swapsRecords = [];
                }
            }
            
            // Combine and format all records
            $allRecords = [];
            
            foreach ($salesRecords as $record) {
                $allRecords[] = [
                    'id' => (int)$record['id'],
                    'company_id' => (int)$record['company_id'],
                    'company_name' => $record['company_name'] ?? 'Unknown',
                    'record_type' => 'sale',
                    'amount' => round((float)($record['amount'] ?? 0), 2),
                    'record_date' => $record['record_date'] ?? '',
                    'status' => $record['payment_status'] ?? 'unknown',
                    'payment_status' => $record['payment_status'] ?? 'unknown',
                    'customer_id' => $record['customer_id'] ?? null,
                    'customer_name' => $record['customer_name'] ?? 'Walk-in Customer',
                    'customer_phone' => $record['customer_phone'] ?? null,
                    'created_by' => $record['created_by'] ?? 'System',
                    'item_count' => (int)($record['item_count'] ?? 0)
                ];
            }
            
            foreach ($repairsRecords as $record) {
                $allRecords[] = [
                    'id' => (int)$record['id'],
                    'company_id' => (int)$record['company_id'],
                    'company_name' => $record['company_name'] ?? 'Unknown',
                    'record_type' => 'repair',
                    'amount' => round((float)($record['amount'] ?? 0), 2),
                    'record_date' => $record['record_date'] ?? '',
                    'status' => $record['repair_status'] ?? 'unknown',
                    'payment_status' => $record['payment_status'] ?? 'unknown',
                    'customer_name' => $record['customer_name'] ?? 'N/A',
                    'customer_contact' => $record['customer_contact'] ?? null,
                    'issue_description' => $record['issue_description'] ?? '',
                    'created_by' => $record['created_by'] ?? 'System',
                    'item_count' => 0
                ];
            }
            
            foreach ($swapsRecords as $record) {
                $allRecords[] = [
                    'id' => (int)$record['id'],
                    'company_id' => (int)$record['company_id'],
                    'company_name' => $record['company_name'] ?? 'Unknown',
                    'record_type' => 'swap',
                    'amount' => round((float)($record['amount'] ?? 0), 2),
                    'record_date' => $record['record_date'] ?? '',
                    'status' => $record['swap_status'] ?? 'unknown',
                    'created_by' => $record['created_by'] ?? 'System',
                    'item_count' => 0
                ];
            }
            
            // Sort by date (newest first)
            usort($allRecords, function($a, $b) {
                return strtotime($b['record_date']) - strtotime($a['record_date']);
            });
            
            // Calculate summary
            $summary = [
                'total_records' => count($allRecords),
                'total_sales' => count($salesRecords),
                'total_repairs' => count($repairsRecords),
                'total_swaps' => count($swapsRecords),
                'total_revenue' => round(array_sum(array_column($allRecords, 'amount')), 2),
                'sales_revenue' => round(array_sum(array_map(function($r) { return $r['record_type'] === 'sale' ? $r['amount'] : 0; }, $allRecords)), 2),
                'repairs_revenue' => round(array_sum(array_map(function($r) { return $r['record_type'] === 'repair' ? $r['amount'] : 0; }, $allRecords)), 2),
                'swaps_revenue' => round(array_sum(array_map(function($r) { return $r['record_type'] === 'swap' ? $r['amount'] : 0; }, $allRecords)), 2)
            ];
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'records' => $allRecords,
                'summary' => $summary,
                'filters' => [
                    'company_id' => $companyId,
                    'period' => $period,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'record_type' => $recordType
                ]
            ]);
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Company Audit Error: " . $e->getMessage());
            error_log("Company Audit Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch audit records',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Error $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Company Audit Fatal Error: " . $e->getMessage());
            error_log("Company Audit Fatal Stack: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}


