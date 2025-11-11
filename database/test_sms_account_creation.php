<?php
/**
 * Test script to diagnose SMS account creation issues
 * This will try to create SMS accounts for existing companies
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>SMS Account Creation Test</h2>\n";
echo "<pre>\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all companies
    echo "Step 1: Checking companies...\n";
    $companies = $db->query("SELECT id, name FROM companies ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($companies) . " companies\n\n";
    
    // Check if company_sms_accounts table exists
    echo "Step 2: Checking company_sms_accounts table...\n";
    $tableCheck = $db->query("SHOW TABLES LIKE 'company_sms_accounts'");
    if (!$tableCheck || $tableCheck->rowCount() == 0) {
        echo "⚠ Table 'company_sms_accounts' does not exist. Attempting to create...\n";
        
        // Check companies table structure first
        echo "Checking companies table structure...\n";
        $companiesCheck = $db->query("SHOW COLUMNS FROM companies WHERE Field = 'id'");
        $companyIdType = 'BIGINT UNSIGNED';
        if ($companiesCheck && $companiesCheck->rowCount() > 0) {
            $idColumn = $companiesCheck->fetch(PDO::FETCH_ASSOC);
            $companyIdType = $idColumn['Type'] ?? 'BIGINT UNSIGNED';
            echo "  Companies.id type: {$companyIdType}\n";
        }
        echo "\n";
        
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS company_sms_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id {$companyIdType} NOT NULL UNIQUE,
                total_sms INT NOT NULL DEFAULT 0,
                sms_used INT NOT NULL DEFAULT 0,
                sms_remaining INT AS (total_sms - sms_used) STORED,
                status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
                sms_sender_name VARCHAR(15) NOT NULL DEFAULT 'SellApp',
                custom_sms_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $db->exec($createTableSQL);
            echo "✓ Table created successfully with foreign key\n\n";
        } catch (\Exception $e) {
            echo "⚠ Failed to create table with foreign key: " . $e->getMessage() . "\n";
            echo "   Attempting to create without foreign key constraint...\n";
            
            // Try without foreign key
            $createTableSQLNoFK = "
                CREATE TABLE IF NOT EXISTS company_sms_accounts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_id {$companyIdType} NOT NULL UNIQUE,
                    total_sms INT NOT NULL DEFAULT 0,
                    sms_used INT NOT NULL DEFAULT 0,
                    sms_remaining INT AS (total_sms - sms_used) STORED,
                    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
                    sms_sender_name VARCHAR(15) NOT NULL DEFAULT 'SellApp',
                    custom_sms_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_company_id (company_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            try {
                $db->exec($createTableSQLNoFK);
                echo "✓ Table created successfully without foreign key constraint\n\n";
            } catch (\Exception $e2) {
                echo "✗ Failed to create table even without foreign key: " . $e2->getMessage() . "\n";
                echo "SQL Error Info: " . json_encode($db->errorInfo()) . "\n\n";
                exit(1);
            }
        }
    } else {
        echo "✓ Table exists\n\n";
    }
    
    // Check existing SMS accounts
    echo "Step 3: Checking existing SMS accounts...\n";
    $existingAccounts = $db->query("SELECT company_id, total_sms, sms_used FROM company_sms_accounts")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($existingAccounts) . " existing SMS accounts\n";
    foreach ($existingAccounts as $acc) {
        echo "  - Company ID {$acc['company_id']}: {$acc['total_sms']} total, {$acc['sms_used']} used\n";
    }
    echo "\n";
    
    // Try to create accounts for companies that don't have them
    echo "Step 4: Creating SMS accounts for companies without them...\n";
    $created = 0;
    $failed = 0;
    
    foreach ($companies as $company) {
        $companyId = $company['id'];
        $companyName = $company['name'];
        
        // Check if account exists
        $checkStmt = $db->prepare("SELECT id FROM company_sms_accounts WHERE company_id = ?");
        $checkStmt->execute([$companyId]);
        
        if ($checkStmt->fetch()) {
            echo "✓ Company '{$companyName}' (ID: {$companyId}) already has SMS account\n";
            continue;
        }
        
        // Try to create account
        echo "Creating SMS account for company '{$companyName}' (ID: {$companyId})... ";
        
        try {
            $insertStmt = $db->prepare("
                INSERT INTO company_sms_accounts (company_id, total_sms, sms_used, status, custom_sms_enabled, sms_sender_name) 
                VALUES (?, 0, 0, 'active', 0, 'SellApp')
            ");
            $result = $insertStmt->execute([$companyId]);
            
            if ($result) {
                echo "✓ Success\n";
                $created++;
            } else {
                $errorInfo = $insertStmt->errorInfo();
                echo "✗ Failed: " . json_encode($errorInfo) . "\n";
                $failed++;
            }
        } catch (\PDOException $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "   Error Code: " . $e->getCode() . "\n";
            echo "   SQL State: " . ($e->errorInfo[0] ?? 'N/A') . "\n";
            $failed++;
        } catch (\Exception $e) {
            echo "✗ General Error: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
    
    echo "\n";
    echo "=== Summary ===\n";
    echo "Created: {$created}\n";
    echo "Failed: {$failed}\n";
    echo "Total companies: " . count($companies) . "\n";
    
    // Final verification
    echo "\n=== Final Verification ===\n";
    $allAccounts = $db->query("SELECT company_id, total_sms, sms_used, status FROM company_sms_accounts ORDER BY company_id")->fetchAll(PDO::FETCH_ASSOC);
    echo "Total SMS accounts: " . count($allAccounts) . "\n";
    
    foreach ($allAccounts as $acc) {
        $compStmt = $db->prepare("SELECT name FROM companies WHERE id = ?");
        $compStmt->execute([$acc['company_id']]);
        $compName = $compStmt->fetchColumn() ?: 'Unknown';
        echo "  - {$compName} (ID: {$acc['company_id']}): {$acc['total_sms']} total, {$acc['sms_used']} used, Status: {$acc['status']}\n";
    }
    
    echo "\n✅ Test completed!\n";
    echo "</pre>\n";
    
} catch (\Exception $e) {
    echo "<pre>\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "</pre>\n";
    http_response_code(500);
}

