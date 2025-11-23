<?php
session_start();
$_SESSION['user'] = ['id' => 1, 'company_id' => 11, 'role' => 'manager'];

// Capture output
ob_start();
require_once __DIR__ . '/app/Controllers/CustomerController.php';
$controller = new App\Controllers\CustomerController();
$controller->webIndex();
$html = ob_get_clean();

// Extract tbody
preg_match('/<tbody[^>]*id="customersTableBody"[^>]*>(.*?)<\/tbody>/s', $html, $matches);

header('Content-Type: text/plain');
if ($matches) {
    echo "Raw <tbody> HTML:\n";
    echo "==========================================\n\n";
    echo $matches[0];
    echo "\n\n==========================================\n";
    
    // Count tr tags
    $trCount = substr_count($matches[0], '<tr data-customer-id=');
    echo "\nTotal <tr> tags: $trCount\n";
} else {
    echo "Could not find tbody";
}

