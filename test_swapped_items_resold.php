<?php
/**
 * Test File: Check for Resold Swapped Items Still Showing in Salesperson Dashboard
 * 
 * This script checks for swapped items that have been resold but still appear
 * in the products list for salespersons.
 * 
 * Usage: Access via browser or run via CLI
 * URL: https://sellapp.store/test_swapped_items_resold.php
 */

// Start session and include necessary files
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Models/Database.php';

// Set headers for browser viewing
header('Content-Type: text/html; charset=utf-8');

// Get database connection
try {
    $db = \Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Swapped Items Resold Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3; }
        .error { background: #ffebee; border-left-color: #f44336; }
        .warning { background: #fff3e0; border-left-color: #ff9800; }
        .success { background: #e8f5e9; border-left-color: #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #2196F3; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-sold { background: #f44336; color: white; }
        .badge-available { background: #4CAF50; color: white; }
        .badge-zero { background: #ff9800; color: white; }
        .count { font-size: 24px; font-weight: bold; color: #2196F3; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Swapped Items Resold - Diagnostic Test</h1>
        <p><strong>Purpose:</strong> Identify swapped items that have been resold but still appear in salesperson dashboard</p>
        <hr>

<?php

// ============================================
// TEST 1: Check swapped_items table for resold items
// ============================================
echo '<div class="section">';
echo '<h2>Test 1: Swapped Items Marked as Sold</h2>';

try {
    $soldItemsQuery = $db->prepare("
        SELECT 
            si.id,
            si.swap_id,
            si.inventory_product_id,
            si.status,
            si.resold_on,
            si.resell_price,
            p.id as product_id,
            p.name as product_name,
            p.quantity as product_quantity,
            p.is_swapped_item,
            p.swap_ref_id,
            p.status as product_status,
            p.company_id
        FROM swapped_items si
        LEFT JOIN products p ON (p.swap_ref_id = si.id OR p.id = si.inventory_product_id)
        WHERE si.status = 'sold'
        ORDER BY si.resold_on DESC
        LIMIT 50
    ");
    $soldItemsQuery->execute();
    $soldItems = $soldItemsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p class="count">Found: ' . count($soldItems) . ' resold swapped items</p>';
    
    if (count($soldItems) > 0) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Swapped Item ID</th>';
        echo '<th>Product ID</th>';
        echo '<th>Product Name</th>';
        echo '<th>Product Quantity</th>';
        echo '<th>is_swapped_item</th>';
        echo '<th>swap_ref_id</th>';
        echo '<th>Resold On</th>';
        echo '<th>Status</th>';
        echo '</tr>';
        
        foreach ($soldItems as $item) {
            $qty = intval($item['product_quantity'] ?? 0);
            $isSwapped = intval($item['is_swapped_item'] ?? 0);
            $hasSwapRef = !empty($item['swap_ref_id']);
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['id']) . '</td>';
            echo '<td>' . htmlspecialchars($item['product_id'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['product_name'] ?? 'N/A') . '</td>';
            echo '<td><span class="badge ' . ($qty > 0 ? 'badge-available' : 'badge-zero') . '">' . $qty . '</span></td>';
            echo '<td>' . ($isSwapped ? '<span class="badge badge-sold">YES</span>' : 'NO') . '</td>';
            echo '<td>' . ($hasSwapRef ? htmlspecialchars($item['swap_ref_id']) : 'NULL') . '</td>';
            echo '<td>' . htmlspecialchars($item['resold_on'] ?? 'N/A') . '</td>';
            echo '<td><span class="badge badge-sold">' . htmlspecialchars($item['status']) . '</span></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// ============================================
// TEST 2: Check products that should be hidden but aren't
// ============================================
echo '<div class="section">';
echo '<h2>Test 2: Products with is_swapped_item=1 AND quantity=0 (Should be hidden)</h2>';

try {
    $problemProductsQuery = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.quantity,
            p.is_swapped_item,
            p.swap_ref_id,
            p.status as product_status,
            p.company_id,
            si.id as swapped_item_id,
            si.status as swapped_item_status,
            si.resold_on
        FROM products p
        LEFT JOIN swapped_items si ON (p.swap_ref_id = si.id OR p.id = si.inventory_product_id)
        WHERE (p.is_swapped_item = 1 OR si.id IS NOT NULL)
        AND p.quantity = 0
        ORDER BY p.id DESC
        LIMIT 50
    ");
    $problemProductsQuery->execute();
    $problemProducts = $problemProductsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p class="count">Found: ' . count($problemProducts) . ' swapped products with quantity=0</p>';
    
    if (count($problemProducts) > 0) {
        echo '<div class="warning">⚠️ These products should NOT appear in salesperson dashboard!</div>';
        echo '<table>';
        echo '<tr>';
        echo '<th>Product ID</th>';
        echo '<th>Product Name</th>';
        echo '<th>Quantity</th>';
        echo '<th>is_swapped_item</th>';
        echo '<th>Swapped Item Status</th>';
        echo '<th>Resold On</th>';
        echo '<th>Company ID</th>';
        echo '</tr>';
        
        foreach ($problemProducts as $product) {
            $swappedStatus = $product['swapped_item_status'] ?? 'N/A';
            $isSold = ($swappedStatus === 'sold');
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($product['id']) . '</td>';
            echo '<td>' . htmlspecialchars($product['name']) . '</td>';
            echo '<td><span class="badge badge-zero">' . htmlspecialchars($product['quantity']) . '</span></td>';
            echo '<td><span class="badge badge-sold">YES</span></td>';
            echo '<td>' . ($isSold ? '<span class="badge badge-sold">SOLD</span>' : htmlspecialchars($swappedStatus)) . '</td>';
            echo '<td>' . htmlspecialchars($product['resold_on'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($product['company_id']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="success">✅ No problematic products found!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// ============================================
// TEST 3: Check what products would show in salesperson dashboard
// ============================================
echo '<div class="section">';
echo '<h2>Test 3: Products That Would Appear in Salesperson Dashboard</h2>';

// Get a sample company_id (you can modify this)
$testCompanyId = $_GET['company_id'] ?? 11; // Default to company 11

try {
    // This mimics the query used in Product::findByCompanyPaginated
    // when swappedItemsOnly = false
    $hasIsSwappedItem = false;
    $hasInventoryProductId = false;
    
    // Check if columns exist
    $checkIsSwapped = $db->query("SHOW COLUMNS FROM products LIKE 'is_swapped_item'");
    $hasIsSwappedItem = $checkIsSwapped && $checkIsSwapped->rowCount() > 0;
    
    $checkInventory = $db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
    $hasInventoryProductId = $checkInventory && $checkInventory->rowCount() > 0;
    
    $si2Join = $hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "";
    $si2Select = $hasInventoryProductId ? "CASE WHEN si2.id IS NOT NULL THEN 1 ELSE 0 END as has_inventory_link, si2.status as si2_status," : "";
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.quantity,
            p.is_swapped_item,
            p.swap_ref_id,
            p.status as product_status,
            p.company_id,
            {$si2Select}
            si.id as swapped_item_id,
            si.status as swapped_item_status
        FROM products p
        LEFT JOIN swapped_items si ON p.swap_ref_id = si.id
        {$si2Join}
        WHERE p.company_id = ?
    ";
    
    // Add the filtering logic (same as in Product model)
    if ($hasIsSwappedItem) {
        $conditions = [];
        $conditions[] = "(COALESCE(p.is_swapped_item, 0) = 1 AND COALESCE(p.quantity, 0) = 0)";
        if ($hasInventoryProductId) {
            $conditions[] = "(si2.id IS NOT NULL AND COALESCE(p.quantity, 0) = 0)";
        }
        if (!empty($conditions)) {
            $sql .= " AND NOT (" . implode(" OR ", $conditions) . ")";
        }
    }
    
    $sql .= " ORDER BY p.id DESC LIMIT 100";
    
    $dashboardQuery = $db->prepare($sql);
    $dashboardQuery->execute([$testCompanyId]);
    $dashboardProducts = $dashboardQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter to find swapped items that shouldn't be there
    $problematicInDashboard = [];
    foreach ($dashboardProducts as $product) {
        $isSwapped = intval($product['is_swapped_item'] ?? 0) > 0 || 
                     (isset($product['has_inventory_link']) && intval($product['has_inventory_link']) > 0);
        $qty = intval($product['quantity'] ?? 0);
        $swappedStatus = $product['swapped_item_status'] ?? $product['si2_status'] ?? null;
        
        // If it's a swapped item with quantity 0 OR status sold, it shouldn't be here
        if ($isSwapped && ($qty == 0 || $swappedStatus === 'sold')) {
            $problematicInDashboard[] = $product;
        }
    }
    
    echo '<p><strong>Company ID:</strong> ' . htmlspecialchars($testCompanyId) . '</p>';
    echo '<p class="count">Total products in query: ' . count($dashboardProducts) . '</p>';
    echo '<p class="count" style="color: ' . (count($problematicInDashboard) > 0 ? '#f44336' : '#4CAF50') . ';">';
    echo 'Problematic swapped items: ' . count($problematicInDashboard) . '</p>';
    
    if (count($problematicInDashboard) > 0) {
        echo '<div class="error">❌ FOUND ISSUE: These resold swapped items are still showing in dashboard!</div>';
        echo '<table>';
        echo '<tr>';
        echo '<th>Product ID</th>';
        echo '<th>Product Name</th>';
        echo '<th>Quantity</th>';
        echo '<th>is_swapped_item</th>';
        echo '<th>Swapped Item Status</th>';
        echo '<th>Issue</th>';
        echo '</tr>';
        
        foreach ($problematicInDashboard as $product) {
            $swappedStatus = $product['swapped_item_status'] ?? $product['si2_status'] ?? 'N/A';
            $issue = [];
            if (intval($product['quantity'] ?? 0) == 0) $issue[] = 'Quantity = 0';
            if ($swappedStatus === 'sold') $issue[] = 'Status = SOLD';
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($product['id']) . '</td>';
            echo '<td>' . htmlspecialchars($product['name']) . '</td>';
            echo '<td><span class="badge badge-zero">' . htmlspecialchars($product['quantity']) . '</span></td>';
            echo '<td><span class="badge badge-sold">YES</span></td>';
            echo '<td><span class="badge badge-sold">' . htmlspecialchars($swappedStatus) . '</span></td>';
            echo '<td>' . implode(', ', $issue) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="success">✅ No problematic swapped items found in dashboard query!</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// ============================================
// TEST 4: Check the actual query being used
// ============================================
echo '<div class="section">';
echo '<h2>Test 4: Query Analysis</h2>';

echo '<h3>Current Filtering Logic:</h3>';
echo '<p>The query should exclude:</p>';
echo '<ul>';
echo '<li>Products where <code>is_swapped_item = 1 AND quantity = 0</code></li>';
echo '<li>Products linked via <code>inventory_product_id</code> where <code>quantity = 0</code></li>';
echo '</ul>';

echo '<h3>Potential Issue:</h3>';
echo '<div class="warning">';
echo '<p><strong>The query does NOT check <code>swapped_items.status = \'sold\'</code></strong></p>';
echo '<p>This means if a swapped item is marked as sold in the <code>swapped_items</code> table but the product quantity is not set to 0, it might still appear.</p>';
echo '<p><strong>Recommendation:</strong> The query should also exclude products where the linked <code>swapped_items.status = \'sold\'</code></p>';
echo '</div>';

echo '<h3>SQL Query That Should Be Used:</h3>';
echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">';
echo htmlspecialchars("
SELECT p.*
FROM products p
LEFT JOIN swapped_items si ON p.swap_ref_id = si.id
LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id
WHERE p.company_id = ?
AND NOT (
    (COALESCE(p.is_swapped_item, 0) = 1 AND COALESCE(p.quantity, 0) = 0)
    OR (si2.id IS NOT NULL AND COALESCE(p.quantity, 0) = 0)
    OR (si.id IS NOT NULL AND si.status = 'sold')  -- ADD THIS CHECK
    OR (si2.id IS NOT NULL AND si2.status = 'sold')  -- ADD THIS CHECK
)
");
echo '</pre>';
echo '</div>';

// ============================================
// SUMMARY
// ============================================
echo '<div class="section success">';
echo '<h2>📊 Summary</h2>';
echo '<p><strong>Test completed at:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ol>';
echo '<li>Review the problematic products found above</li>';
echo '<li>Check if the filtering query needs to include <code>swapped_items.status = \'sold\'</code> check</li>';
echo '<li>Verify that <code>markAsSold()</code> properly sets product quantity to 0</li>';
echo '<li>Update the Product model queries to exclude sold swapped items</li>';
echo '</ol>';
echo '</div>';

?>

    </div>
</body>
</html>








