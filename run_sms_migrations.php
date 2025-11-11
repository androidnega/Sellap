<?php
/**
 * SMS Pricing System Migration Runner
 * This script helps you run the required database migrations for SMS pricing
 */

require_once __DIR__ . '/config/database.php';

echo "========================================\n";
echo "SMS Pricing System Migration Runner\n";
echo "========================================\n\n";

try {
    $db = \Database::getInstance()->getConnection();
    echo "✓ Database connection successful\n\n";
    
    // Check which tables exist
    $tables = ['sms_vendor_plans', 'company_sms_pricing'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $check = $db->query("SHOW TABLES LIKE '$table'");
        $result = $check->fetchAll(); // Fetch all to clear buffer
        if ($result && count($result) > 0) {
            $existingTables[] = $table;
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' does NOT exist\n";
        }
    }
    
    echo "\n";
    
    // Run migrations if needed
    $migrationFiles = [
        'create_sms_payments_table.sql', // Create base table first
        'create_sms_vendor_plans_table.sql',
        'fix_sms_payments_company_id.sql', // Fix existing table first if needed
        'fix_admin_sms_account_fk.sql', // Allow company_id = 0 for admin account
        'create_company_sms_pricing_table.sql',
        'update_sms_payments_for_paystack.sql',
        'seed_sms_vendor_plans.sql'
    ];
    
    foreach ($migrationFiles as $file) {
        $path = __DIR__ . '/database/migrations/' . $file;
        if (file_exists($path)) {
            echo "Running migration: $file\n";
            
            $sql = file_get_contents($path);
            
            // Remove SOURCE commands and comments
            $sql = preg_replace('/SOURCE\s+[^;]+;/i', '', $sql);
            $sql = preg_replace('/--.*$/m', '', $sql);
            
            try {
                // For files with PREPARE/EXECUTE statements, execute the whole SQL as-is
                // Otherwise, split by semicolon
                if (stripos($sql, 'PREPARE') !== false || stripos($sql, 'EXECUTE') !== false) {
                    // Execute multi-statement SQL directly
                    $db->exec($sql);
                } else {
                    // Split by semicolon and execute each statement
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            // Use exec for DDL statements and complex queries
                            if (stripos($statement, 'SELECT') === 0) {
                                $stmt = $db->query($statement);
                                if ($stmt) {
                                    $stmt->fetchAll(); // Clear buffer
                                }
                            } else {
                                $db->exec($statement);
                            }
                        }
                    }
                }
                echo "  ✓ Successfully executed\n";
            } catch (\Exception $e) {
                echo "  ✗ Error: " . $e->getMessage() . "\n";
                // Continue with next migration
            }
        } else {
            echo "✗ Migration file not found: $file\n";
        }
    }
    
    echo "\n";
    
    // Verify vendor plans were seeded
    $check = $db->query("SELECT COUNT(*) as count FROM sms_vendor_plans");
    if ($check) {
        $result = $check->fetchAll();
        $count = $result[0]['count'] ?? 0;
        if ($count > 0) {
            echo "✓ Vendor plans seeded: $count plans found\n";
        } else {
            echo "✗ No vendor plans found. Please run seed_sms_vendor_plans.sql manually\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Migration process completed!\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your database configuration in config/database.php\n";
    exit(1);
}

