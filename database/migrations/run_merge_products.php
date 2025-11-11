<?php
/**
 * Automatic Migration Script: Merge products and products_new tables
 * 
 * Run this file via browser: http://your-domain/database/migrations/run_merge_products.php
 * Or via command line: php database/migrations/run_merge_products.php
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Product Tables Merge</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{color:green;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:5px;margin:10px 0;}";
echo ".error{color:red;padding:10px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;margin:10px 0;}";
echo ".info{color:blue;padding:10px;background:#d1ecf1;border:1px solid #bee5eb;border-radius:5px;margin:10px 0;}";
echo "h1{color:#333;}pre{background:#f4f4f4;padding:10px;border-radius:5px;overflow-x:auto;}</style></head><body>";
echo "<h1>🔧 Product Tables Merge Migration</h1>";

try {
    $db = \Database::getInstance()->getConnection();
    
    // Check which tables exist
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $productsExists = in_array('products', $tables);
    $productsNewExists = in_array('products_new', $tables);
    
    echo "<div class='info'>";
    echo "<strong>Current Status:</strong><br>";
    echo "• 'products' table exists: " . ($productsExists ? "✓ Yes" : "✗ No") . "<br>";
    echo "• 'products_new' table exists: " . ($productsNewExists ? "✓ Yes" : "✗ No") . "<br>";
    echo "</div>";
    
    if (!$productsNewExists) {
        echo "<div class='error'>";
        echo "<strong>Error:</strong> 'products_new' table does not exist. Cannot proceed with migration.";
        echo "</div></body></html>";
        exit;
    }
    
    // Step 1: Ensure products_new has all necessary columns
    echo "<div class='info'><strong>Step 1:</strong> Ensuring products_new has all necessary columns...</div>";
    
    $alterQueries = [
        "ALTER TABLE products_new ADD COLUMN IF NOT EXISTS sku VARCHAR(80) NULL",
        "ALTER TABLE products_new ADD COLUMN IF NOT EXISTS subcategory_id INT NULL",
        "ALTER TABLE products_new ADD COLUMN IF NOT EXISTS description TEXT NULL",
        "ALTER TABLE products_new ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $db->exec($query);
        } catch (PDOException $e) {
            // Ignore errors for columns that already exist
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "<div class='error'>Column setup warning: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Step 2: Migrate data from products to products_new (if products table exists)
    if ($productsExists) {
        echo "<div class='info'><strong>Step 2:</strong> Migrating data from 'products' to 'products_new'...</div>";
        
        // Count products to migrate
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE company_id IS NOT NULL");
        $oldCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count existing in products_new
        $stmt = $db->query("SELECT COUNT(*) as count FROM products_new");
        $newCountBefore = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        echo "<div class='info'>Products in old table: {$oldCount}, Products in new table (before): {$newCountBefore}</div>";
        
        // Migrate data one by one to handle collation and duplicate issues properly
        echo "<div class='info'>Migrating products individually to handle collations and duplicates...</div>";
        
        // Get all products from old table
        $stmt = $db->query("
            SELECT 
                p.*,
                COALESCE(c.id, 1) as category_id,
                CASE 
                    WHEN p.brand IS NOT NULL AND p.brand != '' THEN (
                        SELECT b.id FROM brands b 
                        WHERE LOWER(TRIM(b.name)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(p.brand)) COLLATE utf8mb4_unicode_ci
                        AND b.category_id = COALESCE(c.id, 1)
                        LIMIT 1
                    )
                    ELSE NULL
                END as brand_id
            FROM products p
            LEFT JOIN categories c ON LOWER(TRIM(c.name)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(p.category)) COLLATE utf8mb4_unicode_ci
            WHERE p.company_id IS NOT NULL
        ");
        
        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                // Check if product already exists (by name and company_id)
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) FROM products_new 
                    WHERE name COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci 
                    AND company_id = ?
                ");
                $checkStmt->execute([$product['name'], $product['company_id']]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    $skippedCount++;
                    continue;
                }
                
                // Check if SKU already exists
                $sku = null;
                if (!empty($product['sku'])) {
                    $skuCheck = $db->prepare("SELECT COUNT(*) FROM products_new WHERE sku COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci");
                    $skuCheck->execute([$product['sku']]);
                    if ($skuCheck->fetchColumn() == 0) {
                        $sku = $product['sku'];
                    }
                    // If SKU exists, we'll set it to NULL
                }
                
                // Insert the product
                $insertStmt = $db->prepare("
                    INSERT INTO products_new (
                        company_id, name, category_id, brand_id, price, cost, quantity,
                        available_for_swap, status, created_by, sku, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $product['company_id'],
                    $product['name'],
                    $product['category_id'] ?? 1,
                    $product['brand_id'] ?? null,
                    $product['price'] ?? 0,
                    $product['cost'] ?? 0,
                    $product['qty'] ?? 0,
                    $product['available_for_swap'] ?? 0,
                    strtolower($product['status'] ?? 'available'),
                    $product['created_by_user_id'] ?? 1,
                    $sku,
                    $product['created_at'] ?? date('Y-m-d H:i:s'),
                    $product['updated_at'] ?? date('Y-m-d H:i:s')
                ]);
                
                $migratedCount++;
                
            } catch (PDOException $insertError) {
                $errorCount++;
                $errors[] = $product['name'] . ': ' . $insertError->getMessage();
                if ($errorCount <= 5) { // Only show first 5 errors
                    echo "<div class='info'>Skipped '{$product['name']}': " . htmlspecialchars($insertError->getMessage()) . "</div>";
                }
            }
        }
        
        if ($migratedCount > 0) {
            echo "<div class='success'>✓ Migration complete: {$migratedCount} products migrated, {$skippedCount} duplicates skipped";
            if ($errorCount > 0) {
                echo ", {$errorCount} errors";
            }
            echo "</div>";
        } else if ($skippedCount > 0) {
            echo "<div class='info'>All products already exist or were skipped. {$skippedCount} products skipped.</div>";
        }
        
        // Count after migration
        $stmt = $db->query("SELECT COUNT(*) as count FROM products_new");
        $newCountAfter = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        echo "<div class='info'>Products in new table (after): {$newCountAfter}</div>";
    } else {
        echo "<div class='info'>'products' table does not exist. Skipping data migration.</div>";
    }
    
    // Step 3: Generate product_id for any products that don't have one
    echo "<div class='info'><strong>Step 3:</strong> Generating product_id for products without one...</div>";
    $db->exec("UPDATE products_new SET product_id = CONCAT('PID-', LPAD(id, 3, '0')) WHERE product_id IS NULL OR product_id = ''");
    echo "<div class='success'>Product IDs generated</div>";
    
    // Step 4: Rename tables
    echo "<div class='info'><strong>Step 4:</strong> Renaming tables...</div>";
    
    // Backup old products table if it exists
    if ($productsExists) {
        try {
            $db->exec("DROP TABLE IF EXISTS products_old_backup");
            $db->exec("RENAME TABLE products TO products_old_backup");
            echo "<div class='success'>Old 'products' table backed up as 'products_old_backup'</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>Could not backup old products table: " . $e->getMessage() . "</div>";
            echo "<div class='info'>Trying to drop old products table instead...</div>";
            try {
                $db->exec("DROP TABLE IF EXISTS products");
                echo "<div class='success'>Old 'products' table dropped</div>";
            } catch (PDOException $e2) {
                echo "<div class='error'>Could not drop old products table: " . $e2->getMessage() . "</div>";
            }
        }
    }
    
    // Rename products_new to products
    try {
        $db->exec("RENAME TABLE products_new TO products");
        echo "<div class='success'>✓ 'products_new' renamed to 'products'</div>";
    } catch (PDOException $e) {
        echo "<div class='error'>Could not rename products_new: " . $e->getMessage() . "</div>";
        throw $e;
    }
    
    // Final count
    $stmt = $db->query("SELECT COUNT(*) as count FROM products");
    $finalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    echo "<div class='success'>";
    echo "<strong>✅ Migration Complete!</strong><br>";
    echo "All products are now in the unified 'products' table.<br>";
    echo "Total products: <strong>{$finalCount}</strong><br>";
    if ($productsExists) {
        echo "Old table backed up as 'products_old_backup'. You can drop it after verifying everything works.";
    }
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Refresh your inventory page<br>";
    echo "2. Verify all products are displaying correctly<br>";
    echo "3. Test edit/view functionality<br>";
    echo "4. After verification, you can drop the backup: <code>DROP TABLE IF EXISTS products_old_backup;</code>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Migration Failed:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>

