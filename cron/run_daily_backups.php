<?php
/**
 * Daily Backup Cron Job
 * 
 * This script should be run daily via cron job:
 * 0 2 * * * /usr/bin/php /path/to/sellapp/cron/run_daily_backups.php
 * 
 * Or on Windows Task Scheduler:
 * php.exe C:\xampp\htdocs\sellapp\cron\run_daily_backups.php
 */

// Set time limit for long-running backup operations
set_time_limit(3600); // 1 hour
ini_set('memory_limit', '512M');

// Change to script directory
chdir(__DIR__);

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/BackupScheduler.php';

use App\Services\BackupScheduler;

// Log start
$logFile = __DIR__ . '/../logs/backup_scheduler.log';
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

logMessage("=== Starting Daily Backup Scheduler ===", $logFile);

try {
    $scheduler = new BackupScheduler();
    $results = $scheduler->runScheduledBackups();

    // Log results
    logMessage("Backup completed for " . count($results['companies']) . " companies", $logFile);
    
    $systemBackup = $results['system'] ?? null;
    if (is_array($systemBackup) && !empty($systemBackup['success'])) {
        $backupId = $systemBackup['backup_id'] ?? 'unknown';
        logMessage("System backup created: ID " . $backupId, $logFile);
    }

    if (!empty($results['errors'])) {
        logMessage("Errors encountered: " . count($results['errors']), $logFile);
        foreach ($results['errors'] as $error) {
            logMessage("  - " . $error, $logFile);
        }
    }

    logMessage("=== Daily Backup Scheduler Completed ===", $logFile);
    exit(0);
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), $logFile);
    logMessage("Stack trace: " . $e->getTraceAsString(), $logFile);
    exit(1);
}

