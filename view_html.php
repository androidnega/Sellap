<?php
// Capture the HTML output of the customer page
ob_start();

$_GET['page'] = 1;
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'company_id' => 11,
    'role' => 'manager'
];

require_once __DIR__ . '/app/Controllers/CustomerController.php';
$controller = new App\Controllers\CustomerController();
$controller->webIndex();

$html = ob_get_clean();

// Extract just the table body
preg_match('/<tbody[^>]*id="customersTableBody"[^>]*>(.*?)<\/tbody>/s', $html, $matches);

if ($matches) {
    $tbody = $matches[0];
    
    // Count <tr> tags
    preg_match_all('/<tr[^>]*data-customer-id="(\d+)"/', $tbody, $rows);
    
    echo "Table body HTML analysis:\n";
    echo "══════════════════════════════════════════\n\n";
    echo "Total <tr> tags found: " . count($rows[0]) . "\n\n";
    
    if (!empty($rows[1])) {
        echo "Customer IDs in HTML:\n";
        foreach ($rows[1] as $id) {
            echo "  - Customer ID: $id\n";
        }
    }
    
    echo "\n══════════════════════════════════════════\n";
    echo "Full <tbody> content:\n";
    echo "══════════════════════════════════════════\n\n";
    echo $tbody;
} else {
    echo "Could not find table body in HTML\n";
}

