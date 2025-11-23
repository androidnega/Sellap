<?php
header('Content-Type: text/plain');

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Models/Customer.php';

// Set up session like a logged in user
$_SESSION['user'] = [
    'id' => 1,
    'company_id' => 11,
    'role' => 'manager'
];

$companyId = 11;
$customerModel = new App\Models\Customer();

echo "HTML Rendering Test\n";
echo "══════════════════════════════════════════\n\n";

// Get customers
$customers = $customerModel->getPaginated(1, 10, null, null, $companyId);

echo "Customers from database: " . count($customers) . "\n\n";

// Simulate the rendering loop
$displayedCount = 0;
$renderedIds = [];

foreach ($customers as $customer) {
    if (isset($renderedIds[$customer['id']])) {
        echo "SKIPPED: Duplicate customer ID {$customer['id']}\n";
        continue;
    }
    $renderedIds[$customer['id']] = true;
    $displayedCount++;
    
    echo "Rendering #{$displayedCount}:\n";
    echo "  ID: {$customer['id']}\n";
    echo "  Unique ID: {$customer['unique_id']}\n";
    echo "  Name: {$customer['full_name']}\n";
    echo "  Phone: {$customer['phone_number']}\n\n";
}

echo "══════════════════════════════════════════\n";
echo "Total displayed: {$displayedCount}\n";
