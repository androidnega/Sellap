<?php
/**
 * Automatic swapped_items table creation script
 * This script safely creates the swapped_items table with proper foreign key handling
 */

// Handle being called from different locations
$basePath = __DIR__;
if (file_exists($basePath . '/../../config/database.php')) {
    require_once $basePath . '/../../config/database.php';
} elseif (file_exists(__DIR__ . '/../../config/database.php')) {
    require_once __DIR__ . '/../../config/database.php';
} else {
    // Try from root
    require_once __DIR__ . '/../../../config/database.php';
}

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting swapped_items table creation...\n";
    
    // Check if table already exists
    $checkTable = $db->query("SHOW TABLES LIKE 'swapped_items'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if ($tableExists) {
        echo "✓ swapped_items table already exists.\n";
        echo "Checking and fixing any issues...\n\n";
        
        // Check if swaps table exists and verify/fix swap_id type
        $checkSwapsTable = $db->query("SHOW TABLES LIKE 'swaps'");
        if ($checkSwapsTable->rowCount() > 0) {
            $swapsColumns = $db->query("SHOW COLUMNS FROM swaps WHERE Field = 'id'");
            $swapsIdCol = $swapsColumns->fetch();
            
            if ($swapsIdCol) {
                $swapsIdType = $swapsIdCol['Type'];
                $isBigInt = stripos($swapsIdType, 'bigint') !== false;
                
                // Check current swap_id type
                $swapIdCol = $db->query("SHOW COLUMNS FROM swapped_items WHERE Field = 'swap_id'")->fetch();
                $swapIdType = $swapIdCol['Type'];
                $swapIdIsBigInt = stripos($swapIdType, 'bigint') !== false;
                
                // Fix type mismatch
                if ($isBigInt && !$swapIdIsBigInt) {
                    echo "Fixing swap_id column type (INT -> BIGINT UNSIGNED)...\n";
                    try {
                        // Drop foreign key if it exists
                        $checkFK = $db->query("
                            SELECT CONSTRAINT_NAME FROM information_schema.table_constraints 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'swapped_items' 
                            AND constraint_name = 'fk_swapped_items_swap'
                        ");
                        if ($checkFK->rowCount() > 0) {
                            $fk = $checkFK->fetch();
                            $db->exec("ALTER TABLE swapped_items DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
                            echo "  Dropped existing foreign key.\n";
                        }
                        
                        $db->exec("ALTER TABLE swapped_items MODIFY COLUMN swap_id BIGINT UNSIGNED NOT NULL");
                        echo "✓ swap_id column updated to BIGINT UNSIGNED.\n";
                        
                        // Re-add foreign key
                        $db->exec("
                            ALTER TABLE swapped_items 
                            ADD CONSTRAINT fk_swapped_items_swap 
                            FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                        ");
                        echo "✓ Foreign key re-added.\n\n";
                    } catch (PDOException $e) {
                        echo "⚠ Warning: Could not update swap_id type: " . $e->getMessage() . "\n\n";
                    }
                } elseif (!$isBigInt && $swapIdIsBigInt) {
                    echo "Fixing swap_id column type (BIGINT -> INT)...\n";
                    try {
                        // Drop foreign key if it exists
                        $checkFK = $db->query("
                            SELECT CONSTRAINT_NAME FROM information_schema.table_constraints 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'swapped_items' 
                            AND constraint_name = 'fk_swapped_items_swap'
                        ");
                        if ($checkFK->rowCount() > 0) {
                            $fk = $checkFK->fetch();
                            $db->exec("ALTER TABLE swapped_items DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
                            echo "  Dropped existing foreign key.\n";
                        }
                        
                        $db->exec("ALTER TABLE swapped_items MODIFY COLUMN swap_id INT NOT NULL");
                        echo "✓ swap_id column updated to INT.\n";
                        
                        // Re-add foreign key
                        $db->exec("
                            ALTER TABLE swapped_items 
                            ADD CONSTRAINT fk_swapped_items_swap 
                            FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                        ");
                        echo "✓ Foreign key re-added.\n\n";
                    } catch (PDOException $e) {
                        echo "⚠ Warning: Could not update swap_id type: " . $e->getMessage() . "\n\n";
                    }
                } else {
                    echo "✓ swap_id column type matches swaps.id type.\n\n";
                }
                
                // Ensure foreign key exists
                $checkFK = $db->query("
                    SELECT COUNT(*) as count FROM information_schema.table_constraints 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'swapped_items' 
                    AND constraint_name = 'fk_swapped_items_swap'
                ");
                $fkExists = $checkFK->fetch()['count'] > 0;
                
                if (!$fkExists) {
                    echo "Adding missing foreign key constraint...\n";
                    try {
                        $db->exec("
                            ALTER TABLE swapped_items 
                            ADD CONSTRAINT fk_swapped_items_swap 
                            FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                        ");
                        echo "✓ Foreign key added.\n\n";
                    } catch (PDOException $e) {
                        echo "⚠ Warning: Could not add foreign key: " . $e->getMessage() . "\n\n";
                    }
                }
            }
        }
        
        echo "✓ Table check complete. swapped_items table is ready.\n";
    } else {
        echo "Creating swapped_items table...\n";
    
    // Create table without foreign keys first
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS swapped_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            swap_id INT NOT NULL,
            brand VARCHAR(50) NOT NULL,
            model VARCHAR(100) NOT NULL,
            imei VARCHAR(20) NULL,
            `condition` VARCHAR(20) NOT NULL,
            estimated_value DECIMAL(10,2) NOT NULL,
            resell_price DECIMAL(10,2) NOT NULL,
            status ENUM('in_stock','sold') DEFAULT 'in_stock',
            resold_on DATETIME NULL,
            inventory_product_id INT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_swap (swap_id),
            INDEX idx_status (status),
            INDEX idx_brand_model (brand, model),
            INDEX idx_imei (imei)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($createTableSQL);
    echo "✓ swapped_items table created successfully.\n";
    
    // Check if swaps table exists
    $checkSwapsTable = $db->query("SHOW TABLES LIKE 'swaps'");
    if ($checkSwapsTable->rowCount() > 0) {
        echo "Checking swaps table structure...\n";
        
        // Get swaps table ID column type
        $swapsColumns = $db->query("SHOW COLUMNS FROM swaps WHERE Field = 'id'");
        $swapsIdCol = $swapsColumns->fetch();
        
        if ($swapsIdCol) {
            // Check if foreign key already exists
            $checkFK = $db->query("
                SELECT COUNT(*) as count FROM information_schema.table_constraints 
                WHERE table_schema = DATABASE() 
                AND table_name = 'swapped_items' 
                AND constraint_name = 'fk_swapped_items_swap'
            ");
            $fkExists = $checkFK->fetch()['count'] > 0;
            
            if (!$fkExists) {
                echo "Adding foreign key constraint for swap_id...\n";
                
                // Check swaps.id column type - if it's BIGINT, we need to match it
                $swapsIdType = $swapsIdCol['Type'];
                $isBigInt = stripos($swapsIdType, 'bigint') !== false;
                
                // If swaps.id is BIGINT, we need to change swap_id to BIGINT
                if ($isBigInt) {
                    echo "Swaps table uses BIGINT for id. Updating swap_id column...\n";
                    $db->exec("ALTER TABLE swapped_items MODIFY COLUMN swap_id BIGINT UNSIGNED NOT NULL");
                }
                
                // Add foreign key
                try {
                    $db->exec("
                        ALTER TABLE swapped_items 
                        ADD CONSTRAINT fk_swapped_items_swap 
                        FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                    ");
                    echo "✓ Foreign key for swap_id added successfully.\n";
                } catch (PDOException $e) {
                    echo "⚠ Warning: Could not add foreign key for swap_id: " . $e->getMessage() . "\n";
                    echo "  Table will work without foreign key constraint.\n";
                }
            } else {
                echo "✓ Foreign key for swap_id already exists.\n";
            }
        }
    } else {
        echo "⚠ Warning: swaps table not found. Foreign key will not be added.\n";
        echo "  You may need to add it manually after creating the swaps table.\n";
    }
    
    // Try to add inventory_product_id foreign key (optional)
    $productsTable = null;
    $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
    $checkProducts = $db->query("SHOW TABLES LIKE 'products'");
    
    if ($checkProductsNew->rowCount() > 0) {
        $productsTable = 'products_new';
    } elseif ($checkProducts->rowCount() > 0) {
        $productsTable = 'products';
    }
    
    if ($productsTable) {
        $checkFK2 = $db->query("
            SELECT COUNT(*) as count FROM information_schema.table_constraints 
            WHERE table_schema = DATABASE() 
            AND table_name = 'swapped_items' 
            AND constraint_name = 'fk_swapped_items_inventory_product'
        ");
        $fk2Exists = $checkFK2->fetch()['count'] > 0;
        
        if (!$fk2Exists) {
            echo "Adding foreign key constraint for inventory_product_id...\n";
            try {
                // Check products table ID column type
                $productsColumns = $db->query("SHOW COLUMNS FROM {$productsTable} WHERE Field = 'id'");
                $productsIdCol = $productsColumns->fetch();
                $isProductsBigInt = $productsIdCol && stripos($productsIdCol['Type'], 'bigint') !== false;
                
                // Adjust column type if needed
                if ($isProductsBigInt) {
                    $db->exec("ALTER TABLE swapped_items MODIFY COLUMN inventory_product_id BIGINT UNSIGNED NULL");
                }
                
                $db->exec("
                    ALTER TABLE swapped_items 
                    ADD CONSTRAINT fk_swapped_items_inventory_product 
                    FOREIGN KEY (inventory_product_id) REFERENCES {$productsTable}(id) ON DELETE SET NULL
                ");
                echo "✓ Foreign key for inventory_product_id added successfully.\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: Could not add foreign key for inventory_product_id: " . $e->getMessage() . "\n";
                echo "  Table will work without this foreign key constraint.\n";
            }
        } else {
            echo "✓ Foreign key for inventory_product_id already exists.\n";
        }
    } else {
        echo "⚠ Note: No products table found. inventory_product_id foreign key will not be added.\n";
    }
    
    echo "\n✓ swapped_items table setup completed successfully!\n";
    } // End of else block for creating swapped_items table
    
    // ============================================
    // CREATE swap_profit_links TABLE
    // ============================================
    echo "\n=== Checking swap_profit_links table ===\n";
    
    $checkProfitTable = $db->query("SHOW TABLES LIKE 'swap_profit_links'");
    $profitTableExists = $checkProfitTable->rowCount() > 0;
    
    if ($profitTableExists) {
        echo "✓ swap_profit_links table already exists.\n";
        echo "Checking and fixing any issues...\n\n";
        
        // Check if swaps table exists and verify/fix swap_id type
        $checkSwapsTable2 = $db->query("SHOW TABLES LIKE 'swaps'");
        if ($checkSwapsTable2->rowCount() > 0) {
            $swapsColumns2 = $db->query("SHOW COLUMNS FROM swaps WHERE Field = 'id'");
            $swapsIdCol2 = $swapsColumns2->fetch();
            
            if ($swapsIdCol2) {
                $swapsIdType2 = $swapsIdCol2['Type'];
                $isBigInt2 = stripos($swapsIdType2, 'bigint') !== false;
                
                // Check current swap_id type in profit_links
                $profitSwapIdCol = $db->query("SHOW COLUMNS FROM swap_profit_links WHERE Field = 'swap_id'")->fetch();
                if ($profitSwapIdCol) {
                    $profitSwapIdType = $profitSwapIdCol['Type'];
                    $profitSwapIdIsBigInt = stripos($profitSwapIdType, 'bigint') !== false;
                    
                    // Fix type mismatch
                    if ($isBigInt2 && !$profitSwapIdIsBigInt) {
                        echo "Fixing swap_id column type in swap_profit_links (INT -> BIGINT UNSIGNED)...\n";
                        try {
                            // Drop foreign key if it exists
                            $checkFK = $db->query("
                                SELECT CONSTRAINT_NAME FROM information_schema.table_constraints 
                                WHERE table_schema = DATABASE() 
                                AND table_name = 'swap_profit_links' 
                                AND constraint_name LIKE '%swap%'
                            ");
                            if ($checkFK->rowCount() > 0) {
                                while ($fk = $checkFK->fetch()) {
                                    $db->exec("ALTER TABLE swap_profit_links DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
                                    echo "  Dropped existing foreign key: {$fk['CONSTRAINT_NAME']}\n";
                                }
                            }
                            
                            $db->exec("ALTER TABLE swap_profit_links MODIFY COLUMN swap_id BIGINT UNSIGNED NOT NULL");
                            echo "✓ swap_id column updated to BIGINT UNSIGNED.\n";
                            
                            // Re-add foreign key
                            $db->exec("
                                ALTER TABLE swap_profit_links 
                                ADD CONSTRAINT fk_swap_profit_links_swap 
                                FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                            ");
                            echo "✓ Foreign key re-added.\n\n";
                        } catch (PDOException $e) {
                            echo "⚠ Warning: Could not update swap_id type: " . $e->getMessage() . "\n\n";
                        }
                    } else {
                        echo "✓ swap_id column type matches swaps.id type.\n\n";
                    }
                    
                    // Ensure foreign key exists
                    $checkFK = $db->query("
                        SELECT COUNT(*) as count FROM information_schema.table_constraints 
                        WHERE table_schema = DATABASE() 
                        AND table_name = 'swap_profit_links' 
                        AND constraint_name LIKE '%swap%'
                    ");
                    $fkExists = $checkFK->fetch()['count'] > 0;
                    
                    if (!$fkExists) {
                        echo "Adding missing foreign key constraint...\n";
                        try {
                            $db->exec("
                                ALTER TABLE swap_profit_links 
                                ADD CONSTRAINT fk_swap_profit_links_swap 
                                FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                            ");
                            echo "✓ Foreign key added.\n\n";
                        } catch (PDOException $e) {
                            echo "⚠ Warning: Could not add foreign key: " . $e->getMessage() . "\n\n";
                        }
                    }
                }
            }
        }
        
        echo "✓ swap_profit_links table check complete.\n";
    } else {
        echo "Creating swap_profit_links table...\n";
        
        // Get swaps table ID type first
        $swapsIdType = 'INT';
        $checkSwapsForProfit = $db->query("SHOW TABLES LIKE 'swaps'");
        if ($checkSwapsForProfit->rowCount() > 0) {
            $swapsIdCol = $db->query("SHOW COLUMNS FROM swaps WHERE Field = 'id'")->fetch();
            if ($swapsIdCol) {
                $swapsIdType = stripos($swapsIdCol['Type'], 'bigint') !== false ? 'BIGINT UNSIGNED' : 'INT';
            }
        }
        
        // Create table
        $createProfitTableSQL = "
            CREATE TABLE IF NOT EXISTS swap_profit_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                swap_id {$swapsIdType} NOT NULL,
                company_product_cost DECIMAL(10,2) NOT NULL,
                customer_phone_value DECIMAL(10,2) NOT NULL,
                amount_added_by_customer DECIMAL(10,2) DEFAULT 0,
                profit_estimate DECIMAL(10,2) NOT NULL,
                final_profit DECIMAL(10,2) NULL,
                status ENUM('pending','finalized') DEFAULT 'pending',
                finalized_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_swap (swap_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createProfitTableSQL);
        echo "✓ swap_profit_links table created successfully.\n";
        
        // Add foreign key if swaps table exists
        $checkSwapsTableForFK = $db->query("SHOW TABLES LIKE 'swaps'");
        if ($checkSwapsTableForFK->rowCount() > 0) {
            echo "Adding foreign key constraint for swap_id...\n";
            try {
                $db->exec("
                    ALTER TABLE swap_profit_links 
                    ADD CONSTRAINT fk_swap_profit_links_swap 
                    FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE
                ");
                echo "✓ Foreign key for swap_id added successfully.\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: Could not add foreign key for swap_id: " . $e->getMessage() . "\n";
                echo "  Table will work without foreign key constraint.\n";
            }
        } else {
            echo "⚠ Warning: swaps table not found. Foreign key will not be added.\n";
            echo "  You may need to add it manually after creating the swaps table.\n";
        }
    }
    
    echo "\n✓ All swap-related tables setup completed successfully!\n";
    echo "You can now use the swap functionality.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

