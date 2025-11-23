<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/app/Models/Customer.php';
    
    $_SESSION['user'] = ['id' => 1, 'company_id' => 11, 'role' => 'manager'];
    
    $customerModel = new App\Models\Customer();
    $customers = $customerModel->getPaginated(1, 10, null, null, 11);
    
    echo "Customers from Model: " . count($customers) . "\n\n";
    
    // Manually render the tbody
    echo "<tbody id=\"customersTableBody\">\n";
    
    $displayedCount = 0;
    $renderedIds = [];
    
    foreach ($customers as $customer) {
        if (isset($renderedIds[$customer['id']])) {
            echo "<!-- SKIPPED duplicate ID {$customer['id']} -->\n";
            continue;
        }
        $renderedIds[$customer['id']] = true;
        $displayedCount++;
        
        echo "  <tr data-customer-id=\"{$customer['id']}\">\n";
        echo "    <td>{$customer['unique_id']}</td>\n";
        echo "    <td>{$customer['full_name']}</td>\n";
        echo "    <td>{$customer['phone_number']}</td>\n";
        echo "  </tr>\n";
    }
    
    echo "</tbody>\n\n";
    echo "Total rendered: $displayedCount\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
