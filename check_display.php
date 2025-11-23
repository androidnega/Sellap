<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Models/Customer.php';

// Use your company ID
$companyId = 11;

$customerModel = new App\Models\Customer();

echo "Testing customer display logic:\n\n";

// Get customers like the controller does
$customers = $customerModel->getPaginated(1, 10, null, null, $companyId);

echo "Customers returned from getPaginated(): " . count($customers) . "\n\n";

foreach ($customers as $i => $customer) {
    echo ($i+1) . ". ID: {$customer['id']}\n";
    echo "   Unique ID: {$customer['unique_id']}\n";
    echo "   Name: {$customer['full_name']}\n";
    echo "   Phone: {$customer['phone_number']}\n";
    echo "   Email: " . ($customer['email'] ?: 'N/A') . "\n";
    echo "   Created: {$customer['created_at']}\n";
    echo "\n";
}

// Check if deduplication is removing any
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT DISTINCT * FROM customers WHERE company_id = ? ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET 0");
$stmt->execute([$companyId]);
$directQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Direct SQL query returns: " . count($directQuery) . " customers\n";

