<?php
/**
 * Run inventory travel sessions migration (CLI).
 *
 * CLI:
 *   php database/migrations/run_inventory_travel_sessions_migration.php
 *
 * Admin UI (system_admin): Dashboard → Migration Tools → "Inventory travel sessions" → Run migration
 *   {BASE_URL_PATH}/dashboard/tools/run-inventory-travel-sessions-migration
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(403);
    echo "Run from CLI only. Use Migration Tools in the dashboard (system_admin).\n";
    exit(1);
}

try {
    $db = \Database::getInstance()->getConnection();
    $path = __DIR__ . '/create_inventory_travel_sessions.sql';
    if (!is_readable($path)) {
        throw new RuntimeException('Missing file: ' . $path);
    }

    $hasSessions = $db->query("SHOW TABLES LIKE 'inventory_travel_sessions'")->rowCount() > 0;
    $hasLines = $db->query("SHOW TABLES LIKE 'inventory_travel_snapshot_lines'")->rowCount() > 0;
    if ($hasSessions && $hasLines) {
        echo "OK: Tables already exist. Nothing to do.\n";
        exit(0);
    }

    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $stmt = preg_replace('/^(?:--[^\r\n]*(?:\r\n|\n|\r))+/', '', $stmt);
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $db->exec($stmt);
    }

    $okS = $db->query("SHOW TABLES LIKE 'inventory_travel_sessions'")->rowCount() > 0;
    $okL = $db->query("SHOW TABLES LIKE 'inventory_travel_snapshot_lines'")->rowCount() > 0;
    if (!$okS || !$okL) {
        throw new RuntimeException('Migration ran but one or both tables are still missing.');
    }

    echo "OK: inventory_travel_sessions migration applied.\n";
    exit(0);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
