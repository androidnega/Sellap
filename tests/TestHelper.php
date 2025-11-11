<?php
/**
 * Test Helper Class (PHASE G)
 * Provides utility methods for testing reset functionality
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class TestHelper {
    private static $db;
    private static $testCompanyId = null;
    private static $testSystemAdminId = null;
    private static $testManagerId = null;
    
    /**
     * Get database connection
     */
    public static function getDB() {
        if (!self::$db) {
            self::$db = \Database::getInstance()->getConnection();
        }
        return self::$db;
    }
    
    /**
     * Create test company with seeded data
     */
    public static function createTestCompany() {
        $db = self::getDB();
        
        // Create company
        $stmt = $db->prepare("
            INSERT INTO companies (name, email, phone_number, status) 
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute(['Test Company ' . time(), 'test@example.com', '1234567890']);
        $companyId = $db->lastInsertId();
        self::$testCompanyId = $companyId;
        
        // Create test users
        $stmt = $db->prepare("
            INSERT INTO users (username, password, full_name, email, role, company_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // System admin (no company_id)
        $stmt->execute(['test_admin_' . time(), password_hash('test123', PASSWORD_DEFAULT), 'Test Admin', 'admin@test.com', 'system_admin', null]);
        self::$testSystemAdminId = $db->lastInsertId();
        
        // Manager for company
        $stmt->execute(['test_manager_' . time(), password_hash('test123', PASSWORD_DEFAULT), 'Test Manager', 'manager@test.com', 'manager', $companyId]);
        self::$testManagerId = $db->lastInsertId();
        
        // Create test products
        $stmt = $db->prepare("
            INSERT INTO products (name, description, price, quantity, company_id, category_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        for ($i = 1; $i <= 5; $i++) {
            $stmt->execute([
                "Test Product $i",
                "Test Description $i",
                100.00 * $i,
                10 * $i,
                $companyId,
                null // No category for simplicity
            ]);
        }
        
        // Create test customers
        $stmt = $db->prepare("
            INSERT INTO customers (name, phone_number, email, company_id) 
            VALUES (?, ?, ?, ?)
        ");
        for ($i = 1; $i <= 3; $i++) {
            $stmt->execute([
                "Test Customer $i",
                "555000$i",
                "customer$i@test.com",
                $companyId
            ]);
        }
        
        // Create test swaps
        $stmt = $db->prepare("
            INSERT INTO swaps (company_id, customer_id, total_value, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$companyId, 1, 500.00]);
        
        // Create test pos_sales
        $stmt = $db->prepare("
            INSERT INTO pos_sales (company_id, customer_id, total_amount, payment_method, created_at) 
            VALUES (?, ?, ?, 'CASH', NOW())
        ");
        $stmt->execute([$companyId, 1, 250.00]);
        
        return $companyId;
    }
    
    /**
     * Clean up test data
     */
    public static function cleanupTestData() {
        $db = self::getDB();
        
        if (self::$testCompanyId) {
            // Delete test company and all related data
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $tables = ['swaps', 'pos_sales', 'customers', 'products', 'users', 'companies'];
            foreach ($tables as $table) {
                try {
                    if ($table === 'users') {
                        $db->exec("DELETE FROM users WHERE id = " . (int)self::$testSystemAdminId . " OR id = " . (int)self::$testManagerId);
                    } elseif ($table === 'companies') {
                        $db->exec("DELETE FROM companies WHERE id = " . (int)self::$testCompanyId);
                    } else {
                        $db->exec("DELETE FROM $table WHERE company_id = " . (int)self::$testCompanyId);
                    }
                } catch (\Exception $e) {
                    // Ignore errors for cleanup
                }
            }
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
        
        // Clean up test files
        $testDir = __DIR__ . '/../storage/test_files/';
        if (is_dir($testDir)) {
            $files = glob($testDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Get test company ID
     */
    public static function getTestCompanyId() {
        return self::$testCompanyId;
    }
    
    /**
     * Get test system admin ID
     */
    public static function getTestSystemAdminId() {
        return self::$testSystemAdminId;
    }
    
    /**
     * Create test files for file cleanup testing
     */
    public static function createTestFiles($companyId, $count = 5) {
        $testDir = __DIR__ . '/../storage/test_files/';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0777, true);
        }
        
        $files = [];
        for ($i = 1; $i <= $count; $i++) {
            $filename = "test_product_{$companyId}_{$i}.jpg";
            $filepath = $testDir . $filename;
            file_put_contents($filepath, "test image content $i");
            $files[] = [
                'path' => $filepath,
                'type' => 'local'
            ];
        }
        
        return $files;
    }
    
    /**
     * Count rows in table for company
     */
    public static function countRows($table, $companyId) {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE company_id = ?");
        $stmt->execute([$companyId]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Count system admin users
     */
    public static function countSystemAdmins() {
        $db = self::getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'system_admin' AND company_id IS NULL");
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get JWT token for test user
     */
    public static function getTestToken($userId, $role = 'system_admin') {
        require_once __DIR__ . '/../app/Services/AuthService.php';
        $authService = new \App\Services\AuthService();
        
        $payload = [
            'sub' => $userId,
            'username' => 'test_user',
            'role' => $role,
            'company_id' => null,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        
        return $authService->generateToken($payload);
    }
}

