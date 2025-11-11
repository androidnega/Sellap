<?php

namespace App\Controllers;

use App\Models\POSSale;
use App\Services\ExportService;
use App\Middleware\WebAuthMiddleware;

class ReportsController {
    private $sale;
    private $exportService;
    
    public function __construct() {
        $this->sale = new POSSale();
        $this->exportService = new ExportService();
    }
    
    /**
     * Display reports page for salespersons
     */
    public function index() {
        WebAuthMiddleware::handle(['salesperson']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;
        $companyId = $user['company_id'] ?? null;
        
        if (!$userId || !$companyId) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $title = 'Sales Reports';
        $page = 'reports';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/reports_index.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }
    
    /**
     * Export sales report (PDF or Excel)
     */
    public function export() {
        WebAuthMiddleware::handle(['salesperson']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;
        $companyId = $user['company_id'] ?? null;
        
        if (!$userId || !$companyId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $format = $_GET['format'] ?? 'pdf';
        $period = $_GET['period'] ?? 'today';
        
        // Calculate date range based on period
        $dateFrom = null;
        $dateTo = date('Y-m-d');
        
        switch ($period) {
            case 'today':
                $dateFrom = date('Y-m-d');
                $dateTo = date('Y-m-d');
                break;
            case 'week':
                $dateFrom = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $dateFrom = date('Y-m-01');
                break;
            case 'year':
                $dateFrom = date('Y-01-01');
                break;
            case 'custom':
                $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
                $dateTo = $_GET['date_to'] ?? date('Y-m-d');
                break;
        }
        
        // Get sales for this salesperson
        $sales = $this->sale->findByCashier($userId, $companyId, $dateFrom, $dateTo);
        
        // Format data for export
        $formattedData = [];
        $totalRevenue = 0;
        $totalSales = 0;
        
        foreach ($sales as $sale) {
            $totalRevenue += (float)($sale['final_amount'] ?? $sale['total_amount'] ?? 0);
            $totalSales++;
            
            $formattedData[] = [
                'Sale ID' => $sale['unique_id'] ?? 'N/A',
                'Date' => date('Y-m-d H:i:s', strtotime($sale['created_at'] ?? 'now')),
                'Customer' => $sale['customer_name_from_table'] ?? 'Walk-in',
                'Items' => $sale['item_count'] ?? 0,
                'Subtotal' => number_format((float)($sale['total_amount'] ?? 0), 2),
                'Discount' => number_format((float)($sale['discount'] ?? 0), 2),
                'Tax' => number_format((float)($sale['tax'] ?? 0), 2),
                'Total' => number_format((float)($sale['final_amount'] ?? $sale['total_amount'] ?? 0), 2),
                'Payment Method' => $sale['payment_method'] ?? 'CASH',
                'Status' => $sale['payment_status'] ?? 'PAID'
            ];
        }
        
        // Add summary row
        if (!empty($formattedData)) {
            $formattedData[] = [
                'Sale ID' => 'SUMMARY',
                'Date' => '',
                'Customer' => '',
                'Items' => '',
                'Subtotal' => '',
                'Discount' => '',
                'Tax' => '',
                'Total' => '₵' . number_format($totalRevenue, 2),
                'Payment Method' => 'Total Sales: ' . $totalSales,
                'Status' => ''
            ];
        }
        
        // Generate filename
        $periodLabel = ucfirst($period);
        if ($period === 'custom') {
            $periodLabel = $dateFrom . '_to_' . $dateTo;
        }
        $filename = 'sales_report_' . $periodLabel . '_' . date('Ymd') . '.' . ($format === 'xlsx' ? 'xlsx' : 'pdf');
        
        // Export
        if ($format === 'xlsx') {
            $this->exportService->exportExcel($formattedData, $filename, 'Sales Report - ' . $periodLabel);
        } else {
            $this->exportService->exportPDF($formattedData, $filename, 'Sales Report - ' . $periodLabel);
        }
    }
    
    /**
     * Preview API - Get stats for selected period
     */
    public function preview() {
        WebAuthMiddleware::handle(['salesperson']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;
        $companyId = $user['company_id'] ?? null;
        
        if (!$userId || !$companyId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Get sales for this salesperson
        $sales = $this->sale->findByCashier($userId, $companyId, $dateFrom, $dateTo);
        
        $totalRevenue = 0;
        $totalSales = count($sales);
        
        foreach ($sales as $sale) {
            $totalRevenue += (float)($sale['final_amount'] ?? $sale['total_amount'] ?? 0);
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sales' => $totalSales,
                'total_revenue' => $totalRevenue
            ]
        ]);
        exit;
    }
}

