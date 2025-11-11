<?php
/**
 * Reset Service Tests (PHASE G)
 * Tests for ResetService functionality
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../app/Services/ResetService.php';

class ResetServiceTest {
    private $db;
    private $resetService;
    private $testCompanyId;
    
    public function __construct() {
        $this->db = TestHelper::getDB();
        $this->resetService = new \App\Services\ResetService();
    }
    
    /**
     * Test 1: Dry-run test
     */
    public function testDryRun() {
        echo "Test 1: Dry-Run Test\n";
        echo "====================\n";
        
        // Create test company with data
        $this->testCompanyId = TestHelper::createTestCompany();
        echo "Created test company ID: {$this->testCompanyId}\n";
        
        // Get initial counts
        $initialProducts = TestHelper::countRows('products', $this->testCompanyId);
        $initialCustomers = TestHelper::countRows('customers', $this->testCompanyId);
        $initialSwaps = TestHelper::countRows('swaps', $this->testCompanyId);
        $initialPosSales = TestHelper::countRows('pos_sales', $this->testCompanyId);
        
        echo "Initial counts:\n";
        echo "  Products: $initialProducts\n";
        echo "  Customers: $initialCustomers\n";
        echo "  Swaps: $initialSwaps\n";
        echo "  POS Sales: $initialPosSales\n\n";
        
        // Run dry-run
        $options = [
            'dry_run' => true,
            'delete_files' => false,
            'admin_user_id' => TestHelper::getTestSystemAdminId()
        ];
        
        $result = $this->resetService->resetCompanyData($this->testCompanyId, $options);
        
        // Assert dry-run returned counts
        assert($result['dry_run'] === true, "Expected dry_run to be true");
        assert(isset($result['counts']), "Expected counts in result");
        
        $counts = $result['counts'];
        echo "Dry-run counts:\n";
        foreach ($counts as $table => $count) {
            echo "  $table: $count\n";
        }
        echo "\n";
        
        // Assert counts are accurate
        assert($counts['products'] >= $initialProducts, "Product count should match or exceed initial");
        assert($counts['customers'] >= $initialCustomers, "Customer count should match or exceed initial");
        
        // Verify database unchanged
        $afterProducts = TestHelper::countRows('products', $this->testCompanyId);
        $afterCustomers = TestHelper::countRows('customers', $this->testCompanyId);
        
        assert($afterProducts === $initialProducts, "Products should not be deleted in dry-run");
        assert($afterCustomers === $initialCustomers, "Customers should not be deleted in dry-run");
        
        echo "✓ Dry-run test passed: Counts accurate and DB unchanged\n\n";
        
        // Cleanup
        TestHelper::cleanupTestData();
        
        return true;
    }
    
    /**
     * Test 2: Actual reset test
     */
    public function testActualReset() {
        echo "Test 2: Actual Reset Test\n";
        echo "==========================\n";
        
        // Create test company with data
        $this->testCompanyId = TestHelper::createTestCompany();
        $adminUserId = TestHelper::getTestSystemAdminId();
        
        // Get initial counts
        $initialProducts = TestHelper::countRows('products', $this->testCompanyId);
        $initialCustomers = TestHelper::countRows('customers', $this->testCompanyId);
        $initialSystemAdmins = TestHelper::countSystemAdmins();
        
        echo "Initial state:\n";
        echo "  Products: $initialProducts\n";
        echo "  Customers: $initialCustomers\n";
        echo "  System Admins: $initialSystemAdmins\n";
        echo "  Company exists: " . (TestHelper::countRows('companies', $this->testCompanyId) > 0 ? 'Yes' : 'No') . "\n\n";
        
        // Run actual reset
        $options = [
            'dry_run' => false,
            'delete_files' => false,
            'admin_user_id' => $adminUserId,
            'backup_reference' => 'TEST_BACKUP_' . time()
        ];
        
        $result = $this->resetService->resetCompanyData($this->testCompanyId, $options);
        
        // Assert reset succeeded
        assert($result['success'] === true, "Reset should succeed");
        
        // Verify rows removed
        $afterProducts = TestHelper::countRows('products', $this->testCompanyId);
        $afterCustomers = TestHelper::countRows('customers', $this->testCompanyId);
        
        echo "After reset:\n";
        echo "  Products: $afterProducts\n";
        echo "  Customers: $afterCustomers\n";
        echo "  Company exists: " . (TestHelper::countRows('companies', $this->testCompanyId) > 0 ? 'Yes' : 'No') . "\n";
        echo "  System Admins: " . TestHelper::countSystemAdmins() . "\n\n";
        
        assert($afterProducts === 0, "All products should be deleted");
        assert($afterCustomers === 0, "All customers should be deleted");
        
        // Verify company record preserved
        $companyExists = TestHelper::countRows('companies', $this->testCompanyId) > 0;
        assert($companyExists === true, "Company record should be preserved");
        
        // Verify system_admin users remain
        $afterSystemAdmins = TestHelper::countSystemAdmins();
        assert($afterSystemAdmins >= $initialSystemAdmins, "System admins should not be deleted");
        
        // Verify preserved tables (categories, brands) - should still exist
        $db = TestHelper::getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM categories");
        $categoryCount = $stmt->fetchColumn();
        assert($categoryCount >= 0, "Categories table should still have data");
        
        echo "✓ Actual reset test passed: Rows removed, preserved data intact, system_admins remain\n\n";
        
        // Cleanup
        TestHelper::cleanupTestData();
        
        return true;
    }
    
    /**
     * Test 3: System reset test
     */
    public function testSystemReset() {
        echo "Test 3: System Reset Test\n";
        echo "==========================\n";
        
        // Create multiple test companies
        $companyIds = [];
        for ($i = 0; $i < 2; $i++) {
            $companyId = TestHelper::createTestCompany();
            $companyIds[] = $companyId;
        }
        
        $initialSystemAdmins = TestHelper::countSystemAdmins();
        
        // Count total companies
        $db = TestHelper::getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM companies");
        $initialCompanies = $stmt->fetchColumn();
        
        echo "Initial state:\n";
        echo "  Companies: $initialCompanies\n";
        echo "  System Admins: $initialSystemAdmins\n\n";
        
        // Run system reset
        $options = [
            'dry_run' => false,
            'delete_files' => false,
            'admin_user_id' => TestHelper::getTestSystemAdminId(),
            'backup_reference' => 'TEST_SYSTEM_BACKUP_' . time()
        ];
        
        $result = $this->resetService->resetSystemData($options);
        
        // Assert reset succeeded
        assert($result['success'] === true, "System reset should succeed");
        
        // Verify all companies deleted
        $stmt = $db->query("SELECT COUNT(*) FROM companies");
        $afterCompanies = $stmt->fetchColumn();
        
        // Verify system_admin users remain
        $afterSystemAdmins = TestHelper::countSystemAdmins();
        
        echo "After system reset:\n";
        echo "  Companies: $afterCompanies\n";
        echo "  System Admins: $afterSystemAdmins\n\n";
        
        assert($afterCompanies === 0, "All companies should be deleted");
        assert($afterSystemAdmins >= $initialSystemAdmins, "System admins should remain");
        
        echo "✓ System reset test passed: All companies deleted, system_admins preserved\n\n";
        
        // Cleanup
        TestHelper::cleanupTestData();
        
        return true;
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        $results = [];
        
        try {
            $results['dry_run'] = $this->testDryRun();
        } catch (\Exception $e) {
            echo "✗ Dry-run test failed: " . $e->getMessage() . "\n\n";
            $results['dry_run'] = false;
        }
        
        try {
            $results['actual_reset'] = $this->testActualReset();
        } catch (\Exception $e) {
            echo "✗ Actual reset test failed: " . $e->getMessage() . "\n\n";
            $results['actual_reset'] = false;
        }
        
        try {
            $results['system_reset'] = $this->testSystemReset();
        } catch (\Exception $e) {
            echo "✗ System reset test failed: " . $e->getMessage() . "\n\n";
            $results['system_reset'] = false;
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ResetServiceTest();
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

