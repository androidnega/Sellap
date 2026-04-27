<?php
/**
 * Daily job: send scheduled holiday SMS for the current server date.
 * Crontab example: 0 8 * * * /usr/bin/php /path/to/Sellap/cron/run_holiday_sms.php
 */
set_time_limit(3600);

$root = dirname(__DIR__);
chdir($root);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = $root . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

if (file_exists($root . '/.env')) {
    $env = parse_ini_file($root . '/.env');
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

require_once $root . '/config/app.php';
require_once $root . '/config/database.php';
require_once $root . '/app/Services/HolidaySmsDispatcher.php';

$logFile = $root . '/storage/logs/holiday_sms.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$result = \App\Services\HolidaySmsDispatcher::runScheduledForToday();
$line = date('Y-m-d H:i:s') . ' ' . json_encode($result) . "\n";
if (is_dir($logDir) && is_writable($logDir)) {
    @file_put_contents($logFile, $line, FILE_APPEND);
}
if (php_sapi_name() === 'cli') {
    echo $line;
}

exit(0);
