<?php

namespace App\Helpers;

use App\Models\CompanyModule;

/**
 * Dashboard Widgets Helper
 * Filters dashboard widgets based on enabled modules
 */
class DashboardWidgets {
    
    /**
     * Module name to module key mapping
     */
    private static $moduleKeyMap = [
        'Products & Inventory' => 'products_inventory',
        'POS / Sales' => 'pos_sales',
        'Swap' => 'swap',
        'Repairs' => 'repairs',
        'Customers' => 'customers',
        'Staff Management' => 'staff_management',
        'Reports & Analytics' => 'reports_analytics',
        'Notifications & SMS' => 'notifications_sms',
        'Company Management' => 'company_management',
        'System Settings' => 'system_settings'
    ];
    
    /**
     * Dashboard widgets mapping (from SYSTEM_MODULE_AUDIT.json)
     */
    private static $widgetsMapping = [
        'manager_dashboard' => [
            'Products & Inventory' => [
                'overview-total-products',
                'product-total',
                'product-available',
                'product-out-of-stock',
                'product-inventory-value',
                'product-total-quantity',
                'low-stock-alerts'
            ],
            'POS / Sales' => [
                'overview-today-sales',
                'financial-sales-revenue',
                'financial-total-revenue',
                'recent-sales-table',
                'sales-chart'
            ],
            'Swap' => [
                'overview-today-swaps',
                'financial-swap-profit',
                'swap-stats-section',
                'recent-swaps-table'
            ],
            'Repairs' => [
                'overview-today-repairs',
                'financial-repair-revenue',
                'repair-stats-section',
                'recent-repairs-table'
            ],
            'Customers' => [
                'overview-total-customers'
            ],
            'Staff Management' => [
                'overview-active-staff',
                'staff-performance-section'
            ],
            'Notifications & SMS' => [
                'sms-usage-section',
                'sms-low-balance-alert'
            ],
            'Reports & Analytics' => [
                'financial-summary-section',
                'financial-total-profit',
                'financial-profit-margin'
            ]
        ],
        'admin_dashboard' => [
            'Company Management' => [
                'total-companies',
                'companies-with-low-balance',
                'top-performing-companies'
            ],
            'Reports & Analytics' => [
                'platform-revenue',
                'total-users',
                'company-performance-chart'
            ],
            'Notifications & SMS' => [
                'total-sms-used',
                'companies-with-low-sms-balance',
                'most-active-senders'
            ]
        ]
    ];
    
    /**
     * Get enabled widgets for a dashboard type based on enabled modules
     * 
     * @param string $dashboardType 'manager_dashboard' or 'admin_dashboard'
     * @param array $enabledModuleKeys Array of enabled module keys (e.g., ['swap', 'pos_sales'])
     * @param string|null $userRole User role (system_admin bypasses filtering)
     * @return array Array of widget IDs that should be displayed
     */
    public static function filterByModules($dashboardType, $enabledModuleKeys, $userRole = null) {
        // System admins see all widgets
        if ($userRole === 'system_admin') {
            return self::getAllWidgets($dashboardType);
        }
        
        // Get widgets mapping for this dashboard type
        $mapping = self::$widgetsMapping[$dashboardType] ?? [];
        
        $allowedWidgets = [];
        
        // Iterate through module widgets and include only those from enabled modules
        foreach ($mapping as $moduleName => $widgets) {
            $moduleKey = self::$moduleKeyMap[$moduleName] ?? null;
            
            // If module key exists and is enabled, include its widgets
            if ($moduleKey && in_array($moduleKey, $enabledModuleKeys)) {
                $allowedWidgets = array_merge($allowedWidgets, $widgets);
            }
        }
        
        return $allowedWidgets;
    }
    
    /**
     * Get all widgets for a dashboard type (no filtering)
     * 
     * @param string $dashboardType 'manager_dashboard' or 'admin_dashboard'
     * @return array Array of all widget IDs
     */
    public static function getAllWidgets($dashboardType) {
        $mapping = self::$widgetsMapping[$dashboardType] ?? [];
        $allWidgets = [];
        
        foreach ($mapping as $widgets) {
            $allWidgets = array_merge($allWidgets, $widgets);
        }
        
        return $allWidgets;
    }
    
    /**
     * Check if a widget should be displayed
     * 
     * @param string $widgetId Widget ID to check
     * @param string $dashboardType Dashboard type
     * @param array $enabledModuleKeys Enabled module keys
     * @param string|null $userRole User role
     * @return bool True if widget should be displayed
     */
    public static function shouldDisplayWidget($widgetId, $dashboardType, $enabledModuleKeys, $userRole = null) {
        $allowedWidgets = self::filterByModules($dashboardType, $enabledModuleKeys, $userRole);
        return in_array($widgetId, $allowedWidgets);
    }
    
    /**
     * Get module key for a module name
     * 
     * @param string $moduleName Module name (e.g., 'Swap')
     * @return string|null Module key (e.g., 'swap') or null if not found
     */
    public static function getModuleKey($moduleName) {
        return self::$moduleKeyMap[$moduleName] ?? null;
    }
    
    /**
     * Filter metrics array to only include metrics for enabled modules
     * 
     * @param array $metrics Metrics array (e.g., ['swap' => [...], 'products' => [...]])
     * @param array $enabledModuleKeys Enabled module keys
     * @param string|null $userRole User role
     * @return array Filtered metrics array
     */
    public static function filterMetrics($metrics, $enabledModuleKeys, $userRole = null) {
        // System admins see all metrics
        if ($userRole === 'system_admin') {
            return $metrics;
        }
        
        $filtered = [];
        
        // Map of metric keys to module keys
        $metricModuleMap = [
            'swaps' => 'swap',
            'products' => 'products_inventory',
            'total_sales' => 'pos_sales',
            'total_revenue' => 'pos_sales',
            'avg_sale' => 'pos_sales',
            'total_repairs' => 'repairs',
            'repair_revenue' => 'repairs',
            'pending_repairs' => 'repairs',
            'in_progress_repairs' => 'repairs',
            'completed_repairs' => 'repairs',
            'active_repairs' => 'repairs',
            'monthly_repairs' => 'repairs',
            'today_sales' => 'pos_sales',
            'today_revenue' => 'pos_sales',
            'monthly_sales' => 'pos_sales',
            'monthly_revenue' => 'pos_sales',
            'pending_swaps' => 'swap',
            'monthly_swaps' => 'swap',
            'total_swaps' => 'swap',
            'completed_swaps' => 'swap',
            'resold_swaps' => 'swap',
            'total_swap_value' => 'swap'
        ];
        
        foreach ($metrics as $key => $value) {
            // Always include non-module-specific metrics and payment_stats
            if (!isset($metricModuleMap[$key]) || $key === 'payment_stats') {
                $filtered[$key] = $value;
                continue;
            }
            
            // Check if the module for this metric is enabled
            $moduleKey = $metricModuleMap[$key];
            if (in_array($moduleKey, $enabledModuleKeys)) {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get today's sales count based on user role
     */
    public static function getTodaySalesCount($companyId, $userId = null, $userRole = 'salesperson') {
        try {
            $db = \Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            
            if (in_array($userRole, ['manager', 'admin'])) {
                // Manager/Admin - all company sales
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND DATE(created_at) = ? AND swap_id IS NULL
                ");
                $stmt->execute([$companyId, $today]);
            } elseif ($userRole === 'technician') {
                // Technician - repairs completed today
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM repairs_new 
                    WHERE company_id = ? AND technician_id = ? AND DATE(created_at) = ? AND status = 'completed'
                ");
                $stmt->execute([$companyId, $userId, $today]);
            } else {
                // Salesperson - their own sales
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM pos_sales 
                    WHERE company_id = ? AND created_by_user_id = ? AND DATE(created_at) = ? 
                    AND swap_id IS NULL
                    AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))
                ");
                $stmt->execute([$companyId, $userId, $today]);
            }
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("Error getting today's sales count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if New Year message should be displayed
     * Shows only in January if enabled in settings
     */
    public static function shouldShowNewYearMessage() {
        $currentMonth = (int)date('n'); // 1-12
        if ($currentMonth !== 1) {
            return false; // Only show in January
        }
        
        // Check if enabled in system settings
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'new_year_message_enabled'");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $enabled = $result ? (int)$result['setting_value'] : 1; // Default to enabled
            return $enabled === 1;
        } catch (\Exception $e) {
            error_log("Error checking new year message setting: " . $e->getMessage());
            return true; // Default to enabled if error
        }
    }
}

