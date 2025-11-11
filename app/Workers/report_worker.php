<?php
/**
 * Report Worker - Generates and sends scheduled reports
 * 
 * This should be run via cron:
 * 0 0 * * * php /path/to/app/Workers/report_worker.php (daily at midnight)
 * 0 0 * * 1 php /path/to/app/Workers/report_worker.php (weekly on Monday)
 * 0 0 1 * * php /path/to/app/Workers/report_worker.php (monthly on 1st)
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AnalyticsService.php';
require_once __DIR__ . '/../Services/ExportService.php';

use App\Services\AnalyticsService;
use App\Services\ExportService;

echo "[" . date('Y-m-d H:i:s') . "] Report Worker started\n";

try {
    $analyticsService = new AnalyticsService();
    $exportService = new ExportService();
    $db = \Database::getInstance()->getConnection();

    // Get all scheduled reports due to run
    $stmt = $db->prepare("
        SELECT * FROM scheduled_reports
        WHERE enabled = 1
        AND (next_run IS NULL OR next_run <= NOW())
        ORDER BY next_run ASC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($reports as $report) {
        echo "[" . date('Y-m-d H:i:s') . "] Processing report: {$report['name']} (ID: {$report['id']})\n";
        
        try {
            $parameters = json_decode($report['parameters'] ?? '{}', true);
            $format = $parameters['format'] ?? 'csv';
            $recipients = $parameters['recipients'] ?? [];
            
            // Generate report data based on type
            $data = [];
            $filename = '';
            $title = '';

            switch ($report['type']) {
                case 'daily_sales':
                    $dateFrom = date('Y-m-d', strtotime('-1 day'));
                    $dateTo = date('Y-m-d', strtotime('-1 day'));
                    $data = $analyticsService->getSalesByDateRange($report['company_id'], $dateFrom, $dateTo);
                    $filename = 'daily_sales_' . date('Ymd', strtotime('-1 day')) . '.' . $format;
                    $title = 'Daily Sales Report - ' . $dateFrom;
                    break;

                case 'weekly_inventory':
                    $data = $analyticsService->getInventoryStats($report['company_id']);
                    // Convert to array format for export
                    $data = [['Metric' => 'Total Products', 'Value' => $data['total']],
                            ['Metric' => 'In Stock', 'Value' => $data['in_stock']],
                            ['Metric' => 'Low Stock', 'Value' => $data['low_stock']],
                            ['Metric' => 'Out of Stock', 'Value' => $data['out_of_stock']],
                            ['Metric' => 'Total Value', 'Value' => number_format($data['total_value'], 2)]];
                    $filename = 'weekly_inventory_' . date('Ymd') . '.' . $format;
                    $title = 'Weekly Inventory Report';
                    break;

                case 'monthly_profit':
                    $dateFrom = date('Y-m-01'); // Start of month
                    $dateTo = date('Y-m-d'); // Today
                    $profitStats = $analyticsService->getProfitStats($report['company_id'], $dateFrom, $dateTo);
                    $data = [['Period' => date('F Y'), 'Revenue' => number_format($profitStats['revenue'], 2),
                             'Cost' => number_format($profitStats['cost'], 2),
                             'Profit' => number_format($profitStats['profit'], 2),
                             'Margin %' => $profitStats['margin']]];
                    $filename = 'monthly_profit_' . date('Ym') . '.' . $format;
                    $title = 'Monthly Profit Report - ' . date('F Y');
                    break;

                default:
                    echo "[" . date('Y-m-d H:i:s') . "] Unknown report type: {$report['type']}\n";
                    continue 2;
            }

            // Generate export file (store in reports directory)
            $reportsDir = __DIR__ . '/../../storage/reports';
            if (!is_dir($reportsDir)) {
                mkdir($reportsDir, 0755, true);
            }

            $filepath = $reportsDir . '/' . $filename;
            
            // Create temporary export
            ob_start();
            if ($format === 'xlsx') {
                $exportService->exportExcel($data, $filename, $title);
            } elseif ($format === 'pdf') {
                $exportService->exportPDF($data, $filename, $title);
            } else {
                $exportService->exportCSV($data, $filename);
            }
            $output = ob_get_clean();

            // Save to file
            file_put_contents($filepath, $output);

            // TODO: Send via email to recipients
            // foreach ($recipients as $email) {
            //     mail($email, $title, "Please find attached report.", [
            //         'From' => 'noreply@sellapp.com',
            //         'Content-Type' => 'multipart/mixed'
            //     ]);
            // }

            // Update report status
            $nextRun = calculateNextRun($report['cron_expr']);
            $stmt = $db->prepare("
                UPDATE scheduled_reports
                SET last_run = NOW(), next_run = :next_run
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $report['id'],
                'next_run' => $nextRun
            ]);

            echo "[" . date('Y-m-d H:i:s') . "] Report generated: {$filename}\n";

        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERROR processing report {$report['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Report Worker completed.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

/**
 * Calculate next run time from cron expression
 * Simple implementation - for production use a proper cron parser library
 */
function calculateNextRun($cronExpr) {
    // Very basic implementation
    // For production, use a library like mtdowling/cron-expression
    $parts = explode(' ', $cronExpr);
    
    if (count($parts) !== 5) {
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }

    // Default to next day if cron parsing is complex
    return date('Y-m-d H:i:s', strtotime('+1 day'));
}

