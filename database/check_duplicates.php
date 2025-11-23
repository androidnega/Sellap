<?php
/**
 * Check for Duplicate Customers
 * 
 * This script checks if there are any duplicate customers in your database
 * without making any changes. Use this to verify if you have duplicates
 * before running the cleanup script.
 * 
 * Usage: php database/check_duplicates.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Checking for Duplicate Customers ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Find all duplicate phone numbers within each company
    $query = "
        SELECT 
            company_id,
            phone_number,
            COUNT(*) as count,
            GROUP_CONCAT(id ORDER BY created_at ASC) as all_ids,
            GROUP_CONCAT(unique_id ORDER BY created_at ASC SEPARATOR ', ') as unique_ids,
            GROUP_CONCAT(full_name ORDER BY created_at ASC SEPARATOR ', ') as names,
            MIN(created_at) as first_created,
            MAX(created_at) as last_created
        FROM customers
        WHERE phone_number IS NOT NULL 
        AND phone_number != ''
        GROUP BY company_id, phone_number
        HAVING COUNT(*) > 1
        ORDER BY company_id, phone_number
    ";
    
    $stmt = $db->query($query);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✓ GOOD NEWS! No duplicate customers found.\n";
        echo "  Your database is clean.\n\n";
        
        // Check if constraint exists
        $constraintQuery = "
            SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'customers'
            AND CONSTRAINT_NAME = 'unique_company_phone'
        ";
        $stmt = $db->query($constraintQuery);
        $hasConstraint = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($hasConstraint) {
            echo "✓ Unique constraint is in place.\n";
            echo "  Duplicates are prevented at database level.\n";
        } else {
            echo "⚠ Warning: Unique constraint NOT found.\n";
            echo "  Consider running: database/add_unique_constraint_customers.sql\n";
            echo "  This will prevent future duplicates.\n";
        }
        
        exit(0);
    }
    
    // Display duplicates
    echo "⚠ DUPLICATES FOUND!\n";
    echo "  Total groups: " . count($duplicates) . "\n\n";
    
    $totalDuplicateRecords = 0;
    
    foreach ($duplicates as $i => $dup) {
        $groupNum = $i + 1;
        $duplicateCount = $dup['count'] - 1; // -1 because one will be kept
        $totalDuplicateRecords += $duplicateCount;
        
        echo "──────────────────────────────────────────────────────\n";
        echo "Group #{$groupNum}\n";
        echo "──────────────────────────────────────────────────────\n";
        echo "Company ID:       {$dup['company_id']}\n";
        echo "Phone Number:     {$dup['phone_number']}\n";
        echo "Duplicate Count:  {$dup['count']} customers\n";
        echo "Customer IDs:     {$dup['all_ids']}\n";
        echo "Unique IDs:       {$dup['unique_ids']}\n";
        echo "Names:            {$dup['names']}\n";
        echo "First Created:    {$dup['first_created']}\n";
        echo "Last Created:     {$dup['last_created']}\n";
        echo "Records to Delete: {$duplicateCount}\n";
        echo "\n";
    }
    
    echo "══════════════════════════════════════════════════════\n";
    echo "SUMMARY\n";
    echo "══════════════════════════════════════════════════════\n";
    echo "Total duplicate groups:  " . count($duplicates) . "\n";
    echo "Total records to delete: {$totalDuplicateRecords}\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Backup your database first!\n";
    echo "   mysqldump -u root -p sellapp_db > backup.sql\n\n";
    echo "2. Run the cleanup script:\n";
    echo "   php database/cleanup_duplicate_customers.php\n\n";
    echo "3. Add the unique constraint:\n";
    echo "   mysql -u root -p sellapp_db < database/add_unique_constraint_customers.sql\n\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

