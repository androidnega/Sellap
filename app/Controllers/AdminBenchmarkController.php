<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Services\AnalyticsService;
use PDO;

require_once __DIR__ . '/../../config/database.php';

class AdminBenchmarkController {
    private $db;
    private $analyticsService;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Show benchmarks dashboard (Admin only)
     */
    public function index() {
        // Check admin access
        WebAuthMiddleware::handle(['system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'system_admin') {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }

        $title = 'Cross-Company Benchmarks';
        $GLOBALS['pageTitle'] = $title;
        
        // Load view
        require __DIR__ . '/../Views/admin_benchmarks.php';
    }

    /**
     * Get benchmark data (API)
     * GET /api/admin/benchmarks
     */
    public function getBenchmarks() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Check admin access
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user || $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                exit;
            }

            $metric = $_GET['metric'] ?? 'all';
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Start of month
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $benchmarks = [];

            if ($metric === 'all' || $metric === 'sales') {
                $benchmarks['sales'] = $this->getSalesBenchmarks($dateFrom, $dateTo);
            }

            if ($metric === 'all' || $metric === 'profit') {
                $benchmarks['profit'] = $this->getProfitBenchmarks($dateFrom, $dateTo);
            }

            if ($metric === 'all' || $metric === 'repairs') {
                $benchmarks['repairs'] = $this->getRepairBenchmarks($dateFrom, $dateTo);
            }

            if ($metric === 'all' || $metric === 'swaps') {
                $benchmarks['swaps'] = $this->getSwapBenchmarks($dateFrom, $dateTo);
            }

            if ($metric === 'all' || $metric === 'customers') {
                $benchmarks['customers'] = $this->getCustomerBenchmarks($dateFrom, $dateTo);
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'benchmarks' => $benchmarks,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Benchmarks error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get sales benchmarks (anonymized)
     */
    private function getSalesBenchmarks($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                company_id,
                COUNT(*) as sales_count,
                COALESCE(SUM(final_amount), 0) as revenue,
                COALESCE(AVG(final_amount), 0) as avg_sale,
                COALESCE(SUM(final_amount) / NULLIF(DATEDIFF(:date_to, :date_from), 0), 0) as daily_avg
            FROM pos_sales
            WHERE DATE(created_at) BETWEEN :date_from AND :date_to
            GROUP BY company_id
            ORDER BY revenue DESC
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [
                'top_performers' => [],
                'average' => 0,
                'median' => 0,
                'percentiles' => []
            ];
        }

        $revenues = array_column($results, 'revenue');
        $sortedRevenues = $revenues;
        sort($sortedRevenues);

        // Anonymize company IDs (assign random labels)
        $companyLabels = [];
        foreach ($results as &$result) {
            if (!isset($companyLabels[$result['company_id']])) {
                $companyLabels[$result['company_id']] = 'Company #' . (count($companyLabels) + 1);
            }
            $result['company_label'] = $companyLabels[$result['company_id']];
            unset($result['company_id']); // Remove actual ID
        }

        // Top 10 performers
        $topPerformers = array_slice($results, 0, 10);

        // Calculate percentiles
        $percentiles = [];
        $percentileRanges = [10, 25, 50, 75, 90];
        foreach ($percentileRanges as $p) {
            $index = floor(count($sortedRevenues) * $p / 100);
            $percentiles[$p] = $sortedRevenues[$index] ?? 0;
        }

        return [
            'top_performers' => $topPerformers,
            'average' => round(array_sum($revenues) / count($revenues), 2),
            'median' => round($sortedRevenues[floor(count($sortedRevenues) / 2)], 2),
            'percentiles' => $percentiles,
            'total_companies' => count($results)
        ];
    }

    /**
     * Get profit benchmarks
     */
    private function getProfitBenchmarks($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                ps.company_id,
                COALESCE(SUM(ps.final_amount - COALESCE(
                    (SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, 0))
                     FROM pos_sale_items psi 
                     LEFT JOIN products p ON psi.item_id = p.id 
                     WHERE psi.pos_sale_id = ps.id),
                    ps.final_amount * 0.7
                )), 0) as profit,
                COALESCE(SUM(ps.final_amount), 0) as revenue
            FROM pos_sales ps
            WHERE DATE(ps.created_at) BETWEEN :date_from AND :date_to
            GROUP BY ps.company_id
            ORDER BY profit DESC
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return ['top_performers' => [], 'average' => 0];
        }

        $profits = array_column($results, 'profit');
        $margins = array_map(function($row) {
            return $row['revenue'] > 0 ? ($row['profit'] / $row['revenue']) * 100 : 0;
        }, $results);

        // Anonymize
        foreach ($results as &$result) {
            $result['company_label'] = 'Company #' . (array_search($result, $results) + 1);
            $result['margin'] = round($margins[array_search($result, $results)], 2);
            unset($result['company_id']);
        }

        return [
            'top_performers' => array_slice($results, 0, 10),
            'average' => round(array_sum($profits) / count($profits), 2),
            'average_margin' => round(array_sum($margins) / count($margins), 2)
        ];
    }

    /**
     * Get repair benchmarks
     */
    private function getRepairBenchmarks($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                company_id,
                COUNT(*) as repair_count,
                COALESCE(SUM(total_cost), 0) as revenue,
                COALESCE(AVG(total_cost), 0) as avg_repair_cost
            FROM repairs_new
            WHERE DATE(created_at) BETWEEN :date_from AND :date_to
            GROUP BY company_id
            ORDER BY revenue DESC
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return ['top_performers' => [], 'average' => 0];
        }

        // Anonymize
        foreach ($results as &$result) {
            $result['company_label'] = 'Company #' . (array_search($result, $results) + 1);
            unset($result['company_id']);
        }

        $revenues = array_column($results, 'revenue');

        return [
            'top_performers' => array_slice($results, 0, 10),
            'average' => round(array_sum($revenues) / count($revenues), 2)
        ];
    }

    /**
     * Get swap benchmarks
     */
    private function getSwapBenchmarks($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                company_id,
                COUNT(*) as swap_count,
                COALESCE(SUM(resell_price - estimated_value + added_cash), 0) as profit,
                COALESCE(AVG(resell_price - estimated_value + added_cash), 0) as avg_swap_profit
            FROM swaps
            WHERE DATE(created_at) BETWEEN :date_from AND :date_to
            GROUP BY company_id
            ORDER BY profit DESC
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return ['top_performers' => [], 'average' => 0];
        }

        // Anonymize
        foreach ($results as &$result) {
            $result['company_label'] = 'Company #' . (array_search($result, $results) + 1);
            unset($result['company_id']);
        }

        $profits = array_column($results, 'profit');

        return [
            'top_performers' => array_slice($results, 0, 10),
            'average' => round(array_sum($profits) / count($profits), 2)
        ];
    }

    /**
     * Get customer benchmarks
     */
    private function getCustomerBenchmarks($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                c.company_id,
                COUNT(DISTINCT c.id) as customer_count,
                COUNT(DISTINCT ps.id) as transaction_count,
                COALESCE(AVG(ps.final_amount), 0) as avg_transaction_value
            FROM customers c
            LEFT JOIN pos_sales ps ON c.id = ps.customer_id 
                AND DATE(ps.created_at) BETWEEN :date_from AND :date_to
            WHERE c.created_at <= :date_to
            GROUP BY c.company_id
            ORDER BY customer_count DESC
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return ['top_performers' => [], 'average' => 0];
        }

        // Anonymize
        foreach ($results as &$result) {
            $result['company_label'] = 'Company #' . (array_search($result, $results) + 1);
            unset($result['company_id']);
        }

        $counts = array_column($results, 'customer_count');

        return [
            'top_performers' => array_slice($results, 0, 10),
            'average' => round(array_sum($counts) / count($counts), 0)
        ];
    }
}

