<?php
/**
 * Auto-migration script for sms_logs table
 * Run this via: /migrate/create-sms-logs
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'sms_logs'");
    if ($checkTable->rowCount() > 0) {
        echo "✓ sms_logs table already exists\n";
        
        // Check if 'test_sms' is in the enum
        $checkColumn = $db->query("SHOW COLUMNS FROM sms_logs WHERE Field = 'message_type'");
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($columnInfo['Type'] ?? '', 'test_sms') === false) {
            echo "Updating message_type enum to include 'test_sms'...\n";
            $db->exec("ALTER TABLE sms_logs MODIFY COLUMN message_type ENUM('purchase', 'swap', 'repair', 'system', 'custom', 'test_sms') NOT NULL");
            echo "✓ Updated message_type enum\n";
        }
        
        exit(0);
    }
    
    echo "Creating sms_logs table...\n";
    
    // Create table
    $db->exec("
        CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            message_type ENUM('purchase', 'swap', 'repair', 'system', 'custom', 'test_sms') NOT NULL,
            recipient VARCHAR(15) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
            sender_id VARCHAR(15) DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_company_id (company_id),
            INDEX idx_message_type (message_type),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at),
            INDEX idx_recipient (recipient)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ sms_logs table created successfully\n";
    echo "✓ Migration completed\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit(1);
}

