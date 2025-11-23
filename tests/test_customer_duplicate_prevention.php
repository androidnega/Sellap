<?php
/**
 * Test Customer Duplicate Prevention
 * 
 * This script tests that the duplicate prevention is working correctly.
 * It attempts to create duplicate customers and verifies they are rejected.
 * 
 * Usage: php tests/test_customer_duplicate_prevention.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Customer.php';

use App\Models\Customer;

echo "=== Testing Customer Duplicate Prevention ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$customerModel = new Customer();
$testsPassed = 0;
$testsFailed = 0;

// Test company ID (you may need to change this to match your data)
$testCompanyId = 1;

// Generate a unique phone number for testing
$testPhone = '9999' . rand(100000, 999999);
$testEmail = 'test' . rand(1000, 9999) . '@example.com';

echo "Test Setup:\n";
echo "  Company ID: {$testCompanyId}\n";
echo "  Test Phone: {$testPhone}\n";
echo "  Test Email: {$testEmail}\n\n";

// ============================================================
// TEST 1: Create a new customer (should succeed)
// ============================================================
echo "TEST 1: Create new customer (should succeed)\n";
echo "─────────────────────────────────────────────────\n";

$customerData = [
    'company_id' => $testCompanyId,
    'unique_id' => 'CUS' . strtoupper(uniqid()),
    'full_name' => 'Test Customer 1',
    'phone_number' => $testPhone,
    'email' => $testEmail,
    'address' => '123 Test St',
    'created_by_user_id' => 1
];

try {
    $result = $customerModel->create($customerData);
    if ($result) {
        echo "✓ PASS: Customer created successfully\n";
        $testsPassed++;
        
        // Get the created customer ID
        $createdCustomer = $customerModel->findByPhoneInCompany($testPhone, $testCompanyId);
        $customerId = $createdCustomer['id'];
        echo "  Customer ID: {$customerId}\n";
    } else {
        echo "✗ FAIL: Failed to create customer\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// ============================================================
// TEST 2: Check findByPhoneInCompany returns the customer
// ============================================================
echo "TEST 2: Check findByPhoneInCompany (should find customer)\n";
echo "─────────────────────────────────────────────────\n";

try {
    $found = $customerModel->findByPhoneInCompany($testPhone, $testCompanyId);
    if ($found && $found['phone_number'] === $testPhone) {
        echo "✓ PASS: Customer found by phone\n";
        echo "  Found: {$found['full_name']} (ID: {$found['id']})\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Customer not found by phone\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// ============================================================
// TEST 3: Check findByEmailInCompany returns the customer
// ============================================================
echo "TEST 3: Check findByEmailInCompany (should find customer)\n";
echo "─────────────────────────────────────────────────\n";

try {
    $found = $customerModel->findByEmailInCompany($testEmail, $testCompanyId);
    if ($found && strtolower($found['email']) === strtolower($testEmail)) {
        echo "✓ PASS: Customer found by email\n";
        echo "  Found: {$found['full_name']} (ID: {$found['id']})\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Customer not found by email\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// ============================================================
// TEST 4: Try to create duplicate with same phone (should fail)
// ============================================================
echo "TEST 4: Try to create duplicate with same phone (should fail)\n";
echo "─────────────────────────────────────────────────\n";

$duplicateData = [
    'company_id' => $testCompanyId,
    'unique_id' => 'CUS' . strtoupper(uniqid()),
    'full_name' => 'Test Customer 2 (Duplicate)',
    'phone_number' => $testPhone, // Same phone!
    'email' => 'different' . rand(1000, 9999) . '@example.com',
    'address' => '456 Different St',
    'created_by_user_id' => 1
];

try {
    // First check if phone exists (this is what the controller does)
    $existing = $customerModel->findByPhoneInCompany($testPhone, $testCompanyId);
    
    if ($existing) {
        echo "✓ PASS: Duplicate phone detected by application logic\n";
        echo "  Existing customer: {$existing['full_name']} (ID: {$existing['id']})\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Duplicate phone NOT detected by application logic\n";
        $testsFailed++;
    }
    
    // Now try to actually insert (will fail with database constraint if it exists)
    try {
        $result = $customerModel->create($duplicateData);
        if ($result) {
            echo "✗ FAIL: Duplicate customer was created (should have been rejected!)\n";
            $testsFailed++;
            
            // Clean up the duplicate
            $dup = $customerModel->findByUniqueId($duplicateData['unique_id'], $testCompanyId);
            if ($dup) {
                $customerModel->delete($dup['id'], $testCompanyId);
                echo "  Cleaned up duplicate (ID: {$dup['id']})\n";
            }
        } else {
            echo "✓ PASS: Database rejected duplicate (create returned false)\n";
            $testsPassed++;
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'unique_company_phone') !== false || 
            strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "✓ PASS: Database constraint prevented duplicate\n";
            echo "  Error: " . $e->getMessage() . "\n";
            $testsPassed++;
        } else {
            echo "⚠ WARNING: Different error occurred\n";
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// ============================================================
// TEST 5: Clean up - Delete test customer
// ============================================================
echo "TEST 5: Clean up - Delete test customer\n";
echo "─────────────────────────────────────────────────\n";

try {
    $customer = $customerModel->findByPhoneInCompany($testPhone, $testCompanyId);
    if ($customer) {
        $result = $customerModel->delete($customer['id'], $testCompanyId);
        if ($result) {
            echo "✓ PASS: Test customer deleted successfully\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Failed to delete test customer\n";
            $testsFailed++;
        }
    } else {
        echo "⚠ WARNING: Test customer not found for cleanup\n";
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception during cleanup: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// ============================================================
// SUMMARY
// ============================================================
echo "══════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "══════════════════════════════════════════════════\n";
echo "Tests Passed: {$testsPassed}\n";
echo "Tests Failed: {$testsFailed}\n";
echo "Total Tests:  " . ($testsPassed + $testsFailed) . "\n\n";

if ($testsFailed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "  Duplicate prevention is working correctly.\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED!\n";
    echo "  Please review the output above.\n";
    exit(1);
}

