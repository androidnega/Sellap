<?php
/**
 * Local cleanup script runner
 */
header('Content-Type: text/plain');
echo "=== Running Duplicate Customer Cleanup ===\n\n";

ob_implicit_flush(true);

require_once __DIR__ . '/../config/database.php';

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
        MIN(id) as keep_id
    FROM customers
    WHERE phone_number IS NOT NULL AND phone_number != ''
    GROUP BY company_id, phone_number
    HAVING COUNT(*) > 1
";

$stmt = $db->query($query);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✓ No duplicates found!\n";
    exit;
}

echo "Found " . count($duplicates) . " duplicate groups:\n\n";

// Process each duplicate group
foreach ($duplicates as $dup) {
    echo "Phone: {$dup['phone_number']} (Company: {$dup['company_id']})\n";
    echo "  Count: {$dup['count']}\n";
    echo "  Keeping ID: {$dup['keep_id']}\n";
    
    $allIds = explode(',', $dup['all_ids']);
    $deleteIds = array_filter($allIds, fn($id) => $id != $dup['keep_id']);
    
    foreach ($deleteIds as $deleteId) {
        echo "  Deleting ID: {$deleteId}... ";
        
        // Update related records
        $db->prepare("UPDATE pos_sales SET customer_id = ? WHERE customer_id = ?")->execute([$dup['keep_id'], $deleteId]);
        $db->prepare("UPDATE repairs SET customer_id = ? WHERE customer_id = ?")->execute([$dup['keep_id'], $deleteId]);
        $db->prepare("UPDATE swaps SET customer_id = ? WHERE customer_id = ?")->execute([$dup['keep_id'], $deleteId]);
        
        // Delete duplicate
        $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$deleteId]);
        echo "✓ Done\n";
    }
    echo "\n";
}

echo "=== Cleanup Complete! ===\n";

