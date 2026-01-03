<?php
/**
 * Test POS Products Count
 * URL: https://sellapp.store/test_pos_products.php?company_id=11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/app/Models/Database.php';
    require_once __DIR__ . '/app/Models/Product.php';
    
    $db = \Database::getInstance()->getConnection();
    $testCompanyId = $_GET['company_id'] ?? 11;
    $productModel = new \App\Models\Product();
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Products Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3; }
        .error { background: #ffebee; border-left-color: #f44336; }
        .success { background: #e8f5e9; border-left-color: #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #2196F3; color: white; }
        .count { font-size: 24px; font-weight: bold; color: #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 POS Products Count Test</h1>
        <p><strong>Company ID:</strong> <?= htmlspecialchars($testCompanyId) ?></p>
        <hr>

<?php

echo '<div class="section">';
echo '<h2>Product Counts</h2>';

try {
    // Total products
    $q1 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
    $q1->execute([$testCompanyId]);
    $totalProducts = $q1->fetch(PDO::FETCH_ASSOC)['total'];
    
    // In stock (quantity > 0)
    $q2 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity > 0");
    $q2->execute([$testCompanyId]);
    $inStock = $q2->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Out of stock
    $outOfStock = $totalProducts - $inStock;
    
    // Swapped items (total)
    $q3 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND is_swapped_item = 1");
    $q3->execute([$testCompanyId]);
    $swappedTotal = $q3->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Swapped items in stock
    $q4 = $db->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND is_swapped_item = 1 AND quantity > 0");
    $q4->execute([$testCompanyId]);
    $swappedInStock = $q4->fetch(PDO::FETCH_ASSOC)['total'];
    
    // What POS API should return
    $posProducts = $productModel->findByCompanyForPOS($testCompanyId, 10000);
    $posCount = count($posProducts);
    
    echo '<table>';
    echo '<tr><th>Metric</th><th>Count</th></tr>';
    echo '<tr><td>Total Products</td><td class="count">' . $totalProducts . '</td></tr>';
    echo '<tr><td>In Stock (qty > 0)</td><td class="count">' . $inStock . '</td></tr>';
    echo '<tr><td>Out of Stock</td><td class="count">' . $outOfStock . '</td></tr>';
    echo '<tr><td>Swapped Items (Total)</td><td class="count">' . $swappedTotal . '</td></tr>';
    echo '<tr><td>Swapped Items (In Stock)</td><td class="count">' . $swappedInStock . '</td></tr>';
    echo '<tr style="background:#e8f5e9;"><td><strong>POS API Returns</strong></td><td class="count">' . $posCount . '</td></tr>';
    echo '</table>';
    
    if ($posCount < $inStock) {
        echo '<div class="error">';
        echo '<p><strong>❌ ISSUE:</strong> POS API returns ' . $posCount . ' products, but there are ' . $inStock . ' in-stock products!</p>';
        echo '<p><strong>Missing:</strong> ' . ($inStock - $posCount) . ' products</p>';
        echo '</div>';
    } else {
        echo '<div class="success">';
        echo '<p><strong>✅ CORRECT:</strong> POS API returns all ' . $posCount . ' in-stock products!</p>';
        echo '</div>';
    }
    
    // Check if code is deployed
    $productFile = __DIR__ . '/app/Models/Product.php';
    if (file_exists($productFile)) {
        $content = file_get_contents($productFile);
        $hasNewLimit = strpos($content, '10000') !== false && strpos($content, 'findByCompanyForPOS') !== false;
        
        if ($hasNewLimit) {
            echo '<div class="success"><p><strong>✅ Code Updated:</strong> POS limit is 10,000</p></div>';
        } else {
            echo '<div class="error"><p><strong>❌ Code NOT Updated:</strong> Changes not deployed!</p></div>';
        }
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

?>

    </div>
</body>
</html>

