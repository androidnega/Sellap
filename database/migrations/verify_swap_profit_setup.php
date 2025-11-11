<?php
/**
 * Verification script to check swap profit tracking setup
 * Run this to verify all components are in place
 */

require_once __DIR__ . '/../../config/database.php';

$db = \Database::getInstance()->getConnection();
$errors = [];
$warnings = [];
$success = [];

echo "🔍 Verifying Swap Profit Tracking Setup...\n\n";

// 1. Check if swap_profit_links table exists
echo "1. Checking swap_profit_links table...\n";
try {
    $db->query("SELECT 1 FROM swap_profit_links LIMIT 1");
    $success[] = "✓ swap_profit_links table exists";
    
    // Check for sale ID columns
    $columns = $db->query("SHOW COLUMNS FROM swap_profit_links")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('company_item_sale_id', $columns)) {
        $success[] = "✓ company_item_sale_id column exists";
    } else {
        $errors[] = "✗ company_item_sale_id column MISSING - Run migration: add_sale_ids_to_swap_profit_links.sql";
    }
    
    if (in_array('customer_item_sale_id', $columns)) {
        $success[] = "✓ customer_item_sale_id column exists";
    } else {
        $errors[] = "✗ customer_item_sale_id column MISSING - Run migration: add_sale_ids_to_swap_profit_links.sql";
    }
    
} catch (\Exception $e) {
    $errors[] = "✗ swap_profit_links table does not exist: " . $e->getMessage();
}

// 2. Check if pos_sales table exists
echo "\n2. Checking pos_sales table...\n";
try {
    $db->query("SELECT 1 FROM pos_sales LIMIT 1");
    $success[] = "✓ pos_sales table exists";
} catch (\Exception $e) {
    $errors[] = "✗ pos_sales table does not exist: " . $e->getMessage();
}

// 3. Check if swaps table exists
echo "\n3. Checking swaps table...\n";
try {
    $db->query("SELECT 1 FROM swaps LIMIT 1");
    $success[] = "✓ swaps table exists";
} catch (\Exception $e) {
    $errors[] = "✗ swaps table does not exist: " . $e->getMessage();
}

// 4. Check if swapped_items table exists
echo "\n4. Checking swapped_items table...\n";
try {
    $db->query("SELECT 1 FROM swapped_items LIMIT 1");
    $success[] = "✓ swapped_items table exists";
} catch (\Exception $e) {
    $warnings[] = "⚠ swapped_items table does not exist (optional, but recommended)";
}

// 5. Check existing swaps and profit links
echo "\n5. Checking existing swap data...\n";
try {
    $swapCount = $db->query("SELECT COUNT(*) FROM swaps")->fetchColumn();
    $success[] = "✓ Found {$swapCount} swaps";
    
    $profitLinkCount = $db->query("SELECT COUNT(*) FROM swap_profit_links")->fetchColumn();
    $success[] = "✓ Found {$profitLinkCount} profit links";
    
    // Check how many have sale IDs linked
    if (in_array('company_item_sale_id', $columns ?? [])) {
        $linkedCount = $db->query("SELECT COUNT(*) FROM swap_profit_links WHERE company_item_sale_id IS NOT NULL")->fetchColumn();
        $success[] = "✓ {$linkedCount} swaps have company sale linked";
        
        $bothLinkedCount = $db->query("SELECT COUNT(*) FROM swap_profit_links WHERE company_item_sale_id IS NOT NULL AND customer_item_sale_id IS NOT NULL")->fetchColumn();
        $success[] = "✓ {$bothLinkedCount} swaps have both sales linked (ready for profit calculation)";
        
        $finalizedCount = $db->query("SELECT COUNT(*) FROM swap_profit_links WHERE status = 'finalized'")->fetchColumn();
        $success[] = "✓ {$finalizedCount} swaps have finalized profit";
    }
} catch (\Exception $e) {
    $warnings[] = "⚠ Could not check existing data: " . $e->getMessage();
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 VERIFICATION SUMMARY\n";
echo str_repeat("=", 50) . "\n\n";

if (count($success) > 0) {
    echo "✅ SUCCESS:\n";
    foreach ($success as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "❌ ERRORS (Action Required):\n";
    foreach ($errors as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
    echo "📝 NEXT STEPS:\n";
    echo "   1. Run the migration: database/migrations/add_sale_ids_to_swap_profit_links.sql\n";
    echo "   2. Re-run this verification script\n";
    exit(1);
} else {
    echo "✅ All checks passed! Swap profit tracking is properly set up.\n\n";
    echo "📋 System is ready to:\n";
    echo "   • Track company item sales during swap creation\n";
    echo "   • Track customer item resales\n";
    echo "   • Automatically calculate profit when both items are sold\n";
    echo "   • Display realized profit in manager dashboard\n";
    exit(0);
}

