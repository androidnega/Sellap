<?php
/**
 * Dedicated automigrate entrypoint.
 * Runs lightweight, idempotent schema fixes without router/session dependency.
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    $db = \Database::getInstance()->getConnection();
    $messages = [];

    $ensureColumns = function($tableName, array $requiredColumns) use ($db, &$messages) {
        $existsStmt = $db->query("SHOW TABLES LIKE " . $db->quote($tableName));
        if ($existsStmt->rowCount() === 0) {
            $messages[] = "Skipped {$tableName}: table not found";
            return;
        }

        $columnsStmt = $db->query("SHOW COLUMNS FROM {$tableName}");
        $existing = [];
        foreach ($columnsStmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if (!empty($col['Field'])) {
                $existing[$col['Field']] = true;
            }
        }

        foreach ($requiredColumns as $columnName => $definition) {
            if (isset($existing[$columnName])) {
                $messages[] = "{$tableName}.{$columnName}: exists";
                continue;
            }
            $db->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$definition}");
            $messages[] = "{$tableName}.{$columnName}: added";
        }
    };

    $ensureColumns('products', [
        'sku' => "VARCHAR(255) NULL AFTER product_id",
        'category_name' => "VARCHAR(255) NULL AFTER category",
        'brand_name' => "VARCHAR(255) NULL AFTER brand",
    ]);
    $ensureColumns('products_new', [
        'sku' => "VARCHAR(255) NULL AFTER product_id",
        'category_name' => "VARCHAR(255) NULL AFTER category",
        'brand_name' => "VARCHAR(255) NULL AFTER brand",
    ]);

    echo "Automigrate completed.\n\n";
    echo implode("\n", $messages);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo "Automigrate failed: " . $e->getMessage();
    exit;
}
