<?php

namespace App\Services;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class AnomalyDetectionService {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Detect anomalies in daily revenue using z-score method
     * 
     * @param int $companyId
     * @param float $threshold Z-score threshold (default 3.0 for 3 standard deviations)
     * @param int $lookbackDays Number of days to use for mean/std calculation
     * @return array Array of anomalies detected
     */
    public function detectRevenueAnomaly($companyId, $threshold = 3.0, $lookbackDays = 30) {
        try {
            // Get daily revenue for lookback period
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COALESCE(SUM(final_amount), 0) as revenue
                FROM pos_sales
                WHERE company_id = :company_id
                AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'days' => $lookbackDays
            ]);
            $dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($dailyRevenue) < 7) {
                return []; // Not enough data
            }

            // Calculate mean and standard deviation
            $revenues = array_column($dailyRevenue, 'revenue');
            $mean = array_sum($revenues) / count($revenues);
            
            $variance = 0;
            foreach ($revenues as $rev) {
                $variance += pow($rev - $mean, 2);
            }
            $stdDev = sqrt($variance / count($revenues));

            if ($stdDev == 0) {
                return []; // No variation
            }

            // Check today's revenue
            $todayRevenue = (float)($revenues[count($revenues) - 1] ?? 0);
            $zScore = abs(($todayRevenue - $mean) / $stdDev);

            $anomalies = [];
            if ($zScore > $threshold) {
                $anomalies[] = [
                    'type' => 'revenue_spike',
                    'date' => date('Y-m-d'),
                    'value' => $todayRevenue,
                    'mean' => $mean,
                    'std_dev' => $stdDev,
                    'z_score' => round($zScore, 2),
                    'severity' => $zScore > 4.0 ? 'critical' : 'warning',
                    'message' => sprintf(
                        'Revenue anomaly detected: ₵%s (expected: ₵%s ± ₵%s, z-score: %.2f)',
                        number_format($todayRevenue, 2),
                        number_format($mean, 2),
                        number_format($stdDev, 2),
                        $zScore
                    )
                ];
            }

            return $anomalies;
        } catch (\Exception $e) {
            error_log("AnomalyDetectionService::detectRevenueAnomaly error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect refund/sales ratio spike
     * 
     * @param int $companyId
     * @param float $threshold Ratio threshold (default 0.2 = 20%)
     * @param int $hours Lookback hours (default 24)
     * @return array
     */
    public function detectRefundSpike($companyId, $threshold = 0.2, $hours = 24) {
        try {
            // Get sales count in last N hours
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as sales_count
                FROM pos_sales
                WHERE company_id = :company_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'hours' => $hours
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $salesCount = (int)($result['sales_count'] ?? 0);

            // Get refund count (assuming refunds are tracked via event_type or status)
            // For now, we'll check for sales with negative amounts or specific status
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as refund_count
                FROM pos_sales
                WHERE company_id = :company_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                AND (final_amount < 0 OR payment_method = 'refund')
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'hours' => $hours
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $refundCount = (int)($result['refund_count'] ?? 0);

            $anomalies = [];
            if ($salesCount > 0) {
                $ratio = $refundCount / $salesCount;
                if ($ratio > $threshold) {
                    $anomalies[] = [
                        'type' => 'refund_spike',
                        'ratio' => round($ratio * 100, 2),
                        'refund_count' => $refundCount,
                        'sales_count' => $salesCount,
                        'threshold' => $threshold * 100,
                        'severity' => $ratio > 0.4 ? 'critical' : 'warning',
                        'message' => sprintf(
                            'Refund spike detected: %.2f%% refund rate (%d refunds / %d sales)',
                            $ratio * 100,
                            $refundCount,
                            $salesCount
                        )
                    ];
                }
            }

            return $anomalies;
        } catch (\Exception $e) {
            error_log("AnomalyDetectionService::detectRefundSpike error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect inventory discrepancies using expected sales rate
     * 
     * @param int $companyId
     * @return array
     */
    public function detectInventoryDiscrepancy($companyId) {
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
                GROUP BY p.id, p.name, p.product_id, p.quantity, p.qty
                HAVING current_stock > 0 AND units_sold_30d > 0
            ");
            $stmt->execute(['company_id' => $companyId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $anomalies = [];
            foreach ($products as $product) {
                $avgDailySales = (float)$product['avg_daily_sales'];
                $currentStock = (float)$product['current_stock'];
                
                // Estimate days until out of stock
                if ($avgDailySales > 0) {
                    $daysUntilOutOfStock = $currentStock / $avgDailySales;
                    
                    // Expected stock based on sales rate and lead time
                    $leadTime = 7; // days (assume 7 day lead time)
                    $expectedStock = $avgDailySales * $leadTime;
                    $discrepancy = abs($currentStock - $expectedStock);
                    $discrepancyPercent = $expectedStock > 0 ? ($discrepancy / $expectedStock) * 100 : 0;

                    // Flag if stock is significantly different from expected
                    if ($discrepancyPercent > 50 && $daysUntilOutOfStock < 14) {
                        $anomalies[] = [
                            'type' => 'inventory_discrepancy',
                            'product_id' => $product['id'],
                            'product_name' => $product['name'],
                            'sku' => $product['sku'],
                            'current_stock' => $currentStock,
                            'expected_stock' => round($expectedStock, 2),
                            'discrepancy_percent' => round($discrepancyPercent, 2),
                            'days_until_out' => round($daysUntilOutOfStock, 2),
                            'severity' => $daysUntilOutOfStock < 7 ? 'critical' : 'warning',
                            'message' => sprintf(
                                'Inventory discrepancy for %s: Current %d, Expected ~%d (%.2f%% difference). May run out in %.1f days.',
                                $product['name'],
                                $currentStock,
                                round($expectedStock),
                                $discrepancyPercent,
                                $daysUntilOutOfStock
                            )
                        ];
                    }
                }
            }

            return $anomalies;
        } catch (\Exception $e) {
            error_log("AnomalyDetectionService::detectInventoryDiscrepancy error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Run all anomaly detection checks
     * 
     * @param int $companyId
     * @return array
     */
    public function runAllChecks($companyId) {
        $allAnomalies = [];

        // Revenue anomaly
        $revenueAnomalies = $this->detectRevenueAnomaly($companyId);
        $allAnomalies = array_merge($allAnomalies, $revenueAnomalies);

        // Refund spike
        $refundAnomalies = $this->detectRefundSpike($companyId);
        $allAnomalies = array_merge($allAnomalies, $refundAnomalies);

        // Inventory discrepancies
        $inventoryAnomalies = $this->detectInventoryDiscrepancy($companyId);
        $allAnomalies = array_merge($allAnomalies, $inventoryAnomalies);

        return $allAnomalies;
    }

    /**
     * Calculate exponential moving average for trend detection
     * 
     * @param array $values Array of numeric values
     * @param float $alpha Smoothing factor (0-1, default 0.3)
     * @return array Array with 'values' and 'trend' (up/down/stable)
     */
    public function calculateEMA($values, $alpha = 0.3) {
        if (empty($values)) {
            return ['values' => [], 'trend' => 'stable'];
        }

        $ema = [];
        $ema[0] = $values[0];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = $alpha * $values[$i] + (1 - $alpha) * $ema[$i - 1];
        }

        // Determine trend
        $recentValues = array_slice($ema, -5);
        $first = $recentValues[0];
        $last = $recentValues[count($recentValues) - 1];
        
        $percentChange = $first > 0 ? (($last - $first) / $first) * 100 : 0;
        
        if ($percentChange > 5) {
            $trend = 'up';
        } elseif ($percentChange < -5) {
            $trend = 'down';
        } else {
            $trend = 'stable';
        }

        return [
            'values' => $ema,
            'trend' => $trend,
            'percent_change' => round($percentChange, 2)
        ];
    }
}

