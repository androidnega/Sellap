<?php

/**
 * Test file to diagnose POS product loading issue
 * This will help identify why only 205 products load instead of 239
 * 
 * Access via: https://app.dcapple.com/test_pos_products_count.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials for app.dcapple.com
$host = 'localhost';
$dbname = 'dcapple3_app';
$username = 'dcapple3_appuser';
$password = 'Atomic2@2020^';
$port = 3306;

?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Products Count Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f9f9f9;
        }
        .success {
            color: #4caf50;
            font-weight: bold;
        }
        .error {
            color: #d32f2f;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .count-box {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background: #e3f2fd;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
        }
        .missing-products {
            background: #ffebee;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 POS Products Count Diagnostic Test</h1>
        <p><strong>Server:</strong> app.dcapple.com</p>
        
        <?php
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ];
            
            $connection = new PDO($dsn, $username, $password, $options);
            echo '<div class="test-section">';
            echo '<h2>✓ Database Connection Successful</h2>';
            echo '</div>';
            
            // First, find all companies that have products
            echo '<div class="test-section">';
            echo '<h2>Available Companies</h2>';
            $stmt = $connection->query("
                SELECT p.company_id, COUNT(*) as product_count 
                FROM products p 
                GROUP BY p.company_id 
                ORDER BY product_count DESC
            ");
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($companies) > 0) {
                echo '<table>';
                echo '<tr><th>Company ID</th><th>Product Count</th><th>Action</th></tr>';
                foreach ($companies as $company) {
                    $cid = $company['company_id'];
                    $count = $company['product_count'];
                    $current = isset($_GET['company_id']) && $_GET['company_id'] == $cid ? ' (Current)' : '';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($cid) . $current . '</td>';
                    echo '<td>' . htmlspecialchars($count) . '</td>';
                    echo '<td><a href="?company_id=' . htmlspecialchars($cid) . '">Test This Company</a></td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="warning">No companies found with products</div>';
            }
            echo '</div>';
            
            // Get company_id from URL or use the first company with products
            if (isset($_GET['company_id'])) {
                $companyId = intval($_GET['company_id']);
            } else {
                // Auto-detect: use the company with the most products
                if (count($companies) > 0) {
                    $companyId = $companies[0]['company_id'];
                    echo '<div class="test-section">';
                    echo '<h2>Auto-Detected Company</h2>';
                    echo '<div class="info">Using Company ID: ' . $companyId . ' (has ' . $companies[0]['product_count'] . ' products)</div>';
                    echo '<div class="info">To test a different company, click the link above or add ?company_id=X to URL</div>';
                    echo '</div>';
                } else {
                    $companyId = 1; // Default fallback
                }
            }
            
            echo '<div class="test-section">';
            echo '<h2>Test Parameters</h2>';
            echo '<div class="info"><strong>Company ID:</strong> ' . htmlspecialchars($companyId) . '</div>';
            echo '</div>';
            
            // Test 1: Total products count
            echo '<div class="test-section">';
            echo '<h2>Test 1: Total Products Count</h2>';
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo '<div class="count-box">Total Products: ' . $totalCount . '</div>';
            echo '</div>';
            
            // Test 2: Products with quantity > 0
            echo '<div class="test-section">';
            echo '<h2>Test 2: Products with Quantity > 0 (In Stock)</h2>';
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND COALESCE(quantity, 0) > 0");
            $stmt->execute([$companyId]);
            $inStockCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo '<div class="count-box">In Stock: ' . $inStockCount . '</div>';
            echo '<div class="info">Expected: ' . ($totalCount - 39) . ' (Total - Out of Stock)</div>';
            echo '</div>';
            
            // Test 3: Products with quantity = 0
            echo '<div class="test-section">';
            echo '<h2>Test 3: Products with Quantity = 0 (Out of Stock)</h2>';
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND COALESCE(quantity, 0) = 0");
            $stmt->execute([$companyId]);
            $outOfStockCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo '<div class="count-box">Out of Stock: ' . $outOfStockCount . '</div>';
            echo '</div>';
            
            // Test 4: Check for swapped items columns
            echo '<div class="test-section">';
            echo '<h2>Test 4: Check Swapped Items Columns</h2>';
            $stmt = $connection->query("SHOW COLUMNS FROM products LIKE 'is_swapped_item'");
            $hasIsSwappedItem = $stmt->rowCount() > 0;
            $stmt = $connection->query("SHOW COLUMNS FROM products LIKE 'swap_ref_id'");
            $hasSwapRefId = $stmt->rowCount() > 0;
            $stmt = $connection->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
            $hasInventoryProductId = $stmt->rowCount() > 0;
            
            echo '<div class="info">';
            echo 'has_is_swapped_item column: ' . ($hasIsSwappedItem ? '<span class="success">Yes</span>' : '<span class="error">No</span>') . '<br>';
            echo 'has_swap_ref_id column: ' . ($hasSwapRefId ? '<span class="success">Yes</span>' : '<span class="error">No</span>') . '<br>';
            echo 'has_inventory_product_id column: ' . ($hasInventoryProductId ? '<span class="success">Yes</span>' : '<span class="error">No</span>') . '<br>';
            echo '</div>';
            echo '</div>';
            
            // Test 5: Regular products (not swapped) with quantity > 0
            echo '<div class="test-section">';
            echo '<h2>Test 5: Regular Products (Not Swapped) with Quantity > 0</h2>';
            if ($hasIsSwappedItem) {
                $sql = "SELECT COUNT(*) as total FROM products p 
                        WHERE p.company_id = ? 
                        AND COALESCE(p.quantity, 0) > 0 
                        AND COALESCE(p.is_swapped_item, 0) = 0";
                $stmt = $connection->prepare($sql);
                $stmt->execute([$companyId]);
                $regularCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo '<div class="count-box">Regular Products: ' . $regularCount . '</div>';
            } else {
                echo '<div class="warning">is_swapped_item column does not exist</div>';
                $regularCount = $inStockCount;
            }
            echo '</div>';
            
            // Test 6: Swapped items with quantity > 0
            echo '<div class="test-section">';
            echo '<h2>Test 6: Swapped Items with Quantity > 0</h2>';
            if ($hasIsSwappedItem || $hasInventoryProductId) {
                $sql = "SELECT COUNT(DISTINCT p.id) as total 
                        FROM products p";
                if ($hasInventoryProductId) {
                    $sql .= " LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id";
                }
                $sql .= " WHERE p.company_id = ? 
                          AND COALESCE(p.quantity, 0) > 0";
                if ($hasIsSwappedItem) {
                    $sql .= " AND (COALESCE(p.is_swapped_item, 0) = 1";
                    if ($hasInventoryProductId) {
                        $sql .= " OR si2.id IS NOT NULL";
                    }
                    $sql .= ")";
                } elseif ($hasInventoryProductId) {
                    $sql .= " AND si2.id IS NOT NULL";
                }
                
                $stmt = $connection->prepare($sql);
                $stmt->execute([$companyId]);
                $swappedCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo '<div class="count-box">Swapped Items: ' . $swappedCount . '</div>';
            } else {
                echo '<div class="warning">No swapped item columns found</div>';
                $swappedCount = 0;
            }
            echo '</div>';
            
            // Test 7: Simulate POS query exactly
            echo '<div class="test-section">';
            echo '<h2>Test 7: Simulate POS Query (findByCompanyForPOS)</h2>';
            
            // Build the exact query that POS uses
            $isSwappedItemSelect = $hasIsSwappedItem ? 'COALESCE(p.is_swapped_item, 0) as is_swapped_item,' : '0 as is_swapped_item,';
            $swapRefIdSelect = $hasSwapRefId ? 'p.swap_ref_id,' : 'NULL as swap_ref_id,';
            
            $si2Join = '';
            if ($hasInventoryProductId) {
                $si2Join = "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id";
            }
            
            $resellPriceSelect = $hasSwapRefId ? 
                'COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as resell_price, COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as display_price,' : 
                ($hasInventoryProductId ? 'COALESCE(si2.resell_price, p.price, 0) as resell_price, COALESCE(si2.resell_price, p.price, 0) as display_price,' : 
                'p.price as resell_price, p.price as display_price,');
            
            $hasInventoryLinkSelect = $hasInventoryProductId ? "CASE WHEN si2.id IS NOT NULL THEN 1 ELSE 0 END as has_inventory_link," : "";
            
            $sql = "
                SELECT 
                    p.id,
                    p.company_id,
                    p.name,
                    COALESCE(p.product_id, CONCAT('PID-', LPAD(p.id, 3, '0'))) as product_id,
                    p.category_id,
                    p.brand_id,
                    p.price,
                    p.cost,
                    COALESCE(p.quantity, 0) as quantity,
                    COALESCE(p.status, 'available') as status,
                    COALESCE(p.available_for_swap, 0) as available_for_swap,
                    {$isSwappedItemSelect}
                    {$swapRefIdSelect}
                    {$resellPriceSelect}
                    {$hasInventoryLinkSelect}
                    p.item_location,
                    COALESCE(NULLIF(TRIM(p.model_name), ''), 'N/A') as model_name,
                    COALESCE(c.name, 'N/A') as category_name,
                    COALESCE(b.name, 'N/A') as brand_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                " . ($hasSwapRefId ? "LEFT JOIN swapped_items si ON p.swap_ref_id = si.id" : "") . "
                {$si2Join}
                WHERE p.company_id = ?
                AND COALESCE(p.quantity, 0) > 0
                ORDER BY p.id DESC
                LIMIT 10000
            ";
            
            $stmt = $connection->prepare($sql);
            $stmt->execute([$companyId]);
            $posProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $posCount = count($posProducts);
            
            echo '<div class="count-box">POS Query Result: ' . $posCount . ' products</div>';
            echo '<div class="info">This is what the POS page should load</div>';
            
            // Calculate difference
            $expectedCount = $inStockCount;
            $difference = $expectedCount - $posCount;
            
            if ($difference > 0) {
                echo '<div class="missing-products">';
                echo '<span class="warning">⚠ Missing Products: ' . $difference . '</span><br>';
                echo 'Expected: ' . $expectedCount . ' products<br>';
                echo 'Actual: ' . $posCount . ' products<br>';
                echo '</div>';
            } else {
                echo '<div class="success">✓ All products are loading correctly!</div>';
            }
            echo '</div>';
            
            // Test 8: Show sample of products that might be missing
            if ($difference > 0 && $difference <= 50) {
                echo '<div class="test-section">';
                echo '<h2>Test 8: Products That Should Load But Might Be Missing</h2>';
                
                // Get all products with quantity > 0
                $stmt = $connection->prepare("
                    SELECT p.id, p.name, p.quantity, 
                           COALESCE(p.is_swapped_item, 0) as is_swapped_item,
                           p.status
                    FROM products p
                    WHERE p.company_id = ? 
                    AND COALESCE(p.quantity, 0) > 0
                    ORDER BY p.id DESC
                ");
                $stmt->execute([$companyId]);
                $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get IDs from POS query
                $posProductIds = array_column($posProducts, 'id');
                
                // Find missing products
                $missingProducts = [];
                foreach ($allProducts as $product) {
                    if (!in_array($product['id'], $posProductIds)) {
                        $missingProducts[] = $product;
                    }
                }
                
                if (count($missingProducts) > 0) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Name</th><th>Quantity</th><th>Is Swapped</th><th>Status</th></tr>';
                    foreach ($missingProducts as $product) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($product['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($product['name']) . '</td>';
                        echo '<td>' . htmlspecialchars($product['quantity']) . '</td>';
                        echo '<td>' . ($product['is_swapped_item'] ? 'Yes' : 'No') . '</td>';
                        echo '<td>' . htmlspecialchars($product['status']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="success">No missing products found (this is good!)</div>';
                }
                echo '</div>';
            }
            
            // Test 9: Check for NULL quantities
            echo '<div class="test-section">';
            echo '<h2>Test 9: Products with NULL Quantity</h2>';
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND quantity IS NULL");
            $stmt->execute([$companyId]);
            $nullQuantityCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo '<div class="count-box">NULL Quantity: ' . $nullQuantityCount . '</div>';
            if ($nullQuantityCount > 0) {
                echo '<div class="warning">⚠ Products with NULL quantity are treated as 0 and excluded from POS</div>';
            }
            echo '</div>';
            
            // Summary
            echo '<div class="test-section" style="background: #e3f2fd; border-left-color: #2196f3;">';
            echo '<h2>📊 Summary</h2>';
            echo '<table>';
            echo '<tr><th>Metric</th><th>Count</th></tr>';
            echo '<tr><td>Total Products</td><td>' . $totalCount . '</td></tr>';
            echo '<tr><td>Out of Stock (qty = 0)</td><td>' . $outOfStockCount . '</td></tr>';
            echo '<tr><td>In Stock (qty > 0)</td><td>' . $inStockCount . '</td></tr>';
            echo '<tr><td>Regular Products (qty > 0)</td><td>' . ($regularCount ?? 'N/A') . '</td></tr>';
            echo '<tr><td>Swapped Items (qty > 0)</td><td>' . ($swappedCount ?? 'N/A') . '</td></tr>';
            echo '<tr><td><strong>POS Query Result</strong></td><td><strong>' . $posCount . '</strong></td></tr>';
            echo '<tr><td>Expected POS Count</td><td>' . $inStockCount . '</td></tr>';
            echo '<tr><td><strong>Difference</strong></td><td><strong>' . ($inStockCount - $posCount) . '</strong></td></tr>';
            echo '</table>';
            echo '</div>';
            
            $connection = null;
            
        } catch (PDOException $e) {
            echo '<div class="test-section">';
            echo '<h2 class="error">✗ Database Error</h2>';
            echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test-section">';
            echo '<h2 class="error">✗ Error</h2>';
            echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '</div>';
        }
        ?>
        
        <div class="test-section" style="background: #fff3cd; border-left-color: #ffc107;">
            <h2>📝 Notes</h2>
            <ul>
                <li>This test file simulates the exact POS query to identify missing products</li>
                <li>Change company_id via URL parameter: ?company_id=2</li>
                <li>Delete this file after testing for security reasons</li>
                <li>If difference > 0, check the "Missing Products" table above</li>
            </ul>
        </div>
    </div>
</body>
</html>

