<?php
/**
 * Test File: Check Exact Product Query on Live Server
 * 
 * This tests what query is actually running for salesperson products
 * URL: https://sellapp.store/test_product_query_live.php?company_id=11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Try different path configurations
    $configPath = __DIR__ . '/config/database.php';
    if (!file_exists($configPath)) {
        $configPath = dirname(__DIR__) . '/config/database.php';
    }
    
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        throw new Exception('Database config not found');
    }
    
    // Try to include Database model
    $dbModelPath = __DIR__ . '/app/Models/Database.php';
    if (!file_exists($dbModelPath)) {
        // Try alternate path
        $dbModelPath = __DIR__ . '/Database.php';
    }
    
    if (file_exists($dbModelPath)) {
        require_once $dbModelPath;
    }
    
    // Try direct PDO connection if Database class not available
    if (!class_exists('Database')) {
        // Use PDO directly
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'manuelc8_sellapp';
        $username = getenv('DB_USER') ?: 'manuelc8_sellapp';
        $password = getenv('DB_PASS') ?: '';
        
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } else {
        $db = \Database::getInstance()->getConnection();
    }
    
    $testCompanyId = $_GET['company_id'] ?? 11;
    
} catch (Exception $e) {
    die('<html><body style="font-family:Arial;padding:50px;"><h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Query Test - Live Server</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3; }
        .error { background: #ffebee; border-left-color: #f44336; }
        .warning { background: #fff3e0; border-left-color: #ff9800; }
        .success { background: #e8f5e9; border-left-color: #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #2196F3; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .count { font-size: 24px; font-weight: bold; color: #2196F3; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 11px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
        .info-box { background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Product Query Test - Live Server</h1>
        <p><strong>Company ID:</strong> <?= htmlspecialchars($testCompanyId) ?></p>
        <p><strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?></p>
        <hr>

<?php

// Test 1: Check actual product counts with different queries
echo '<div class="section">';
echo '<h2>Test 1: Product Count by Different Queries</h2>';

try {
    // Check columns
    $hasIsSwappedItem = false;
    $hasInventoryProductId = false;
    
    try {
        $check = $db->query("SHOW COLUMNS FROM products LIKE 'is_swapped_item'");
        $hasIsSwappedItem = $check && $check->rowCount() > 0;
    } catch (Exception $e) {}
    
    try {
        $check = $db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
        $hasInventoryProductId = $check && $check->rowCount() > 0;
    } catch (Exception $e) {}
    
    echo '<div class="info-box">';
    echo '<p><strong>is_swapped_item column exists:</strong> ' . ($hasIsSwappedItem ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>inventory_product_id column exists:</strong> ' . ($hasInventoryProductId ? 'YES' : 'NO') . '</p>';
    echo '</div>';
    
    // Query 1: All products
    $q1 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
    $q1->execute([$testCompanyId]);
    $allProducts = $q1->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 2: Products with quantity > 0
    $q2 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity > 0");
    $q2->execute([$testCompanyId]);
    $inStockProducts = $q2->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 3: Out of stock products
    $q3 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity = 0");
    $q3->execute([$testCompanyId]);
    $outOfStockProducts = $q3->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 4: Products excluding swapped items (OLD SALESPERSON QUERY)
    $si2Join = $hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "";
    $q4sql = "SELECT COUNT(*) as total FROM products p {$si2Join} WHERE p.company_id = ?";
    if ($hasIsSwappedItem) {
        $q4sql .= " AND COALESCE(p.is_swapped_item, 0) = 0";
    }
    if ($hasInventoryProductId) {
        $q4sql .= " AND (si2.id IS NULL OR COALESCE(p.is_swapped_item, 0) = 0)";
    }
    $q4 = $db->prepare($q4sql);
    $q4->execute([$testCompanyId]);
    $oldSalespersonQuery = $q4->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 5: Products with quantity > 0 only (NEW SALESPERSON QUERY)
    $q5sql = "SELECT COUNT(*) as total FROM products p {$si2Join} WHERE p.company_id = ? AND COALESCE(p.quantity, 0) > 0";
    $q5 = $db->prepare($q5sql);
    $q5->execute([$testCompanyId]);
    $newSalespersonQuery = $q5->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 6: Swapped items
    $q6sql = "SELECT COUNT(*) as total FROM products p {$si2Join} WHERE p.company_id = ?";
    if ($hasIsSwappedItem) {
        $q6sql .= " AND (COALESCE(p.is_swapped_item, 0) = 1";
        if ($hasInventoryProductId) {
            $q6sql .= " OR si2.id IS NOT NULL";
        }
        $q6sql .= ")";
    }
    $q6 = $db->prepare($q6sql);
    $q6->execute([$testCompanyId]);
    $swappedItems = $q6->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo '<table>';
    echo '<tr><th>Query Type</th><th>Count</th><th>Description</th></tr>';
    echo '<tr><td>All Products</td><td class="count">' . $allProducts . '</td><td>Total in database</td></tr>';
    echo '<tr><td>In Stock (qty > 0)</td><td class="count">' . $inStockProducts . '</td><td>Products available for sale</td></tr>';
    echo '<tr><td>Out of Stock (qty = 0)</td><td class="count">' . $outOfStockProducts . '</td><td>Not available</td></tr>';
    echo '<tr><td>Swapped Items</td><td class="count">' . $swappedItems . '</td><td>From trade-ins</td></tr>';
    echo '<tr style="background: #ffebee;"><td><strong>OLD Salesperson Query</strong></td><td class="count">' . $oldSalespersonQuery . '</td><td><strong>Excludes ALL swapped items</strong></td></tr>';
    echo '<tr style="background: #e8f5e9;"><td><strong>NEW Salesperson Query</strong></td><td class="count">' . $newSalespersonQuery . '</td><td><strong>Shows only in-stock (qty > 0)</strong></td></tr>';
    echo '</table>';
    
    if ($oldSalespersonQuery < $allProducts) {
        echo '<div class="error">';
        echo '<p><strong>❌ PROBLEM:</strong> OLD query excludes ' . ($allProducts - $oldSalespersonQuery) . ' products (swapped items)</p>';
        echo '<p>This is the current behavior on the live server if changes weren\'t deployed.</p>';
        echo '</div>';
    }
    
    if ($newSalespersonQuery < $allProducts) {
        echo '<div class="success">';
        echo '<p><strong>✅ CORRECT:</strong> NEW query shows ' . $newSalespersonQuery . ' in-stock products (hides ' . ($allProducts - $newSalespersonQuery) . ' out-of-stock)</p>';
        echo '<p>Salespersons should only see products they can sell (quantity > 0).</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 2: Check which products are hidden
echo '<div class="section">';
echo '<h2>Test 2: Sample of Hidden Products</h2>';

try {
    // Get 20 products that OLD query excludes
    $si2Join = $hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "";
    $hiddenSql = "
        SELECT p.id, p.name, p.quantity, p.is_swapped_item, p.status
        FROM products p {$si2Join}
        WHERE p.company_id = ?
    ";
    
    if ($hasIsSwappedItem) {
        $hiddenSql .= " AND (COALESCE(p.is_swapped_item, 0) = 1";
        if ($hasInventoryProductId) {
            $hiddenSql .= " OR si2.id IS NOT NULL";
        }
        $hiddenSql .= ")";
    }
    
    $hiddenSql .= " LIMIT 20";
    
    $hiddenQuery = $db->prepare($hiddenSql);
    $hiddenQuery->execute([$testCompanyId]);
    $hiddenProducts = $hiddenQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($hiddenProducts) > 0) {
        echo '<p>Sample of ' . count($hiddenProducts) . ' swapped items (hidden by OLD query):</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Quantity</th><th>Is Swapped</th><th>Status</th></tr>';
        foreach ($hiddenProducts as $p) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($p['id']) . '</td>';
            echo '<td>' . htmlspecialchars($p['name']) . '</td>';
            echo '<td>' . htmlspecialchars($p['quantity']) . '</td>';
            echo '<td>' . ($p['is_swapped_item'] ? 'YES' : 'NO') . '</td>';
            echo '<td>' . htmlspecialchars($p['status']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No swapped items found.</p>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 3: Check if changes were deployed
echo '<div class="section">';
echo '<h2>Test 3: Deployment Check</h2>';

try {
    // Try to check if the Product model has the new code
    $productModelPath = __DIR__ . '/app/Models/Product.php';
    if (file_exists($productModelPath)) {
        $productModelContent = file_get_contents($productModelPath);
        
        // Check for the new filter
        $hasNewFilter = strpos($productModelContent, 'show only in-stock products (quantity > 0)') !== false;
        $hasOldFilter = strpos($productModelContent, 'show all items including quantity = 0') !== false;
        
        echo '<div class="info-box">';
        echo '<p><strong>Product Model Path:</strong> ' . htmlspecialchars($productModelPath) . '</p>';
        echo '<p><strong>Has NEW filter code:</strong> ' . ($hasNewFilter ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>') . '</p>';
        echo '<p><strong>Has OLD filter code:</strong> ' . ($hasOldFilter ? '<span style="color: orange;">⚠️ YES (not updated)</span>' : '<span style="color: green;">✅ NO (updated)</span>') . '</p>';
        echo '</div>';
        
        if (!$hasNewFilter) {
            echo '<div class="error">';
            echo '<p><strong>❌ DEPLOYMENT ISSUE:</strong> The code changes have NOT been deployed to the live server!</p>';
            echo '<p><strong>Action required:</strong> Push changes to git and pull on the live server.</p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<p><strong>✅ CODE UPDATED:</strong> The new filter code is present in the Product model.</p>';
            echo '</div>';
        }
    }
    
} catch (Exception $e) {
    echo '<div class="warning">Could not check deployment: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Summary
echo '<div class="section success">';
echo '<h2>📊 Summary</h2>';
echo '<p><strong>Test completed at:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<h3>Current Status:</h3>';
echo '<ul>';
echo '<li><strong>Total Products:</strong> ' . $allProducts . '</li>';
echo '<li><strong>In Stock:</strong> ' . $inStockProducts . '</li>';
echo '<li><strong>Out of Stock:</strong> ' . $outOfStockProducts . '</li>';
echo '<li><strong>Swapped Items:</strong> ' . $swappedItems . '</li>';
echo '</ul>';

echo '<h3>What Salespersons SHOULD See:</h3>';
echo '<ul>';
echo '<li><strong>Expected:</strong> ' . $newSalespersonQuery . ' products (only in-stock items)</li>';
echo '<li><strong>Reason:</strong> Only show products they can actually sell (quantity > 0)</li>';
echo '</ul>';

echo '<h3>Action Required:</h3>';
echo '<ol>';
echo '<li>On your local machine, run: <code>git add . && git commit -m "Fix salesperson product filter" && git push</code></li>';
echo '<li>On the live server, run: <code>git pull</code></li>';
echo '<li>Refresh this page to verify changes</li>';
echo '</ol>';
echo '</div>';

?>

    </div>
</body>
</html>

