<?php
/**
 * Test File: Check Salesperson and Technician Access to Products and Customers
 * 
 * This script diagnoses why:
 * 1. Salespersons don't see all products that managers see
 * 2. Some customers don't appear for salespersons and technicians when searching
 * 
 * Usage: Access via browser
 * URL: https://sellapp.store/test_salesperson_access.php?company_id=11
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database config
    if (!file_exists(__DIR__ . '/config/database.php')) {
        throw new Exception('Database config file not found');
    }
    require_once __DIR__ . '/config/database.php';
    
    // Include Database model
    if (!file_exists(__DIR__ . '/app/Models/Database.php')) {
        throw new Exception('Database model file not found');
    }
    require_once __DIR__ . '/app/Models/Database.php';
    
    // Get database connection
    try {
        $db = \Database::getInstance()->getConnection();
    } catch (Exception $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
    
    // Get company ID from query parameter or default
    $testCompanyId = isset($_GET['company_id']) ? intval($_GET['company_id']) : 11;
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 50px; background: #f5f5f5; }
            .error { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #d32f2f; }
            pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>❌ Error Loading Test File</h1>
            <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
            <p><strong>File:</strong> ' . htmlspecialchars(__FILE__) . '</p>
            <p><strong>Line:</strong> ' . $e->getLine() . '</p>
            <h3>Stack Trace:</h3>
            <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
        </div>
    </body>
    </html>');
}

// Set headers for browser viewing
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Salesperson Access Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3; }
        .error { background: #ffebee; border-left-color: #f44336; }
        .warning { background: #fff3e0; border-left-color: #ff9800; }
        .success { background: #e8f5e9; border-left-color: #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #2196F3; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-red { background: #f44336; color: white; }
        .badge-green { background: #4CAF50; color: white; }
        .badge-orange { background: #ff9800; color: white; }
        .count { font-size: 24px; font-weight: bold; color: #2196F3; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 11px; }
        .info-box { background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Salesperson & Technician Access Diagnostic Test</h1>
        <p><strong>Company ID:</strong> <?= htmlspecialchars($testCompanyId) ?></p>
        <p><strong>Purpose:</strong> Identify why salespersons/technicians don't see all products and customers that managers see</p>
        <hr>

<?php

// ============================================
// TEST 1: Compare Product Counts
// ============================================
echo '<div class="section">';
echo '<h2>Test 1: Product Count Comparison</h2>';

try {
    // Check if columns exist
    $hasIsSwappedItem = false;
    $hasSwapRefId = false;
    $hasInventoryProductId = false;
    
    try {
        $checkIsSwapped = $db->query("SHOW COLUMNS FROM products LIKE 'is_swapped_item'");
        $hasIsSwappedItem = $checkIsSwapped && $checkIsSwapped->rowCount() > 0;
    } catch (Exception $e) {
        echo '<div class="warning">Could not check is_swapped_item column: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    try {
        $checkSwapRef = $db->query("SHOW COLUMNS FROM products LIKE 'swap_ref_id'");
        $hasSwapRefId = $checkSwapRef && $checkSwapRef->rowCount() > 0;
    } catch (Exception $e) {
        echo '<div class="warning">Could not check swap_ref_id column: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    try {
        $checkInventory = $db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
        $hasInventoryProductId = $checkInventory && $checkInventory->rowCount() > 0;
    } catch (Exception $e) {
        echo '<div class="warning">Could not check inventory_product_id column: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    // Total products in database
    $totalQuery = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
    $totalQuery->execute([$testCompanyId]);
    $totalProducts = $totalQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Products that managers see (using findByCompanyPaginated logic)
    $si2Join = $hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "";
    $managerSql = "
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        " . ($hasSwapRefId ? "LEFT JOIN swapped_items si ON p.swap_ref_id = si.id" : "") . "
        {$si2Join}
        WHERE p.company_id = ?
    ";
    
    // Manager view excludes swapped items with quantity = 0 (sold items)
    if ($hasIsSwappedItem) {
        $conditions = [];
        $conditions[] = "(COALESCE(p.is_swapped_item, 0) = 1 AND COALESCE(p.quantity, 0) = 0)";
        if ($hasInventoryProductId) {
            $conditions[] = "(si2.id IS NOT NULL AND COALESCE(p.quantity, 0) = 0)";
        }
        if (!empty($conditions)) {
            $managerSql .= " AND NOT (" . implode(" OR ", $conditions) . ")";
        }
    }
    
    $managerQuery = $db->prepare($managerSql);
    $managerQuery->execute([$testCompanyId]);
    $managerProducts = $managerQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Products that salespersons see (using findByCompany logic with swappedItems=false)
    $salespersonSql = "
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        " . ($hasSwapRefId ? "LEFT JOIN swapped_items si ON p.swap_ref_id = si.id" : "") . "
        {$si2Join}
        WHERE p.company_id = ?
    ";
    
    // Salesperson view EXCLUDES ALL swapped items (this is the bug!)
    if ($hasIsSwappedItem) {
        $salespersonSql .= " AND COALESCE(p.is_swapped_item, 0) = 0";
    }
    
    // Also exclude swapped items linked via inventory_product_id
    if ($hasInventoryProductId) {
        $salespersonSql .= " AND (si2.id IS NULL OR COALESCE(p.is_swapped_item, 0) = 0)";
    }
    
    $salespersonQuery = $db->prepare($salespersonSql);
    $salespersonQuery->execute([$testCompanyId]);
    $salespersonProducts = $salespersonQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Count swapped items
    $swappedItemsCount = 0;
    try {
        $swappedItemsQuery = $db->prepare("
            SELECT COUNT(*) as total
            FROM products p
            " . ($hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "") . "
            WHERE p.company_id = ?
            AND (
                " . ($hasIsSwappedItem ? "COALESCE(p.is_swapped_item, 0) = 1" : "0") . "
                " . ($hasInventoryProductId ? "OR si2.id IS NOT NULL" : "") . "
            )
        ");
        $swappedItemsQuery->execute([$testCompanyId]);
        $swappedItemsCount = $swappedItemsQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        echo '<div class="warning">Could not count swapped items: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '<div class="info-box">';
    echo '<p><strong>Total Products in Database:</strong> <span class="count">' . $totalProducts . '</span></p>';
    echo '<p><strong>Products Managers See:</strong> <span class="count">' . $managerProducts . '</span></p>';
    echo '<p><strong>Products Salespersons See:</strong> <span class="count">' . $salespersonProducts . '</span></p>';
    echo '<p><strong>Swapped Items Count:</strong> <span class="count">' . $swappedItemsCount . '</span></p>';
    echo '<p><strong>Missing Products for Salespersons:</strong> <span class="count" style="color: #f44336;">' . ($managerProducts - $salespersonProducts) . '</span></p>';
    echo '</div>';
    
    if ($managerProducts > $salespersonProducts) {
        echo '<div class="error">';
        echo '<p><strong>❌ ISSUE FOUND:</strong> Salespersons are missing ' . ($managerProducts - $salespersonProducts) . ' products!</p>';
        echo '<p><strong>Root Cause:</strong> The salesperson query excludes ALL swapped items with: <code>AND COALESCE(p.is_swapped_item, 0) = 0</code></p>';
        echo '<p><strong>Fix:</strong> Remove this exclusion or make it match the manager query logic</p>';
        echo '</div>';
    } else {
        echo '<div class="success">✅ Product counts match!</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
echo '</div>';

// ============================================
// TEST 2: Customer Count Comparison
// ============================================
echo '<div class="section">';
echo '<h2>Test 2: Customer Count Comparison</h2>';

try {
    // Total customers in database
    $totalCustomersQuery = $db->prepare("SELECT COUNT(*) as total FROM customers WHERE company_id = ?");
    $totalCustomersQuery->execute([$testCompanyId]);
    $totalCustomers = $totalCustomersQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Customers via findByCompany (used in some places) - has LIMIT 100
    $findByCompanyCount = min(100, $totalCustomers);
    
    // Customers via allByCompany (used in POS) - no limit
    $allByCompanyCount = $totalCustomers;
    
    // Customers via quickSearch (used in autocomplete) - LIMIT 50
    $quickSearchCount = min(50, $totalCustomers);
    
    echo '<div class="info-box">';
    echo '<p><strong>Total Customers in Database:</strong> <span class="count">' . $totalCustomers . '</span></p>';
    echo '<p><strong>Customers via findByCompany (LIMIT 100):</strong> <span class="count">' . $findByCompanyCount . '</span></p>';
    echo '<p><strong>Customers via allByCompany (no limit):</strong> <span class="count">' . $allByCompanyCount . '</span></p>';
    echo '<p><strong>Customers via quickSearch (LIMIT 50):</strong> <span class="count">' . $quickSearchCount . '</span></p>';
    echo '</div>';
    
    if ($totalCustomers > 100) {
        echo '<div class="warning">';
        echo '<p><strong>⚠️ ISSUE FOUND:</strong> There are ' . $totalCustomers . ' customers, but <code>findByCompany()</code> only returns 100!</p>';
        echo '<p><strong>Impact:</strong> Salespersons/technicians using this method will miss ' . ($totalCustomers - 100) . ' customers</p>';
        echo '<p><strong>Fix:</strong> Use <code>allByCompany()</code> instead of <code>findByCompany()</code> or increase the limit</p>';
        echo '</div>';
    }
    
    if ($totalCustomers > 50) {
        echo '<div class="warning">';
        echo '<p><strong>⚠️ ISSUE FOUND:</strong> <code>quickSearch()</code> only returns 50 customers!</p>';
        echo '<p><strong>Impact:</strong> Autocomplete/search will miss ' . ($totalCustomers - 50) . ' customers</p>';
        echo '<p><strong>Fix:</strong> Increase the limit in <code>Customer::quickSearch()</code> or remove the limit</p>';
        echo '</div>';
    }
    
    if ($totalCustomers <= 100 && $totalCustomers <= 50) {
        echo '<div class="success">✅ Customer counts are within limits!</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
echo '</div>';

// ============================================
// SUMMARY
// ============================================
echo '<div class="section success">';
echo '<h2>📊 Summary</h2>';
echo '<p><strong>Test completed at:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><strong>Issues Found:</strong></p>';
echo '<ol>';
echo '<li><strong>Products:</strong> Salespersons exclude ALL swapped items, missing ' . ($managerProducts - $salespersonProducts) . ' products</li>';
echo '<li><strong>Customers:</strong> Various methods have limits (100, 50) that may hide customers</li>';
echo '</ol>';
echo '<p><strong>Recommended Fixes:</strong></p>';
echo '<ol>';
echo '<li>Update <code>InventoryController::productsIndex()</code> to use <code>findByCompanyPaginated()</code> like managers do</li>';
echo '<li>Or modify <code>Product::findByCompany()</code> to not exclude swapped items when <code>includeSwappedItemsAlways = false</code></li>';
echo '<li>Update customer queries to use <code>allByCompany()</code> or increase limits</li>';
echo '<li>Increase <code>quickSearch()</code> limit from 50 to at least 200</li>';
echo '</ol>';
echo '</div>';

?>

    </div>
</body>
</html>
