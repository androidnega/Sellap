<?php

namespace App\Services;

use PDO;
use App\Models\CompanyModule;
use App\Models\SmartRecommendation;

require_once __DIR__ . '/../../config/database.php';

class AnalyticsService {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get sales statistics for a company
     * 
     * @param int $company_id
     * @param string|null $date_from
     * @param string|null $date_to
     * @param int|null $staff_id Optional staff member filter
     * @return array
     */
    public function getSalesStats($company_id, $date_from = null, $date_to = null, $staff_id = null) {
        // Exclude repair-related sales from salesperson stats (when staff_id is provided)
        // Repair sales have notes like "Repair #X - Products sold by repairer"
        $excludeRepairSales = $staff_id ? " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))" : "";
        
        $todayWhere = "company_id = :company_id AND DATE(created_at) = CURDATE(){$excludeRepairSales}";
        $todayParams = ['company_id' => $company_id];
        if ($staff_id) {
            $todayWhere .= " AND created_by_user_id = :staff_id";
            $todayParams['staff_id'] = $staff_id;
        }
        
        // Always calculate today's stats (for reference)
        $todayQuery = $this->db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(final_amount), 0) as revenue,
                COALESCE(AVG(final_amount), 0) as avg_sale
            FROM pos_sales 
            WHERE {$todayWhere}
        ");
        $todayQuery->execute($todayParams);
        $today = $todayQuery->fetch(PDO::FETCH_ASSOC);

        // Always calculate monthly stats (for reference)
        $monthlyWhere = "company_id = :company_id AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()){$excludeRepairSales}";
        $monthlyParams = ['company_id' => $company_id];
        if ($staff_id) {
            $monthlyWhere .= " AND created_by_user_id = :staff_id";
            $monthlyParams['staff_id'] = $staff_id;
        }
        
        $monthlyQuery = $this->db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(final_amount), 0) as revenue,
                COALESCE(AVG(final_amount), 0) as avg_sale
            FROM pos_sales 
            WHERE {$monthlyWhere}
        ");
        $monthlyQuery->execute($monthlyParams);
        $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);

        // Filtered stats (for selected date range) - ALWAYS calculate if dates provided
        $filtered = null;
        if ($date_from || $date_to) {
            $where = "company_id = :company_id{$excludeRepairSales}";
            $params = ['company_id' => $company_id];

            if ($date_from) {
                $where .= " AND DATE(created_at) >= :date_from";
                $params['date_from'] = $date_from;
            }
            if ($date_to) {
                $where .= " AND DATE(created_at) <= :date_to";
                $params['date_to'] = $date_to;
            }
            if ($staff_id) {
                $where .= " AND created_by_user_id = :staff_id";
                $params['staff_id'] = $staff_id;
            }

            $filteredQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(final_amount), 0) as revenue,
                    COALESCE(AVG(final_amount), 0) as avg_sale
                FROM pos_sales 
                WHERE {$where}
            ");
            $filteredQuery->execute($params);
            $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
        }

        return [
            'today' => [
                'count' => (int)($today['count'] ?? 0),
                'revenue' => (float)($today['revenue'] ?? 0),
                'avg_sale' => (float)($today['avg_sale'] ?? 0)
            ],
            'monthly' => [
                'count' => (int)($monthly['count'] ?? 0),
                'revenue' => (float)($monthly['revenue'] ?? 0),
                'avg_sale' => (float)($monthly['avg_sale'] ?? 0)
            ],
            'filtered' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0),
                'avg_sale' => (float)($filtered['avg_sale'] ?? 0)
            ] : null,
            'period' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0),
                'avg_sale' => (float)($filtered['avg_sale'] ?? 0)
            ] : null
        ];
    }

    /**
     * Get repair statistics for a company
     * 
     * @param int $company_id
     * @param string|null $date_from
     * @param string|null $date_to
     * @return array
     */
    public function getRepairStats($company_id, $date_from = null, $date_to = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $costColumn = $hasRepairsNew ? 'total_cost' : 'total_cost';
        
        $where = "company_id = :company_id";
        $params = ['company_id' => $company_id];

        if ($date_from) {
            $where .= " AND DATE(created_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(created_at) <= :date_to";
            $params['date_to'] = $date_to;
        }

        // Active repairs - check status column name
        if ($hasRepairsNew) {
            $activeQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM {$repairsTable} 
                WHERE company_id = :company_id 
                AND status IN ('pending', 'in_progress', 'PENDING', 'IN_PROGRESS')
            ");
        } else {
            $activeQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM {$repairsTable} 
                WHERE company_id = :company_id 
                AND UPPER(repair_status) IN ('PENDING', 'IN_PROGRESS')
            ");
        }
        $activeQuery->execute(['company_id' => $company_id]);
        $active = (int)$activeQuery->fetchColumn();

        // Monthly repairs
        $monthlyQuery = $this->db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM({$costColumn}), 0) as revenue
            FROM {$repairsTable} 
            WHERE company_id = :company_id 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $monthlyQuery->execute(['company_id' => $company_id]);
        $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);

        // Filtered stats
        $filtered = null;
        if ($date_from || $date_to) {
            $filteredQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM({$costColumn}), 0) as revenue
                FROM {$repairsTable} 
                WHERE {$where}
            ");
            $filteredQuery->execute($params);
            $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
        }

        return [
            'active' => $active,
            'monthly' => [
                'count' => (int)($monthly['count'] ?? 0),
                'revenue' => (float)($monthly['revenue'] ?? 0)
            ],
            'filtered' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0)
            ] : null,
            'period' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0)
            ] : null
        ];
    }

    /**
     * Get swap statistics for a company
     * 
     * @param int $company_id
     * @param string|null $date_from
     * @param string|null $date_to
     * @return array
     */
    public function getSwapStats($company_id, $date_from = null, $date_to = null) {
        $where = "company_id = :company_id";
        $params = ['company_id' => $company_id];

        if ($date_from) {
            $where .= " AND DATE(created_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(created_at) <= :date_to";
            $params['date_to'] = $date_to;
        }

        // Pending swaps
        $pendingQuery = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM swaps 
            WHERE company_id = :company_id 
            AND UPPER(swap_status) = 'PENDING'
        ");
        $pendingQuery->execute(['company_id' => $company_id]);
        $pending = (int)$pendingQuery->fetchColumn();

        // Monthly swaps
        $monthlyQuery = $this->db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(total_value), 0) as revenue
            FROM swaps 
            WHERE company_id = :company_id 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $monthlyQuery->execute(['company_id' => $company_id]);
        $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);

        // Swap profit (from swap_profit_links if exists)
        $profit = 0;
        try {
            $profitQuery = $this->db->prepare("
                SELECT COALESCE(SUM(final_profit), 0) as profit
                FROM swap_profit_links spl
                INNER JOIN swaps s ON spl.swap_id = s.id
                WHERE s.company_id = :company_id 
                AND spl.status = 'finalized'
            ");
            $profitQuery->execute(['company_id' => $company_id]);
            $profitResult = $profitQuery->fetch(PDO::FETCH_ASSOC);
            $profit = (float)($profitResult['profit'] ?? 0);
        } catch (\Exception $e) {
            // Table might not exist, ignore
        }

        // Filtered stats
        $filtered = null;
        if ($date_from || $date_to) {
            $filteredQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(total_value), 0) as revenue
                FROM swaps 
                WHERE {$where}
            ");
            $filteredQuery->execute($params);
            $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
        }

        // Swap profit for period (if date range provided)
        $periodProfit = $profit;
        if ($date_from || $date_to) {
            try {
                $periodProfitQuery = $this->db->prepare("
                    SELECT COALESCE(SUM(final_profit), 0) as profit
                    FROM swap_profit_links spl
                    INNER JOIN swaps s ON spl.swap_id = s.id
                    WHERE s.company_id = :company_id 
                    AND spl.status = 'finalized'
                    AND DATE(spl.finalized_at) >= :date_from
                    AND DATE(spl.finalized_at) <= :date_to
                ");
                $periodProfitParams = ['company_id' => $company_id];
                if ($date_from) $periodProfitParams['date_from'] = $date_from;
                if ($date_to) $periodProfitParams['date_to'] = $date_to;
                $periodProfitQuery->execute($periodProfitParams);
                $periodProfitResult = $periodProfitQuery->fetch(PDO::FETCH_ASSOC);
                $periodProfit = (float)($periodProfitResult['profit'] ?? 0);
            } catch (\Exception $e) {
                // Table might not exist, ignore
            }
        }

        return [
            'pending' => $pending,
            'monthly' => [
                'count' => (int)($monthly['count'] ?? 0),
                'revenue' => (float)($monthly['revenue'] ?? 0)
            ],
            'profit' => $profit,
            'filtered' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0)
            ] : null,
            'period' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0),
                'profit' => $periodProfit
            ] : null
        ];
    }

    /**
     * Get inventory statistics for a company
     * 
     * @param int $company_id
     * @return array
     */
    public function getInventoryStats($company_id) {
        // Total products
        $totalQuery = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM products 
            WHERE company_id = :company_id
        ");
        $totalQuery->execute(['company_id' => $company_id]);
        $total = (int)$totalQuery->fetchColumn();

        // Detect which quantity column exists (use instance property to cache)
        if (!isset($this->quantityColumn)) {
            $this->quantityColumn = 'quantity'; // Default
            try {
                $colCheck = $this->db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
                if ($colCheck->rowCount() == 0) {
                    $colCheck = $this->db->query("SHOW COLUMNS FROM products LIKE 'qty'");
                    if ($colCheck->rowCount() > 0) {
                        $this->quantityColumn = 'qty';
                    }
                }
            } catch (\Exception $e) {
                // Default to quantity if check fails
                $this->quantityColumn = 'quantity';
                error_log("Could not detect quantity column, defaulting to 'quantity': " . $e->getMessage());
            }
        }
        $quantityCol = $this->quantityColumn;

        // In stock
        $inStockQuery = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM products 
            WHERE company_id = :company_id 
            AND ({$quantityCol} > 0 OR {$quantityCol} IS NOT NULL)
        ");
        $inStockQuery->execute(['company_id' => $company_id]);
        $inStock = (int)$inStockQuery->fetchColumn();

        // Low stock (< 10)
        $lowStockQuery = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM products 
            WHERE company_id = :company_id 
            AND {$quantityCol} > 0 AND {$quantityCol} < 10
        ");
        $lowStockQuery->execute(['company_id' => $company_id]);
        $lowStock = (int)$lowStockQuery->fetchColumn();

        // Out of stock
        $outOfStockQuery = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM products 
            WHERE company_id = :company_id 
            AND ({$quantityCol} = 0 OR {$quantityCol} IS NULL)
        ");
        $outOfStockQuery->execute(['company_id' => $company_id]);
        $outOfStock = (int)$outOfStockQuery->fetchColumn();

        // Total inventory value
        $valueQuery = $this->db->prepare("
            SELECT COALESCE(SUM(price * COALESCE({$quantityCol}, 0)), 0) as total_value
            FROM products 
            WHERE company_id = :company_id
        ");
        $valueQuery->execute(['company_id' => $company_id]);
        $totalValue = (float)$valueQuery->fetchColumn();

        return [
            'total' => $total,
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => $totalValue
        ];
    }

    /**
     * Get profit statistics for a company
     * 
     * @param int $company_id
     * @param string|null $date_from
     * @param string|null $date_to
     * @param int|null $staff_id Optional staff member filter
     * @return array
     */
    public function getProfitStats($company_id, $date_from = null, $date_to = null, $staff_id = null) {
        $where = "ps.company_id = :company_id";
        $params = ['company_id' => $company_id];

        // If no date range provided, use current month (matching getSalesStats monthly behavior)
        if (!$date_from && !$date_to) {
            $where .= " AND MONTH(ps.created_at) = MONTH(CURDATE()) AND YEAR(ps.created_at) = YEAR(CURDATE())";
        } else {
            // Use datetime comparison to match audit trail (includes full day)
            if ($date_from) {
                $where .= " AND ps.created_at >= :date_from_start";
                $params['date_from_start'] = $date_from . ' 00:00:00';
            }
            if ($date_to) {
                $where .= " AND ps.created_at <= :date_to_end";
                $params['date_to_end'] = $date_to . ' 23:59:59';
            }
        }
        
        // Add staff filter if provided and exclude repair-related sales from salesperson stats
        if ($staff_id) {
            $where .= " AND ps.created_by_user_id = :staff_id";
            // Exclude repair sales from salesperson stats
            $where .= " AND (ps.notes IS NULL OR (ps.notes NOT LIKE '%Repair #%' AND ps.notes NOT LIKE '%Products sold by repairer%'))";
            $params['staff_id'] = $staff_id;
        }

        // Calculate profit: revenue - cost
        // Use a more robust approach with JOINs instead of correlated subqueries
        // First, determine which products table exists
        $checkProductsNew = $this->db->query("SHOW TABLES LIKE 'products_new'");
        $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
        
        // Check if pos_sales has total_cost column
        $checkCostCol = $this->db->query("SHOW COLUMNS FROM pos_sales LIKE 'total_cost'");
        $hasCostCol = $checkCostCol && $checkCostCol->rowCount() > 0;
        
        // ALWAYS calculate cost from products table to ensure accuracy
        // Don't use stored total_cost as it may be incorrect or outdated
        // Always use actual product cost from products table
        // Calculate cost from pos_sale_items joined with products
        $profitQuery = $this->db->prepare("
            SELECT 
                COALESCE(SUM(ps.final_amount), 0) as revenue,
                COALESCE(SUM(
                    (SELECT COALESCE(SUM(psi.quantity * COALESCE(p.cost, 0)), 0)
                     FROM pos_sale_items psi 
                     LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                     WHERE psi.pos_sale_id = ps.id)
                ), 0) as cost
            FROM pos_sales ps
            WHERE {$where}
        ");
        
        try {
            $profitQuery->execute($params);
            $result = $profitQuery->fetch(PDO::FETCH_ASSOC);

            $revenue = (float)($result['revenue'] ?? 0);
            $cost = (float)($result['cost'] ?? 0);
            
            // Validate and prevent anomalies
            // Ensure cost is not negative
            if ($cost < 0) {
                error_log("Profit stats WARNING: Negative cost detected (₵{$cost}) for company {$company_id}, setting to 0");
                $cost = 0;
            }
            
            // If cost is 0 or very close to revenue, it might mean no cost data was found
            // In that case, use a default margin calculation (assume 20-30% profit margin)
            if ($cost == 0 && $revenue > 0) {
                // No cost data found, use default 25% margin (75% cost)
                $cost = $revenue * 0.75;
                error_log("Profit stats: No cost data found for company {$company_id}, using default 75% cost estimate");
            } else if ($cost >= $revenue && $revenue > 0) {
                // Cost is higher than revenue - could be legitimate loss or data issue
                // Log warning but allow it (might be legitimate)
                error_log("Profit stats WARNING: Cost (₵{$cost}) >= Revenue (₵{$revenue}) for company {$company_id} - possible data issue or legitimate loss");
            }
            
            // Calculate profit as Selling Price - Cost Price (Revenue - Cost)
            // This is the standard profit formula: Profit = Revenue - Cost
            $profit = $revenue - $cost;
            
            // Round to prevent floating point anomalies
            $revenue = round($revenue, 2);
            $cost = round($cost, 2);
            $profit = round($profit, 2);
            
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
            $margin = round($margin, 2);
            
            // Log for debugging
            if ($revenue > 0) {
                error_log("Profit stats: Revenue={$revenue}, Cost={$cost}, Profit={$profit}, Margin={$margin}% for company {$company_id}" . ($staff_id ? " staff {$staff_id}" : ""));
            }

            // Log for debugging if revenue is 0 but should have sales
            if ($revenue == 0 && $date_from && $date_to) {
                // Check if there are any sales at all for this company
                $checkSales = $this->db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as total FROM pos_sales WHERE company_id = :company_id");
                $checkSales->execute(['company_id' => $company_id]);
                $salesCheck = $checkSales->fetch(PDO::FETCH_ASSOC);
                
                if ($salesCheck['count'] > 0) {
                    error_log("Profit stats WARNING: Revenue is 0 for company {$company_id} from {$date_from} to {$date_to}, but company has {$salesCheck['count']} total sales worth {$salesCheck['total']}");
                } else {
                    error_log("Profit stats: Revenue is 0 for company {$company_id} from {$date_from} to {$date_to} - no sales found for this company");
                }
            }

            return [
                'revenue' => $revenue, // Already rounded
                'cost' => $cost, // Already rounded
                'profit' => $profit, // Already rounded
                'margin' => $margin // Already rounded
            ];
        } catch (\Exception $e) {
            error_log("Error calculating profit stats: " . $e->getMessage());
            // Return zero if query fails
            return [
                'revenue' => 0,
                'cost' => 0,
                'profit' => 0,
                'margin' => 0
            ];
        }
    }

    /**
     * Trace an item across all modules (sales, swaps, repairs, inventory)
     * 
     * @param int $company_id
     * @param string $query Search term (IMEI, product_id, SKU, customer phone, etc.)
     * @return array
     */
    public function traceItem($company_id, $query) {
        $results = [];
        $searchTerm = '%' . $query . '%';

        // Search in sales
        try {
            // First, try exact match on unique_id (sale ID)
            $exactMatch = false;
            if (preg_match('/^POS[A-Z0-9]+$/', $query)) {
                // This looks like a sale unique_id, try exact match first
                $exactQuery = $this->db->prepare("
                    SELECT 
                        'sale' as type,
                        ps.id,
                        ps.created_at as date,
                        ps.final_amount as amount,
                        c.full_name as customer,
                        (SELECT GROUP_CONCAT(item_description SEPARATOR ', ') FROM pos_sale_items WHERE pos_sale_id = ps.id LIMIT 3) as item,
                        ps.unique_id as reference
                    FROM pos_sales ps
                    LEFT JOIN customers c ON ps.customer_id = c.id
                    WHERE ps.company_id = :company_id 
                    AND ps.unique_id = :exact_query
                    ORDER BY ps.created_at DESC
                    LIMIT 50
                ");
                $exactQuery->execute(['company_id' => $company_id, 'exact_query' => $query]);
                $exactSales = $exactQuery->fetchAll(PDO::FETCH_ASSOC);
                if (count($exactSales) > 0) {
                    $results = array_merge($results, $exactSales);
                    $exactMatch = true;
                }
            }
            
            // Also do the broader search with LIKE for partial matches
            $salesQuery = $this->db->prepare("
                SELECT DISTINCT
                    'sale' as type,
                    ps.id,
                    ps.created_at as date,
                    ps.final_amount as amount,
                    c.full_name as customer,
                    COALESCE(
                        (SELECT GROUP_CONCAT(item_description SEPARATOR ', ') FROM pos_sale_items WHERE pos_sale_id = ps.id LIMIT 3),
                        'Multiple items'
                    ) as item,
                    ps.unique_id as reference
                FROM pos_sales ps
                LEFT JOIN customers c ON ps.customer_id = c.id
                LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                LEFT JOIN products p ON psi.item_id = p.id
                WHERE ps.company_id = :company_id 
                AND (
                    ps.unique_id LIKE :query
                    OR ps.id = :query_id
                    OR c.phone_number LIKE :query
                    OR c.full_name LIKE :query
                    OR psi.item_description LIKE :query
                    OR p.imei LIKE :query 
                    OR p.product_id LIKE :query
                )
                ORDER BY ps.created_at DESC
                LIMIT 50
            ");
            // Try to parse query as integer for ID search
            $queryId = is_numeric($query) ? intval($query) : 0;
            $salesQuery->execute([
                'company_id' => $company_id, 
                'query' => $searchTerm,
                'query_id' => $queryId
            ]);
            $sales = $salesQuery->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge results, avoiding duplicates
            $existingIds = array_column($results, 'id');
            foreach ($sales as $sale) {
                if (!in_array($sale['id'], $existingIds)) {
                    $results[] = $sale;
                    $existingIds[] = $sale['id'];
                }
            }
        } catch (\Exception $e) {
            error_log("TraceItem sales search error: " . $e->getMessage());
        }

        // Search in swaps
        try {
            $swapsQuery = $this->db->prepare("
                SELECT 
                    'swap' as type,
                    s.id,
                    s.created_at as date,
                    s.total_value as amount,
                    c.full_name as customer,
                    CONCAT(cp.brand, ' ', cp.model) as item,
                    s.unique_id as reference
                FROM swaps s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                WHERE s.company_id = :company_id 
                AND (
                    cp.imei LIKE :query
                    OR c.phone_number LIKE :query
                    OR c.full_name LIKE :query
                    OR s.unique_id LIKE :query
                )
                ORDER BY s.created_at DESC
                LIMIT 50
            ");
            $swapsQuery->execute(['company_id' => $company_id, 'query' => $searchTerm]);
            $swaps = $swapsQuery->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $swaps);
        } catch (\Exception $e) {
            // Ignore errors
        }

        // Search in repairs
        try {
            $repairsQuery = $this->db->prepare("
                SELECT 
                    'repair' as type,
                    r.id,
                    r.created_at as date,
                    r.total_cost as amount,
                    c.full_name as customer,
                    r.phone_description as item,
                    r.unique_id as reference
                FROM repairs r
                LEFT JOIN customers c ON r.customer_id = c.id
                WHERE r.company_id = :company_id 
                AND (
                    r.imei LIKE :query
                    OR c.phone_number LIKE :query
                    OR c.full_name LIKE :query
                    OR r.unique_id LIKE :query
                )
                ORDER BY r.created_at DESC
                LIMIT 50
            ");
            $repairsQuery->execute(['company_id' => $company_id, 'query' => $searchTerm]);
            $repairs = $repairsQuery->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $repairs);
        } catch (\Exception $e) {
            // Ignore errors
        }

        // Search in inventory
        try {
            $inventoryQuery = $this->db->prepare("
                SELECT 
                    'inventory' as type,
                    p.id,
                    p.created_at as date,
                    p.price as amount,
                    NULL as customer,
                    p.name as item,
                    p.product_id as reference
                FROM products p
                WHERE p.company_id = :company_id 
                AND (
                    p.imei LIKE :query
                    OR p.product_id LIKE :query
                    OR p.name LIKE :query
                )
                ORDER BY p.created_at DESC
                LIMIT 50
            ");
            $inventoryQuery->execute(['company_id' => $company_id, 'query' => $searchTerm]);
            $inventory = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $inventory);
        } catch (\Exception $e) {
            // Ignore errors
        }

        // Sort by date descending
        usort($results, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($results, 0, 100); // Limit total results
    }

    /**
     * Get sales by date range with detailed data
     * 
     * @param int $company_id
     * @param string $from
     * @param string $to
     * @return array
     */
    /**
     * Get sales by date range
     * @param int|null $staff_id Optional staff member filter
     */
    public function getSalesByDateRange($company_id, $from, $to, $staff_id = null) {
        $where = "ps.company_id = :company_id
            AND DATE(ps.created_at) >= :date_from
            AND DATE(ps.created_at) <= :date_to";
        $params = [
            'company_id' => $company_id,
            'date_from' => $from,
            'date_to' => $to
        ];
        
        if ($staff_id) {
            $where .= " AND ps.created_by_user_id = :staff_id";
            $params['staff_id'] = $staff_id;
        }
        
        $query = $this->db->prepare("
            SELECT 
                ps.id,
                ps.unique_id,
                ps.created_at,
                ps.final_amount,
                ps.payment_method,
                ps.payment_status,
                c.full_name as customer_name,
                c.phone_number as customer_phone,
                u.full_name as cashier_name,
                COUNT(psi.id) as item_count,
                GROUP_CONCAT(psi.item_description SEPARATOR '; ') as items
            FROM pos_sales ps
            LEFT JOIN customers c ON ps.customer_id = c.id
            LEFT JOIN users u ON ps.created_by_user_id = u.id
            LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
            WHERE {$where}
            GROUP BY ps.id
            ORDER BY ps.created_at DESC
        ");
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get swaps by date range with detailed data
     * 
     * @param int $company_id
     * @param string $from
     * @param string $to
     * @return array
     */
    /**
     * Get swaps by date range
     * @param int|null $staff_id Optional staff member filter (salesperson)
     */
    public function getSwapsByDateRange($company_id, $from, $to, $staff_id = null) {
        $where = "s.company_id = :company_id
            AND DATE(s.created_at) >= :date_from
            AND DATE(s.created_at) <= :date_to";
        $params = [
            'company_id' => $company_id,
            'date_from' => $from,
            'date_to' => $to
        ];
        
        if ($staff_id) {
            $where .= " AND s.salesperson_id = :staff_id";
            $params['staff_id'] = $staff_id;
        }
        
        $query = $this->db->prepare("
            SELECT 
                s.id,
                s.unique_id,
                s.created_at,
                s.total_value,
                s.swap_status,
                c.full_name as customer_name,
                c.phone_number as customer_phone,
                cp.brand,
                cp.model,
                CONCAT(cp.brand, ' ', cp.model) as item_description
            FROM swaps s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
            WHERE {$where}
            ORDER BY s.created_at DESC
        ");
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get repairs by date range with detailed data
     * 
     * @param int $company_id
     * @param string $from
     * @param string $to
     * @return array
     */
    /**
     * Get repairs by date range
     * @param int|null $staff_id Optional staff member filter (technician)
     */
    public function getRepairsByDateRange($company_id, $from, $to, $staff_id = null) {
        $where = "r.company_id = :company_id
            AND DATE(r.created_at) >= :date_from
            AND DATE(r.created_at) <= :date_to";
        $params = [
            'company_id' => $company_id,
            'date_from' => $from,
            'date_to' => $to
        ];
        
        // Check which repairs table exists and use appropriate column
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        
        if ($staff_id) {
            if ($hasRepairsNew) {
                $where .= " AND r.assigned_technician_id = :staff_id";
            } else {
                $where .= " AND r.technician_id = :staff_id";
            }
            $params['staff_id'] = $staff_id;
        }
        
        $query = $this->db->prepare("
            SELECT 
                r.id,
                r.unique_id,
                r.created_at,
                r.total_cost,
                r.repair_status,
                r.payment_status,
                r.phone_description,
                r.imei,
                c.full_name as customer_name,
                c.phone_number as customer_phone
            FROM " . ($hasRepairsNew ? "repairs_new" : "repairs") . " r
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE {$where}
            ORDER BY r.created_at DESC
        ");
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get profit breakdown by period
     * 
     * @param int $company_id
     * @param string $period 'daily', 'weekly', 'monthly'
     * @return array
     */
    public function getProfitBreakdown($company_id, $period = 'monthly') {
        try {
            $dateFormat = 'YEAR(ps.created_at), MONTH(ps.created_at)';
            $groupBy = 'YEAR(ps.created_at), MONTH(ps.created_at)';
            
            if ($period === 'daily') {
                $dateFormat = 'DATE(ps.created_at)';
                $groupBy = 'DATE(ps.created_at)';
            } elseif ($period === 'weekly') {
                $dateFormat = 'YEAR(ps.created_at), WEEK(ps.created_at)';
                $groupBy = 'YEAR(ps.created_at), WEEK(ps.created_at)';
            }

            $query = $this->db->prepare("
                SELECT 
                    {$dateFormat} as period,
                    COALESCE(SUM(ps.final_amount), 0) as revenue,
                    COALESCE(SUM(
                        COALESCE(
                            (SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, 0))
                             FROM pos_sale_items psi 
                             LEFT JOIN products p ON psi.item_id = p.id 
                             WHERE psi.pos_sale_id = ps.id),
                            ps.final_amount * 0.8
                        )
                    ), 0) as cost
                FROM pos_sales ps
                WHERE ps.company_id = :company_id
                AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY {$groupBy}
                ORDER BY period DESC
                LIMIT 30
            ");
            $query->execute(['company_id' => $company_id]);
            
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($row) {
                $revenue = (float)$row['revenue'];
                $cost = (float)$row['cost'];
                $profit = $revenue - $cost;
                $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                
                return [
                    'period' => $row['period'],
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin' => round($margin, 2)
                ];
            }, $results);
        } catch (\Exception $e) {
            error_log("AnalyticsService::getProfitBreakdown error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top customers by revenue
     * 
     * @param int $company_id
     * @param int $limit
     * @param string|null $date_from Optional date range start
     * @param string|null $date_to Optional date range end
     * @return array
     */
    public function getTopCustomers($company_id, $limit = 10, $date_from = null, $date_to = null) {
        try {
            $whereClause = "c.company_id = :company_id";
            $params = [
                'company_id' => $company_id
            ];
            
            // Add date range filter if provided - use datetime comparison for accuracy
            if ($date_from && $date_to) {
                $whereClause .= " AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end";
                $params['date_from_start'] = $date_from . ' 00:00:00';
                $params['date_to_end'] = $date_to . ' 23:59:59';
            } elseif ($date_from) {
                $whereClause .= " AND ps.created_at >= :date_from_start";
                $params['date_from_start'] = $date_from . ' 00:00:00';
            } elseif ($date_to) {
                $whereClause .= " AND ps.created_at <= :date_to_end";
                $params['date_to_end'] = $date_to . ' 23:59:59';
            }
            
            $query = $this->db->prepare("
                SELECT 
                    c.id,
                    c.full_name,
                    c.phone_number,
                    COUNT(ps.id) as transaction_count,
                    COALESCE(SUM(ps.final_amount), 0) as total_revenue,
                    MAX(ps.created_at) as last_transaction
                FROM customers c
                INNER JOIN pos_sales ps ON c.id = ps.customer_id
                WHERE {$whereClause}
                GROUP BY c.id, c.full_name, c.phone_number
                ORDER BY total_revenue DESC
                LIMIT :limit
            ");
            
            // Bind parameters
            foreach ($params as $key => $value) {
                if ($key === 'company_id') {
                    $query->bindValue(':' . $key, $value, PDO::PARAM_INT);
                } else {
                    $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
                }
            }
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->execute();
            
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'name' => $row['full_name'],
                    'phone' => $row['phone_number'],
                    'transaction_count' => (int)$row['transaction_count'],
                    'total_revenue' => (float)$row['total_revenue'],
                    'last_transaction' => $row['last_transaction']
                ];
            }, $results);
        } catch (\Exception $e) {
            error_log("AnalyticsService::getTopCustomers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top products by sales
     * 
     * @param int $company_id
     * @param int $limit
     * @param string|null $date_from Optional date range start
     * @param string|null $date_to Optional date range end
     * @return array
     */
    public function getTopProducts($company_id, $limit = 10, $date_from = null, $date_to = null) {
        try {
            // Determine which products table to use
            $checkTable = $this->db->query("SHOW TABLES LIKE 'products_new'");
            $productsTable = ($checkTable && $checkTable->rowCount() > 0) ? 'products_new' : 'products';
            
            // Start with company_id filter on pos_sales table
            $whereClause = "ps.company_id = :company_id";
            $params = [
                'company_id' => $company_id
            ];
            
            // Add date range filter if provided - use datetime comparison for accuracy
            if ($date_from && $date_to) {
                $whereClause .= " AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end";
                $params['date_from_start'] = $date_from . ' 00:00:00';
                $params['date_to_end'] = $date_to . ' 23:59:59';
            } elseif ($date_from) {
                $whereClause .= " AND ps.created_at >= :date_from_start";
                $params['date_from_start'] = $date_from . ' 00:00:00';
            } elseif ($date_to) {
                $whereClause .= " AND ps.created_at <= :date_to_end";
                $params['date_to_end'] = $date_to . ' 23:59:59';
            } else {
                // Default to last 90 days if no date range provided
                $whereClause .= " AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            }
            
            // Simplified query - just group by item_description to get top selling items
            // This will work regardless of whether products are linked via item_id
            $query = $this->db->prepare("
                SELECT 
                    psi.item_description as name,
                    SUM(psi.quantity) as units_sold,
                    COALESCE(SUM(psi.total_price), 0) as total_revenue,
                    COUNT(DISTINCT psi.pos_sale_id) as sale_count
                FROM pos_sale_items psi
                INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                WHERE {$whereClause}
                GROUP BY psi.item_description
                HAVING SUM(psi.quantity) > 0
                ORDER BY units_sold DESC
                LIMIT :limit
            ");
            
            // Bind parameters
            foreach ($params as $key => $value) {
                if ($key === 'company_id') {
                    $query->bindValue(':' . $key, $value, PDO::PARAM_INT);
                } else {
                    $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
                }
            }
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            error_log("getTopProducts: Executing query with params: " . json_encode($params));
            error_log("getTopProducts: WHERE clause: " . $whereClause);
            
            $query->execute();
            
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("getTopProducts: Query returned " . count($results) . " products for date range " . ($date_from ?? 'null') . " to " . ($date_to ?? 'null'));
            if (count($results) > 0) {
                error_log("getTopProducts: Sample product: " . json_encode($results[0]));
            } else {
                error_log("getTopProducts: No products found - checking if there are any sales in date range");
                // Debug query to check if there are any sales
                $debugQuery = $this->db->prepare("SELECT COUNT(*) as count FROM pos_sales ps WHERE ps.company_id = :company_id AND ps.created_at >= :date_from_start AND ps.created_at <= :date_to_end");
                $debugQuery->execute([
                    'company_id' => $company_id,
                    'date_from_start' => ($date_from ?? date('Y-m-d', strtotime('-90 days'))) . ' 00:00:00',
                    'date_to_end' => ($date_to ?? date('Y-m-d')) . ' 23:59:59'
                ]);
                $debugResult = $debugQuery->fetch(PDO::FETCH_ASSOC);
                error_log("getTopProducts: Sales count in date range: " . ($debugResult['count'] ?? 0));
            }
            
            return array_map(function($row) {
                return [
                    'id' => 0,
                    'sku' => '',
                    'name' => $row['name'] ?? 'Unknown Product',
                    'brand' => '',
                    'category' => '',
                    'units_sold' => (int)$row['units_sold'],
                    'total_revenue' => (float)$row['total_revenue'],
                    'sale_count' => (int)$row['sale_count']
                ];
            }, $results);
        } catch (\Exception $e) {
            error_log("AnalyticsService::getTopProducts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get time series data for charts
     * 
     * @param int $company_id
     * @param string $from
     * @param string $to
     * @param string $type 'sales', 'repairs', 'swaps', or 'all'
     * @return array
     */
    public function getTimeSeriesData($company_id, $from, $to, $type = 'all') {
        try {
            $results = [
                'labels' => [],
                'sales' => [],
                'repairs' => [],
                'swaps' => []
            ];

            // Generate date range
            try {
                $start = new \DateTime($from);
                $end = new \DateTime($to);
                $end->modify('+1 day'); // Include end date
                $interval = new \DateInterval('P1D');
                $dateRange = new \DatePeriod($start, $interval, $end);
                
                $labels = [];
                foreach ($dateRange as $date) {
                    $labels[] = $date->format('Y-m-d');
                }
                // Ensure labels are sorted chronologically (oldest to newest) - left to right
                usort($labels, function($a, $b) {
                    return strcmp($a, $b); // Ascending order: oldest first
                });
                $results['labels'] = $labels;
                
                error_log("getTimeSeriesData: Generated " . count($labels) . " labels from {$from} to {$to}");
            } catch (\Exception $e) {
                error_log("getTimeSeriesData: Error generating date range: " . $e->getMessage());
                // Fallback: generate simple date range
                $labels = [];
                $current = strtotime($from);
                $endTime = strtotime($to);
                while ($current <= $endTime) {
                    $labels[] = date('Y-m-d', $current);
                    $current = strtotime('+1 day', $current);
                }
                $results['labels'] = $labels;
                error_log("getTimeSeriesData: Fallback generated " . count($labels) . " labels");
            }

            // Get sales data
            if ($type === 'all' || $type === 'sales') {
                // Use datetime comparison to ensure we capture all sales including those on the end date
                $fromStart = $from . ' 00:00:00';
                $toEnd = $to . ' 23:59:59';
                
                $salesQuery = $this->db->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(final_amount), 0) as revenue,
                        COUNT(*) as count
                    FROM pos_sales
                    WHERE company_id = :company_id
                    AND created_at >= :date_from_start
                    AND created_at <= :date_to_end
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $salesQuery->execute([
                    'company_id' => $company_id,
                    'date_from_start' => $fromStart,
                    'date_to_end' => $toEnd
                ]);
                $salesData = $salesQuery->fetchAll(PDO::FETCH_ASSOC);
                
                // Also check specifically for Nov 5 and Nov 6
                $nov5Check = $this->db->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(final_amount), 0) as revenue,
                        COUNT(*) as count
                    FROM pos_sales
                    WHERE company_id = :company_id
                    AND DATE(created_at) = '2025-11-05'
                ");
                $nov5Check->execute(['company_id' => $company_id]);
                $nov5Data = $nov5Check->fetch(PDO::FETCH_ASSOC);
                if ($nov5Data && $nov5Data['revenue'] > 0) {
                    error_log("getTimeSeriesData: Nov 5 sales found directly: " . json_encode($nov5Data));
                }
                
                $nov6Check = $this->db->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(final_amount), 0) as revenue,
                        COUNT(*) as count
                    FROM pos_sales
                    WHERE company_id = :company_id
                    AND DATE(created_at) = '2025-11-06'
                ");
                $nov6Check->execute(['company_id' => $company_id]);
                $nov6Data = $nov6Check->fetch(PDO::FETCH_ASSOC);
                if ($nov6Data && $nov6Data['revenue'] > 0) {
                    error_log("getTimeSeriesData: Nov 6 sales found directly: " . json_encode($nov6Data));
                }
                
                error_log("getTimeSeriesData: Sales query returned " . count($salesData) . " records for date range {$from} to {$to}");
                if (count($salesData) > 0) {
                    error_log("getTimeSeriesData: Sample sales data: " . json_encode($salesData[0]));
                    error_log("getTimeSeriesData: All sales data: " . json_encode($salesData));
                }
                
                $salesMap = [];
                foreach ($salesData as $row) {
                    // DATE() function returns Y-m-d format directly, but handle edge cases
                    $dateKey = $row['date'];
                    
                    // Normalize date format - handle various formats
                    if (is_string($dateKey)) {
                        // If it contains time, extract just the date part
                        if (strpos($dateKey, ' ') !== false) {
                            $dateKey = substr($dateKey, 0, 10); // Get YYYY-MM-DD part
                        }
                        // Only normalize if it's not already in Y-m-d format
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                            // Already in correct format, use as-is
                            // No need to normalize
                        } else {
                            // Normalize to Y-m-d format
                            $dateKey = date('Y-m-d', strtotime($dateKey));
                        }
                    } else {
                        // If it's already a date object or timestamp
                        $dateKey = date('Y-m-d', is_numeric($dateKey) ? $dateKey : strtotime($dateKey));
                    }
                    
                    error_log("getTimeSeriesData: Mapping date '{$row['date']}' -> '{$dateKey}' with revenue: " . $row['revenue']);
                    
                    $salesMap[$dateKey] = [
                        'revenue' => (float)$row['revenue'],
                        'count' => (int)$row['count']
                    ];
                }
                
                error_log("getTimeSeriesData: Sales map has " . count($salesMap) . " entries");
                if (count($salesMap) > 0) {
                    $mapKeys = array_keys($salesMap);
                    error_log("getTimeSeriesData: Sample sales map keys: " . implode(', ', array_slice($mapKeys, 0, 5)));
                    error_log("getTimeSeriesData: Sample sales map values: " . json_encode(array_slice($salesMap, 0, 3, true)));
                }
                
                // Check if Nov 5 and Nov 6 are in the map
                $nov5Key = date('Y-m-d', strtotime('2025-11-05'));
                $nov6Key = date('Y-m-d', strtotime('2025-11-06'));
                if (isset($salesMap[$nov5Key])) {
                    error_log("getTimeSeriesData: Found Nov 5 data: " . json_encode($salesMap[$nov5Key]));
                } else {
                    error_log("getTimeSeriesData: Nov 5 ({$nov5Key}) NOT found in sales map");
                }
                if (isset($salesMap[$nov6Key])) {
                    error_log("getTimeSeriesData: Found Nov 6 data: " . json_encode($salesMap[$nov6Key]));
                } else {
                    error_log("getTimeSeriesData: Nov 6 ({$nov6Key}) NOT found in sales map");
                }
                
                foreach ($labels as $index => $label) {
                    // Normalize label to Y-m-d format
                    $normalizedLabel = $label;
                    if (is_string($label) && strpos($label, ' ') !== false) {
                        $normalizedLabel = substr($label, 0, 10);
                    }
                    // Only normalize if not already in Y-m-d format
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedLabel)) {
                        $normalizedLabel = date('Y-m-d', strtotime($normalizedLabel));
                    }
                    
                    // Try exact match first
                    $revenue = $salesMap[$normalizedLabel] ?? null;
                    
                    // If no exact match, try date comparison (in case of timezone issues)
                    if (!$revenue) {
                        foreach ($salesMap as $mapDate => $mapData) {
                            $normalizedMapDate = $mapDate; // Already normalized
                            if ($normalizedMapDate === $normalizedLabel) {
                                $revenue = $mapData;
                                break;
                            }
                        }
                    }
                    
                    $results['sales'][] = $revenue ?? ['revenue' => 0, 'count' => 0];
                    
                    // Log if this is Nov 5 or Nov 6
                    if ($normalizedLabel === $nov5Key || $normalizedLabel === $nov6Key) {
                        $revenueValue = $revenue ? $revenue['revenue'] : 0;
                        $mapKeys = array_keys($salesMap);
                        error_log("getTimeSeriesData: Label '{$label}' -> normalized '{$normalizedLabel}' (index {$index}) mapped to revenue: {$revenueValue}");
                        error_log("getTimeSeriesData: Sales map keys: " . implode(', ', $mapKeys));
                        error_log("getTimeSeriesData: Looking for '{$normalizedLabel}' in map, found: " . (isset($salesMap[$normalizedLabel]) ? 'YES' : 'NO'));
                    }
                }
                
                // Log a sample to verify data is being mapped
                $nonZeroCount = 0;
                foreach ($results['sales'] as $sale) {
                    if ($sale['revenue'] > 0) $nonZeroCount++;
                }
                error_log("getTimeSeriesData: Sales results have " . $nonZeroCount . " non-zero entries out of " . count($results['sales']));
            }

            // Get repairs data
            if ($type === 'all' || $type === 'repairs') {
                $repairsQuery = $this->db->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(total_cost), 0) as revenue,
                        COUNT(*) as count
                    FROM repairs
                    WHERE company_id = :company_id
                    AND DATE(created_at) >= :date_from
                    AND DATE(created_at) <= :date_to
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $repairsQuery->execute([
                    'company_id' => $company_id,
                    'date_from' => $from,
                    'date_to' => $to
                ]);
                $repairsData = $repairsQuery->fetchAll(PDO::FETCH_ASSOC);
                
                $repairsMap = [];
                foreach ($repairsData as $row) {
                    $repairsMap[$row['date']] = [
                        'revenue' => (float)$row['revenue'],
                        'count' => (int)$row['count']
                    ];
                }
                
                foreach ($labels as $label) {
                    $results['repairs'][] = $repairsMap[$label] ?? ['revenue' => 0, 'count' => 0];
                }
            }

            // Get swaps data
            if ($type === 'all' || $type === 'swaps') {
                // Check if total_value column exists
                $hasTotalValue = false;
                try {
                    $checkCol = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
                    $hasTotalValue = $checkCol->rowCount() > 0;
                } catch (\Exception $e) {
                    $hasTotalValue = false;
                }
                
                // Build revenue calculation based on available columns
                if ($hasTotalValue) {
                    $revenueCalc = "COALESCE(SUM(total_value), 0)";
                } else {
                    // Try to use final_price or calculate from other columns
                    $hasFinalPrice = false;
                    try {
                        $checkCol = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
                        $hasFinalPrice = $checkCol->rowCount() > 0;
                    } catch (\Exception $e) {
                        $hasFinalPrice = false;
                    }
                    
                    if ($hasFinalPrice) {
                        $revenueCalc = "COALESCE(SUM(final_price), 0)";
                    } else {
                        // If no revenue column exists, use 0
                        $revenueCalc = "0";
                    }
                }
                
                $swapsQuery = $this->db->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        {$revenueCalc} as revenue,
                        COUNT(*) as count
                    FROM swaps
                    WHERE company_id = :company_id
                    AND DATE(created_at) >= :date_from
                    AND DATE(created_at) <= :date_to
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $swapsQuery->execute([
                    'company_id' => $company_id,
                    'date_from' => $from,
                    'date_to' => $to
                ]);
                $swapsData = $swapsQuery->fetchAll(PDO::FETCH_ASSOC);
                
                $swapsMap = [];
                foreach ($swapsData as $row) {
                    $swapsMap[$row['date']] = [
                        'revenue' => (float)$row['revenue'],
                        'count' => (int)$row['count']
                    ];
                }
                
                foreach ($labels as $label) {
                    $results['swaps'][] = $swapsMap[$label] ?? ['revenue' => 0, 'count' => 0];
                }
            }

            return $results;
        } catch (\Exception $e) {
            error_log("AnalyticsService::getTimeSeriesData error: " . $e->getMessage());
            return [
                'labels' => [],
                'sales' => [],
                'repairs' => [],
                'swaps' => []
            ];
        }
    }

    /**
     * Generate smart recommendations for a company
     * 
     * @param int $companyId
     * @param bool $saveToDatabase Save recommendations to database
     * @return array Generated recommendations
     */
    public function generateRecommendations($companyId, $saveToDatabase = true) {
        $recommendations = [];
        $forecastService = new ForecastService();
        $recommendationModel = new SmartRecommendation();

        // Check enabled modules
        $moduleModel = new CompanyModule();
        $enabledModules = $moduleModel->getEnabledModules($companyId);

        // 1. Inventory/Sales recommendations
        if (in_array('pos_sales', $enabledModules)) {
            $restockForecast = $forecastService->forecastRestockNeeds($companyId, 14);
            
            if ($restockForecast['success'] && !empty($restockForecast['products'])) {
                foreach ($restockForecast['products'] as $product) {
                    if ($product['priority'] === 'high') {
                        $recommendations[] = [
                            'company_id' => $companyId,
                            'title' => "Restock Alert: {$product['product_name']}",
                            'message' => sprintf(
                                "Your %s stock will run out in %.1f days at the current sales rate. Recommended restock: %d units.",
                                $product['product_name'],
                                $product['days_until_out'],
                                $product['restock_quantity']
                            ),
                            'type' => 'inventory',
                            'priority' => $product['priority'],
                            'confidence' => $product['confidence'],
                            'action_url' => BASE_URL_PATH . '/dashboard/inventory'
                        ];
                    }
                }
            }

            // 2. Profit optimization recommendations
            $topProducts = $this->getTopProducts($companyId, 20);
            foreach ($topProducts as $product) {
                $margin = 0;
                if ($product['revenue'] > 0 && $product['cost'] > 0) {
                    $margin = (($product['revenue'] - $product['cost']) / $product['revenue']) * 100;
                }

                // Flag low margin products
                if ($margin > 0 && $margin < 20 && $product['units_sold'] > 5) {
                    $recommendedPrice = $product['cost'] * 1.3; // Suggest 30% markup
                    
                    $recommendations[] = [
                        'company_id' => $companyId,
                        'title' => "Low Margin Alert: {$product['name']}",
                        'message' => sprintf(
                            "%s has a low profit margin of %.1f%%. Consider adjusting price to ₵%.2f for better profitability (current: ₵%.2f).",
                            $product['name'],
                            $margin,
                            $recommendedPrice,
                            $product['price']
                        ),
                        'type' => 'profit',
                        'priority' => 'medium',
                        'confidence' => 0.75,
                        'action_url' => BASE_URL_PATH . '/dashboard/products/edit/' . $product['id']
                    ];
                }
            }
        }

        // 3. Swap recommendations
        if (in_array('swaps', $enabledModules)) {
            // Check for undervalued swaps
            $stmt = $this->db->prepare("
                SELECT 
                    s.id,
                    s.customer_brand,
                    s.customer_model,
                    s.estimated_value,
                    s.resell_price,
                    s.created_at
                FROM swaps s
                WHERE s.company_id = :company_id
                AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND s.resell_price > 0
                ORDER BY s.created_at DESC
                LIMIT 50
            ");
            $stmt->execute(['company_id' => $companyId]);
            $swaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalLoss = 0;
            $count = 0;
            foreach ($swaps as $swap) {
                if ($swap['estimated_value'] > 0 && $swap['resell_price'] < $swap['estimated_value'] * 0.8) {
                    $totalLoss += ($swap['estimated_value'] - $swap['resell_price']);
                    $count++;
                }
            }

            if ($count > 0 && $totalLoss > 100) {
                $avgLoss = $totalLoss / $count;
                $weeklyLoss = ($totalLoss / 30) * 7;

                $recommendations[] = [
                    'company_id' => $companyId,
                    'title' => "Swap Pricing Optimization",
                    'message' => sprintf(
                        "You're losing an average of ₵%.2f per swap due to undervalued phones. Estimated weekly loss: ₵%.2f. Consider reviewing swap valuation criteria.",
                        $avgLoss,
                        $weeklyLoss
                    ),
                    'type' => 'swap',
                    'priority' => 'medium',
                    'confidence' => 0.8,
                    'action_url' => BASE_URL_PATH . '/dashboard/swaps'
                ];
            }
        }

        // 4. Repair recommendations
        if (in_array('repairs', $enabledModules)) {
            $repairForecast = $forecastService->forecastRepairDemand($companyId, 7);
            
            if ($repairForecast['success']) {
                $avgDaily = $repairForecast['avg_daily_repairs'];
                $trend = $repairForecast['trend'];

                // Check if repair demand is increasing significantly
                if ($trend['direction'] === 'increasing' && $trend['slope'] > 0.5) {
                    $recommendations[] = [
                        'company_id' => $companyId,
                        'title' => "Increasing Repair Demand Detected",
                        'message' => sprintf(
                            "Repair bookings increased by %.1f per day. Consider adding staff or extending operating hours to handle the increased demand.",
                            $trend['slope']
                        ),
                        'type' => 'repair',
                        'priority' => 'medium',
                        'confidence' => 0.7,
                        'action_url' => BASE_URL_PATH . '/dashboard/repairs'
                    ];
                }
            }
        }

        // 5. Sales trend recommendations
        if (in_array('pos_sales', $enabledModules)) {
            $salesForecast = $forecastService->predictSales($companyId, 'weekly', 7);
            
            if ($salesForecast['success'] && isset($salesForecast['trend'])) {
                $trend = $salesForecast['trend'];
                
                if ($trend['direction'] === 'down' && abs($trend['slope']) > 50) {
                    $recommendations[] = [
                        'company_id' => $companyId,
                        'title' => "Declining Sales Trend",
                        'message' => sprintf(
                            "Sales are declining by ₵%.2f per day. Consider running promotions or reviewing pricing strategy to reverse the trend.",
                            abs($trend['slope'])
                        ),
                        'type' => 'sales',
                        'priority' => 'high',
                        'confidence' => 0.75,
                        'action_url' => BASE_URL_PATH . '/dashboard/audit-trail'
                    ];
                } elseif ($trend['direction'] === 'up' && $trend['slope'] > 100) {
                    $recommendations[] = [
                        'company_id' => $companyId,
                        'title' => "Strong Sales Growth",
                        'message' => sprintf(
                            "Great! Sales are growing by ₵%.2f per day. Consider increasing inventory to meet the growing demand.",
                            $trend['slope']
                        ),
                        'type' => 'sales',
                        'priority' => 'low',
                        'confidence' => 0.8,
                        'action_url' => BASE_URL_PATH . '/dashboard/inventory'
                    ];
                }
            }
        }

        // Save to database if requested
        if ($saveToDatabase && !empty($recommendations)) {
            foreach ($recommendations as $rec) {
                try {
                    // Set expiration (30 days)
                    $rec['expires_at'] = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $recommendationModel->create($rec);
                } catch (\Exception $e) {
                    error_log("Error saving recommendation: " . $e->getMessage());
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get profit optimization suggestions
     * 
     * @param int $companyId
     * @return array
     */
    public function getProfitOptimizationSuggestions($companyId) {
        try {
            $suggestions = [];
            $topProducts = $this->getTopProducts($companyId, 50);

            foreach ($topProducts as $product) {
                if ($product['units_sold'] < 3) continue; // Skip low-volume products

                $cost = $product['cost'] ?? 0;
                $price = $product['price'] ?? 0;
                $revenue = $product['revenue'] ?? 0;

                if ($cost <= 0 || $price <= 0) continue;

                $margin = (($revenue - ($cost * $product['units_sold'])) / $revenue) * 100;
                $currentMarkup = (($price - $cost) / $cost) * 100;

                // Calculate price elasticity estimate (simplified)
                $avgPrice = $price;
                $avgQuantity = $product['units_sold'];
                $elasticity = -1.5; // Assumed elasticity (can be refined with historical data)

                // Suggest optimal price based on margin and demand
                if ($margin < 25 && $currentMarkup < 30) {
                    // Low margin - suggest price increase
                    $optimalPrice = $cost * 1.35; // 35% markup
                    $estimatedProfitIncrease = (($optimalPrice - $price) * $avgQuantity * 0.9); // 90% of volume retained

                    $suggestions[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'current_price' => $price,
                        'current_cost' => $cost,
                        'current_margin' => round($margin, 2),
                        'suggested_price' => round($optimalPrice, 2),
                        'suggested_margin' => round((($optimalPrice - $cost) / $optimalPrice) * 100, 2),
                        'estimated_profit_increase' => round($estimatedProfitIncrease, 2),
                        'rationale' => "Low profit margin detected. Increasing price will improve profitability."
                    ];
                } elseif ($margin > 50 && $currentMarkup > 50) {
                    // High margin - consider price reduction to increase volume
                    $optimalPrice = $cost * 1.25; // 25% markup
                    $estimatedVolumeIncrease = $avgQuantity * 1.2; // 20% volume increase
                    $estimatedProfitIncrease = (($optimalPrice * $estimatedVolumeIncrease) - ($cost * $estimatedVolumeIncrease)) - (($price * $avgQuantity) - ($cost * $avgQuantity));

                    if ($estimatedProfitIncrease > 0) {
                        $suggestions[] = [
                            'product_id' => $product['id'],
                            'product_name' => $product['name'],
                            'current_price' => $price,
                            'current_cost' => $cost,
                            'current_margin' => round($margin, 2),
                            'suggested_price' => round($optimalPrice, 2),
                            'suggested_margin' => round((($optimalPrice - $cost) / $optimalPrice) * 100, 2),
                            'estimated_profit_increase' => round($estimatedProfitIncrease, 2),
                            'rationale' => "High margin product. Price reduction may increase volume and total profit."
                        ];
                    }
                }
            }

            // Sort by estimated profit increase
            usort($suggestions, function($a, $b) {
                return $b['estimated_profit_increase'] <=> $a['estimated_profit_increase'];
            });

            return array_slice($suggestions, 0, 10); // Top 10 suggestions
        } catch (\Exception $e) {
            error_log("AnalyticsService::getProfitOptimizationSuggestions error: " . $e->getMessage());
            return [];
        }
    }
}

