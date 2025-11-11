<?php
/**
 * Report Scheduler Worker - Handles scheduled backups and reports
 * 
 * This should be run via cron every hour:
 * 0 * * * * php /path/to/app/Workers/report_scheduler.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/BackupService.php';
require_once __DIR__ . '/../Services/AnalyticsService.php';
require_once __DIR__ . '/../Services/ExportService.php';

use App\Services\BackupService;
use App\Services\AnalyticsService;
use App\Services\ExportService;

echo "[" . date('Y-m-d H:i:s') . "] Report Scheduler Worker started\n";

try {
    $backupService = new BackupService();
    $analyticsService = new AnalyticsService();
    $exportService = new ExportService();
    $db = \Database::getInstance()->getConnection();

    // Get scheduled reports due to run
    $stmt = $db->prepare("
        SELECT * FROM scheduled_reports
        WHERE enabled = 1
        AND (next_run IS NULL OR next_run <= NOW())
        ORDER BY next_run ASC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($reports as $report) {
        echo "[" . date('Y-m-d H:i:s') . "] Processing scheduled report: {$report['name']} (ID: {$report['id']})\n";
        
        try {
            $parameters = json_decode($report['parameters'] ?? '{}', true);
            $format = $parameters['format'] ?? 'csv';
            $recipients = $parameters['recipients'] ?? [];
            $companyId = $report['company_id'];

            // Handle backup reports
            if ($report['type'] === 'backup') {
                $backupResult = $backupService->exportCompanyData($companyId, 'json');
                
                if ($backupResult['success']) {
                    echo "[" . date('Y-m-d H:i:s') . "] Backup created: {$backupResult['filename']}\n";
                    
                    // TODO: Send notification if recipients are configured
                    // foreach ($recipients as $email) {
                    //     mail($email, 'Backup Completed', "Backup file: {$backupResult['filename']}");
                    // }
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Backup failed for company {$companyId}\n";
                }
            } else {
                // Regular analytics report
                $dateFrom = date('Y-m-d', strtotime('-1 day'));
                $dateTo = date('Y-m-d', strtotime('-1 day'));
                
                $data = [];
                $filename = '';
                $title = '';

                switch ($report['type']) {
                    case 'daily_sales':
                        $data = $analyticsService->getSalesByDateRange($companyId, $dateFrom, $dateTo);
                        $filename = 'daily_sales_' . date('Ymd', strtotime('-1 day')) . '.' . $format;
                        $title = 'Daily Sales Report - ' . $dateFrom;
                        break;

                    case 'weekly_inventory':
                        $inventoryStats = $analyticsService->getInventoryStats($companyId);
                        $data = [
                            ['Metric' => 'Total Products', 'Value' => $inventoryStats['total']],
                            ['Metric' => 'In Stock', 'Value' => $inventoryStats['in_stock']],
                            ['Metric' => 'Low Stock', 'Value' => $inventoryStats['low_stock']],
                            ['Metric' => 'Out of Stock', 'Value' => $inventoryStats['out_of_stock']],
                            ['Metric' => 'Total Value', 'Value' => number_format($inventoryStats['total_value'], 2)]
                        ];
                        $filename = 'weekly_inventory_' . date('Ymd') . '.' . $format;
                        $title = 'Weekly Inventory Report';
                        break;

                    case 'monthly_profit':
                        $dateFrom = date('Y-m-01');
                        $dateTo = date('Y-m-d');
                        $profitStats = $analyticsService->getProfitStats($companyId, $dateFrom, $dateTo);
                        $data = [[
                            'Period' => date('F Y'),
                            'Revenue' => number_format($profitStats['revenue'], 2),
                            'Cost' => number_format($profitStats['cost'], 2),
                            'Profit' => number_format($profitStats['profit'], 2),
                            'Margin %' => $profitStats['margin']
                        ]];
                        $filename = 'monthly_profit_' . date('Ym') . '.' . $format;
                        $title = 'Monthly Profit Report - ' . date('F Y');
                        break;

                    default:
                        echo "[" . date('Y-m-d H:i:s') . "] Unknown report type: {$report['type']}\n";
                        continue 2;
                }

                // Generate export file
                $reportsDir = __DIR__ . '/../../storage/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }

                $filepath = $reportsDir . '/' . $filename;
                
                ob_start();
                if ($format === 'xlsx') {
                    $exportService->exportExcel($data, $filename, $title);
                } elseif ($format === 'pdf') {
                    $exportService->exportPDF($data, $filename, $title);
                } else {
                    $exportService->exportCSV($data, $filename);
                }
                $output = ob_get_clean();

                file_put_contents($filepath, $output);
                echo "[" . date('Y-m-d H:i:s') . "] Report generated: {$filename}\n";
            }

            // Update report status
            $nextRun = $this->calculateNextRun($report['cron_expr']);
            $stmt = $db->prepare("
                UPDATE scheduled_reports
                SET last_run = NOW(), next_run = :next_run
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $report['id'],
                'next_run' => $nextRun
            ]);

        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERROR processing report {$report['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Report Scheduler Worker completed.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

/**
 * Calculate next run time from cron expression
 */
function calculateNextRun($cronExpr) {
    // Very basic implementation - for production use mtdowling/cron-expression
    $parts = explode(' ', $cronExpr);
    
    if (count($parts) !== 5) {
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }

    // Default to next day if cron parsing is complex
    return date('Y-m-d H:i:s', strtotime('+1 day'));
}

