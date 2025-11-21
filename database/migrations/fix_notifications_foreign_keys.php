<?php
/**
 * Fix Notifications Foreign Key Constraints
 * 
 * This script cleans up orphaned notifications before adding foreign key constraints.
 * Run this script to fix the foreign key constraint error when uploading to cPanel phpMyAdmin.
 * 
 * Usage: php fix_notifications_foreign_keys.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    echo "========================================\n";
    echo "Fixing Notifications Foreign Keys\n";
    echo "========================================\n\n";
    
    // Step 1: Check for orphaned notifications
    echo "Step 1: Checking for orphaned notifications...\n";
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        WHERE u.id IS NULL
    ");
    $orphanedUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Found {$orphanedUsers} notifications with invalid user_id\n";
    
    // Step 2: Check for invalid company_id
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM notifications n
        LEFT JOIN companies c ON n.company_id = c.id
        WHERE c.id IS NULL
    ");
    $orphanedCompanies = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Found {$orphanedCompanies} notifications with invalid company_id\n\n";
    
    // Step 3: Delete orphaned notifications
    if ($orphanedUsers > 0) {
        echo "Step 2: Deleting notifications with invalid user_id...\n";
        $stmt = $db->prepare("
            DELETE n FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE u.id IS NULL
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo "Deleted {$deleted} orphaned notifications\n\n";
    }
    
    if ($orphanedCompanies > 0) {
        echo "Step 3: Deleting notifications with invalid company_id...\n";
        $stmt = $db->prepare("
            DELETE n FROM notifications n
            LEFT JOIN companies c ON n.company_id = c.id
            WHERE c.id IS NULL
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo "Deleted {$deleted} orphaned notifications\n\n";
    }
    
    // Step 4: Drop existing foreign key constraints if they exist
    echo "Step 4: Dropping existing foreign key constraints (if any)...\n";
    try {
        $db->exec("ALTER TABLE notifications DROP FOREIGN KEY IF EXISTS notifications_ibfk_1");
        echo "Dropped notifications_ibfk_1\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown key') === false) {
            echo "Note: notifications_ibfk_1 may not exist\n";
        }
    }
    
    try {
        $db->exec("ALTER TABLE notifications DROP FOREIGN KEY IF EXISTS notifications_ibfk_2");
        echo "Dropped notifications_ibfk_2\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown key') === false) {
            echo "Note: notifications_ibfk_2 may not exist\n";
        }
    }
    echo "\n";
    
    // Step 5: Add foreign key constraints
    echo "Step 5: Adding foreign key constraints...\n";
    try {
        $db->exec("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ");
        echo "✓ Added notifications_ibfk_1 (user_id -> users.id)\n";
    } catch (PDOException $e) {
        echo "✗ Error adding notifications_ibfk_1: " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_ibfk_2 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
        ");
        echo "✓ Added notifications_ibfk_2 (company_id -> companies.id)\n";
    } catch (PDOException $e) {
        echo "✗ Error adding notifications_ibfk_2: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Step 6: Verify
    echo "Step 6: Verifying data integrity...\n";
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN companies c ON n.company_id = c.id
        WHERE u.id IS NULL OR c.id IS NULL
    ");
    $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($remaining == 0) {
        echo "✓ All notifications have valid foreign keys!\n";
        echo "\n========================================\n";
        echo "SUCCESS: Foreign key constraints fixed!\n";
        echo "========================================\n";
    } else {
        echo "⚠ Warning: {$remaining} orphaned notifications still remain\n";
        echo "Please investigate these records manually.\n";
    }
    
} catch (PDOException $e) {
    echo "\n========================================\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "========================================\n";
    exit(1);
}

