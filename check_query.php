<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();
$companyId = 11;

// Check what the DISTINCT query returns
echo "Test 1: DISTINCT query (what the model uses)\n";
echo "==============================================\n";
$sql = "SELECT DISTINCT * FROM customers WHERE company_id = ? ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET 0";
$stmt = $db->prepare($sql);
$stmt->execute([$companyId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Rows returned: " . count($results) . "\n\n";
foreach ($results as $i => $row) {
    echo ($i+1) . ". ID: {$row['id']}, {$row['unique_id']}, {$row['full_name']}\n";
}

echo "\n\nTest 2: Without DISTINCT\n";
echo "==============================================\n";
$sql2 = "SELECT * FROM customers WHERE company_id = ? ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET 0";
$stmt2 = $db->prepare($sql2);
$stmt2->execute([$companyId]);
$results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Rows returned: " . count($results2) . "\n\n";
foreach ($results2 as $i => $row) {
    echo ($i+1) . ". ID: {$row['id']}, {$row['unique_id']}, {$row['full_name']}\n";
}

echo "\n\nTest 3: Check for actual duplicates in database\n";
echo "==============================================\n";
$sql3 = "SELECT id, unique_id, full_name, COUNT(*) as count FROM customers WHERE company_id = ? GROUP BY id HAVING COUNT(*) > 1";
$stmt3 = $db->prepare($sql3);
$stmt3->execute([$companyId]);
$dupes = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($dupes)) {
    echo "No duplicate IDs in database\n";
} else {
    echo "DUPLICATE IDS FOUND:\n";
    foreach ($dupes as $d) {
        echo "  ID {$d['id']} appears {$d['count']} times\n";
    }
}

