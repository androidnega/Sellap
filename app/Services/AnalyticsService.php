<?php

namespace App\Services;

use PDO;
use App\Models\CompanyModule;
use App\Models\SmartRecommendation;

require_once __DIR__ . '/../../config/database.php';

class AnalyticsService {
    private $db;
    private $quantityColumn;

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
        // Check if is_swap_mode column exists
        $hasIsSwapMode = false;
        try {
            $checkIsSwapMode = $this->db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("AnalyticsService::getSalesStats: Error checking is_swap_mode column: " . $e->getMessage());
        }
        
        // Exclude swap sales from sales history (swaps should only appear on swap page)
        $excludeSwapSales = $hasIsSwapMode ? " AND (is_swap_mode = 0 OR is_swap_mode IS NULL)" : "";
        
        // Exclude repair-related sales from salesperson stats (when staff_id is provided)
        // Repair sales have notes like "Repair #X - Products sold by repairer"
        $excludeRepairSales = $staff_id ? " AND (notes IS NULL OR (notes NOT LIKE '%Repair #%' AND notes NOT LIKE '%Products sold by repairer%'))" : "";
        
        $todayWhere = "company_id = :company_id AND DATE(created_at) = CURDATE(){$excludeSwapSales}{$excludeRepairSales}";
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
        $monthlyWhere = "company_id = :company_id AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()){$excludeSwapSales}{$excludeRepairSales}";
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

        // Filtered stats (for selected date range) or ALL-TIME if no dates
        $filtered = null;
        // If both dates are null/empty, calculate ALL-TIME stats
        // If at least one date is provided, calculate filtered stats for that range
        if (empty($date_from) && empty($date_to)) {
            // ALL-TIME: No date filtering
            $where = "company_id = :company_id{$excludeSwapSales}{$excludeRepairSales}";
            $params = ['company_id' => $company_id];
            
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
        } elseif ($date_from || $date_to) {
            // Date range filtering
            $where = "company_id = :company_id{$excludeSwapSales}{$excludeRepairSales}";
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
     * @param int|null $staff_id Optional staff member filter (technician)
     * @return array
     */
    public function getRepairStats($company_id, $date_from = null, $date_to = null, $staff_id = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $costColumn = $hasRepairsNew ? 'total_cost' : 'total_cost';
        
        // Determine technician column name
        $technicianColumn = null;
        if ($hasRepairsNew) {
            $technicianColumn = 'assigned_technician_id';
        } else {
            $technicianColumn = 'technician_id';
        }
        
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
        
        // Add staff filter if provided
        if ($staff_id) {
            $where .= " AND {$technicianColumn} = :staff_id";
            $params['staff_id'] = $staff_id;
        }

        // Active repairs - check status column name
        $activeWhere = "company_id = :company_id";
        $activeParams = ['company_id' => $company_id];
        if ($staff_id) {
            $activeWhere .= " AND {$technicianColumn} = :staff_id";
            $activeParams['staff_id'] = $staff_id;
        }
        
        if ($hasRepairsNew) {
            $activeQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM {$repairsTable} 
                WHERE {$activeWhere}
                AND status IN ('pending', 'in_progress', 'PENDING', 'IN_PROGRESS')
            ");
        } else {
            $activeQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM {$repairsTable} 
                WHERE {$activeWhere}
                AND UPPER(repair_status) IN ('PENDING', 'IN_PROGRESS')
            ");
        }
        $activeQuery->execute($activeParams);
        $active = (int)$activeQuery->fetchColumn();

        // Monthly repairs
        $monthlyWhere = "company_id = :company_id 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())";
        $monthlyParams = ['company_id' => $company_id];
        if ($staff_id) {
            $monthlyWhere .= " AND {$technicianColumn} = :staff_id";
            $monthlyParams['staff_id'] = $staff_id;
        }
        
        $monthlyQuery = $this->db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM({$costColumn}), 0) as revenue
            FROM {$repairsTable} 
            WHERE {$monthlyWhere}
        ");
        $monthlyQuery->execute($monthlyParams);
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
     * @param int|null $staff_id Optional staff member filter
     * @return array
     */
    public function getSwapStats($company_id, $date_from = null, $date_to = null, $staff_id = null) {
        // Log input parameters
        error_log("getSwapStats called: company_id={$company_id}, date_from={$date_from}, date_to={$date_to}, staff_id=" . ($staff_id ?? 'null'));
        
        // Check which staff column exists in swaps table
        $staffColumn = null;
        try {
            $checkSalespersonId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'salesperson_id'");
            $checkHandledBy = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'handled_by'");
            $checkCreatedByUserId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'created_by_user_id'");
            $checkCreatedBy = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'created_by'");
            
            if ($checkSalespersonId->rowCount() > 0) {
                $staffColumn = 'salesperson_id';
            } elseif ($checkHandledBy->rowCount() > 0) {
                $staffColumn = 'handled_by';
            } elseif ($checkCreatedByUserId->rowCount() > 0) {
                $staffColumn = 'created_by_user_id';
            } elseif ($checkCreatedBy->rowCount() > 0) {
                $staffColumn = 'created_by';
            }
        } catch (\Exception $e) {
            error_log("getSwapStats: Error checking staff columns: " . $e->getMessage());
        }
        
        $where = "s.company_id = :company_id";
        $params = ['company_id' => $company_id];

        if ($date_from) {
            $where .= " AND DATE(s.created_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(s.created_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        // Add staff filter if provided and column exists
        if ($staff_id && $staffColumn) {
            $where .= " AND s.{$staffColumn} = :staff_id";
            $params['staff_id'] = $staff_id;
        }
        
        // Create where clause without alias for simple queries
        $whereNoAlias = str_replace('s.', '', $where);
        
        // Debug: Check if any swaps exist at all for this company
        try {
            $totalCheck = $this->db->prepare("SELECT COUNT(*) as total FROM swaps WHERE company_id = :company_id");
            $totalCheck->execute(['company_id' => $company_id]);
            $totalSwaps = (int)$totalCheck->fetchColumn();
            error_log("getSwapStats: Total swaps for company {$company_id}: {$totalSwaps}");
            
            if ($date_from || $date_to) {
                $rangeCheck = $this->db->prepare("SELECT COUNT(*) as total FROM swaps WHERE {$whereNoAlias}");
                $rangeCheck->execute($params);
                $rangeSwaps = (int)$rangeCheck->fetchColumn();
                error_log("getSwapStats: Swaps in date range: {$rangeSwaps}");
            }
        } catch (\Exception $e) {
            error_log("getSwapStats: Error checking swap count: " . $e->getMessage());
        }

        // Check which status column exists
        $hasStatus = false;
        $hasSwapStatus = false;
        try {
            $checkStatus = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'status'");
            $hasStatus = $checkStatus->rowCount() > 0;
            $checkSwapStatus = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'swap_status'");
            $hasSwapStatus = $checkSwapStatus->rowCount() > 0;
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Pending swaps - add staff filter if provided
        $pendingParams = ['company_id' => $company_id];
        $pendingWhere = "company_id = :company_id";
        if ($staff_id && $staffColumn) {
            $pendingWhere .= " AND {$staffColumn} = :staff_id";
            $pendingParams['staff_id'] = $staff_id;
        }
        
        $pending = 0;
        if ($hasStatus) {
            $pendingQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM swaps 
                WHERE {$pendingWhere}
                AND (UPPER(status) = 'PENDING' OR status IS NULL)
            ");
            $pendingQuery->execute($pendingParams);
            $pending = (int)$pendingQuery->fetchColumn();
        } elseif ($hasSwapStatus) {
            $pendingQuery = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM swaps 
                WHERE {$pendingWhere}
                AND UPPER(swap_status) = 'PENDING'
            ");
            $pendingQuery->execute($pendingParams);
            $pending = (int)$pendingQuery->fetchColumn();
        }

        // Check which revenue column exists (total_value, final_price, or company_product_id)
        $hasTotalValue = false;
        $hasFinalPrice = false;
        $hasCompanyProductId = false;
        try {
            $checkTotalValue = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
            $hasTotalValue = $checkTotalValue->rowCount() > 0;
            $checkFinalPrice = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
            $hasFinalPrice = $checkFinalPrice->rowCount() > 0;
            $checkCompanyProductId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_product_id'");
            $hasCompanyProductId = $checkCompanyProductId->rowCount() > 0;
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Monthly swaps - calculate revenue
        // For swaps: revenue = total_value (which is Cash Top-up + Resold Price)
        $monthly = ['count' => 0, 'revenue' => 0];
        
        // Build monthly where clause with staff filter
        $monthlyWhere = "s.company_id = :company_id 
                AND MONTH(s.created_at) = MONTH(CURDATE()) 
                AND YEAR(s.created_at) = YEAR(CURDATE())";
        $monthlyParams = ['company_id' => $company_id];
        if ($staff_id && $staffColumn) {
            $monthlyWhere .= " AND s.{$staffColumn} = :staff_id";
            $monthlyParams['staff_id'] = $staff_id;
        }
        
        if ($hasTotalValue) {
            // Use total_value which should contain Cash Top-up + Resold Price
            // total_value is calculated as: Cash Top-up (final_price - given_phone_value or added_cash) + Resold Price
            // For swaps where total_value is 0 or NULL, fallback to added_cash to ensure cash top-ups are counted immediately
            $monthlyQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(
                        CASE 
                            WHEN s.total_value > 0 THEN s.total_value
                            WHEN s.added_cash > 0 THEN s.added_cash
                            ELSE 0
                        END
                    ), 0) as revenue
                FROM swaps s
                WHERE {$monthlyWhere}
            ");
            $monthlyQuery->execute($monthlyParams);
            $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
        } elseif ($hasFinalPrice) {
            // final_price exists - calculate cash top-up from final_price - customer_product_value
            // Check if swapped_items table exists to get customer product value
            $hasSwappedItems = false;
            try {
                $check = $this->db->query("SHOW TABLES LIKE 'swapped_items'");
                $hasSwappedItems = $check->rowCount() > 0;
            } catch (Exception $e) {
                $hasSwappedItems = false;
            }
            
            if ($hasSwappedItems) {
                // Calculate cash top-up: final_price - estimated_value (customer product value)
                // Use subquery to get first swapped_item per swap
                try {
                    $monthlyQuery = $this->db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(cash_topup), 0) as revenue
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
                            WHERE {$monthlyWhere}
                        ) as swap_calc
                    ");
                    $monthlyQuery->execute($monthlyParams);
                    $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
                    if (!$monthly) {
                        $monthly = ['count' => 0, 'revenue' => 0];
                    }
                } catch (\Exception $e) {
                    // Fallback for older MySQL versions (no ROW_NUMBER)
                    error_log("AnalyticsService getSwapStats: ROW_NUMBER() not supported, using fallback: " . $e->getMessage());
                    $monthlyQuery = $this->db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(cash_topup), 0) as revenue
                        FROM (
                            SELECT s.id,
                                CASE 
                                    WHEN s.final_price > 0 AND si.estimated_value > 0 AND (s.final_price - si.estimated_value) > 0 
                                        THEN (s.final_price - si.estimated_value)
                                    ELSE 0
                                END as cash_topup
                            FROM swaps s
                            LEFT JOIN swapped_items si ON s.id = si.swap_id
                            WHERE {$monthlyWhere}
                            GROUP BY s.id
                        ) as swap_calc
                    ");
                    $monthlyQuery->execute($monthlyParams);
                    $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
                    if (!$monthly) {
                        $monthly = ['count' => 0, 'revenue' => 0];
                    }
                }
            } else {
                // No swapped_items table - can't calculate cash top-up, use 0
                $monthlyWhereNoAlias = str_replace('s.', '', $monthlyWhere);
                $monthlyQuery = $this->db->prepare("
                    SELECT 
                        COUNT(*) as count,
                        0 as revenue
                    FROM swaps 
                    WHERE {$monthlyWhereNoAlias}
                ");
                $monthlyQuery->execute($monthlyParams);
                $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
            }
        } elseif ($hasCompanyProductId) {
            // Calculate from company_product price if company_product_id exists
            $monthlyQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(sp.price), 0) as revenue
                FROM swaps s
                LEFT JOIN products sp ON s.company_product_id = sp.id
                WHERE {$monthlyWhere}
            ");
            $monthlyQuery->execute($monthlyParams);
            $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
        } else {
            // No revenue column found - count only
            $monthlyWhereNoAlias = str_replace('s.', '', $monthlyWhere);
            $monthlyQuery = $this->db->prepare("
                SELECT 
                    COUNT(*) as count,
                    0 as revenue
                FROM swaps 
                WHERE {$monthlyWhereNoAlias}
            ");
            $monthlyQuery->execute($monthlyParams);
            $monthly = $monthlyQuery->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get monthly profit (for current month)
        $monthlyProfit = 0;
        try {
            // Check if swap_profit_links table exists
            $checkProfitTable = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
            if ($checkProfitTable->rowCount() > 0) {
                // Only count finalized profits when customer item has been resold
                // Never count estimated profits as realized gains
                $monthlyProfitWhere = "s.company_id = :company_id 
                    AND spl.customer_item_sale_id IS NOT NULL
                    AND MONTH(s.created_at) = MONTH(CURDATE()) 
                    AND YEAR(s.created_at) = YEAR(CURDATE())";
                $monthlyProfitParams = ['company_id' => $company_id];
                if ($staff_id && $staffColumn) {
                    $monthlyProfitWhere .= " AND s.{$staffColumn} = :staff_id";
                    $monthlyProfitParams['staff_id'] = $staff_id;
                }
                
                $monthlyProfitQuery = $this->db->prepare("
                    SELECT COALESCE(
                        SUM(CASE 
                            WHEN spl.customer_item_sale_id IS NOT NULL AND spl.final_profit IS NOT NULL 
                            THEN spl.final_profit
                            WHEN spl.customer_item_sale_id IS NOT NULL AND spl.profit_estimate IS NOT NULL 
                            THEN spl.profit_estimate
                            ELSE 0 
                        END), 0
                    ) as profit
                    FROM swap_profit_links spl
                    INNER JOIN swaps s ON spl.swap_id = s.id
                    WHERE {$monthlyProfitWhere}
                ");
                $monthlyProfitQuery->execute($monthlyProfitParams);
                $monthlyProfitResult = $monthlyProfitQuery->fetch(PDO::FETCH_ASSOC);
                $monthlyProfit = (float)($monthlyProfitResult['profit'] ?? 0);
            }
        } catch (\Exception $e) {
            error_log("getSwapStats: Error fetching monthly profit from swap_profit_links: " . $e->getMessage());
            // Table might not exist, try alternative
            try {
                $checkFinalProfit = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_profit'");
                if ($checkFinalProfit->rowCount() > 0) {
                    $monthlyProfitWhereNoAlias = "company_id = :company_id 
                        AND final_profit IS NOT NULL
                        AND MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())";
                    $monthlyProfitParamsNoAlias = ['company_id' => $company_id];
                    if ($staff_id && $staffColumn) {
                        $monthlyProfitWhereNoAlias .= " AND {$staffColumn} = :staff_id";
                        $monthlyProfitParamsNoAlias['staff_id'] = $staff_id;
                    }
                    
                    $monthlyProfitQuery = $this->db->prepare("
                        SELECT COALESCE(SUM(final_profit), 0) as profit
                        FROM swaps 
                        WHERE {$monthlyProfitWhereNoAlias}
                    ");
                    $monthlyProfitQuery->execute($monthlyProfitParamsNoAlias);
                    $monthlyProfitResult = $monthlyProfitQuery->fetch(PDO::FETCH_ASSOC);
                    $monthlyProfit = (float)($monthlyProfitResult['profit'] ?? 0);
                }
            } catch (\Exception $e2) {
                error_log("getSwapStats: Error fetching monthly profit from swaps.final_profit: " . $e2->getMessage());
            }
        }
        
        // If monthly profit is still 0, try to calculate from swap data
        if ($monthlyProfit == 0) {
            try {
                $checkCustomerValue = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'given_phone_value'");
                $checkCompanyProductId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_product_id'");
                $hasCustomerValue = $checkCustomerValue->rowCount() > 0;
                $hasCompanyProductId = $checkCompanyProductId->rowCount() > 0;
                
                if ($hasCustomerValue && $hasCompanyProductId) {
                    // Check what cost column exists in products table
                    $checkProductCost = $this->db->query("SHOW COLUMNS FROM products LIKE 'cost'");
                    $checkPurchasePrice = $this->db->query("SHOW COLUMNS FROM products LIKE 'purchase_price'");
                    $hasProductCost = $checkProductCost->rowCount() > 0;
                    $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
                    
                    $costColumn = '0';
                    if ($hasProductCost) {
                        $costColumn = 'COALESCE(p.cost, 0)';
                    } elseif ($hasPurchasePrice) {
                        $costColumn = 'COALESCE(p.purchase_price, 0)';
                    }
                    
                    $monthlyProfitCalcQuery = $this->db->prepare("
                        SELECT COALESCE(
                            SUM(
                                COALESCE(s.given_phone_value, 0) + 
                                COALESCE(s.added_cash, 0) + 
                                COALESCE(s.cash_added, 0) - 
                                {$costColumn}
                            ), 0
                        ) as estimated_profit
                        FROM swaps s
                        LEFT JOIN products p ON s.company_product_id = p.id
                        WHERE {$monthlyWhere}
                    ");
                    $monthlyProfitCalcQuery->execute($monthlyParams);
                    $monthlyProfitCalcResult = $monthlyProfitCalcQuery->fetch(PDO::FETCH_ASSOC);
                    $estimatedMonthlyProfit = (float)($monthlyProfitCalcResult['estimated_profit'] ?? 0);
                    if ($estimatedMonthlyProfit != 0) {
                        $monthlyProfit = $estimatedMonthlyProfit;
                    }
                }
            } catch (\Exception $e3) {
                // Ignore
            }
        }

        // Swap profit (from swap_profit_links if exists)
        // Include both finalized profits and estimated profits for swaps not yet finalized
        $profit = 0;
        try {
            // Check if swap_profit_links table exists
            $checkProfitTable = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
            if ($checkProfitTable->rowCount() > 0) {
                // Check if swapped_items table exists to check resale status
                $checkSwappedItems = $this->db->query("SHOW TABLES LIKE 'swapped_items'");
                $hasSwappedItems = $checkSwappedItems->rowCount() > 0;
                
                if ($hasSwappedItems) {
                    // Use same logic as SwapController: count profit for resold items
                    // If item is resold (swapped_items.status = 'sold'), profit is realized
                    // Use profit_estimate if final_profit is NULL (matches view logic)
                    $profitQuery = $this->db->prepare("
                        SELECT COALESCE(
                            SUM(
                                CASE 
                                    -- Use final_profit if available, otherwise use profit_estimate for resold items
                                    WHEN spl.final_profit IS NOT NULL THEN spl.final_profit
                                    WHEN spl.profit_estimate IS NOT NULL THEN spl.profit_estimate
                                    ELSE 0
                                END
                            ), 0
                        ) as profit
                        FROM swap_profit_links spl
                        INNER JOIN swaps s ON spl.swap_id = s.id
                        LEFT JOIN swapped_items si ON si.swap_id = s.id
                        WHERE s.company_id = :company_id
                        AND (si.status = 'sold' OR spl.customer_item_sale_id IS NOT NULL)
                    ");
                    $profitQuery->execute(['company_id' => $company_id]);
                    $profitResult = $profitQuery->fetch(\PDO::FETCH_ASSOC);
                    $profit = (float)($profitResult['profit'] ?? 0);
                    error_log("getSwapStats: Profit from swap_profit_links (with resold check): {$profit}");
                } else {
                    // Fallback: use old logic if swapped_items table doesn't exist
                    $profitQuery = $this->db->prepare("
                        SELECT COALESCE(
                            SUM(CASE 
                                WHEN spl.customer_item_sale_id IS NOT NULL AND spl.final_profit IS NOT NULL 
                                THEN spl.final_profit
                                WHEN spl.customer_item_sale_id IS NOT NULL AND spl.profit_estimate IS NOT NULL 
                                THEN spl.profit_estimate
                                ELSE 0 
                            END), 0
                        ) as profit
                        FROM swap_profit_links spl
                        INNER JOIN swaps s ON spl.swap_id = s.id
                        WHERE s.company_id = :company_id
                        AND spl.customer_item_sale_id IS NOT NULL
                    ");
                    $profitQuery->execute(['company_id' => $company_id]);
                    $profitResult = $profitQuery->fetch(\PDO::FETCH_ASSOC);
                    $profit = (float)($profitResult['profit'] ?? 0);
                    error_log("getSwapStats: Profit from swap_profit_links (fallback): {$profit}");
                }
            } else {
                error_log("getSwapStats: swap_profit_links table does not exist");
            }
        } catch (\Exception $e) {
            error_log("getSwapStats: Error fetching profit from swap_profit_links: " . $e->getMessage());
            // Table might not exist, try alternative calculation from swaps table
            try {
                // For fallback, only count profit from swaps that have customer_item_sale_id in swap_profit_links
                $checkFinalProfit = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_profit'");
                $checkProfitLinks = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
                if ($checkFinalProfit->rowCount() > 0 && $checkProfitLinks->rowCount() > 0) {
                    $profitQuery = $this->db->prepare("
                        SELECT COALESCE(SUM(s.final_profit), 0) as profit
                        FROM swaps s
                        INNER JOIN swap_profit_links spl ON s.id = spl.swap_id
                        WHERE s.company_id = :company_id 
                        AND s.final_profit IS NOT NULL
                        AND spl.customer_item_sale_id IS NOT NULL
                    ");
                    $profitQuery->execute(['company_id' => $company_id]);
                    $profitResult = $profitQuery->fetch(\PDO::FETCH_ASSOC);
                    $profit = (float)($profitResult['profit'] ?? 0);
                    error_log("getSwapStats: Profit from swaps.final_profit (with resold check): {$profit}");
                }
            } catch (\Exception $e2) {
                error_log("getSwapStats: Error fetching profit from swaps.final_profit: " . $e2->getMessage());
            }
        }

        // Filtered stats (for date range) - ALWAYS calculate when date range is provided
        $filtered = null;
        if ($date_from || $date_to) {
            try {
                // Use the same column detection logic as monthly
                if ($hasTotalValue) {
                    // Use total_value which should contain Cash Top-up + Resold Price
                    // total_value is calculated as: Cash Top-up (final_price - given_phone_value or added_cash) + Resold Price
                    // For swaps where total_value is 0 or NULL, fallback to added_cash to ensure cash top-ups are counted immediately
                    $filteredQuery = $this->db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(
                                CASE 
                                    WHEN s.total_value > 0 THEN s.total_value
                                    WHEN s.added_cash > 0 THEN s.added_cash
                                    ELSE 0
                                END
                            ), 0) as revenue
                        FROM swaps s
                        WHERE {$where}
                    ");
                    $filteredQuery->execute($params);
                    $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                } elseif ($hasFinalPrice) {
                    // final_price exists - calculate cash top-up from final_price - customer_product_value
                    // Check if swapped_items table exists
                    $hasSwappedItems = false;
                    try {
                        $check = $this->db->query("SHOW TABLES LIKE 'swapped_items'");
                        $hasSwappedItems = $check->rowCount() > 0;
                    } catch (Exception $e) {
                        $hasSwappedItems = false;
                    }
                    
                    if ($hasSwappedItems) {
                        // Calculate cash top-up: final_price - estimated_value
                        try {
                            $filteredQuery = $this->db->prepare("
                                SELECT 
                                    COUNT(*) as count,
                                    COALESCE(SUM(cash_topup), 0) as revenue
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
                                    WHERE {$where}
                                ) as swap_calc
                            ");
                            $filteredQuery->execute($params);
                            $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                            if (!$filtered) {
                                $filtered = ['count' => 0, 'revenue' => 0];
                            }
                        } catch (\Exception $e) {
                            // Fallback for older MySQL versions
                            error_log("AnalyticsService getSwapStats: ROW_NUMBER() not supported in filtered query, using fallback: " . $e->getMessage());
                            $filteredQuery = $this->db->prepare("
                                SELECT 
                                    COUNT(*) as count,
                                    COALESCE(SUM(cash_topup), 0) as revenue
                                FROM (
                                    SELECT s.id,
                                        CASE 
                                            WHEN s.final_price > 0 AND si.estimated_value > 0 AND (s.final_price - si.estimated_value) > 0 
                                                THEN (s.final_price - si.estimated_value)
                                            ELSE 0
                                        END as cash_topup
                                    FROM swaps s
                                    LEFT JOIN swapped_items si ON s.id = si.swap_id
                                    WHERE {$where}
                                    GROUP BY s.id
                                ) as swap_calc
                            ");
                            $filteredQuery->execute($params);
                            $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                            if (!$filtered) {
                                $filtered = ['count' => 0, 'revenue' => 0];
                            }
                        }
                    } else {
                        // No swapped_items table - can't calculate cash top-up, use 0
                        $filteredQuery = $this->db->prepare("
                            SELECT 
                                COUNT(*) as count,
                                0 as revenue
                            FROM swaps 
                            WHERE {$where}
                        ");
                        $filteredQuery->execute($params);
                        $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                    }
                } elseif ($hasCompanyProductId) {
                    // Calculate from company_product price
                    $filteredQuery = $this->db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            COALESCE(SUM(sp.price), 0) as revenue
                        FROM swaps s
                        LEFT JOIN products sp ON s.company_product_id = sp.id
                        WHERE {$where}
                    ");
                    $filteredQuery->execute($params);
                    $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                } else {
                    // No revenue column found - count only
                    $filteredQuery = $this->db->prepare("
                        SELECT 
                            COUNT(*) as count,
                            0 as revenue
                        FROM swaps 
                        WHERE {$where}
                    ");
                    $filteredQuery->execute($params);
                    $filtered = $filteredQuery->fetch(PDO::FETCH_ASSOC);
                }
                
                // Ensure filtered has count and revenue even if query returns empty
                if (!$filtered) {
                    $filtered = ['count' => 0, 'revenue' => 0];
                }
                
                // Log for debugging
                error_log("getSwapStats: Filtered stats for company {$company_id}, date range {$date_from} to {$date_to}: " . json_encode($filtered));
            } catch (\Exception $e) {
                error_log("getSwapStats: Error fetching filtered stats: " . $e->getMessage());
                error_log("getSwapStats: Stack trace: " . $e->getTraceAsString());
                $filtered = ['count' => 0, 'revenue' => 0];
            }
        }

        // Swap profit for period (if date range provided, otherwise use monthly profit)
        $periodProfit = $monthlyProfit; // Default to monthly profit when no date filter
        if ($date_from || $date_to) {
            try {
                // Check if swap_profit_links table exists
                $checkProfitTable = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
                if ($checkProfitTable->rowCount() > 0) {
                    // Check if swapped_items table exists to check resale status
                    $checkSwappedItems = $this->db->query("SHOW TABLES LIKE 'swapped_items'");
                    $hasSwappedItems = $checkSwappedItems->rowCount() > 0;
                    
                    if ($hasSwappedItems) {
                        // Use same logic as SwapController: count profit for resold items
                        // If item is resold (swapped_items.status = 'sold'), profit is realized
                        // Use profit_estimate if final_profit is NULL (matches view logic)
                        $periodProfitWhere = "s.company_id = :company_id
                            AND (si.status = 'sold' OR spl.customer_item_sale_id IS NOT NULL)
                            AND (
                                (customer_sale.id IS NOT NULL AND DATE(customer_sale.created_at) >= :date_from AND DATE(customer_sale.created_at) <= :date_to)
                                OR (customer_sale.id IS NULL AND DATE(s.created_at) >= :date_from AND DATE(s.created_at) <= :date_to)
                            )";
                        $periodProfitParams = ['company_id' => $company_id];
                        if ($date_from) $periodProfitParams['date_from'] = $date_from;
                        if ($date_to) $periodProfitParams['date_to'] = $date_to;
                        if ($staff_id && $staffColumn) {
                            $periodProfitWhere .= " AND s.{$staffColumn} = :staff_id";
                            $periodProfitParams['staff_id'] = $staff_id;
                        }
                        
                        $periodProfitQuery = $this->db->prepare("
                            SELECT COALESCE(
                                SUM(
                                    CASE 
                                        WHEN spl.final_profit IS NOT NULL THEN spl.final_profit
                                        WHEN spl.profit_estimate IS NOT NULL THEN spl.profit_estimate
                                        ELSE 0
                                    END
                                ), 0
                            ) as profit
                            FROM swap_profit_links spl
                            INNER JOIN swaps s ON spl.swap_id = s.id
                            LEFT JOIN swapped_items si ON si.swap_id = s.id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE {$periodProfitWhere}
                        ");
                        $periodProfitQuery->execute($periodProfitParams);
                        $periodProfitResult = $periodProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $periodProfit = (float)($periodProfitResult['profit'] ?? 0);
                        error_log("getSwapStats: Period profit from swap_profit_links (with resold check): {$periodProfit}");
                    } else {
                        // Fallback: use old logic if swapped_items table doesn't exist
                        $periodProfitWhereFallback = "s.company_id = :company_id 
                            AND spl.customer_item_sale_id IS NOT NULL
                            AND (
                                (customer_sale.id IS NOT NULL AND DATE(customer_sale.created_at) >= :date_from AND DATE(customer_sale.created_at) <= :date_to)
                                OR (customer_sale.id IS NULL AND DATE(s.created_at) >= :date_from AND DATE(s.created_at) <= :date_to)
                            )";
                        $periodProfitParamsFallback = ['company_id' => $company_id];
                        if ($date_from) $periodProfitParamsFallback['date_from'] = $date_from;
                        if ($date_to) $periodProfitParamsFallback['date_to'] = $date_to;
                        if ($staff_id && $staffColumn) {
                            $periodProfitWhereFallback .= " AND s.{$staffColumn} = :staff_id";
                            $periodProfitParamsFallback['staff_id'] = $staff_id;
                        }
                        
                        $periodProfitQuery = $this->db->prepare("
                            SELECT COALESCE(
                                SUM(CASE 
                                    WHEN spl.customer_item_sale_id IS NOT NULL AND spl.final_profit IS NOT NULL 
                                    THEN spl.final_profit
                                    WHEN spl.customer_item_sale_id IS NOT NULL AND spl.profit_estimate IS NOT NULL 
                                    THEN spl.profit_estimate
                                    ELSE 0 
                                END), 0
                            ) as profit
                            FROM swap_profit_links spl
                            INNER JOIN swaps s ON spl.swap_id = s.id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE {$periodProfitWhereFallback}
                        ");
                        $periodProfitQuery->execute($periodProfitParamsFallback);
                        $periodProfitResult = $periodProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $periodProfit = (float)($periodProfitResult['profit'] ?? 0);
                        error_log("getSwapStats: Period profit from swap_profit_links (fallback): {$periodProfit}");
                    }
                }
            } catch (\Exception $e) {
                error_log("getSwapStats: Error fetching period profit from swap_profit_links: " . $e->getMessage());
                // Table might not exist, try alternative calculation
                try {
                    // For fallback, only count profit from swaps that have customer_item_sale_id in swap_profit_links
                    $checkFinalProfit = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_profit'");
                    $checkProfitLinks = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
                    if ($checkFinalProfit->rowCount() > 0 && $checkProfitLinks->rowCount() > 0) {
                        // CRITICAL FIX: Filter by customer sale date (when item was resold), not swap creation date
                        $periodProfitQuery = $this->db->prepare("
                            SELECT COALESCE(SUM(s.final_profit), 0) as profit
                            FROM swaps s
                            INNER JOIN swap_profit_links spl ON s.id = spl.swap_id
                            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
                            WHERE s.company_id = :company_id 
                            AND s.final_profit IS NOT NULL
                            AND spl.customer_item_sale_id IS NOT NULL
                            AND (
                                (customer_sale.id IS NOT NULL AND DATE(customer_sale.created_at) >= :date_from AND DATE(customer_sale.created_at) <= :date_to)
                                OR (customer_sale.id IS NULL AND DATE(s.created_at) >= :date_from AND DATE(s.created_at) <= :date_to)
                            )
                        ");
                        $periodProfitParams = ['company_id' => $company_id];
                        if ($date_from) $periodProfitParams['date_from'] = $date_from;
                        if ($date_to) $periodProfitParams['date_to'] = $date_to;
                        $periodProfitQuery->execute($periodProfitParams);
                        $periodProfitResult = $periodProfitQuery->fetch(\PDO::FETCH_ASSOC);
                        $periodProfit = (float)($periodProfitResult['profit'] ?? 0);
                    }
                } catch (\Exception $e2) {
                    error_log("getSwapStats: Error fetching period profit from swaps.final_profit: " . $e2->getMessage());
                }
            }
            
            // If period profit is still 0, try to calculate from swap data
            if ($periodProfit == 0) {
                try {
                    $checkCustomerValue = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'given_phone_value'");
                    $checkCompanyProductId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_product_id'");
                    $hasCustomerValue = $checkCustomerValue->rowCount() > 0;
                    $hasCompanyProductId = $checkCompanyProductId->rowCount() > 0;
                    
                    if ($hasCustomerValue && $hasCompanyProductId) {
                        // Check what cost column exists in products table
                        $checkProductCost = $this->db->query("SHOW COLUMNS FROM products LIKE 'cost'");
                        $checkPurchasePrice = $this->db->query("SHOW COLUMNS FROM products LIKE 'purchase_price'");
                        $hasProductCost = $checkProductCost->rowCount() > 0;
                        $hasPurchasePrice = $checkPurchasePrice->rowCount() > 0;
                        
                        $costColumn = '0';
                        if ($hasProductCost) {
                            $costColumn = 'COALESCE(p.cost, 0)';
                        } elseif ($hasPurchasePrice) {
                            $costColumn = 'COALESCE(p.purchase_price, 0)';
                        }
                        
                        $periodProfitCalcQuery = $this->db->prepare("
                            SELECT COALESCE(
                                SUM(
                                    COALESCE(s.given_phone_value, 0) + 
                                    COALESCE(s.added_cash, 0) + 
                                    COALESCE(s.cash_added, 0) - 
                                    {$costColumn}
                                ), 0
                            ) as estimated_profit
                            FROM swaps s
                            LEFT JOIN products p ON s.company_product_id = p.id
                            WHERE s.company_id = :company_id 
                            AND DATE(s.created_at) >= :date_from
                            AND DATE(s.created_at) <= :date_to
                        ");
                        $periodProfitParams = ['company_id' => $company_id];
                        if ($date_from) $periodProfitParams['date_from'] = $date_from;
                        if ($date_to) $periodProfitParams['date_to'] = $date_to;
                        $periodProfitCalcQuery->execute($periodProfitParams);
                        $periodProfitCalcResult = $periodProfitCalcQuery->fetch(PDO::FETCH_ASSOC);
                        $estimatedPeriodProfit = (float)($periodProfitCalcResult['estimated_profit'] ?? 0);
                        if ($estimatedPeriodProfit != 0) {
                            $periodProfit = $estimatedPeriodProfit;
                            error_log("getSwapStats: Using estimated period profit from swap data: {$periodProfit}");
                        }
                    }
                } catch (\Exception $e3) {
                    error_log("getSwapStats: Error calculating estimated period profit: " . $e3->getMessage());
                }
            }
        }

        // When date range is provided, use filtered data for period
        // When no date range, use monthly data for period
        $periodCount = 0;
        $periodRevenue = 0;
        $periodProfitValue = $monthlyProfit;
        
        if ($date_from || $date_to) {
            // Use filtered data when date range is provided
            $periodCount = (int)($filtered['count'] ?? 0);
            $periodRevenue = (float)($filtered['revenue'] ?? 0);
            $periodProfitValue = $periodProfit;
        } else {
            // Use monthly data when no date range
            $periodCount = (int)($monthly['count'] ?? 0);
            $periodRevenue = (float)($monthly['revenue'] ?? 0);
            $periodProfitValue = $monthlyProfit;
        }
        
        return [
            'pending' => $pending,
            'monthly' => [
                'count' => (int)($monthly['count'] ?? 0),
                'revenue' => (float)($monthly['revenue'] ?? 0),
                'profit' => $monthlyProfit
            ],
            'profit' => $profit,
            'filtered' => $filtered ? [
                'count' => (int)($filtered['count'] ?? 0),
                'revenue' => (float)($filtered['revenue'] ?? 0)
            ] : null,
            'period' => [
                'count' => $periodCount,
                'revenue' => $periodRevenue,
                'profit' => $periodProfitValue
            ]
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

        // If no date range provided, calculate ALL-TIME stats (no date filtering)
        // If dates are provided, filter by date range
        if (empty($date_from) && empty($date_to)) {
            // ALL-TIME: No date filtering - include all sales
            // Don't add any date conditions
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
        
        // Check which cost column exists (prioritize cost_price, then cost)
        $checkCostPrice = $this->db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
        $checkCost = $this->db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
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
        
        // EXCLUDE swap transactions - swaps should only be tracked on swap page
        // Use swap_id IS NULL to exclude all swap-related sales
        // Calculate cost by summing item costs, not as a correlated subquery within SUM
        // IMPORTANT: The subquery must only include sales within the date range
        $profitQuery = $this->db->prepare("
            SELECT 
                COALESCE(SUM(ps.final_amount), 0) as revenue,
                COALESCE(SUM(psi_cost.total_cost), 0) as cost
            FROM pos_sales ps
            LEFT JOIN (
                SELECT 
                    psi.pos_sale_id,
                    SUM(psi.quantity * {$costColumn}) as total_cost
                     FROM pos_sale_items psi 
                INNER JOIN pos_sales ps_inner ON psi.pos_sale_id = ps_inner.id
                     LEFT JOIN {$productsTable} p ON (
                    (psi.item_id = p.id AND p.company_id = ps_inner.company_id)
                    OR ((psi.item_id IS NULL OR psi.item_id = 0) AND LOWER(TRIM(psi.item_description)) = LOWER(TRIM(p.name)) AND p.company_id = ps_inner.company_id)
                     )
                WHERE {$where}
                AND p.id IS NOT NULL
                GROUP BY psi.pos_sale_id
            ) psi_cost ON psi_cost.pos_sale_id = ps.id
            WHERE {$where}
            AND ps.swap_id IS NULL
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
        try {
            $results = [];
            $searchTerm = '%' . $query . '%';
            
            error_log("TraceItem: Starting search - company_id: {$company_id}, query: {$query}");
            
            // First, let's check if the query matches SWAP-{id} pattern
            if (preg_match('/^SWAP-(\d+)$/i', $query, $matches)) {
                error_log("TraceItem: Query matches SWAP-{id} pattern, extracted ID: " . $matches[1]);
            }

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
                // Check which columns exist in swaps table
                $checkSwapsTable = $this->db->query("SHOW TABLES LIKE 'swaps'");
                if ($checkSwapsTable->rowCount() > 0) {
                    $swapsColumns = $this->db->query("SHOW COLUMNS FROM swaps")->fetchAll(PDO::FETCH_COLUMN);
                    $hasSwapCode = in_array('swap_code', $swapsColumns);
                    $hasTransactionCode = in_array('transaction_code', $swapsColumns);
                    $hasUniqueId = in_array('unique_id', $swapsColumns);
                    $hasTotalValue = in_array('total_value', $swapsColumns);
                    $hasFinalPrice = in_array('final_price', $swapsColumns);
                    
                    error_log("TraceItem: Swap columns - swap_code: " . ($hasSwapCode ? 'yes' : 'no') . ", transaction_code: " . ($hasTransactionCode ? 'yes' : 'no') . ", unique_id: " . ($hasUniqueId ? 'yes' : 'no'));
                    
                    // Build reference column selection
                    $referenceSelect = "CONCAT('SWAP-', s.id)";
                    if ($hasSwapCode) {
                        $referenceSelect = "COALESCE(s.swap_code, CONCAT('SWAP-', s.id))";
                    } elseif ($hasTransactionCode) {
                        $referenceSelect = "COALESCE(s.transaction_code, CONCAT('SWAP-', s.id))";
                    } elseif ($hasUniqueId) {
                        $referenceSelect = "COALESCE(s.unique_id, CONCAT('SWAP-', s.id))";
                    }
                    
                    error_log("TraceItem: Reference select: {$referenceSelect}");
                    
                    // Build amount column selection
                    $amountSelect = '0';
                    if ($hasTotalValue) {
                        $amountSelect = 'COALESCE(s.total_value, 0)';
                    } elseif ($hasFinalPrice) {
                        $amountSelect = 'COALESCE(s.final_price, 0)';
                    }
                    
                    error_log("TraceItem: Amount select: {$amountSelect}");
                    
                    // First, try exact match on SWAP-{id} format
                    $exactSwapMatch = false;
                    if (preg_match('/^SWAP-(\d+)$/i', $query, $matches)) {
                        $swapId = (int)$matches[1];
                        error_log("TraceItem: Searching for swap with ID: {$swapId} (from query: {$query})");
                        
                        // First, try a simple query without JOINs to see if swap exists
                        $simpleCheck = $this->db->prepare("SELECT id, company_id FROM swaps WHERE id = :swap_id");
                        $simpleCheck->execute(['swap_id' => $swapId]);
                        $simpleResult = $simpleCheck->fetch(PDO::FETCH_ASSOC);
                        if ($simpleResult) {
                            $swapCompanyId = $simpleResult['company_id'] ?? null;
                            error_log("TraceItem: Swap ID {$swapId} exists in database - company_id: " . ($swapCompanyId ?? 'NULL') . ", searching for company: {$company_id}");
                            
                            // If company_id matches or is NULL, proceed with normal query
                            // If it doesn't match, we'll try without company filter as fallback
                            $companyMatches = ($swapCompanyId == $company_id || $swapCompanyId === null);
                        } else {
                            error_log("TraceItem: Swap ID {$swapId} does NOT exist in database");
                            $companyMatches = false;
                        }
                        
                        // Try with company_id filter first
                        $exactSwapQuery = $this->db->prepare("
                            SELECT 
                                'swap' as type,
                                s.id,
                                s.created_at as date,
                                {$amountSelect} as amount,
                                c.full_name as customer,
                                COALESCE(CONCAT(cp.brand, ' ', cp.model), 'Swap Transaction') as item,
                                {$referenceSelect} as reference
                            FROM swaps s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                            WHERE s.company_id = :company_id 
                            AND s.id = :swap_id
                            ORDER BY s.created_at DESC
                            LIMIT 50
                        ");
                        try {
                            $exactSwapQuery->execute(['company_id' => $company_id, 'swap_id' => $swapId]);
                            $exactSwaps = $exactSwapQuery->fetchAll(PDO::FETCH_ASSOC);
                            error_log("TraceItem: Exact swap query (with company filter) returned " . count($exactSwaps) . " results for swap ID {$swapId}");
                        } catch (\Exception $e) {
                            error_log("TraceItem: Error executing exact swap query: " . $e->getMessage());
                            error_log("TraceItem: Query SQL: SELECT ... WHERE s.company_id = {$company_id} AND s.id = {$swapId}");
                            $exactSwaps = [];
                        }
                        
                        // If no results, try without company filter (in case of company_id mismatch or NULL)
                        if (count($exactSwaps) === 0) {
                            error_log("TraceItem: No results with company filter. Trying query without company filter...");
                            $exactSwapQueryNoCompany = $this->db->prepare("
                                SELECT 
                                    'swap' as type,
                                    s.id,
                                    s.created_at as date,
                                    {$amountSelect} as amount,
                                    c.full_name as customer,
                                    COALESCE(CONCAT(cp.brand, ' ', cp.model), 'Swap Transaction') as item,
                                    {$referenceSelect} as reference
                                FROM swaps s
                                LEFT JOIN customers c ON s.customer_id = c.id
                                LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                                WHERE s.id = :swap_id
                                ORDER BY s.created_at DESC
                                LIMIT 50
                            ");
                            try {
                                $exactSwapQueryNoCompany->execute(['swap_id' => $swapId]);
                                $exactSwaps = $exactSwapQueryNoCompany->fetchAll(PDO::FETCH_ASSOC);
                                error_log("TraceItem: Exact swap query (without company filter) returned " . count($exactSwaps) . " results");
                            } catch (\Exception $e) {
                                error_log("TraceItem: Error executing swap query without company filter: " . $e->getMessage());
                                error_log("TraceItem: Error trace: " . $e->getTraceAsString());
                                
                                // Last resort: try simplest possible query
                                try {
                                    error_log("TraceItem: Trying simplest query as last resort...");
                                    $simpleSwapQuery = $this->db->prepare("
                                        SELECT 
                                            'swap' as type,
                                            id,
                                            created_at as date,
                                            0 as amount,
                                            'Walk-in Customer' as customer,
                                            'Swap Transaction' as item,
                                            CONCAT('SWAP-', id) as reference
                                        FROM swaps
                                        WHERE id = :swap_id
                                        LIMIT 1
                                    ");
                                    $simpleSwapQuery->execute(['swap_id' => $swapId]);
                                    $simpleSwaps = $simpleSwapQuery->fetchAll(PDO::FETCH_ASSOC);
                                    if (count($simpleSwaps) > 0) {
                                        error_log("TraceItem: Simple query found swap: " . json_encode($simpleSwaps[0]));
                                        $exactSwaps = $simpleSwaps;
                                    }
                                } catch (\Exception $e2) {
                                    error_log("TraceItem: Even simple query failed: " . $e2->getMessage());
                                    $exactSwaps = [];
                                }
                            }
                        }
                        
                        if (count($exactSwaps) > 0) {
                            error_log("TraceItem: Found swap: " . json_encode($exactSwaps[0]));
                            $results = array_merge($results, $exactSwaps);
                            $exactSwapMatch = true;
                        } else {
                            // Log why swap wasn't found
                            if (isset($swapCompanyId)) {
                                error_log("TraceItem: Swap ID {$swapId} exists but query returned no results. Swap company_id: {$swapCompanyId}, searched company_id: {$company_id}");
                            } else {
                                error_log("TraceItem: Swap ID {$swapId} query returned no results and swap existence check failed");
                            }
                        }
                    }
                    
                    // Also try exact match on numeric ID
                    if (!$exactSwapMatch && is_numeric($query)) {
                        $swapId = (int)$query;
                        error_log("TraceItem: Trying numeric ID search for swap ID: {$swapId}");
                        $exactSwapQuery = $this->db->prepare("
                            SELECT 
                                'swap' as type,
                                s.id,
                                s.created_at as date,
                                {$amountSelect} as amount,
                                c.full_name as customer,
                                COALESCE(CONCAT(cp.brand, ' ', cp.model), 'Swap Transaction') as item,
                                {$referenceSelect} as reference
                            FROM swaps s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                            WHERE s.company_id = :company_id 
                            AND s.id = :swap_id
                            ORDER BY s.created_at DESC
                            LIMIT 50
                        ");
                        $exactSwapQuery->execute(['company_id' => $company_id, 'swap_id' => $swapId]);
                        $exactSwaps = $exactSwapQuery->fetchAll(PDO::FETCH_ASSOC);
                        error_log("TraceItem: Numeric ID swap query returned " . count($exactSwaps) . " results");
                        if (count($exactSwaps) > 0) {
                            $results = array_merge($results, $exactSwaps);
                            $exactSwapMatch = true;
                        }
                    }
                    
                    // Build WHERE clause for reference search
                    $referenceWhere = '';
                    if ($hasSwapCode) {
                        $referenceWhere = "s.swap_code LIKE :query";
                    } elseif ($hasTransactionCode) {
                        $referenceWhere = "s.transaction_code LIKE :query";
                    } elseif ($hasUniqueId) {
                        $referenceWhere = "s.unique_id LIKE :query";
                    } else {
                        // Fallback: search in generated reference
                        $referenceWhere = "CONCAT('SWAP-', s.id) LIKE :query";
                    }
                    
                    // Also do the broader search with LIKE for partial matches (only if exact match didn't work)
                    if (!$exactSwapMatch) {
                        error_log("TraceItem: Performing broader swap search with query: {$query}, searchTerm: {$searchTerm}");
                        $swapsQuery = $this->db->prepare("
                            SELECT 
                                'swap' as type,
                                s.id,
                                s.created_at as date,
                                {$amountSelect} as amount,
                                c.full_name as customer,
                                COALESCE(CONCAT(cp.brand, ' ', cp.model), 'Swap Transaction') as item,
                                {$referenceSelect} as reference
                            FROM swaps s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            LEFT JOIN customer_products cp ON s.customer_product_id = cp.id
                            WHERE s.company_id = :company_id 
                            AND (
                                {$referenceWhere}
                                OR cp.imei LIKE :query
                                OR c.phone_number LIKE :query
                                OR c.full_name LIKE :query
                                OR CONCAT('SWAP-', s.id) LIKE :query
                            )
                            ORDER BY s.created_at DESC
                            LIMIT 50
                        ");
                        $swapsQuery->execute(['company_id' => $company_id, 'query' => $searchTerm]);
                        $swaps = $swapsQuery->fetchAll(PDO::FETCH_ASSOC);
                        error_log("TraceItem: Broader swap search returned " . count($swaps) . " results");
                        
                        // Merge results, avoiding duplicates
                        $existingSwapIds = array_column(array_filter($results, function($r) { return $r['type'] === 'swap'; }), 'id');
                        foreach ($swaps as $swap) {
                            if (!in_array($swap['id'], $existingSwapIds)) {
                                $results[] = $swap;
                                $existingSwapIds[] = $swap['id'];
                            }
                        }
                    }
                } else {
                    error_log("TraceItem: Swaps table does not exist");
                }
            } catch (\Exception $e) {
                error_log("TraceItem swaps search error: " . $e->getMessage());
                error_log("TraceItem swaps search trace: " . $e->getTraceAsString());
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
                error_log("TraceItem repairs search error: " . $e->getMessage());
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
                error_log("TraceItem inventory search error: " . $e->getMessage());
            }

            // Sort by date descending
            usort($results, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            error_log("TraceItem: Final results count: " . count($results));
            if (count($results) > 0) {
                error_log("TraceItem: Sample result: " . json_encode($results[0]));
            } else {
                error_log("TraceItem: No results found for query: {$query}, company_id: {$company_id}");
            }

            return array_slice($results, 0, 100); // Limit total results
        } catch (\Exception $e) {
            error_log("TraceItem: Exception: " . $e->getMessage());
            error_log("TraceItem: Exception trace: " . $e->getTraceAsString());
            return [];
        } catch (\Error $e) {
            error_log("TraceItem: Fatal error: " . $e->getMessage());
            error_log("TraceItem: Fatal error trace: " . $e->getTraceAsString());
            return [];
        }
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
        // Check if is_swap_mode and swap_id columns exist
        $hasIsSwapMode = false;
        $hasSwapId = false;
        try {
            $checkIsSwapMode = $this->db->query("SHOW COLUMNS FROM pos_sales LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
            $checkSwapId = $this->db->query("SHOW COLUMNS FROM pos_sales LIKE 'swap_id'");
            $hasSwapId = $checkSwapId->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("AnalyticsService::getSalesByDateRange: Error checking swap columns: " . $e->getMessage());
        }
        
        // Exclude swap sales from sales history (swaps should only appear on swap page)
        // Exclude sales where is_swap_mode = 1 or swap_id IS NOT NULL
        $excludeSwapSales = "";
        if ($hasIsSwapMode) {
            $excludeSwapSales = " AND (ps.is_swap_mode = 0 OR ps.is_swap_mode IS NULL)";
        }
        if ($hasSwapId) {
            $excludeSwapSales .= " AND ps.swap_id IS NULL";
        }
        
        $where = "ps.company_id = :company_id
            AND DATE(ps.created_at) >= :date_from
            AND DATE(ps.created_at) <= :date_to{$excludeSwapSales}";
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
        
        // Check which columns exist in swaps table
        $checkTotalValue = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
        $hasTotalValue = $checkTotalValue->rowCount() > 0;
        $checkFinalPrice = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
        $hasFinalPrice = $checkFinalPrice->rowCount() > 0;
        $checkCompanyProductId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_product_id'");
        $hasCompanyProductId = $checkCompanyProductId->rowCount() > 0;
        $checkCustomerProductId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_product_id'");
        $hasCustomerProductId = $checkCustomerProductId->rowCount() > 0;
        $checkSwapStatus = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'swap_status'");
        $hasSwapStatus = $checkSwapStatus->rowCount() > 0;
        $checkStatus = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'status'");
        $hasStatus = $checkStatus->rowCount() > 0;
        
        // Determine which products table exists
        $checkProductsNew = $this->db->query("SHOW TABLES LIKE 'products_new'");
        $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
        
        // Build total_value select based on available columns
        $needsProductJoin = false;
        if ($hasTotalValue) {
            $totalValueSelect = "COALESCE(s.total_value, 0) as total_value";
        } elseif ($hasFinalPrice) {
            $totalValueSelect = "COALESCE(s.final_price, 0) as total_value";
        } elseif ($hasCompanyProductId) {
            $totalValueSelect = "COALESCE(p.price, 0) as total_value";
            $needsProductJoin = true;
        } else {
            $totalValueSelect = "0 as total_value";
        }
        
        // Build status select based on available columns
        if ($hasSwapStatus && $hasStatus) {
            $statusSelect = "COALESCE(s.swap_status, s.status, 'pending') as swap_status";
        } elseif ($hasSwapStatus) {
            $statusSelect = "COALESCE(s.swap_status, 'pending') as swap_status";
        } elseif ($hasStatus) {
            $statusSelect = "COALESCE(s.status, 'pending') as swap_status";
        } else {
            $statusSelect = "'pending' as swap_status";
        }
        
        // Build JOIN for company product if needed
        $productJoin = "";
        if ($needsProductJoin) {
            $productJoin = "LEFT JOIN {$productsTable} p ON s.company_product_id = p.id AND p.company_id = s.company_id";
        }
        
        // Build customer products JOIN only if column exists
        $customerProductJoin = "";
        $swappedItemsJoin = "";
        
        // Check if swapped_items table exists
        $checkSwappedItems = $this->db->query("SHOW TABLES LIKE 'swapped_items'");
        $hasSwappedItems = $checkSwappedItems->rowCount() > 0;
        
        if ($hasSwappedItems) {
            // Join with swapped_items table to get brand, model from customer's swapped item
            $swappedItemsJoin = "LEFT JOIN swapped_items si ON s.id = si.swap_id";
            $brandSelect = "COALESCE(si.brand, '') as brand";
            $modelSelect = "COALESCE(si.model, '') as model";
            $itemDescriptionSelect = "CONCAT(COALESCE(si.brand, ''), ' ', COALESCE(si.model, '')) as item_description";
        } elseif ($hasCustomerProductId) {
            // Fallback to customer_products if swapped_items doesn't exist
            $customerProductJoin = "LEFT JOIN customer_products cp ON s.customer_product_id = cp.id";
            $brandSelect = "COALESCE(cp.brand, '') as brand";
            $modelSelect = "COALESCE(cp.model, '') as model";
            $itemDescriptionSelect = "CONCAT(COALESCE(cp.brand, ''), ' ', COALESCE(cp.model, '')) as item_description";
        } else {
            $brandSelect = "'' as brand";
            $modelSelect = "'' as model";
            $itemDescriptionSelect = "'' as item_description";
        }
        
        $query = $this->db->prepare("
            SELECT 
                s.id,
                s.unique_id,
                s.created_at,
                {$totalValueSelect},
                {$statusSelect},
                c.full_name as customer_name,
                c.phone_number as customer_phone,
                {$brandSelect},
                {$modelSelect},
                {$itemDescriptionSelect}
            FROM swaps s
            LEFT JOIN customers c ON s.customer_id = c.id
            {$customerProductJoin}
            {$swappedItemsJoin}
            {$productJoin}
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

