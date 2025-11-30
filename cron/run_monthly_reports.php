<?php
/**
 * Monthly Report Cron Job
 * 
 * This script should be run on the 1st of each month:
 * 0 9 1 * * /usr/bin/php /path/to/sellapp/cron/run_monthly_reports.php
 * 
 * Or on Windows Task Scheduler:
 * php.exe C:\xampp\htdocs\sellapp\cron\run_monthly_reports.php
 */

// Set time limit for long-running operations
set_time_limit(3600); // 1 hour
ini_set('memory_limit', '512M');

// Change to script directory
chdir(__DIR__);

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/MonthlyReportService.php';

use App\Services\MonthlyReportService;

// Log start
$logFile = __DIR__ . '/../logs/monthly_reports.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("=== Starting Monthly Report Scheduler ===", $logFile);

try {
    $reportService = new MonthlyReportService();
    $results = $reportService->sendMonthlyReports();

    // Log results
    logMessage("Monthly reports sent: {$results['sent']} successful, {$results['failed']} failed", $logFile);
    
    if (!empty($results['errors'])) {
        logMessage("Errors encountered:", $logFile);
        foreach ($results['errors'] as $error) {
            logMessage("  - " . $error, $logFile);
        }
    }

    logMessage("=== Monthly Report Scheduler Completed ===", $logFile);
    exit(0);
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), $logFile);
    logMessage("Stack trace: " . $e->getTraceAsString(), $logFile);
    exit(1);
}

