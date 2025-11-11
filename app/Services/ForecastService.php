<?php

namespace App\Services;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class ForecastService {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Predict sales for a given timeframe
     * 
     * @param int $companyId
     * @param string $timeframe 'daily', 'weekly', 'monthly'
     * @param int $daysAhead Number of days to predict ahead
     * @return array Predicted sales with confidence intervals
     */
    public function predictSales($companyId, $timeframe = 'weekly', $daysAhead = 7) {
        try {
            // Get historical sales data (last 30 days for daily, last 12 weeks for weekly, last 12 months for monthly)
            $lookbackDays = $timeframe === 'daily' ? 30 : ($timeframe === 'weekly' ? 84 : 365);
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as sale_count,
                    SUM(final_amount) as revenue
                FROM pos_sales
                WHERE company_id = :company_id
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'days' => $lookbackDays
            ]);
            $historical = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($historical) < 7) {
                return [
                    'success' => false,
                    'message' => 'Insufficient historical data for forecasting'
                ];
            }

            // Calculate moving average and trend
            $revenues = array_column($historical, 'revenue');
            $dates = array_column($historical, 'date');
            
            // Simple linear regression for trend
            $trend = $this->calculateLinearTrend($revenues);
            
            // Moving average (7-day window)
            $windowSize = min(7, count($revenues));
            $movingAvg = array_slice($revenues, -$windowSize);
            $avgRevenue = array_sum($movingAvg) / count($movingAvg);
            
            // Standard deviation for confidence intervals
            $stdDev = $this->calculateStdDev($movingAvg);
            
            // Generate predictions
            $predictions = [];
            $lastDate = new \DateTime(end($dates));
            
            for ($i = 1; $i <= $daysAhead; $i++) {
                $futureDate = clone $lastDate;
                $futureDate->modify("+{$i} days");
                
                // Predict using trend + moving average
                $baseValue = $avgRevenue + ($trend['slope'] * $i);
                $confidenceLow = max(0, $baseValue - (1.96 * $stdDev)); // 95% CI
                $confidenceHigh = $baseValue + (1.96 * $stdDev);
                
                $predictions[] = [
                    'date' => $futureDate->format('Y-m-d'),
                    'predicted_revenue' => round($baseValue, 2),
                    'confidence_low' => round($confidenceLow, 2),
                    'confidence_high' => round($confidenceHigh, 2),
                    'confidence_interval' => round(($confidenceHigh - $confidenceLow) / $baseValue * 100, 1) // percentage
                ];
            }

            return [
                'success' => true,
                'timeframe' => $timeframe,
                'historical_avg' => round($avgRevenue, 2),
                'trend' => [
                    'direction' => $trend['slope'] > 0 ? 'up' : ($trend['slope'] < 0 ? 'down' : 'stable'),
                    'slope' => round($trend['slope'], 2)
                ],
                'predictions' => $predictions,
                'summary' => [
                    'total_predicted' => round(array_sum(array_column($predictions, 'predicted_revenue')), 2),
                    'avg_daily_predicted' => round($avgRevenue, 2)
                ]
            ];
        } catch (\Exception $e) {
            error_log("ForecastService::predictSales error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Forecast restock needs based on sales velocity
     * 
     * @param int $companyId
     * @param int $daysAhead Days to forecast ahead
     * @return array Products that need restocking
     */
    public function forecastRestockNeeds($companyId, $daysAhead = 14) {
        try {
            // Get products with recent sales
            $stmt = $this->db->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.product_id as sku,
                    COALESCE(p.quantity, p.qty, 0) as current_stock,
                    COALESCE(SUM(psi.quantity), 0) as units_sold_30d,
                    COALESCE(AVG(psi.quantity), 0) as avg_daily_sales
                FROM products p
                LEFT JOIN pos_sale_items psi ON p.id = psi.item_id
                LEFT JOIN pos_sales ps ON psi.pos_sale_id = ps.id
                WHERE p.company_id = :company_id
                AND (ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR ps.id IS NULL)
                AND (p.quantity > 0 OR p.qty > 0)
                GROUP BY p.id, p.name, p.product_id, p.quantity, p.qty
                HAVING units_sold_30d > 0
            ");
            $stmt->execute(['company_id' => $companyId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $restockNeeds = [];
            
            foreach ($products as $product) {
                $currentStock = (float)$product['current_stock'];
                $avgDailySales = (float)$product['avg_daily_sales'];
                
                if ($avgDailySales <= 0) continue;
                
                // Calculate days until out of stock
                $daysUntilOutOfStock = $currentStock / $avgDailySales;
                
                // Lead time assumption (7 days)
                $leadTime = 7;
                $safetyStock = $avgDailySales * $leadTime * 1.5; // 1.5x safety buffer
                $recommendedStock = $avgDailySales * ($daysAhead + $leadTime) + $safetyStock;
                $restockQuantity = max(0, $recommendedStock - $currentStock);
                
                // Flag if stock will run out within forecast period
                if ($daysUntilOutOfStock <= $daysAhead || $restockQuantity > 0) {
                    $priority = 'high';
                    if ($daysUntilOutOfStock > 7) {
                        $priority = 'medium';
                    }
                    if ($daysUntilOutOfStock > 10) {
                        $priority = 'low';
                    }
                    
                    $restockNeeds[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'sku' => $product['sku'],
                        'current_stock' => $currentStock,
                        'avg_daily_sales' => round($avgDailySales, 2),
                        'days_until_out' => round($daysUntilOutOfStock, 2),
                        'recommended_stock' => round($recommendedStock, 0),
                        'restock_quantity' => round($restockQuantity, 0),
                        'priority' => $priority,
                        'confidence' => min(1.0, max(0.5, 1.0 - ($daysUntilOutOfStock / $daysAhead)))
                    ];
                }
            }

            // Sort by priority and days until out
            usort($restockNeeds, function($a, $b) {
                $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
                if ($priorityOrder[$a['priority']] !== $priorityOrder[$b['priority']]) {
                    return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
                }
                return $a['days_until_out'] <=> $b['days_until_out'];
            });

            return [
                'success' => true,
                'forecast_days' => $daysAhead,
                'products' => $restockNeeds,
                'total_products_needing_restock' => count($restockNeeds)
            ];
        } catch (\Exception $e) {
            error_log("ForecastService::forecastRestockNeeds error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Forecast profit for given interval
     * 
     * @param int $companyId
     * @param string $interval 'weekly' or 'monthly'
     * @return array Profit forecast
     */
    public function forecastProfit($companyId, $interval = 'weekly') {
        try {
            $lookbackDays = $interval === 'weekly' ? 84 : 365; // 12 weeks or 12 months
            
            // Get historical profit data
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    SUM(final_amount - COALESCE(
                        (SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, p.purchase_price, 0))
                         FROM pos_sale_items psi 
                         LEFT JOIN products p ON psi.item_id = p.id 
                         WHERE psi.pos_sale_id = ps.id),
                        ps.final_amount * 0.7
                    )) as profit
                FROM pos_sales ps
                WHERE ps.company_id = :company_id
                AND ps.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(ps.created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'days' => $lookbackDays
            ]);
            $historical = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($historical) < 7) {
                return [
                    'success' => false,
                    'message' => 'Insufficient historical data'
                ];
            }

            $profits = array_column($historical, 'profit');
            $trend = $this->calculateLinearTrend($profits);
            
            $windowSize = min(7, count($profits));
            $movingAvg = array_slice($profits, -$windowSize);
            $avgProfit = array_sum($movingAvg) / count($movingAvg);
            $stdDev = $this->calculateStdDev($movingAvg);

            // Forecast next period
            $forecastPeriods = $interval === 'weekly' ? 4 : 3; // 4 weeks or 3 months
            $predictions = [];
            
            for ($i = 1; $i <= $forecastPeriods; $i++) {
                $baseValue = $avgProfit + ($trend['slope'] * ($interval === 'weekly' ? 7 : 30) * $i);
                $predictions[] = [
                    'period' => $i,
                    'predicted_profit' => round($baseValue, 2),
                    'confidence_low' => round(max(0, $baseValue - (1.96 * $stdDev)), 2),
                    'confidence_high' => round($baseValue + (1.96 * $stdDev), 2)
                ];
            }

            return [
                'success' => true,
                'interval' => $interval,
                'historical_avg' => round($avgProfit, 2),
                'trend' => [
                    'direction' => $trend['slope'] > 0 ? 'up' : ($trend['slope'] < 0 ? 'down' : 'stable'),
                    'slope' => round($trend['slope'], 2)
                ],
                'predictions' => $predictions,
                'total_forecasted' => round(array_sum(array_column($predictions, 'predicted_profit')), 2)
            ];
        } catch (\Exception $e) {
            error_log("ForecastService::forecastProfit error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate linear trend (slope and intercept)
     */
    private function calculateLinearTrend($values) {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0];
        }

        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $values[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / (($n * $sumX2) - ($sumX * $sumX));
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return [
            'slope' => $slope,
            'intercept' => $intercept
        ];
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStdDev($values) {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);

        return sqrt($variance);
    }

    /**
     * Forecast repair demand
     * 
     * @param int $companyId
     * @param int $daysAhead
     * @return array
     */
    public function forecastRepairDemand($companyId, $daysAhead = 7) {
        try {
            // Get historical repair data
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as repair_count,
                    SUM(total_cost) as revenue
                FROM repairs_new
                WHERE company_id = :company_id
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute(['company_id' => $companyId]);
            $historical = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($historical) < 7) {
                return [
                    'success' => false,
                    'message' => 'Insufficient historical data'
                ];
            }

            $counts = array_column($historical, 'repair_count');
            $trend = $this->calculateLinearTrend($counts);
            
            $windowSize = min(7, count($counts));
            $movingAvg = array_slice($counts, -$windowSize);
            $avgDaily = array_sum($movingAvg) / count($movingAvg);
            
            $predictedTotal = ($avgDaily + ($trend['slope'] * $daysAhead)) * $daysAhead;
            
            return [
                'success' => true,
                'forecast_days' => $daysAhead,
                'avg_daily_repairs' => round($avgDaily, 2),
                'predicted_total' => round($predictedTotal, 0),
                'trend' => [
                    'direction' => $trend['slope'] > 0 ? 'increasing' : ($trend['slope'] < 0 ? 'decreasing' : 'stable'),
                    'slope' => round($trend['slope'], 2)
                ]
            ];
        } catch (\Exception $e) {
            error_log("ForecastService::forecastRepairDemand error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

