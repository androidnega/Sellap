<?php

namespace App\Helpers;

/**
 * Check if New Year message should be displayed
 * Shows only in January, automatically disables after January ends
 */
function shouldShowNewYearMessage() {
    $currentMonth = (int)date('n'); // 1-12
    return $currentMonth === 1; // Only show in January
}

/**
 * Get New Year message HTML
 */
function getNewYearMessage() {
    if (!shouldShowNewYearMessage()) {
        return '';
    }
    
    $currentYear = date('Y');
    return '
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-yellow-400 via-pink-500 to-purple-600 rounded-lg shadow-lg p-4 sm:p-6 text-white animate-pulse">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3 sm:gap-4 flex-1">
                <div class="text-3xl sm:text-4xl md:text-5xl animate-bounce">🎉</div>
                <div>
                    <h2 class="text-xl sm:text-2xl md:text-3xl font-bold mb-1">Happy New Year ' . $currentYear . '!</h2>
                    <p class="text-sm sm:text-base opacity-90">Wishing you a prosperous and successful year ahead!</p>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl md:text-4xl">
                🎊✨🎈
            </div>
        </div>
    </div>';
}

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
}

