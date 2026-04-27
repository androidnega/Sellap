<?php
/**
 * Create company_holiday_messages and holiday_sms_runs tables.
 *
 * Usage: php database/migrations/run_company_holiday_sms_migration.php
 */

require_once __DIR__ . '/../../config/database.php';

$cli = php_sapi_name() === 'cli';
if (!$cli) {
    $allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || (isset($_SERVER['HTTP_HOST']) && str_contains((string)$_SERVER['HTTP_HOST'], 'localhost'));
    if (!$allowed) {
        die('CLI or localhost only.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$sqlFile = __DIR__ . '/create_company_holiday_sms_tables.sql';
if (!is_readable($sqlFile)) {
    echo $cli ? "Missing $sqlFile\n" : "Missing migration SQL";
    exit(1);
}

try {
    $db = \Database::getInstance()->getConnection();
    $sql = file_get_contents($sqlFile);
    $db->exec($sql);
    $msg = "OK: company_holiday_messages, holiday_sms_runs (if not already present).\n";
    echo $cli ? $msg : nl2br(htmlspecialchars($msg));
    exit(0);
} catch (\Exception $e) {
    $err = 'Error: ' . $e->getMessage();
    echo $cli ? $err . "\n" : htmlspecialchars($err);
    exit(1);
}
