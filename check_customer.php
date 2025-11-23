<?php
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

$phone = '43453456789';

$stmt = $db->prepare("SELECT * FROM customers WHERE phone_number = ?");
$stmt->execute([$phone]);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');
echo "Customers with phone {$phone}:\n\n";
echo "Total found: " . count($customers) . "\n\n";

foreach ($customers as $i => $customer) {
    echo "Customer #" . ($i + 1) . ":\n";
    echo "  ID: {$customer['id']}\n";
    echo "  Unique ID: {$customer['unique_id']}\n";
    echo "  Name: {$customer['full_name']}\n";
    echo "  Email: {$customer['email']}\n";
    echo "  Company ID: {$customer['company_id']}\n";
    echo "  Created: {$customer['created_at']}\n";
    echo "\n";
}

