#!/usr/bin/env php
<?php

/**
 * PHASE D: File Cleanup Worker Script
 * Processes pending file deletion jobs from reset_jobs table
 * 
 * Usage:
 *   php workers/process_reset_jobs.php [--limit=N] [--once]
 * 
 * Options:
 *   --limit=N    Process up to N jobs (default: 10)
 *   --once       Process jobs once and exit (default: runs continuously)
 * 
 * Recommended cron setup:
 *   */5 * * * * cd /path/to/sellapp && php workers/process_reset_jobs.php --limit=10 --once
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set time limit (unlimited for CLI)
set_time_limit(0);

// Change to script directory
chdir(__DIR__);

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load application bootstrap
require_once __DIR__ . '/../config/database.php';

use App\Services\FileCleanupService;

/**
 * Parse command line arguments
 */
function parseArguments($argv) {
    $options = [
        'limit' => 10,
        'once' => false,
        'verbose' => false
    ];
    
    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $options['limit'] = (int)substr($arg, 8);
        } elseif ($arg === '--once') {
            $options['once'] = true;
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $options['verbose'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            printUsage();
            exit(0);
        }
    }
    
    return $options;
}

/**
 * Print usage information
 */
function printUsage() {
    echo "File Cleanup Worker - Processes pending file deletion jobs\n\n";
    echo "Usage: php workers/process_reset_jobs.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --limit=N      Process up to N jobs per run (default: 10)\n";
    echo "  --once         Process jobs once and exit (default: continuous)\n";
    echo "  --verbose, -v  Show detailed output\n";
    echo "  --help, -h     Show this help message\n\n";
    echo "Examples:\n";
    echo "  php workers/process_reset_jobs.php --limit=5 --once\n";
    echo "  php workers/process_reset_jobs.php --limit=10 --verbose\n";
}

/**
 * Log message with timestamp
 */
function logMessage($message, $verbose = false) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    
    if ($verbose) {
        echo $logMessage;
    }
    
    error_log($logMessage);
}

/**
 * Main worker loop
 */
function main() {
    global $argv;
    $options = parseArguments($argv ?? []);
    
    logMessage("File cleanup worker starting...", $options['verbose']);
    logMessage("Options: limit={$options['limit']}, once=" . ($options['once'] ? 'yes' : 'no'), $options['verbose']);
    
    $fileCleanupService = new FileCleanupService();
    $iteration = 0;
    
    do {
        $iteration++;
        logMessage("Iteration #{$iteration}: Processing pending jobs...", $options['verbose']);
        
        try {
            $results = $fileCleanupService->processPendingJobs($options['limit']);
            
            if (empty($results)) {
                logMessage("No pending jobs found.", $options['verbose']);
            } else {
                foreach ($results as $result) {
                    if ($result['success']) {
                        logMessage(
                            "Job #{$result['job_id']} completed: {$result['deleted_count']} files deleted, {$result['failed_count']} failed",
                            $options['verbose']
                        );
                    } else {
                        logMessage(
                            "Job #{$result['job_id']} failed: " . ($result['error'] ?? 'Unknown error'),
                            true // Always log failures
                        );
                    }
                }
            }
            
        } catch (\Exception $e) {
            logMessage("Error processing jobs: " . $e->getMessage(), true);
            error_log($e->getTraceAsString());
        }
        
        // If --once flag, exit after one iteration
        if ($options['once']) {
            break;
        }
        
        // Sleep for 30 seconds before next iteration (if running continuously)
        if (!$options['once']) {
            sleep(30);
        }
        
    } while (!$options['once']);
    
    logMessage("File cleanup worker finished.", $options['verbose']);
}

// Run main function
try {
    main();
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
    exit(1);
}

