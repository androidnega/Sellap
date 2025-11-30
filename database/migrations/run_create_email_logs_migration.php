<?php
/**
 * Migration: Create email_logs table
 * Run this migration to add email logging support
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    echo "Creating email_logs table...\n";
    
    // Check if table already exists
    $checkTable = $db->query("SHOW TABLES LIKE 'email_logs'");
    if ($checkTable->rowCount() > 0) {
        echo "Table email_logs already exists. Skipping.\n";
        exit(0);
    }
    
    // Create table
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            email_type ENUM('automatic', 'manual', 'test', 'monthly_report', 'backup', 'notification') DEFAULT 'manual',
            status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
            company_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            role VARCHAR(50) NULL COMMENT 'User role if sent to user',
            error_message TEXT NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_recipient (recipient_email),
            INDEX idx_email_type (email_type),
            INDEX idx_status (status),
            INDEX idx_company_id (company_id),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Logs all emails sent by the system'
    ");
    
    echo "✓ Table email_logs created successfully.\n";
    
    // Try to add foreign keys (may fail if tables don't exist, that's okay)
    try {
        $db->exec("ALTER TABLE email_logs ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL");
        echo "✓ Foreign key for company_id added.\n";
    } catch (\Exception $e) {
        echo "Note: Could not add foreign key for company_id (this is okay): " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("ALTER TABLE email_logs ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "✓ Foreign key for user_id added.\n";
    } catch (\Exception $e) {
        echo "Note: Could not add foreign key for user_id (this is okay): " . $e->getMessage() . "\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

