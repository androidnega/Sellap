<?php
/**
 * Migration script to add lifecycle tracking columns to restock_logs table
 * Run this script to update the restock_logs table with new columns
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    // Check if columns already exist
    $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'quantity_at_restock'");
    if ($checkColumns->rowCount() > 0) {
        echo "Migration already applied. Columns already exist.\n";
        exit(0);
    }
    
    // Add new columns
    $db->exec("
        ALTER TABLE restock_logs 
        ADD COLUMN quantity_at_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity in stock when restocked',
        ADD COLUMN quantity_after_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity after restock',
        ADD COLUMN sold_out_date DATETIME NULL COMMENT 'Date when this restock batch was fully sold out',
        ADD COLUMN user_id BIGINT UNSIGNED NULL COMMENT 'User who performed the restock',
        ADD COLUMN status VARCHAR(20) DEFAULT 'active' COMMENT 'active, sold_out, cancelled'
    ");
    
    // Add indexes
    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_sold_out_date (sold_out_date)");
    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_status (status)");
    $db->exec("ALTER TABLE restock_logs ADD INDEX idx_user_id (user_id)");
    
    echo "Migration applied successfully!\n";
    echo "Added columns: quantity_at_restock, quantity_after_restock, sold_out_date, user_id, status\n";
    
} catch (\Exception $e) {
    echo "Error applying migration: " . $e->getMessage() . "\n";
    exit(1);
}

