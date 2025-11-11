<?php
require_once __DIR__ . '/../../config/database.php';

$db = \Database::getInstance()->getConnection();
$stmt = $db->query("SHOW COLUMNS FROM repairs_new WHERE Field = 'technician_id'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Technician ID Column Status:\n";
echo "Type: " . $col['Type'] . "\n";
echo "Nullable: " . $col['Null'] . "\n";
echo "\n";

if (stripos($col['Type'], 'bigint') !== false && stripos($col['Type'], 'unsigned') !== false) {
    echo "✓ Fix is applied correctly! technician_id is BIGINT UNSIGNED\n";
    exit(0);
} else {
    echo "✗ Fix may not be applied. Current type: " . $col['Type'] . "\n";
    echo "Expected: bigint(20) unsigned\n";
    exit(1);
}

