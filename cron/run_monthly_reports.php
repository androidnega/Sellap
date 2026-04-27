<?php
/**
 * Monthly Report Cron Job
 *
 * Crontab example (1st of month, 09:00):
 *   0 9 1 * * /usr/bin/php /path/to/Sellap/cron/run_monthly_reports.php
 *
 * Design notes (CLI only, no web output from this file):
 * - Load .env so Database matches production; CLI has no HTTP_HOST, otherwise env falls back to "local".
 * - require EmailService before MonthlyReportService: there is no Composer autoload here, so
 *   new EmailService() in the service constructor would otherwise fatal with Error (not Exception).
 * - All failures must use catch (Throwable) in this script so PHP 8 Errors are not silent.
 */
set_time_limit(3600);
ini_set('memory_limit', '512M');
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    // Avoid mixed HTML in stderr if a warning fires; this cron echoes plain text only.
    @ini_set('display_errors', '0');
}

$root = dirname(__DIR__);
chdir($root);

if (file_exists($root . '/.env')) {
    $env = @parse_ini_file($root . '/.env');
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            if (is_string($key) && (is_string($value) || is_int($value) || is_float($value))) {
                $v = (string) $value;
                putenv("{$key}={$v}");
                $_ENV[$key] = $v;
            }
        }
    }
}

$logFile = $root . '/storage/logs/monthly_reports.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

/**
 * @param  string  $message
 * @param  string  $logFile
 * @return void
 */
function monthly_report_log(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
    if (php_sapi_name() === 'cli' && \defined('STDOUT') && \is_resource(\STDOUT)) {
        @fflush(\STDOUT);
    }
}

register_shutdown_function(static function () use ($logFile): void {
    $err = error_get_last();
    if (
        $err
        && \in_array(
            (int) $err['type'],
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
            true
        )
    ) {
        $line = \sprintf(
            '[%s] FATAL (shutdown): %s in %s:%d' . \PHP_EOL,
            date('Y-m-d H:i:s'),
            $err['message'],
            $err['file'],
            $err['line']
        );
        @\file_put_contents($logFile, $line, FILE_APPEND);
        if (\defined('STDERR') && \is_resource(\STDERR)) {
            @\fwrite(\STDERR, $line);
        }
    }
});

// Dependency order: Database → Email (used by service ctor) → Monthly report service
require_once $root . '/config/database.php';
require_once $root . '/app/Services/EmailService.php';
require_once $root . '/app/Services/MonthlyReportService.php';

use App\Services\MonthlyReportService;

monthly_report_log('=== Starting Monthly Report Scheduler (CLI) ===', $logFile);

\App\Services\MonthlyReportService::setStepLog(static function (string $message) use ($logFile): void {
    monthly_report_log($message, $logFile);
});

try {
    monthly_report_log('Step: new MonthlyReportService() ...', $logFile);
    $reportService = new MonthlyReportService();
    monthly_report_log('Step: calling sendMonthlyReports() ...', $logFile);

    $results = $reportService->sendMonthlyReports();

    monthly_report_log("Done: sent={$results['sent']}, failed={$results['failed']}", $logFile);

    if (!empty($results['errors'])) {
        monthly_report_log('Errors encountered:', $logFile);
        foreach ($results['errors'] as $error) {
            monthly_report_log('  - ' . $error, $logFile);
        }
    }

    monthly_report_log('=== Monthly Report Scheduler completed OK ===', $logFile);
    exit(0);
} catch (Throwable $e) {
    $msg = 'FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    monthly_report_log($msg, $logFile);
    $trace = $e->getTraceAsString();
    @file_put_contents($logFile, $trace . PHP_EOL, FILE_APPEND);
    echo $trace . PHP_EOL;
    if (\defined('STDERR') && \is_resource(\STDERR)) {
        @fwrite(\STDERR, $msg . PHP_EOL . $trace . PHP_EOL);
    }
    exit(1);
}
