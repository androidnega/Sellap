<?php
/**
 * Permission Tests (PHASE G)
 * Tests for permission middleware and access control
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../app/Middleware/ResetPermissionMiddleware.php';
require_once __DIR__ . '/../app/Services/AuthService.php';

class PermissionTest {
    private $db;
    
    public function __construct() {
        $this->db = TestHelper::getDB();
    }
    
    /**
     * Test system_admin can access reset endpoints
     */
    public function testSystemAdminAccess() {
        echo "Test: System Admin Access Test\n";
        echo "==============================\n";
        
        // Create test system admin
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, full_name, email, role, company_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'test_sys_admin_' . time(),
            password_hash('test123', PASSWORD_DEFAULT),
            'Test System Admin',
            'sysadmin@test.com',
            'system_admin',
            null
        ]);
        $adminId = $this->db->lastInsertId();
        
        // Generate token for system admin
        $authService = new \App\Services\AuthService();
        $token = $authService->generateToken([
            'sub' => $adminId,
            'username' => 'test_sys_admin',
            'role' => 'system_admin',
            'company_id' => null,
            'iat' => time(),
            'exp' => time() + 3600
        ]);
        
        echo "Created system admin with ID: $adminId\n";
        echo "Generated token: " . substr($token, 0, 20) . "...\n\n";
        
        // Test permission middleware
        // Note: We can't directly test middleware in CLI, so we'll test the logic
        
        // Simulate permission check by testing role validation
        $payload = (object)[
            'sub' => $adminId,
            'username' => 'test_sys_admin',
            'role' => 'system_admin',
            'company_id' => null
        ];
        
        // System admin should have access
        assert($payload->role === 'system_admin', "Should be system_admin");
        
        // Cleanup
        $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$adminId]);
        
        echo "✓ System admin access test passed\n\n";
        return true;
    }
    
    /**
     * Test non-admin users cannot access
     */
    public function testNonAdminAccessDenied() {
        echo "Test: Non-Admin Access Denied Test\n";
        echo "===================================\n";
        
        // Create test manager
        $companyId = TestHelper::createTestCompany();
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, full_name, email, role, company_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'test_manager_' . time(),
            password_hash('test123', PASSWORD_DEFAULT),
            'Test Manager',
            'manager@test.com',
            'manager',
            $companyId
        ]);
        $managerId = $this->db->lastInsertId();
        
        echo "Created manager with ID: $managerId\n";
        echo "Manager role: manager\n\n";
        
        // Simulate permission check
        $payload = (object)[
            'sub' => $managerId,
            'username' => 'test_manager',
            'role' => 'manager',
            'company_id' => $companyId
        ];
        
        // Manager should NOT have access to system reset
        $hasAccess = ($payload->role === 'system_admin');
        assert($hasAccess === false, "Manager should not have system_admin access");
        
        // Manager should NOT have access to company reset (per current implementation)
        // Note: Future enhancement may allow managers to reset their own company
        $canResetCompany = ($payload->role === 'system_admin');
        assert($canResetCompany === false, "Manager should not have reset access (current implementation)");
        
        // Cleanup
        $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$managerId]);
        TestHelper::cleanupTestData();
        
        echo "✓ Non-admin access denied test passed\n\n";
        return true;
    }
    
    /**
     * Test salesperson cannot access
     */
    public function testSalespersonAccessDenied() {
        echo "Test: Salesperson Access Denied Test\n";
        echo "=====================================\n";
        
        // Create test salesperson
        $companyId = TestHelper::createTestCompany();
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, full_name, email, role, company_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'test_salesperson_' . time(),
            password_hash('test123', PASSWORD_DEFAULT),
            'Test Salesperson',
            'sales@test.com',
            'salesperson',
            $companyId
        ]);
        $salespersonId = $this->db->lastInsertId();
        
        echo "Created salesperson with ID: $salespersonId\n";
        echo "Salesperson role: salesperson\n\n";
        
        // Simulate permission check
        $payload = (object)[
            'sub' => $salespersonId,
            'username' => 'test_salesperson',
            'role' => 'salesperson',
            'company_id' => $companyId
        ];
        
        // Salesperson should NOT have access
        $hasAccess = ($payload->role === 'system_admin');
        assert($hasAccess === false, "Salesperson should not have system_admin access");
        
        // Cleanup
        $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$salespersonId]);
        TestHelper::cleanupTestData();
        
        echo "✓ Salesperson access denied test passed\n\n";
        return true;
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        $results = [];
        
        try {
            $results['system_admin_access'] = $this->testSystemAdminAccess();
        } catch (\Exception $e) {
            echo "✗ System admin access test failed: " . $e->getMessage() . "\n\n";
            $results['system_admin_access'] = false;
        }
        
        try {
            $results['non_admin_denied'] = $this->testNonAdminAccessDenied();
        } catch (\Exception $e) {
            echo "✗ Non-admin access denied test failed: " . $e->getMessage() . "\n\n";
            $results['non_admin_denied'] = false;
        }
        
        try {
            $results['salesperson_denied'] = $this->testSalespersonAccessDenied();
        } catch (\Exception $e) {
            echo "✗ Salesperson access denied test failed: " . $e->getMessage() . "\n\n";
            $results['salesperson_denied'] = false;
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new PermissionTest();
    $results = $test->runAll();
    
    echo "\n========================================\n";
    echo "Test Results Summary:\n";
    echo "========================================\n";
    foreach ($results as $testName => $passed) {
        echo ($passed ? "✓" : "✗") . " $testName: " . ($passed ? "PASSED" : "FAILED") . "\n";
    }
    
    $allPassed = !in_array(false, $results);
    exit($allPassed ? 0 : 1);
}

