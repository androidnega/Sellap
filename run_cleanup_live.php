<?php
/**
 * Live server cleanup script
 * DELETE THIS FILE AFTER RUNNING!
 */

// Simple password protection
$PASSWORD = 'cleanup2025'; // Change this if needed
if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('Access denied. Add ?password=cleanup2025 to URL');
}

header('Content-Type: text/plain');
echo "=== Running Duplicate Customer Cleanup ===\n\n";

ob_implicit_flush(true);

require_once __DIR__ . '/config/database.php';

echo "Database connection: ";
try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Connected\n\n";
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    exit;
}

// Find duplicates
$query = "
    SELECT 
        company_id,
        phone_number,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY created_at ASC) as all_ids,
        GROUP_CONCAT(unique_id ORDER BY created_at ASC SEPARATOR ', ') as unique_ids,
        GROUP_CONCAT(full_name ORDER BY created_at ASC SEPARATOR ', ') as names,
        MIN(id) as keep_id,
        MIN(created_at) as first_created
    FROM customers
    WHERE phone_number IS NOT NULL AND phone_number != ''
    GROUP BY company_id, phone_number
    HAVING COUNT(*) > 1
";

$stmt = $db->query($query);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✓ No duplicates found! Database is clean.\n";
    exit;
}

echo "Found " . count($duplicates) . " duplicate groups:\n\n";

$totalDeleted = 0;

// Start transaction
$db->beginTransaction();

try {
    // Process each duplicate group
    foreach ($duplicates as $dup) {
        echo "──────────────────────────────────────────\n";
        echo "Phone: {$dup['phone_number']}\n";
        echo "Company ID: {$dup['company_id']}\n";
        echo "Count: {$dup['count']} customers\n";
        echo "Names: {$dup['names']}\n";
        echo "Unique IDs: {$dup['unique_ids']}\n";
        echo "Keeping ID: {$dup['keep_id']} (created: {$dup['first_created']})\n\n";
        
        $allIds = explode(',', $dup['all_ids']);
        $deleteIds = array_filter($allIds, fn($id) => $id != $dup['keep_id']);
        
        foreach ($deleteIds as $deleteId) {
            echo "  Deleting customer ID: {$deleteId}...\n";
            
            // Update related records to point to kept customer
            $stmt = $db->prepare("UPDATE pos_sales SET customer_id = ? WHERE customer_id = ?");
            $stmt->execute([$dup['keep_id'], $deleteId]);
            $salesUpdated = $stmt->rowCount();
            if ($salesUpdated > 0) echo "    • Updated {$salesUpdated} sales\n";
            
            $stmt = $db->prepare("UPDATE repairs SET customer_id = ? WHERE customer_id = ?");
            $stmt->execute([$dup['keep_id'], $deleteId]);
            $repairsUpdated = $stmt->rowCount();
            if ($repairsUpdated > 0) echo "    • Updated {$repairsUpdated} repairs\n";
            
            $stmt = $db->prepare("UPDATE swaps SET customer_id = ? WHERE customer_id = ?");
            $stmt->execute([$dup['keep_id'], $deleteId]);
            $swapsUpdated = $stmt->rowCount();
            if ($swapsUpdated > 0) echo "    • Updated {$swapsUpdated} swaps\n";
            
            // Delete the duplicate customer
            $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$deleteId]);
            echo "    ✓ Deleted\n\n";
            
            $totalDeleted++;
        }
    }
    
    // Commit transaction
    $db->commit();
    
    echo "══════════════════════════════════════════\n";
    echo "✓ Cleanup Complete!\n";
    echo "  Total duplicates deleted: {$totalDeleted}\n\n";
    echo "⚠ IMPORTANT: Delete this file now!\n";
    echo "  rm run_cleanup_live.php\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back. No changes made.\n";
}

