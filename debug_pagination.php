<?php
require_once __DIR__ . '/config/database.php';

$companyId = 11; // Your company ID

$db = Database::getInstance()->getConnection();

// Check total customers
$stmt = $db->prepare("SELECT COUNT(*) as total FROM customers WHERE company_id = ?");
$stmt->execute([$companyId]);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Total customers for company $companyId: $total\n\n";

// Check what page 1 returns with limit 10
$stmt = $db->prepare("SELECT id, unique_id, full_name, phone_number, created_at FROM customers WHERE company_id = ? ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET 0");
$stmt->execute([$companyId]);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Page 1 (limit 10, offset 0): " . count($customers) . " customers\n";
echo "─────────────────────────────────────────\n";
foreach ($customers as $i => $c) {
    echo ($i+1) . ". ID: {$c['id']}, {$c['unique_id']}, {$c['full_name']}, {$c['phone_number']}, {$c['created_at']}\n";
}

echo "\n";
echo "All customer IDs in company:\n";
$stmt = $db->prepare("SELECT id, unique_id, full_name, created_at FROM customers WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$companyId]);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $c) {
    echo "  - ID {$c['id']}: {$c['unique_id']} ({$c['full_name']}) - {$c['created_at']}\n";
}

