<?php
/**
 * Diagnostic script to check swapped_items table status
 */

// Handle being called from different locations
$basePath = __DIR__;
if (file_exists($basePath . '/../../config/database.php')) {
    require_once $basePath . '/../../config/database.php';
} elseif (file_exists(__DIR__ . '/../../config/database.php')) {
    require_once __DIR__ . '/../../config/database.php';
} else {
    require_once __DIR__ . '/../../../config/database.php';
}

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== swapped_items Table Diagnostic ===\n\n";
    
    // Check if table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'swapped_items'");
    if ($checkTable->rowCount() === 0) {
        echo "✗ swapped_items table does NOT exist.\n";
        exit(1);
    }
    echo "✓ swapped_items table exists.\n\n";
    
    // Get table structure
    echo "Table Structure:\n";
    echo str_repeat("-", 60) . "\n";
    $columns = $db->query("SHOW COLUMNS FROM swapped_items");
    while ($col = $columns->fetch()) {
        printf("%-25s %-20s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    echo "\n";
    
    // Check foreign keys
    echo "Foreign Key Constraints:\n";
    echo str_repeat("-", 60) . "\n";
    $fks = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'swapped_items'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $fkCount = 0;
    while ($fk = $fks->fetch()) {
        $fkCount++;
        echo "FK: {$fk['CONSTRAINT_NAME']}\n";
        echo "  Column: {$fk['COLUMN_NAME']}\n";
        echo "  References: {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n\n";
    }
    
    if ($fkCount === 0) {
        echo "No foreign key constraints found.\n\n";
    }
    
    // Check if swaps table exists and its ID type
    echo "Swaps Table Check:\n";
    echo str_repeat("-", 60) . "\n";
    $checkSwaps = $db->query("SHOW TABLES LIKE 'swaps'");
    if ($checkSwaps->rowCount() > 0) {
        echo "✓ swaps table exists.\n";
        $swapsIdCol = $db->query("SHOW COLUMNS FROM swaps WHERE Field = 'id'")->fetch();
        echo "  swaps.id type: {$swapsIdCol['Type']}\n";
        
        // Check swap_id type in swapped_items
        $swapIdCol = $db->query("SHOW COLUMNS FROM swapped_items WHERE Field = 'swap_id'")->fetch();
        echo "  swapped_items.swap_id type: {$swapIdCol['Type']}\n";
        
        // Check if types match
        $swapsIsBigInt = stripos($swapsIdCol['Type'], 'bigint') !== false;
        $swapIdIsBigInt = stripos($swapIdCol['Type'], 'bigint') !== false;
        
        if ($swapsIsBigInt !== $swapIdIsBigInt) {
            echo "  ⚠ WARNING: Type mismatch! swap_id type doesn't match swaps.id type.\n";
            echo "  This will prevent foreign key creation.\n";
        } else {
            echo "  ✓ Types match.\n";
        }
    } else {
        echo "✗ swaps table does NOT exist.\n";
    }
    echo "\n";
    
    // Test insert (dry run - won't actually insert)
    echo "Testing Insert Capability:\n";
    echo str_repeat("-", 60) . "\n";
    try {
        // Try to prepare an insert statement (won't execute)
        $testStmt = $db->prepare("
            INSERT INTO swapped_items (
                swap_id, brand, model, imei, `condition`, 
                estimated_value, resell_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        echo "✓ Insert statement can be prepared.\n";
    } catch (PDOException $e) {
        echo "✗ Error preparing insert: " . $e->getMessage() . "\n";
    }
    
    // Check table indexes
    echo "\nTable Indexes:\n";
    echo str_repeat("-", 60) . "\n";
    $indexes = $db->query("SHOW INDEXES FROM swapped_items");
    while ($idx = $indexes->fetch()) {
        if ($idx['Key_name'] !== 'PRIMARY') {
            echo "  {$idx['Key_name']} on {$idx['Column_name']}\n";
        }
    }
    
    echo "\n=== Diagnostic Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

