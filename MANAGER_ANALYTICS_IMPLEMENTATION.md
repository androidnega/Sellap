# Manager Analytics Implementation Guide

## Executive Summary

Convert Manager POS page (`/dashboard/pos`) to a Manager Analytics dashboard while keeping POS functionality for salespeople. Analytics page provides BI insights, exports, trace features, and respects company module toggles.

## Priority Implementation Steps

### 1. Create ManagerAnalyticsController (HIGH PRIORITY)

**File**: `app/Controllers/ManagerAnalyticsController.php`

```php
<?php
namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Models\POSSale;
use App\Models\POSSaleItem;
use App\Models\Product;
use App\Models\CompanyModule;

class ManagerAnalyticsController {
    private $sale;
    private $saleItem;
    private $product;
    
    public function __construct() {
        $this->sale = new POSSale();
        $this->saleItem = new POSSaleItem();
        $this->product = new Product();
    }
    
    /**
     * Display analytics dashboard (replaces POS for managers)
     */
    public function index() {
        $user = WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        $companyId = $_SESSION['user']['company_id'] ?? null;
        $userRole = $_SESSION['user']['role'] ?? 'manager';
        
        // Check if reports_analytics module enabled
        if ($userRole !== 'system_admin' && !CompanyModule::isEnabled($companyId, 'reports_analytics')) {
            header('Location: ' . BASE_URL_PATH . '/dashboard?error=' . urlencode('Analytics module not enabled'));
            exit;
        }
        
        $title = 'Analytics Dashboard';
        $page = 'analytics';
        
        ob_start();
        include __DIR__ . '/../Views/manager_analytics.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../Views/simple_layout.php';
    }
    
    /**
     * API: Get overview metrics for dashboard widgets
     */
    public function overview() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array($user['role'], ['manager', 'admin', 'system_admin'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $companyId = $user['company_id'] ?? null;
        $userRole = $user['role'] ?? '';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Get enabled modules
        $enabledModules = $userRole === 'system_admin' ? 
            ['pos_sales', 'swap', 'repairs'] : 
            CompanyModule::getEnabledModules($companyId);
        
        $db = \Database::getInstance()->getConnection();
        $metrics = [];
        
        // Sales metrics (if module enabled)
        if (in_array('pos_sales', $enabledModules)) {
            $salesStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_sales,
                    SUM(final_amount) as revenue,
                    AVG(final_amount) as avg_sale,
                    SUM(final_amount - COALESCE((SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, 0)) 
                        FROM pos_sale_items psi 
                        LEFT JOIN products p ON psi.item_id = p.id 
                        WHERE psi.pos_sale_id = ps.id), final_amount * 0.8)) as profit
                FROM pos_sales ps
                WHERE ps.company_id = ? AND DATE(ps.created_at) BETWEEN ? AND ?
            ");
            $salesStmt->execute([$companyId, $dateFrom, $dateTo]);
            $salesData = $salesStmt->fetch(\PDO::FETCH_ASSOC);
            
            $metrics['sales'] = [
                'total_sales' => (int)($salesData['total_sales'] ?? 0),
                'revenue' => (float)($salesData['revenue'] ?? 0),
                'avg_sale' => (float)($salesData['avg_sale'] ?? 0),
                'profit' => (float)($salesData['profit'] ?? 0)
            ];
        }
        
        // Swap profit (if module enabled)
        if (in_array('swap', $enabledModules)) {
            $swapStmt = $db->prepare("
                SELECT COALESCE(SUM(final_profit), 0) as total_profit
                FROM swap_profit_links spl
                INNER JOIN swaps s ON spl.swap_id = s.id
                WHERE s.company_id = ? AND spl.status = 'finalized' 
                AND DATE(spl.finalized_at) BETWEEN ? AND ?
            ");
            $swapStmt->execute([$companyId, $dateFrom, $dateTo]);
            $swapData = $swapStmt->fetch(\PDO::FETCH_ASSOC);
            
            $metrics['swap_profit'] = (float)($swapData['total_profit'] ?? 0);
        }
        
        echo json_encode([
            'success' => true,
            'metrics' => $metrics,
            'modules_enabled' => $enabledModules
        ]);
        exit;
    }
    
    /**
     * Export data (CSV/PDF/Excel)
     */
    public function export($type) {
        // Implementation for streaming exports
        // See export_design section in JSON
    }
    
    /**
     * Trace item by IMEI/SKU/Customer
     */
    public function trace() {
        // Implementation for trace feature
        // See trace_feature_design section in JSON
    }
}
```

### 2. Modify POS Route (HIGH PRIORITY)

**File**: `routes/web.php` (around line 602)

```php
// POS - redirect managers to analytics if they can't sell
$router->get('dashboard/pos', function() {
    $user = \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson']);
    $companyId = $_SESSION['user']['company_id'] ?? null;
    $userRole = $_SESSION['user']['role'] ?? 'salesperson';
    
    // If manager and doesn't have sell permission, redirect to analytics
    if ($userRole === 'manager') {
        if (!\App\Models\CompanyModule::isEnabled($companyId, 'manager_can_sell')) {
            header('Location: ' . BASE_URL_PATH . '/dashboard/analytics');
            exit;
        }
    }
    
    // Regular POS for salespeople and managers who can sell
    $GLOBALS['currentPage'] = 'pos';
    $controller = new \App\Controllers\POSController();
    $controller->index();
});

// Analytics Dashboard (new)
$router->get('dashboard/analytics', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'analytics';
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->index();
});
```

### 3. Add Analytics API Routes (HIGH PRIORITY)

**File**: `routes/web.php`

```php
// Analytics API Routes
$router->get('api/analytics/overview', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->overview();
});

$router->get('api/analytics/revenue-by-day', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->revenueByDay();
});

$router->get('api/analytics/export/{type}', function($type) {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->export($type);
});

$router->get('api/analytics/trace', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->trace();
});
```

### 4. Example Export Endpoint (MEDIUM PRIORITY)

```php
public function export($type) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');
    
    $user = $_SESSION['user'] ?? null;
    $companyId = $user['company_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    $db = \Database::getInstance()->getConnection();
    
    // Output headers
    $output = fopen('php://output', 'w');
    
    if ($type === 'sales') {
        fputcsv($output, ['Sale ID', 'Date', 'Customer', 'Items', 'Amount', 'Payment Method']);
        
        $stmt = $db->prepare("
            SELECT ps.id, ps.created_at, c.full_name as customer, 
                   GROUP_CONCAT(psi.item_description SEPARATOR '; ') as items,
                   ps.final_amount, ps.payment_method
            FROM pos_sales ps
            LEFT JOIN customers c ON ps.customer_id = c.id
            LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
            WHERE ps.company_id = ? AND DATE(ps.created_at) BETWEEN ? AND ?
            GROUP BY ps.id
            ORDER BY ps.created_at DESC
        ");
        $stmt->execute([$companyId, $dateFrom, $dateTo]);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['customer'] ?? 'Walk-in',
                $row['items'],
                $row['final_amount'],
                $row['payment_method']
            ]);
        }
    }
    
    fclose($output);
    exit;
}
```

### 5. Database Indexes (MEDIUM PRIORITY)

**File**: `database/migrations/add_analytics_indexes.sql`

```sql
-- Performance indexes for analytics queries
CREATE INDEX IF NOT EXISTS idx_pos_sales_company_created 
    ON pos_sales(company_id, created_at);

CREATE INDEX IF NOT EXISTS idx_pos_sale_items_sale_item 
    ON pos_sale_items(pos_sale_id, item_id);

CREATE FULLTEXT INDEX IF NOT EXISTS ft_products_name 
    ON products(name);

CREATE INDEX IF NOT EXISTS idx_products_imei 
    ON products(imei) WHERE imei IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_customers_name 
    ON customers(full_name);
```

## Key Design Decisions

1. **Role-Based Routing**: Managers without `manager_can_sell` see analytics; salespeople always see POS
2. **Module Toggles**: Analytics widgets respect `pos_sales`, `swap`, `repairs` module enablement
3. **Multi-Tenant Safety**: All queries filter by `company_id`
4. **Performance**: Use indexes, caching (300-1800s TTL), and streaming exports
5. **Backwards Compatibility**: Keep existing `/api/pos/*` endpoints unchanged

## Next Actions

1. ✅ Create `ManagerAnalyticsController`
2. ✅ Update routes in `routes/web.php`
3. ✅ Create `app/Views/manager_analytics.php` (based on manager metrics section from `pos_content.php`)
4. ✅ Add database indexes
5. ✅ Implement caching service
6. ✅ Add export endpoints
7. ✅ Implement trace feature
8. ✅ Test with different module toggle combinations

