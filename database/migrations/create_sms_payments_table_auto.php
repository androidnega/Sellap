<?php
/**
 * Auto-migration script for sms_payments table
 * Run this via: /migrate/create-sms-payments
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'sms_payments'");
    if ($checkTable->rowCount() > 0) {
        echo "✓ sms_payments table already exists\n";
        exit(0);
    }
    
    echo "Creating sms_payments table...\n";
    
    // Create table
    $db->exec("
        CREATE TABLE IF NOT EXISTS sms_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            payment_id VARCHAR(255) NOT NULL UNIQUE,
            payment_provider ENUM('paypal', 'stripe', 'manual') NOT NULL DEFAULT 'paypal',
            amount DECIMAL(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'GHS',
            sms_credits INT NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
            paypal_order_id VARCHAR(255) NULL,
            paypal_payer_id VARCHAR(255) NULL,
            payment_details JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_company_id (company_id),
            INDEX idx_payment_id (payment_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_paypal_order_id (paypal_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ sms_payments table created successfully\n";
    echo "✓ Migration completed\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit(1);
}

