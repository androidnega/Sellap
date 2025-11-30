<?php
/**
 * Migration: Add cloudinary_url column to backups table
 * Run this migration to add Cloudinary URL support to backups
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    echo "Adding cloudinary_url column to backups table...\n";
    
    // Check if column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM backups LIKE 'cloudinary_url'");
    if ($checkColumn->rowCount() > 0) {
        echo "Column cloudinary_url already exists. Skipping.\n";
        exit(0);
    }
    
    // Add column
    $db->exec("
        ALTER TABLE backups 
        ADD COLUMN cloudinary_url TEXT NULL 
        COMMENT 'Cloudinary URL where backup is stored' 
        AFTER file_path
    ");
    
    echo "✓ Column cloudinary_url added successfully.\n";
    
    // Try to add index (may fail if column is too long, that's okay)
    try {
        $db->exec("CREATE INDEX idx_cloudinary_url ON backups(cloudinary_url(255))");
        echo "✓ Index added successfully.\n";
    } catch (\Exception $e) {
        echo "Note: Could not add index (this is okay): " . $e->getMessage() . "\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

