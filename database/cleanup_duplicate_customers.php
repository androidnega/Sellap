<?php
/**
 * Database Cleanup Script - Remove Duplicate Customers
 * 
 * This script identifies and removes duplicate customers based on phone number
 * within the same company, keeping only the oldest record.
 * 
 * Usage: Run this script once from command line or browser:
 * php database/cleanup_duplicate_customers.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Customer Duplicate Cleanup Script ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Find all duplicate phone numbers within each company
    $findDuplicatesQuery = "
        SELECT 
            company_id,
            phone_number,
            COUNT(*) as count,
            GROUP_CONCAT(id ORDER BY created_at ASC) as all_ids,
            MIN(id) as keep_id
        FROM customers
        WHERE phone_number IS NOT NULL 
        AND phone_number != ''
        GROUP BY company_id, phone_number
        HAVING COUNT(*) > 1
        ORDER BY company_id, phone_number
    ";
    
    $stmt = $db->query($findDuplicatesQuery);
    $duplicateGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicateGroups)) {
        echo "✓ No duplicate customers found. Database is clean!\n";
        $db->commit();
        exit(0);
    }
    
    echo "Found " . count($duplicateGroups) . " groups of duplicate customers:\n\n";
    
    $totalDeleted = 0;
    
    foreach ($duplicateGroups as $group) {
        $companyId = $group['company_id'];
        $phoneNumber = $group['phone_number'];
        $count = $group['count'];
        $allIds = explode(',', $group['all_ids']);
        $keepId = $group['keep_id'];
        
        echo "─────────────────────────────────────────\n";
        echo "Company ID: {$companyId}\n";
        echo "Phone: {$phoneNumber}\n";
        echo "Duplicates found: {$count}\n";
        echo "Customer IDs: " . implode(', ', $allIds) . "\n";
        echo "Keeping: ID {$keepId} (oldest)\n";
        
        // Get details of the customer we're keeping
        $keepStmt = $db->prepare("SELECT unique_id, full_name, email, created_at FROM customers WHERE id = ?");
        $keepStmt->execute([$keepId]);
        $keepCustomer = $keepStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($keepCustomer) {
            echo "  → {$keepCustomer['unique_id']} - {$keepCustomer['full_name']} (created: {$keepCustomer['created_at']})\n";
        }
        
        // IDs to delete (all except the one we're keeping)
        $deleteIds = array_filter($allIds, function($id) use ($keepId) {
            return $id != $keepId;
        });
        
        if (!empty($deleteIds)) {
            echo "Deleting: " . implode(', ', $deleteIds) . "\n";
            
            foreach ($deleteIds as $deleteId) {
                // Get details before deletion
                $detailStmt = $db->prepare("SELECT unique_id, full_name, email, created_at FROM customers WHERE id = ?");
                $detailStmt->execute([$deleteId]);
                $deleteCustomer = $detailStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($deleteCustomer) {
                    echo "  ✗ {$deleteCustomer['unique_id']} - {$deleteCustomer['full_name']} (created: {$deleteCustomer['created_at']})\n";
                }
                
                // Update related records to point to the kept customer
                // 1. Update pos_sales
                $updateSalesStmt = $db->prepare("UPDATE pos_sales SET customer_id = ? WHERE customer_id = ?");
                $updateSalesStmt->execute([$keepId, $deleteId]);
                $salesUpdated = $updateSalesStmt->rowCount();
                if ($salesUpdated > 0) {
                    echo "    • Updated {$salesUpdated} POS sales\n";
                }
                
                // 2. Update repairs
                $updateRepairsStmt = $db->prepare("UPDATE repairs SET customer_id = ? WHERE customer_id = ?");
                $updateRepairsStmt->execute([$keepId, $deleteId]);
                $repairsUpdated = $updateRepairsStmt->rowCount();
                if ($repairsUpdated > 0) {
                    echo "    • Updated {$repairsUpdated} repairs\n";
                }
                
                // 3. Update swaps
                $updateSwapsStmt = $db->prepare("UPDATE swaps SET customer_id = ? WHERE customer_id = ?");
                $updateSwapsStmt->execute([$keepId, $deleteId]);
                $swapsUpdated = $updateSwapsStmt->rowCount();
                if ($swapsUpdated > 0) {
                    echo "    • Updated {$swapsUpdated} swaps\n";
                }
                
                // Now delete the duplicate customer
                $deleteStmt = $db->prepare("DELETE FROM customers WHERE id = ?");
                $deleteStmt->execute([$deleteId]);
                
                $totalDeleted++;
            }
        }
        
        echo "\n";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "=== Cleanup Complete ===\n";
    echo "Total duplicate customers deleted: {$totalDeleted}\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back. No changes were made.\n";
    exit(1);
}

