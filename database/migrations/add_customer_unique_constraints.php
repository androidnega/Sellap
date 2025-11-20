<?php
/**
 * Migration: Add unique constraints for customer phone and email per company
 * 
 * This migration adds composite unique indexes to prevent duplicate phone numbers
 * and email addresses within the same company, while allowing the same phone/email
 * to exist in different companies (multi-tenant isolation).
 * 
 * IMPORTANT: Before running this migration, ensure there are no existing duplicates
 * in the database. The migration will fail if duplicates exist.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    echo "Starting customer unique constraints migration...\n";
    
    // Check for existing duplicates before adding constraints
    echo "Checking for existing duplicate phone numbers per company...\n";
    $duplicatePhones = $db->query("
        SELECT company_id, phone_number, COUNT(*) as count 
        FROM customers 
        WHERE phone_number IS NOT NULL AND phone_number != ''
        GROUP BY company_id, phone_number 
        HAVING count > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($duplicatePhones)) {
        echo "WARNING: Found duplicate phone numbers:\n";
        foreach ($duplicatePhones as $dup) {
            echo "  Company ID: {$dup['company_id']}, Phone: {$dup['phone_number']}, Count: {$dup['count']}\n";
        }
        echo "Please resolve duplicates before running this migration.\n";
        exit(1);
    }
    
    echo "Checking for existing duplicate emails per company...\n";
    $duplicateEmails = $db->query("
        SELECT company_id, email, COUNT(*) as count 
        FROM customers 
        WHERE email IS NOT NULL AND email != ''
        GROUP BY company_id, email 
        HAVING count > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($duplicateEmails)) {
        echo "WARNING: Found duplicate emails:\n";
        foreach ($duplicateEmails as $dup) {
            echo "  Company ID: {$dup['company_id']}, Email: {$dup['email']}, Count: {$dup['count']}\n";
        }
        echo "Please resolve duplicates before running this migration.\n";
        exit(1);
    }
    
    // Drop existing indexes if they exist (to avoid conflicts)
    echo "Dropping existing indexes if they exist...\n";
    try {
        $db->exec("ALTER TABLE customers DROP INDEX IF EXISTS idx_company_phone");
    } catch (PDOException $e) {
        // Index might not exist, continue
    }
    
    try {
        $db->exec("ALTER TABLE customers DROP INDEX IF EXISTS idx_company_email");
    } catch (PDOException $e) {
        // Index might not exist, continue
    }
    
    // Add composite unique index for phone_number per company
    // This allows same phone in different companies but prevents duplicates within same company
    echo "Adding unique constraint on (company_id, phone_number)...\n";
    $db->exec("
        ALTER TABLE customers 
        ADD UNIQUE INDEX idx_company_phone (company_id, phone_number)
    ");
    echo "✓ Unique constraint added for phone_number per company\n";
    
    // Add composite unique index for email per company (only for non-null emails)
    // Note: MySQL unique indexes allow multiple NULL values, so this works correctly
    echo "Adding unique constraint on (company_id, email)...\n";
    $db->exec("
        ALTER TABLE customers 
        ADD UNIQUE INDEX idx_company_email (company_id, email)
    ");
    echo "✓ Unique constraint added for email per company\n";
    
    echo "\nMigration completed successfully!\n";
    echo "Unique constraints are now enforced at the database level.\n";
    
} catch (PDOException $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
}

