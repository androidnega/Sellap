<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

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

            // Get aggregated sales volume across all companies
            $salesVolumeQuery = $db->query("SELECT COALESCE(SUM(final_amount), 0) as total FROM pos_sales");
            $salesVolume = $salesVolumeQuery->fetch()['total'] ?? 0;

            // Get total repairs revenue across all companies
            $repairsVolumeQuery = $db->query("SELECT COALESCE(SUM(total_cost), 0) as total FROM repairs WHERE payment_status = 'PAID'");
            $repairsVolume = $repairsVolumeQuery->fetch()['total'] ?? 0;

            // Get total transactions count
            $totalTransactions = $db->query("
                SELECT (
                    (SELECT COUNT(*) FROM pos_sales) + 
                    (SELECT COUNT(*) FROM repairs) + 
                    (SELECT COUNT(*) FROM swaps)
                ) as total
            ")->fetch()['total'] ?? 0;

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
        $payload = AuthMiddleware::handle(['system_admin']);
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
            $companies = $query->fetchAll();

            header('Content-Type: application/json');
            echo json_encode($companies);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to fetch companies', 'message' => $e->getMessage()]);
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
                }
            } catch (\Exception $e) {
                // Table might not exist, that's okay - use 0
                error_log("API requests check: " . $e->getMessage());
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
                    $smsCountTotal = $smsTotalResult ? (int)$smsTotalResult->fetchColumn() : 0;
                }
                
                // Fallback to notification_logs if sms_logs doesn't exist
                $tableCheck = $db->query("SHOW TABLES LIKE 'notification_logs'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    // Count all SMS notifications in the table (all entries are SMS-related)
                    // This includes both successful and failed attempts
                    $smsResult = $db->query("
                        SELECT COUNT(*) FROM notification_logs
                    ");
                    $smsCountTotal = $smsResult ? (int)$smsResult->fetchColumn() : 0;
                }
            } catch (\Exception $e) {
                // Table might not exist, that's okay
                error_log("SMS count check: " . $e->getMessage());
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
                $tableCheck = $db->query("SHOW TABLES LIKE 'notification_logs'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    $smsTrendResult = $db->query("
                        SELECT 
                            DATE(created_at) as date,
                            COUNT(*) as count
                        FROM notification_logs
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC
                    ");
                    $smsTrendData = $smsTrendResult ? $smsTrendResult->fetchAll(\PDO::FETCH_ASSOC) : [];
                }
            } catch (\Exception $e) {
                error_log("SMS trend query error: " . $e->getMessage());
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
        $payload = AuthMiddleware::handle(['system_admin']);
        $db = \Database::getInstance()->getConnection();

        try {
            // Get sales analytics
            $salesQuery = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(final_amount), 0) as revenue,
                    COALESCE(AVG(final_amount), 0) as avg_order_value,
                    COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as monthly
                FROM pos_sales
            ");
            $salesData = $salesQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Get repairs analytics
            $repairsQuery = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN payment_status = 'PAID' THEN total_cost ELSE 0 END), 0) as revenue
                FROM repairs
            ");
            $repairsData = $repairsQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Get swaps analytics
            $swapsQuery = $db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status IN ('PENDING', 'APPROVED', 'IN_PROGRESS') THEN 1 END) as active
                FROM swaps
            ");
            $swapsData = $swapsQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Get revenue timeline (last 30 days)
            $revenueTimelineQuery = $db->query("
                SELECT 
                    DATE(created_at) as date,
                    COALESCE(SUM(final_amount), 0) as revenue
                FROM pos_sales
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $revenueTimeline = $revenueTimelineQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get transaction types count
            $transactionTypesQuery = $db->query("
                SELECT 
                    (SELECT COUNT(*) FROM pos_sales) as sales,
                    (SELECT COUNT(*) FROM repairs) as repairs,
                    (SELECT COUNT(*) FROM swaps) as swaps
            ");
            $transactionTypesData = $transactionTypesQuery->fetch(\PDO::FETCH_ASSOC);
            
            // Get user growth (last 90 days, grouped by week)
            $userGrowthQuery = $db->query("
                SELECT 
                    YEAR(created_at) as year,
                    WEEK(created_at) as week,
                    COUNT(*) as count
                FROM users
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY YEAR(created_at), WEEK(created_at)
                ORDER BY year ASC, week ASC
            ");
            $userGrowthData = $userGrowthQuery->fetchAll(\PDO::FETCH_ASSOC);
            
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
                    'active' => (int)($swapsData['active'] ?? 0)
                ],
                'revenue_timeline' => [
                    'labels' => array_map(function($item) {
                        return date('M d', strtotime($item['date']));
                    }, $revenueTimeline),
                    'values' => array_map(function($item) {
                        return (float)$item['revenue'];
                    }, $revenueTimeline)
                ],
                'transaction_types' => [
                    'sales' => (int)($transactionTypesData['sales'] ?? 0),
                    'repairs' => (int)($transactionTypesData['repairs'] ?? 0),
                    'swaps' => (int)($transactionTypesData['swaps'] ?? 0)
                ],
                'user_growth' => [
                    'labels' => array_map(function($item) {
                        $date = new \DateTime();
                        $date->setISODate($item['year'], $item['week']);
                        return $date->format('M d');
                    }, $userGrowthData),
                    'values' => array_map(function($item) {
                        return (int)$item['count'];
                    }, $userGrowthData)
                ]
            ];

            header('Content-Type: application/json');
            echo json_encode($analytics);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch analytics data',
                'message' => $e->getMessage()
            ]);
        }
    }
}


