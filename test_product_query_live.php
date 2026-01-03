<?php
/**
 * Standalone Test - No dependencies
 * URL: https://sellapp.store/test_product_query_live.php?company_id=11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct database connection - no dependencies
try {
    // Database credentials for sellapp.store
    $host = 'localhost';
    $dbname = 'manuelc8_sellapp';
    $username = 'manuelc8_sellapp';
    $password = 'Asempa@2020';  // Update if different
    
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $testCompanyId = isset($_GET['company_id']) ? intval($_GET['company_id']) : 11;
    
} catch (Exception $e) {
    die('<html><body style="font-family:Arial;padding:50px;background:#ffebee;">
    <h1 style="color:#d32f2f;">Database Connection Failed</h1>
    <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
    <p><strong>File:</strong> ' . __FILE__ . '</p>
    <h3>Action:</h3>
    <p>Update database credentials in this file (lines 12-15)</p>
    </body></html>');
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
        <p><strong>Server:</strong> sellapp.store</p>
        <p><strong>Test Time:</strong> <?= date('Y-m-d H:i:s') ?></p>
        <hr>

<?php

// Test 1: Product counts
echo '<div class="section">';
echo '<h2>Test 1: Product Count Analysis</h2>';

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
    
    // Query 1: All products
    $q1 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
    $q1->execute([$testCompanyId]);
    $allProducts = $q1->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 2: In stock
    $q2 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity > 0");
    $q2->execute([$testCompanyId]);
    $inStock = $q2->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 3: Out of stock
    $q3 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity = 0");
    $q3->execute([$testCompanyId]);
    $outOfStock = $q3->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 4: Swapped items
    $swappedItems = 0;
    if ($hasIsSwappedItem) {
        $q4 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND is_swapped_item = 1");
        $q4->execute([$testCompanyId]);
        $swappedItems = $q4->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // Query 5: What OLD salesperson query returns (excludes swapped items)
    $si2Join = $hasInventoryProductId ? "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id" : "";
    $oldQuery = "SELECT COUNT(*) as total FROM products p {$si2Join} WHERE p.company_id = ?";
    if ($hasIsSwappedItem) {
        $oldQuery .= " AND COALESCE(p.is_swapped_item, 0) = 0";
    }
    $q5 = $db->prepare($oldQuery);
    $q5->execute([$testCompanyId]);
    $oldSalespersonCount = $q5->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query 6: What NEW salesperson query SHOULD return (only in-stock)
    $newQuery = "SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity > 0";
    $q6 = $db->prepare($newQuery);
    $q6->execute([$testCompanyId]);
    $newSalespersonCount = $q6->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo '<table>';
    echo '<tr><th>Metric</th><th>Count</th><th>Notes</th></tr>';
    echo '<tr><td>Total Products</td><td class="count">' . $allProducts . '</td><td>All in database</td></tr>';
    echo '<tr><td>In Stock (qty > 0)</td><td class="count">' . $inStock . '</td><td>Can be sold</td></tr>';
    echo '<tr><td>Out of Stock (qty = 0)</td><td class="count">' . $outOfStock . '</td><td>Cannot be sold</td></tr>';
    echo '<tr><td>Swapped Items</td><td class="count">' . $swappedItems . '</td><td>From trade-ins</td></tr>';
    echo '<tr style="background:#ffebee;"><td><strong>CURRENT Salesperson View</strong></td><td class="count">' . $oldSalespersonCount . '</td><td>❌ Excludes ' . ($allProducts - $oldSalespersonCount) . ' swapped items</td></tr>';
    echo '<tr style="background:#e8f5e9;"><td><strong>CORRECT Salesperson View</strong></td><td class="count">' . $newSalespersonCount . '</td><td>✅ Shows only in-stock</td></tr>';
    echo '</table>';
    
    echo '<div class="info-box">';
    echo '<h3>What\'s Happening:</h3>';
    echo '<ul>';
    echo '<li><strong>Managers see:</strong> ' . $allProducts . ' total products (' . $inStock . ' in-stock + ' . $outOfStock . ' out-of-stock)</li>';
    echo '<li><strong>Salespersons CURRENTLY see:</strong> ' . $oldSalespersonCount . ' products (missing ' . ($allProducts - $oldSalespersonCount) . ' swapped items)</li>';
    echo '<li><strong>Salespersons SHOULD see:</strong> ' . $newSalespersonCount . ' in-stock products (can actually sell these)</li>';
    echo '</ul>';
    echo '</div>';
    
    if ($oldSalespersonCount == $allProducts - $swappedItems) {
        echo '<div class="error">';
        echo '<p><strong>❌ PROBLEM CONFIRMED:</strong> Changes NOT deployed! Salespersons still missing ' . $swappedItems . ' swapped items.</p>';
        echo '</div>';
    }
    
    if ($newSalespersonCount == $inStock) {
        echo '<div class="success">';
        echo '<p><strong>✅ CORRECT LOGIC:</strong> Salespersons should see ' . $newSalespersonCount . ' in-stock products.</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 2: Deployment check
echo '<div class="section">';
echo '<h2>Test 2: Check If Code Was Deployed</h2>';

try {
    $productModelPath = __DIR__ . '/app/Models/Product.php';
    
    if (file_exists($productModelPath)) {
        $content = file_get_contents($productModelPath);
        
        $hasNewCode = strpos($content, 'show only in-stock products (quantity > 0)') !== false;
        $hasOldCode = strpos($content, 'show all items including quantity = 0') !== false;
        
        echo '<div class="info-box">';
        echo '<p><strong>File exists:</strong> ' . $productModelPath . '</p>';
        echo '<p><strong>Has NEW code (qty > 0 filter):</strong> ' . ($hasNewCode ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Has OLD code (show all):</strong> ' . ($hasOldCode ? '⚠️ YES (not updated)' : '✅ NO') . '</p>';
        echo '</div>';
        
        if (!$hasNewCode) {
            echo '<div class="error">';
            echo '<h3>❌ CODE NOT DEPLOYED!</h3>';
            echo '<p>The fixes are NOT on the live server yet.</p>';
            echo '<h4>Deploy Steps:</h4>';
            echo '<ol>';
            echo '<li>On your local machine: <code>git push</code></li>';
            echo '<li>SSH to server: <code>ssh manuelc8@sellapp.store</code></li>';
            echo '<li>Navigate: <code>cd /home3/manuelc8/sellapp.store</code></li>';
            echo '<li>Pull changes: <code>git pull</code></li>';
            echo '<li>Refresh this page to verify</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<p><strong>✅ CODE DEPLOYED!</strong> New filter logic is present.</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="warning">';
        echo '<p>Could not find Product model at: ' . $productModelPath . '</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="warning">Could not check: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Summary
echo '<div class="section">';
echo '<h2>📊 Action Required</h2>';

echo '<h3>Step 1: On Your Local Machine</h3>';
echo '<pre>cd C:\\xampp\\htdocs\\sellapp
git add -A
git commit -m "Fix salesperson product filter"
git push</pre>';

echo '<h3>Step 2: On Live Server</h3>';
echo '<pre>ssh manuelc8@sellapp.store
cd /home3/manuelc8/sellapp.store
git pull</pre>';

echo '<h3>Step 3: Verify</h3>';
echo '<p>Refresh this page after deployment to see changes.</p>';

echo '</div>';

?>

    </div>
</body>
</html>
