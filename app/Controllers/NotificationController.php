<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

class NotificationController {
    private $db;
    
    public function __construct() {
        try {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            $this->db = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log("NotificationController: Database connection failed: " . $e->getMessage());
            error_log("NotificationController: File: " . $e->getFile() . " Line: " . $e->getLine());
            $this->db = null;
        } catch (\Error $e) {
            error_log("NotificationController: Database connection error: " . $e->getMessage());
            error_log("NotificationController: File: " . $e->getFile() . " Line: " . $e->getLine());
            $this->db = null;
        }
    }
    
    /**
     * Get notifications for the current user
     * Supports both Bearer token and session-based authentication
     */
    public function getNotifications() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Clean any existing output buffers to prevent HTML errors
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Start output buffering to catch any unwanted output
        ob_start();
        
        // Always set JSON header first to prevent HTML errors
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $userId = null;
            $userRole = null;
            $companyId = null;
            
            // Try Authorization header first (Bearer token)
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    $userId = $payload->sub;
                    $userRole = $payload->role;
                    $companyId = $payload->company_id ?? null;
                } catch (\Exception $e) {
                    // Token validation failed, try session fallback
                    error_log("Token validation failed: " . $e->getMessage());
                }
            }
            
            // Fallback to session-based authentication
            if ($userId === null) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $userData = $_SESSION['user'] ?? null;
                if ($userData && is_array($userData)) {
                    $userId = $userData['id'] ?? null;
                    $userRole = $userData['role'] ?? null;
                    $companyId = $userData['company_id'] ?? null;
                }
                
                // Also check for session token if user data not available
                if ($userId === null && isset($_SESSION['token'])) {
                    try {
                        $auth = new AuthService();
                        $payload = $auth->validateToken($_SESSION['token']);
                        $userId = $payload->sub;
                        $userRole = $payload->role;
                        $companyId = $payload->company_id ?? null;
                        
                        // Update session user data from validated token
                        $_SESSION['user'] = [
                            'id' => $payload->sub,
                            'username' => $payload->username,
                            'role' => $payload->role,
                            'company_id' => $payload->company_id ?? null
                        ];
                    } catch (\Exception $e) {
                        // Token validation failed
                        error_log("Session token validation failed: " . $e->getMessage());
                    }
                }
            }
            
            // If still no user, return error
            if (!$userId) {
                // Clean output buffer before sending error
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or expired token',
                    'message' => 'Invalid or expired token: Expired token'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // Check if database connection is available
            if ($this->db === null) {
                // Try to reconnect
                try {
                    if (!class_exists('Database')) {
                        require_once __DIR__ . '/../../config/database.php';
                    }
                    $this->db = \Database::getInstance()->getConnection();
                } catch (\Throwable $e) {
                    error_log("NotificationController: Failed to reconnect to database: " . $e->getMessage());
                    // Return empty notifications instead of error to prevent breaking the UI
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'notifications' => [],
                        'unread_count' => 0
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
            }
            
            $notifications = [];
            
            // Get dismissed notifications ONCE at the beginning (for all notification types)
            $dismissedNotifications = $this->getAllDismissedNotifications($userId, $companyId, $userRole);
            
            // System Admin: See SMS purchases, SMS sent, and user requests (NOT company inventory)
            if ($userRole === 'system_admin') {
                try {
                    $systemNotifications = $this->getSystemNotifications();
                    $notifications = array_merge($notifications, $systemNotifications);
                } catch (\Exception $e) {
                    error_log("Error fetching system notifications: " . $e->getMessage());
                }
            }
            
            // Manager, Admin, Salesperson, Repairer: See company-specific notifications
            if ($companyId !== null && in_array($userRole, ['manager', 'admin', 'salesperson', 'repairer'])) {
                // Low stock notifications - visible to managers, admins, salespersons, and repairers
                try {
                    $lowStockNotifications = $this->getLowStockNotifications($companyId, $userRole, $userId);
                    $notifications = array_merge($notifications, $lowStockNotifications);
                } catch (\Exception $e) {
                    error_log("Error fetching low stock notifications: " . $e->getMessage());
                }
                
                // Out of stock notifications - visible to managers, admins, salespersons, and repairers
                try {
                    $outOfStockNotifications = $this->getOutOfStockNotifications($companyId, $userRole, $userId);
                    $notifications = array_merge($notifications, $outOfStockNotifications);
                } catch (\Exception $e) {
                    error_log("Error fetching out of stock notifications: " . $e->getMessage());
                }
            }
            
            // Also get database notifications for the user (if any stored notifications exist)
            try {
                if ($this->db !== null) {
                    $dbNotifications = $this->db->prepare("
                        SELECT * FROM notifications 
                        WHERE user_id = ? 
                        AND (company_id = ? OR company_id IS NULL)
                        ORDER BY created_at DESC
                        LIMIT 50
                    ");
                    if ($dbNotifications && $dbNotifications->execute([$userId, $companyId])) {
                        $dbNotifs = $dbNotifications->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($dbNotifs as $dbNotif) {
                            $notifId = $dbNotif['id'] ?? 'notif_' . uniqid();
                            
                            // Skip if this notification was dismissed
                            if ($this->isNotificationDismissed($notifId, $dismissedNotifications)) {
                                continue;
                            }
                            
                            // Filter based on role - salesperson and repairer don't see SMS purchase notifications
                            if ($userRole === 'salesperson' || $userRole === 'repairer') {
                                if (in_array($dbNotif['type'], ['sms_purchase', 'sms_sent'])) {
                                    continue; // Skip SMS-related notifications for salesperson/repairer
                                }
                            }
                            
                            // Filter out read repair notifications (unlike out of stock which persists)
                            // Only show unread repair notifications
                            $isRead = ($dbNotif['status'] ?? 'unread') === 'read';
                            $isRepairNotification = ($dbNotif['type'] ?? '') === 'repair';
                            
                            if ($isRepairNotification && $isRead) {
                                continue; // Skip read repair notifications
                            }
                            
                            $notifications[] = [
                                'id' => $notifId,
                                'type' => $dbNotif['type'] ?? 'system',
                                'title' => $this->getNotificationTitle($dbNotif['type'] ?? 'system'),
                                'message' => $dbNotif['message'] ?? '',
                                'data' => $dbNotif['data'] ? json_decode($dbNotif['data'], true) : [],
                                'created_at' => $dbNotif['created_at'] ?? date('Y-m-d H:i:s'),
                                'read' => $isRead,
                                'priority' => 'medium'
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching database notifications: " . $e->getMessage());
            }
            
            // Final filter: Remove any notifications that are in dismissed list (double-check)
            // This ensures dismissed notifications don't reappear even if they were recreated
            if (!empty($dismissedNotifications)) {
                $notifications = array_filter($notifications, function($notif) use ($dismissedNotifications) {
                    $notifId = $notif['id'] ?? '';
                    return !$this->isNotificationDismissed($notifId, $dismissedNotifications);
                });
                // Re-index array after filtering
                $notifications = array_values($notifications);
            }
            
            // Sort by created_at desc (only if we have notifications)
            if (!empty($notifications)) {
                try {
                    usort($notifications, function($a, $b) {
                        $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                        $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                        return $timeB - $timeA;
                    });
                } catch (\Exception $e) {
                    error_log("Error sorting notifications: " . $e->getMessage());
                    // Continue without sorting if there's an error
                }
            }
            
            // Get unread count
            $unreadCount = count(array_filter($notifications, function($n) {
                return !$n['read'];
            }));
            
            // Clean output buffer and send JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Throwable $e) {
            // Log full error details
            error_log("Notification Error: " . $e->getMessage());
            error_log("Notification Error File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("Notification Error Trace: " . $e->getTraceAsString());
            
            // Clean output buffer before sending error
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch notifications',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    /**
     * Get dismissed notifications for a company (from database or session)
     * Returns array of notification IDs that have been dismissed
     */
    private function getDismissedNotifications($companyId) {
        $dismissed = [];
        
        // Try to get from database first (company-wide)
        if ($this->db !== null && $companyId !== null) {
            try {
                $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    $stmt = $this->db->prepare("
                        SELECT notification_id 
                        FROM dismissed_notifications 
                        WHERE company_id = ?
                    ");
                    $stmt->execute([$companyId]);
                    $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if ($results) {
                        $dismissed = $results;
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching dismissed notifications from database: " . $e->getMessage());
            }
        }
        
        // Fallback to session if database is empty or unavailable
        if (empty($dismissed)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['dismissed_notifications']) && is_array($_SESSION['dismissed_notifications'])) {
                $dismissed = $_SESSION['dismissed_notifications'];
            }
        }
        
        return $dismissed;
    }
    
    /**
     * Check if a notification is dismissed
     * Comprehensive check that handles multiple ID formats
     */
    private function isNotificationDismissed($notificationId, $dismissedList) {
        if (empty($dismissedList) || empty($notificationId)) {
            return false;
        }
        
        // Check exact match
        if (in_array($notificationId, $dismissedList, true)) {
            error_log("NotificationController: Found exact match for dismissed notification: {$notificationId}");
            return true;
        }
        
        // Check string conversion
        if (in_array((string)$notificationId, $dismissedList, true)) {
            error_log("NotificationController: Found string match for dismissed notification: {$notificationId}");
            return true;
        }
        
        // Check with 'notif_' prefix
        if (in_array('notif_' . $notificationId, $dismissedList, true)) {
            error_log("NotificationController: Found 'notif_' prefix match for dismissed notification: {$notificationId}");
            return true;
        }
        
        // Check without 'notif_' prefix
        if (strpos($notificationId, 'notif_') === 0) {
            $idWithoutPrefix = str_replace('notif_', '', $notificationId);
            if (in_array($idWithoutPrefix, $dismissedList, true) || 
                in_array((string)$idWithoutPrefix, $dismissedList, true)) {
                error_log("NotificationController: Found match without 'notif_' prefix for dismissed notification: {$notificationId}");
                return true;
            }
        }
        
        // Check numeric ID variations
        if (is_numeric($notificationId)) {
            $numericId = (int)$notificationId;
            if (in_array($numericId, $dismissedList, true) || 
                in_array((string)$numericId, $dismissedList, true)) {
                error_log("NotificationController: Found numeric match for dismissed notification: {$notificationId}");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get ALL dismissed notifications for a user (company-wide + user-specific)
     * This includes both company-wide dismissed notifications and user-specific ones
     */
    private function getAllDismissedNotifications($userId, $companyId, $userRole) {
        $dismissed = [];
        
        if ($this->db === null) {
            // Fallback to session if database unavailable
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['dismissed_notifications']) && is_array($_SESSION['dismissed_notifications'])) {
                $dismissed = $_SESSION['dismissed_notifications'];
            }
            return array_unique($dismissed);
        }
        
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                // Migrate table if it has old unique key (company_id, notification_id) to new one (company_id, notification_id, dismissed_by)
                // This ensures the table structure supports user-specific dismissals
                try {
                    $indexCheck = $this->db->query("SHOW INDEX FROM dismissed_notifications WHERE Key_name = 'uk_company_notification'");
                    if ($indexCheck && $indexCheck->rowCount() > 0) {
                        // Old unique key exists, drop it and add new one
                        try {
                            $this->db->exec("ALTER TABLE dismissed_notifications DROP INDEX uk_company_notification");
                        } catch (\Exception $e) {
                            // Index might already be dropped, continue
                        }
                        try {
                            $this->db->exec("ALTER TABLE dismissed_notifications ADD UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by)");
                        } catch (\Exception $e) {
                            // Unique key might already exist, continue
                        }
                        try {
                            $this->db->exec("ALTER TABLE dismissed_notifications ADD INDEX idx_dismissed_by (dismissed_by)");
                        } catch (\Exception $e) {
                            // Index might already exist, continue
                        }
                    }
                } catch (\Exception $e) {
                    // Migration might have already been done, continue
                    error_log("Note: Could not migrate unique key (might already be migrated): " . $e->getMessage());
                }
                
                // For salespersons, repairers, and managers: Get user-specific dismissals
                // For system_admin: Get admin-specific dismissals
                if (in_array($userRole, ['salesperson', 'repairer', 'manager', 'admin'])) {
                    // Get notifications dismissed by this specific user for their company
                    if ($companyId !== null && $userId !== null) {
                        $stmt = $this->db->prepare("
                            SELECT DISTINCT notification_id 
                            FROM dismissed_notifications 
                            WHERE dismissed_by = ? AND company_id = ?
                        ");
                        $stmt->execute([$userId, $companyId]);
                        $userDismissed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                        if ($userDismissed) {
                            $dismissed = array_merge($dismissed, $userDismissed);
                            // Log for debugging
                            error_log("NotificationController: Retrieved " . count($userDismissed) . " dismissed notifications for user: {$userId}, company: {$companyId}. Dismissed IDs: " . implode(', ', $userDismissed));
                        }
                    }
                } elseif ($userRole === 'system_admin') {
                    // For system_admin: Get admin-specific dismissals (including NULL company_id)
                    if ($userId !== null) {
                        $stmt = $this->db->prepare("
                            SELECT DISTINCT notification_id 
                            FROM dismissed_notifications 
                            WHERE dismissed_by = ? AND (company_id IS NULL OR notification_id LIKE 'sms_purchase_%')
                        ");
                        $stmt->execute([$userId]);
                        $adminDismissed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                        if ($adminDismissed) {
                            $dismissed = array_merge($dismissed, $adminDismissed);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error fetching dismissed notifications: " . $e->getMessage());
            // Fallback to session if database fails
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['dismissed_notifications']) && is_array($_SESSION['dismissed_notifications'])) {
                $dismissed = array_merge($dismissed, $_SESSION['dismissed_notifications']);
            }
        }
        
        // Remove duplicates and return
        return array_unique($dismissed);
    }
    
    /**
     * Get low stock notifications
     */
    private function getLowStockNotifications($companyId, $userRole, $userId = null) {
        $notifications = [];
        
        // Check if database is available
        if ($this->db === null) {
            return $notifications;
        }
        
        try {
            // Detect table and column names (same logic as DashboardController)
            $tableName = null;
            $quantityCol = 'quantity';
            $hasMinQuantity = false;
            $minQtyDefault = 5;
            
            // Check products table
            try {
                $checkTable = $this->db->query("SHOW TABLES LIKE 'products'");
                if ($checkTable->rowCount() > 0) {
                    $tableName = 'products';
                    $checkCol = $this->db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
                    if ($checkCol->rowCount() > 0) {
                        $quantityCol = 'quantity';
                    } else {
                        $checkCol2 = $this->db->query("SHOW COLUMNS FROM products LIKE 'qty'");
                        if ($checkCol2->rowCount() > 0) {
                            $quantityCol = 'qty';
                        }
                    }
                    $checkMinQty = $this->db->query("SHOW COLUMNS FROM products LIKE 'min_quantity'");
                    $hasMinQuantity = $checkMinQty->rowCount() > 0;
                }
            } catch (\Exception $e) {
                error_log("Error checking products table in notifications: " . $e->getMessage());
            }
            
            if (!$tableName) {
                return $notifications;
            }
            
            // Skip if companyId is null and user is not system_admin
            if ($companyId === null && $userRole !== 'system_admin') {
                return $notifications;
            }
            
            // Build query with dynamic column names
            $minQtyExpr = $hasMinQuantity ? "COALESCE(p.min_quantity, {$minQtyDefault})" : $minQtyDefault;
            
            if ($userRole === 'system_admin') {
                // Admin sees all companies
                $query = $this->db->prepare("
                    SELECT 
                        p.id,
                        p.name,
                        COALESCE(p.{$quantityCol}, 0) as quantity,
                        {$minQtyExpr} as min_stock_level,
                        c.name as company_name,
                        'low_stock' as type,
                        NOW() as created_at,
                        0 as `read`
                    FROM {$tableName} p
                    LEFT JOIN companies c ON p.company_id = c.id
                    WHERE COALESCE(p.{$quantityCol}, 0) <= {$minQtyExpr} AND COALESCE(p.{$quantityCol}, 0) > 0
                    ORDER BY COALESCE(p.{$quantityCol}, 0) ASC
                    LIMIT 20
                ");
                if (!$query) {
                    throw new \Exception("Failed to prepare low stock query for admin");
                }
                $query->execute();
            } else {
                // Other users see only their company
                $query = $this->db->prepare("
                    SELECT 
                        p.id,
                        p.name,
                        COALESCE(p.{$quantityCol}, 0) as quantity,
                        {$minQtyExpr} as min_stock_level,
                        c.name as company_name,
                        'low_stock' as type,
                        NOW() as created_at,
                        0 as `read`
                    FROM {$tableName} p
                    LEFT JOIN companies c ON p.company_id = c.id
                    WHERE p.company_id = ? AND COALESCE(p.{$quantityCol}, 0) <= {$minQtyExpr} AND COALESCE(p.{$quantityCol}, 0) > 0
                    ORDER BY COALESCE(p.{$quantityCol}, 0) ASC
                    LIMIT 20
                ");
                if (!$query) {
                    throw new \Exception("Failed to prepare low stock query for company");
                }
                $query->execute([$companyId]);
            }
            
            $products = $query->fetchAll(\PDO::FETCH_ASSOC);
            if ($products === false) {
                $products = [];
            }
            
            // Get ALL dismissed notifications (company-wide + user-specific)
            $dismissed = $this->getAllDismissedNotifications($userId, $companyId, $userRole);
            
            foreach ($products as $product) {
                $notifId = 'low_stock_' . $product['id'];
                // Skip if this notification was dismissed (check multiple ID formats for compatibility)
                if ($this->isNotificationDismissed($notifId, $dismissed)) {
                    // Log for debugging (can be removed later)
                    error_log("NotificationController: Skipping dismissed low_stock notification: {$notifId} for user: {$userId}");
                    continue;
                }
                
                $notifications[] = [
                    'id' => $notifId,
                    'type' => 'low_stock',
                    'title' => 'Low Stock Alert',
                    'message' => "{$product['name']} is running low ({$product['quantity']} remaining)" . 
                                ($product['company_name'] ? " - {$product['company_name']}" : ''),
                    'data' => [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'quantity' => $product['quantity'],
                        'min_stock_level' => $product['min_stock_level'],
                        'company_name' => $product['company_name']
                    ],
                    'created_at' => $product['created_at'],
                    'read' => false,
                    'priority' => 'high'
                ];
            }
            
        } catch (\Exception $e) {
            error_log("Low stock notification error: " . $e->getMessage());
        }
        
        return $notifications;
    }
    
    /**
     * Get out of stock notifications
     */
    private function getOutOfStockNotifications($companyId, $userRole, $userId = null) {
        $notifications = [];
        
        // Check if database is available
        if ($this->db === null) {
            return $notifications;
        }
        
        try {
            // Detect table and column names (same logic as DashboardController)
            $tableName = null;
            $quantityCol = 'quantity';
            $hasStatus = false;
            
            // Check products table
            try {
                $checkTable = $this->db->query("SHOW TABLES LIKE 'products'");
                if ($checkTable->rowCount() > 0) {
                    $tableName = 'products';
                    $checkCol = $this->db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
                    if ($checkCol->rowCount() > 0) {
                        $quantityCol = 'quantity';
                    } else {
                        $checkCol2 = $this->db->query("SHOW COLUMNS FROM products LIKE 'qty'");
                        if ($checkCol2->rowCount() > 0) {
                            $quantityCol = 'qty';
                        }
                    }
                    $checkStatus = $this->db->query("SHOW COLUMNS FROM products LIKE 'status'");
                    $hasStatus = $checkStatus->rowCount() > 0;
                }
            } catch (\Exception $e) {
                error_log("Error checking products table in notifications: " . $e->getMessage());
            }
            
            if (!$tableName) {
                return $notifications;
            }
            
            // Skip if companyId is null and user is not system_admin
            if ($companyId === null && $userRole !== 'system_admin') {
                return $notifications;
            }
            
            // Build WHERE condition for out of stock
            // Note: Items with quantity = 0 are filtered out from product listings, so exclude them from notifications
            // Only show items with quantity > 0 that have out-of-stock status
            $outOfStockCondition = "FALSE"; // Default to false if no status column
            if ($hasStatus) {
                // Only show items with quantity > 0 that have out-of-stock status
                $outOfStockCondition = "(COALESCE(p.{$quantityCol}, 0) > 0 AND (LOWER(p.status) = 'out_of_stock' OR LOWER(p.status) = 'sold'))";
            }
            
            if ($userRole === 'system_admin') {
                // Admin sees all companies
                $query = $this->db->prepare("
                    SELECT 
                        p.id,
                        p.name,
                        COALESCE(p.{$quantityCol}, 0) as quantity,
                        c.name as company_name,
                        'out_of_stock' as type,
                        NOW() as created_at,
                        0 as `read`
                    FROM {$tableName} p
                    LEFT JOIN companies c ON p.company_id = c.id
                    WHERE {$outOfStockCondition}
                    ORDER BY p.updated_at DESC
                    LIMIT 20
                ");
                if (!$query) {
                    throw new \Exception("Failed to prepare out of stock query for admin");
                }
                $query->execute();
            } else {
                // Other users see only their company
                $query = $this->db->prepare("
                    SELECT 
                        p.id,
                        p.name,
                        COALESCE(p.{$quantityCol}, 0) as quantity,
                        c.name as company_name,
                        'out_of_stock' as type,
                        NOW() as created_at,
                        0 as `read`
                    FROM {$tableName} p
                    LEFT JOIN companies c ON p.company_id = c.id
                    WHERE p.company_id = ? AND {$outOfStockCondition}
                    ORDER BY p.updated_at DESC
                    LIMIT 20
                ");
                if (!$query) {
                    throw new \Exception("Failed to prepare out of stock query for company");
                }
                $query->execute([$companyId]);
            }
            
            $products = $query->fetchAll(\PDO::FETCH_ASSOC);
            if ($products === false) {
                $products = [];
            }
            
            // Get ALL dismissed notifications (company-wide + user-specific)
            $dismissed = $this->getAllDismissedNotifications($userId, $companyId, $userRole);
            
            foreach ($products as $product) {
                $notifId = 'out_of_stock_' . $product['id'];
                // Skip if this notification was dismissed (check multiple ID formats for compatibility)
                if ($this->isNotificationDismissed($notifId, $dismissed)) {
                    // Log for debugging (can be removed later)
                    error_log("NotificationController: Skipping dismissed out_of_stock notification: {$notifId} for user: {$userId}");
                    continue;
                }
                
                $notifications[] = [
                    'id' => $notifId,
                    'type' => 'out_of_stock',
                    'title' => 'Out of Stock Alert',
                    'message' => "{$product['name']} is out of stock" . 
                                ($product['company_name'] ? " - {$product['company_name']}" : ''),
                    'data' => [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'quantity' => $product['quantity'],
                        'company_name' => $product['company_name']
                    ],
                    'created_at' => $product['created_at'],
                    'read' => false,
                    'priority' => 'critical'
                ];
            }
            
        } catch (\Exception $e) {
            error_log("Out of stock notification error: " . $e->getMessage());
        }
        
        return $notifications;
    }
    
    /**
     * Get system notifications for admin
     * Shows SMS credit purchases, SMS messages sent, and user requests
     */
    private function getSystemNotifications() {
        $notifications = [];
        
        // Check if database is available
        if ($this->db === null) {
            return $notifications;
        }
        
        try {
            // Get notifications from the notifications table for admins
            // ONLY administrative notifications: SMS credit purchases (NOT sales/repair/swap SMS)
            $query = $this->db->prepare("
                SELECT n.* 
                FROM notifications n
                INNER JOIN users u ON n.user_id = u.id
                WHERE n.type IN ('sms_purchase', 'user_request', 'system_request')
                AND n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY n.created_at DESC
                LIMIT 50
            ");
            
            if ($query && $query->execute()) {
                $dbNotifications = $query->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($dbNotifications as $dbNotif) {
                    $notifications[] = [
                        'id' => $dbNotif['id'] ?? 'notif_' . uniqid(),
                        'type' => $dbNotif['type'] ?? 'system',
                        'title' => $this->getNotificationTitle($dbNotif['type'] ?? 'system'),
                        'message' => $dbNotif['message'] ?? '',
                        'data' => $dbNotif['data'] ? json_decode($dbNotif['data'], true) : [],
                        'created_at' => $dbNotif['created_at'] ?? date('Y-m-d H:i:s'),
                        'read' => ($dbNotif['status'] ?? 'unread') === 'read' ? true : false,
                        'priority' => 'high'
                    ];
                }
            }
            
            // Get dismissed admin notifications from database (persistent across logouts)
            $dismissedAdminNotifications = [];
            if ($this->db !== null) {
                try {
                    $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
                    if ($tableCheck && $tableCheck->rowCount() > 0) {
                    // Get all dismissed admin notifications (sms_purchase_*)
                    // Check both with NULL company_id and any company_id (for system-wide notifications)
                    $stmt = $this->db->prepare("
                        SELECT DISTINCT notification_id 
                        FROM dismissed_notifications 
                        WHERE notification_id LIKE 'sms_purchase_%'
                    ");
                    $stmt->execute();
                    $adminDismissed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if ($adminDismissed) {
                        $dismissedAdminNotifications = $adminDismissed;
                    }
                    }
                } catch (\Exception $e) {
                    error_log("Error fetching dismissed admin notifications: " . $e->getMessage());
                }
            }
            
            // Fallback to session if database is empty
            if (empty($dismissedAdminNotifications)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                if (isset($_SESSION['dismissed_admin_notifications']) && is_array($_SESSION['dismissed_admin_notifications'])) {
                    $dismissedAdminNotifications = $_SESSION['dismissed_admin_notifications'];
                }
            }
            
            try {
                $checkTable = $this->db->query("SHOW TABLES LIKE 'sms_payments'");
                if ($checkTable && $checkTable->rowCount() > 0) {
                    $smsPurchaseQuery = $this->db->prepare("
                        SELECT sp.*, c.name as company_name, u.username, u.email
                        FROM sms_payments sp
                        LEFT JOIN companies c ON sp.company_id = c.id
                        LEFT JOIN users u ON sp.user_id = u.id
                        WHERE sp.status = 'success'
                        AND sp.completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ORDER BY sp.completed_at DESC
                        LIMIT 20
                    ");
                    
                    if ($smsPurchaseQuery && $smsPurchaseQuery->execute()) {
                        $smsPurchases = $smsPurchaseQuery->fetchAll(\PDO::FETCH_ASSOC);
                        
                        foreach ($smsPurchases as $purchase) {
                            $notificationId = 'sms_purchase_' . $purchase['id'];
                            
                            // Skip if this notification was dismissed
                            if (in_array($notificationId, $dismissedAdminNotifications)) {
                                continue;
                            }
                            
                            // Check if notification already exists to avoid duplicates
                            $existing = array_filter($notifications, function($n) use ($purchase) {
                                return isset($n['data']['payment_id']) && 
                                       $n['data']['payment_id'] == $purchase['payment_id'];
                            });
                            
                            if (empty($existing)) {
                                $companyName = $purchase['company_name'] ?? 'Unknown Company';
                                $username = $purchase['username'] ?? $purchase['email'] ?? 'Unknown User';
                                $credits = $purchase['sms_credits'] ?? 0;
                                $amount = $purchase['amount'] ?? 0;
                                
                                $notifications[] = [
                                    'id' => $notificationId,
                                    'type' => 'sms_purchase',
                                    'title' => 'SMS Credit Purchase',
                                    'message' => "{$username} from {$companyName} purchased {$credits} SMS credits for ₵" . number_format($amount, 2),
                                    'data' => [
                                        'payment_id' => $purchase['payment_id'],
                                        'company_id' => $purchase['company_id'],
                                        'company_name' => $companyName,
                                        'user_id' => $purchase['user_id'],
                                        'username' => $username,
                                        'sms_credits' => $credits,
                                        'amount' => $amount
                                    ],
                                    'created_at' => $purchase['completed_at'] ?? date('Y-m-d H:i:s'),
                                    'read' => false,
                                    'priority' => 'high'
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching SMS purchases for admin notifications: " . $e->getMessage());
            }
            
            // NOTE: Removed SMS sent notifications from sms_logs
            // Admins should NOT see company-level SMS (sales, repairs, swaps)
            // Only administrative notifications (SMS purchases) are shown
            
        } catch (\Exception $e) {
            error_log("System notification error: " . $e->getMessage());
            error_log("System notification error trace: " . $e->getTraceAsString());
        } catch (\Error $e) {
            error_log("System notification fatal error: " . $e->getMessage());
            error_log("System notification error trace: " . $e->getTraceAsString());
        }
        
        return $notifications;
    }
    
    /**
     * Get notification title based on type
     */
    private function getNotificationTitle($type) {
        $titles = [
            'sms_purchase' => 'SMS Credit Purchase',
            'sms_sent' => 'SMS Messages Sent',
            'user_request' => 'User Request',
            'system_request' => 'System Request',
            'system' => 'System Notification'
        ];
        
        return $titles[$type] ?? 'Notification';
    }
    
    /**
     * Mark notification as read
     * Supports both Bearer token and session-based authentication
     */
    /**
     * Delete/clear a notification
     * POST /api/notifications/delete/{id}
     */
    public function deleteNotification($notificationId) {
        // Suppress PHP errors
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $userId = null;
            $companyId = null;
            
            // Try JWT token first
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    $userId = $payload->sub;
                    $companyId = $payload->company_id ?? null;
                } catch (\Exception $e) {
                    // Token validation failed, try session
                }
            }
            
            // Fallback to session
            if ($userId === null) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $userData = $_SESSION['user'] ?? null;
                if ($userData) {
                    $userId = $userData['id'];
                    $companyId = $userData['company_id'] ?? null;
                }
            }
            
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            // All authenticated roles can delete notifications (manager, admin, salesperson, repairer, system_admin)
            // No additional permission check needed - users can only delete their own notifications
            
            // For database notifications, delete them
            if ($this->db !== null) {
                try {
                    // Check if it's a database notification (has numeric ID or starts with 'notif_')
                    if (is_numeric($notificationId) || strpos($notificationId, 'notif_') === 0) {
                        // Try to delete from database (hard delete)
                        $actualId = is_numeric($notificationId) ? $notificationId : str_replace('notif_', '', $notificationId);
                        if (is_numeric($actualId)) {
                            // First, verify the notification exists and belongs to user
                            $checkStmt = $this->db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
                            $checkStmt->execute([$actualId, $userId]);
                            if ($checkStmt->rowCount() > 0) {
                                // Hard delete the notification
                                $deleteStmt = $this->db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                                $deleteStmt->execute([$actualId, $userId]);
                                
                                // Also store dismissal in dismissed_notifications for extra safety (in case notification is recreated)
                                try {
                                    $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
                                    if ($tableCheck && $tableCheck->rowCount() > 0) {
                                        $dismissStmt = $this->db->prepare("
                                            INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                            VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                        ");
                                        // Store with both numeric ID and 'notif_' prefix for compatibility
                                        $dismissStmt->execute([$companyId, $actualId, $userId, $userId]);
                                        $dismissStmt->execute([$companyId, 'notif_' . $actualId, $userId, $userId]);
                                    }
                                } catch (\Exception $e) {
                                    error_log("Error storing dismissed state for database notification: " . $e->getMessage());
                                }
                            }
                        }
                    }
                    // For admin notifications like 'sms_purchase_*', store dismissed state in DATABASE
                    // This ensures persistence across logouts
                    if (strpos($notificationId, 'sms_purchase_') === 0) {
                        // Store dismissed admin notifications in database (persistent)
                        try {
                        // Check if dismissed_notifications table exists
                        $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            // Migrate table if it has old unique key (company_id, notification_id) to new one (company_id, notification_id, dismissed_by)
                            try {
                                $indexCheck = $this->db->query("SHOW INDEX FROM dismissed_notifications WHERE Key_name = 'uk_company_notification'");
                                if ($indexCheck && $indexCheck->rowCount() > 0) {
                                    // Old unique key exists, drop it and add new one
                                    $this->db->exec("ALTER TABLE dismissed_notifications DROP INDEX uk_company_notification");
                                    $this->db->exec("ALTER TABLE dismissed_notifications ADD UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by)");
                                    $this->db->exec("ALTER TABLE dismissed_notifications ADD INDEX idx_dismissed_by (dismissed_by)");
                                }
                            } catch (\Exception $e) {
                                // Index might not exist or might already be migrated, continue
                                error_log("Note: Could not migrate unique key (might already be migrated): " . $e->getMessage());
                            }
                            
                            // For admin notifications, we need to handle system-wide notifications
                            // Since company_id has a foreign key, we'll use a special approach:
                            // Store with a unique identifier that doesn't conflict
                            // First, check if we can use NULL or need to modify the table
                            try {
                                // Try to insert with a system-wide identifier
                                // We'll use notification_id as the unique key, and store system admin notifications
                                // by checking if company_id constraint allows NULL or if we need to modify
                                $stmt = $this->db->prepare("
                                    INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                ");
                                // Get first company ID as fallback (or use a special system company)
                                // For now, use NULL if allowed, otherwise use company_id = 1 (assuming system admin company)
                                $checkNull = $this->db->query("SHOW COLUMNS FROM dismissed_notifications WHERE Field = 'company_id' AND Null = 'YES'");
                                if ($checkNull && $checkNull->rowCount() > 0) {
                                    // Column allows NULL, use it for system-wide notifications
                                    $stmt->execute([null, $notificationId, $userId, $userId]);
                                } else {
                                    // Column doesn't allow NULL, we need to modify the table
                                    // For admin notifications, store with a special system company ID (1 or first company)
                                    $getFirstCompany = $this->db->query("SELECT id FROM companies ORDER BY id ASC LIMIT 1");
                                    $firstCompany = $getFirstCompany->fetch(\PDO::FETCH_ASSOC);
                                    $systemCompanyId = $firstCompany ? (int)$firstCompany['id'] : 1;
                                    $stmt->execute([$systemCompanyId, $notificationId, $userId, $userId]);
                                }
                            } catch (\Exception $e) {
                                // If foreign key constraint fails, try to modify table to allow NULL for company_id
                                error_log("Error inserting dismissed admin notification: " . $e->getMessage());
                                // Try to alter table to allow NULL for company_id (for system-wide notifications)
                                try {
                                    $this->db->exec("ALTER TABLE dismissed_notifications MODIFY company_id BIGINT UNSIGNED NULL");
                                    // Retry with NULL
                                    $stmt = $this->db->prepare("
                                        INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                        VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                    ");
                                    $stmt->execute([null, $notificationId, $userId, $userId]);
                                } catch (\Exception $e2) {
                                    error_log("Error modifying dismissed_notifications table: " . $e2->getMessage());
                                    throw $e; // Re-throw original error
                                }
                            }
                            } else {
                                // Table doesn't exist, create it with NULL allowed for company_id (for system-wide notifications)
                                try {
                                    $this->db->exec("
                                        CREATE TABLE IF NOT EXISTS dismissed_notifications (
                                          id INT AUTO_INCREMENT PRIMARY KEY,
                                          company_id BIGINT UNSIGNED NULL,
                                          notification_id VARCHAR(255) NOT NULL,
                                          dismissed_by INT NOT NULL,
                                          dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                          INDEX idx_company_notification (company_id, notification_id),
                                          INDEX idx_company (company_id),
                                          INDEX idx_notification_id (notification_id),
                                          INDEX idx_dismissed_by (dismissed_by),
                                          UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by),
                                          FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE CASCADE
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                                    ");
                                    // Now insert with NULL for system-wide admin notifications
                                    $stmt = $this->db->prepare("
                                        INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                        VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                    ");
                                    $stmt->execute([null, $notificationId, $userId, $userId]);
                                } catch (\Exception $e) {
                                    error_log("Error creating dismissed_notifications table: " . $e->getMessage());
                                    // If foreign key on company_id causes issue, create without it for company_id
                                    try {
                                        // Drop foreign key if exists, then recreate table
                                        $this->db->exec("DROP TABLE IF EXISTS dismissed_notifications");
                                    $this->db->exec("
                                        CREATE TABLE dismissed_notifications (
                                          id INT AUTO_INCREMENT PRIMARY KEY,
                                          company_id BIGINT UNSIGNED NULL,
                                          notification_id VARCHAR(255) NOT NULL,
                                          dismissed_by INT NOT NULL,
                                          dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                          INDEX idx_company_notification (company_id, notification_id),
                                          INDEX idx_company (company_id),
                                          INDEX idx_notification_id (notification_id),
                                          INDEX idx_dismissed_by (dismissed_by),
                                          UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by),
                                          FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE CASCADE
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                                    ");
                                        $stmt = $this->db->prepare("
                                            INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                            VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                        ");
                                        $stmt->execute([null, $notificationId, $userId, $userId]);
                                    } catch (\Exception $e2) {
                                        error_log("Error creating dismissed_notifications table (fallback): " . $e2->getMessage());
                                        throw $e2;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("Error storing dismissed admin notification in database: " . $e->getMessage());
                            // Fallback to session if database fails
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            if (!isset($_SESSION['dismissed_admin_notifications'])) {
                                $_SESSION['dismissed_admin_notifications'] = [];
                            }
                            $_SESSION['dismissed_admin_notifications'][] = $notificationId;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error deleting notification from database: " . $e->getMessage());
                }
            }
            
            // For dynamic notifications (low stock, out of stock), track dismissed notifications in database
            // This ensures that when a manager clears a notification, it's cleared for all users in the company
            if (strpos($notificationId, 'low_stock_') === 0 || 
                strpos($notificationId, 'out_of_stock_') === 0 ||
                strpos($notificationId, 'critical_') === 0) {
                
                if ($this->db !== null && $companyId !== null) {
                    try {
                        // Check if dismissed_notifications table exists
                        $tableCheck = $this->db->query("SHOW TABLES LIKE 'dismissed_notifications'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            // Migrate table if it has old unique key (company_id, notification_id) to new one (company_id, notification_id, dismissed_by)
                            try {
                                $indexCheck = $this->db->query("SHOW INDEX FROM dismissed_notifications WHERE Key_name = 'uk_company_notification'");
                                if ($indexCheck && $indexCheck->rowCount() > 0) {
                                    // Old unique key exists, drop it and add new one
                                    $this->db->exec("ALTER TABLE dismissed_notifications DROP INDEX uk_company_notification");
                                    $this->db->exec("ALTER TABLE dismissed_notifications ADD UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by)");
                                    $this->db->exec("ALTER TABLE dismissed_notifications ADD INDEX idx_dismissed_by (dismissed_by)");
                                }
                            } catch (\Exception $e) {
                                // Index might not exist or might already be migrated, continue
                                error_log("Note: Could not migrate unique key (might already be migrated): " . $e->getMessage());
                            }
                            
                            // Insert or update dismissed notification for the user
                            // For salespersons/repairers, each user has their own dismissal record
                            // For managers/admins, they can dismiss for the whole company
                            $stmt = $this->db->prepare("
                                INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                            ");
                            $stmt->execute([$companyId, $notificationId, $userId, $userId]);
                            
                            // Verify the dismissal was stored (for debugging and ensuring persistence)
                            $verifyStmt = $this->db->prepare("
                                SELECT id FROM dismissed_notifications 
                                WHERE company_id = ? AND notification_id = ? AND dismissed_by = ?
                            ");
                            $verifyStmt->execute([$companyId, $notificationId, $userId]);
                            if ($verifyStmt->rowCount() === 0) {
                                error_log("Warning: Dismissal record not found after insert for notification: {$notificationId}, user: {$userId}, company: {$companyId}");
                            } else {
                                error_log("Success: Dismissal record stored for notification: {$notificationId}, user: {$userId}, company: {$companyId}");
                            }
                        } else {
                            // Table doesn't exist yet, create it with user-specific unique key
                            $createTable = $this->db->exec("
                                CREATE TABLE IF NOT EXISTS dismissed_notifications (
                                  id INT AUTO_INCREMENT PRIMARY KEY,
                                  company_id BIGINT UNSIGNED NOT NULL,
                                  notification_id VARCHAR(255) NOT NULL,
                                  dismissed_by INT NOT NULL,
                                  dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                  INDEX idx_company_notification (company_id, notification_id),
                                  INDEX idx_company (company_id),
                                  INDEX idx_dismissed_by (dismissed_by),
                                  UNIQUE KEY uk_user_notification (company_id, notification_id, dismissed_by),
                                  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                                  FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE CASCADE
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            if ($createTable !== false) {
                                // Retry insert after creating table
                                $stmt = $this->db->prepare("
                                    INSERT INTO dismissed_notifications (company_id, notification_id, dismissed_by)
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE dismissed_by = ?, dismissed_at = NOW()
                                ");
                                $stmt->execute([$companyId, $notificationId, $userId, $userId]);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Error storing dismissed notification in database: " . $e->getMessage());
                        // Fallback to session if database fails
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        if (!isset($_SESSION['dismissed_notifications'])) {
                            $_SESSION['dismissed_notifications'] = [];
                        }
                        $_SESSION['dismissed_notifications'][] = $notificationId;
                    }
                } else {
                    // Fallback to session if no database or company_id
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    if (!isset($_SESSION['dismissed_notifications'])) {
                        $_SESSION['dismissed_notifications'] = [];
                    }
                    $_SESSION['dismissed_notifications'][] = $notificationId;
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Notification cleared'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    /**
     * Get notification details
     * GET /api/notifications/{id}
     */
    public function getNotificationDetails($notificationId) {
        // Suppress PHP errors
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $userId = null;
            $companyId = null;
            
            // Try JWT token first
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    $userId = $payload->sub;
                    $companyId = $payload->company_id ?? null;
                } catch (\Exception $e) {
                    // Token validation failed, try session
                }
            }
            
            // Fallback to session
            if ($userId === null) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $userData = $_SESSION['user'] ?? null;
                if ($userData) {
                    $userId = $userData['id'];
                    $companyId = $userData['company_id'] ?? null;
                }
            }
            
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            // Get user role for notification fetching
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userRole = $_SESSION['user']['role'] ?? '';
            
            // Get all notifications and find the one with matching ID (same logic as getNotifications)
            $notifications = [];
            
            // System Admin: See ONLY administrative notifications (SMS purchases, user requests)
            // NOT company-level SMS (sales, repairs, swaps) - these are filtered out
            if ($userRole === 'system_admin') {
                try {
                    $systemNotifications = $this->getSystemNotifications();
                    $notifications = array_merge($notifications, $systemNotifications);
                } catch (\Exception $e) {
                    error_log("Error fetching system notifications: " . $e->getMessage());
                }
            }
            
            // Check if this is a dynamic notification (low_stock, out_of_stock) that might be dismissed
            // For details view, we should still show it even if dismissed
            $isDynamicNotification = strpos($notificationId, 'low_stock_') === 0 || 
                                     strpos($notificationId, 'out_of_stock_') === 0 ||
                                     strpos($notificationId, 'critical_') === 0;
            
            // Manager, Admin, Salesperson, Repairer: See company-specific notifications
            if ($companyId !== null && in_array($userRole, ['manager', 'admin', 'salesperson', 'repairer'])) {
                try {
                    // For dynamic notifications, we need to reconstruct them even if dismissed
                    if ($isDynamicNotification) {
                        // Extract product ID from notification ID
                        $productId = null;
                        $notificationType = null;
                        if (strpos($notificationId, 'low_stock_') === 0) {
                            $productId = str_replace('low_stock_', '', $notificationId);
                            $notificationType = 'low_stock';
                        } elseif (strpos($notificationId, 'out_of_stock_') === 0) {
                            $productId = str_replace('out_of_stock_', '', $notificationId);
                            $notificationType = 'out_of_stock';
                        }
                        
                        if ($productId && is_numeric($productId) && $this->db !== null) {
                            // Reconstruct notification from product data
                            try {
                                $tableName = null;
                                $quantityCol = 'quantity';
                                
                                $checkTable = $this->db->query("SHOW TABLES LIKE 'products'");
                                if ($checkTable && $checkTable->rowCount() > 0) {
                                    $tableName = 'products';
                                    $checkCol = $this->db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
                                    if ($checkCol->rowCount() === 0) {
                                        $checkCol2 = $this->db->query("SHOW COLUMNS FROM products LIKE 'qty'");
                                        if ($checkCol2->rowCount() > 0) {
                                            $quantityCol = 'qty';
                                        }
                                    }
                                    
                                    $query = $this->db->prepare("
                                        SELECT 
                                            p.id,
                                            p.name,
                                            COALESCE(p.{$quantityCol}, 0) as quantity,
                                            c.name as company_name
                                        FROM {$tableName} p
                                        LEFT JOIN companies c ON p.company_id = c.id
                                        WHERE p.id = ? AND p.company_id = ?
                                    ");
                                    $query->execute([$productId, $companyId]);
                                    $product = $query->fetch(\PDO::FETCH_ASSOC);
                                    
                                    if ($product) {
                                        if ($notificationType === 'low_stock') {
                                            $notifications[] = [
                                                'id' => 'low_stock_' . $product['id'],
                                                'type' => 'low_stock',
                                                'title' => 'Low Stock Alert',
                                                'message' => "{$product['name']} is running low ({$product['quantity']} remaining)" . 
                                                            ($product['company_name'] ? " - {$product['company_name']}" : ''),
                                                'data' => [
                                                    'product_id' => $product['id'],
                                                    'product_name' => $product['name'],
                                                    'quantity' => $product['quantity'],
                                                    'company_name' => $product['company_name']
                                                ],
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'read' => false,
                                                'priority' => 'high'
                                            ];
                                        } elseif ($notificationType === 'out_of_stock') {
                                            $notifications[] = [
                                                'id' => 'out_of_stock_' . $product['id'],
                                                'type' => 'out_of_stock',
                                                'title' => 'Out of Stock Alert',
                                                'message' => "{$product['name']} is out of stock" . 
                                                            ($product['company_name'] ? " - {$product['company_name']}" : ''),
                                                'data' => [
                                                    'product_id' => $product['id'],
                                                    'product_name' => $product['name'],
                                                    'quantity' => $product['quantity'],
                                                    'company_name' => $product['company_name']
                                                ],
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'read' => false,
                                                'priority' => 'critical'
                                            ];
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                error_log("Error reconstructing dynamic notification: " . $e->getMessage());
                            }
                        }
                    } else {
                        // For non-dynamic notifications, use normal filtering
                        $lowStockNotifications = $this->getLowStockNotifications($companyId, $userRole);
                        $outOfStockNotifications = $this->getOutOfStockNotifications($companyId, $userRole);
                        $notifications = array_merge($notifications, $lowStockNotifications, $outOfStockNotifications);
                    }
                } catch (\Exception $e) {
                    error_log("Error fetching company notifications: " . $e->getMessage());
                }
            }
            
            // Also check database notifications table for stored notifications
            try {
                if ($this->db !== null) {
                    $dbNotifications = $this->db->prepare("
                        SELECT * FROM notifications 
                        WHERE user_id = ? 
                        AND (company_id = ? OR company_id IS NULL)
                        ORDER BY created_at DESC
                        LIMIT 100
                    ");
                    if ($dbNotifications && $dbNotifications->execute([$userId, $companyId])) {
                        $dbNotifs = $dbNotifications->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($dbNotifs as $dbNotif) {
                            // Filter based on role - salesperson and repairer don't see SMS purchase notifications
                            if ($userRole === 'salesperson' || $userRole === 'repairer') {
                                if (in_array($dbNotif['type'], ['sms_purchase', 'sms_sent'])) {
                                    continue; // Skip SMS-related notifications for salesperson/repairer
                                }
                            }
                            
                            $notifications[] = [
                                'id' => $dbNotif['id'] ?? 'notif_' . uniqid(),
                                'type' => $dbNotif['type'] ?? 'system',
                                'title' => $this->getNotificationTitle($dbNotif['type'] ?? 'system'),
                                'message' => $dbNotif['message'] ?? '',
                                'data' => $dbNotif['data'] ? json_decode($dbNotif['data'], true) : [],
                                'created_at' => $dbNotif['created_at'] ?? date('Y-m-d H:i:s'),
                                'read' => ($dbNotif['status'] ?? 'unread') === 'read' ? true : false,
                                'priority' => 'high'
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching database notifications: " . $e->getMessage());
            }
            
            // Find notification by ID (handle both string and numeric IDs)
            $notification = null;
            foreach ($notifications as $notif) {
                if ($notif['id'] === $notificationId || (string)$notif['id'] === (string)$notificationId) {
                    $notification = $notif;
                    break;
                }
            }
            
            // If still not found and it's a dynamic notification, try to get it from database directly
            if (!$notification && $isDynamicNotification && $this->db !== null) {
                // Check if it exists in dismissed_notifications to confirm it was a valid notification
                try {
                    $checkDismissed = $this->db->prepare("
                        SELECT notification_id FROM dismissed_notifications 
                        WHERE notification_id = ? AND company_id = ?
                    ");
                    $checkDismissed->execute([$notificationId, $companyId]);
                    if ($checkDismissed->rowCount() > 0) {
                        // Notification was dismissed, reconstruct it (same logic as above)
                        $productId = str_replace(['low_stock_', 'out_of_stock_'], '', $notificationId);
                        if (is_numeric($productId)) {
                            // Reconstruct notification (code already above, but we'll handle it here too)
                            http_response_code(404);
                            echo json_encode([
                                'success' => false, 
                                'error' => 'Notification was dismissed and product no longer exists or is no longer low/out of stock'
                            ]);
                            exit;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Error checking dismissed notification: " . $e->getMessage());
                }
            }
            
            if (!$notification) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notification not found']);
                exit;
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'notification' => $notification
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    public function markAsRead() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Clean any existing output buffers to prevent HTML errors
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Start output buffering to catch any unwanted output
        ob_start();
        
        // Always set JSON header first to prevent HTML errors
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            $userId = null;
            $userData = null;
            
            // Try Authorization header first (Bearer token)
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                try {
                    $token = substr($authHeader, 7);
                    $auth = new AuthService();
                    $payload = $auth->validateToken($token);
                    $userData = [
                        'id' => $payload->sub,
                        'role' => $payload->role,
                        'company_id' => $payload->company_id ?? null
                    ];
                } catch (\Exception $e) {
                    // Token validation failed, try session fallback
                    error_log("Token validation failed: " . $e->getMessage());
                }
            }
            
            // Fallback to session-based authentication
            if (!$userData) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $userData = $_SESSION['user'] ?? null;
            }
            
            if (!$userData) {
                // Clean output buffer before sending error
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or expired token',
                    'message' => 'Invalid or expired token: Expired token'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $notificationId = $input['notification_id'] ?? null;
            $markAll = isset($input['all']) && $input['all'] === true;
            
            $userId = $userData['id'];
            $companyId = $userData['company_id'] ?? null;
            
            // Check if database connection is available
            if ($this->db === null) {
                try {
                    if (!class_exists('Database')) {
                        require_once __DIR__ . '/../../config/database.php';
                    }
                    $this->db = \Database::getInstance()->getConnection();
                } catch (\Exception $e) {
                    error_log("NotificationController markAsRead: Database connection failed: " . $e->getMessage());
                }
            }
            
            // Mark all as read or specific notification
            if ($markAll) {
                // Mark all notifications as read for this user
                if ($this->db !== null) {
                    try {
                        $stmt = $this->db->prepare("
                            UPDATE notifications 
                            SET status = 'read' 
                            WHERE user_id = ? AND status = 'unread'
                        ");
                        $stmt->execute([$userId]);
                    } catch (\Exception $e) {
                        error_log("Error marking all notifications as read: " . $e->getMessage());
                    }
                }
            } elseif ($notificationId) {
                // Mark specific notification as read
                if ($this->db !== null) {
                    try {
                        $stmt = $this->db->prepare("
                            UPDATE notifications 
                            SET status = 'read' 
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$notificationId, $userId]);
                    } catch (\Exception $e) {
                        error_log("Error marking notification as read: " . $e->getMessage());
                    }
                }
            }
            
            // For dynamic notifications (low stock, out of stock), we can't persist read status
            // They'll be marked as read in the session or just return success
            
            // Clean output buffer and send JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            
            // Clean output buffer before sending error
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            
            echo json_encode([
                'success' => false,
                'error' => 'Failed to mark notification as read'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}
