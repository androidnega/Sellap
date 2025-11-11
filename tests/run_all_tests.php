<?php
/**
 * Test Runner (PHASE G)
 * Runs all test suites
 */

echo "========================================\n";
echo "Reset System Test Suite (PHASE G)\n";
echo "========================================\n\n";

$testFiles = [
    'ResetServiceTest.php',
    'FileCleanupTest.php',
    'PermissionTest.php',
    'ConcurrencyTest.php'
];

$allResults = [];
$totalTests = 0;
$passedTests = 0;

foreach ($testFiles as $testFile) {
    $testPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "⚠ Warning: Test file not found: $testFile\n";
        continue;
    }
    
    echo "\n";
    echo str_repeat("=", 50) . "\n";
    echo "Running: $testFile\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Capture output
    ob_start();
    
    try {
        require_once $testPath;
        
        // Extract class name from file name
        $className = str_replace('.php', '', $testFile);
        
        if (class_exists($className)) {
            $test = new $className();
            $results = $test->runAll();
            
            $allResults[$testFile] = $results;
            
            foreach ($results as $testName => $passed) {
                $totalTests++;
                if ($passed) {
                    $passedTests++;
                }
            }
        } else {
            echo "⚠ Warning: Test class not found: $className\n";
        }
    } catch (\Exception $e) {
        echo "✗ Error running $testFile: " . $e->getMessage() . "\n";
        $allResults[$testFile] = ['error' => $e->getMessage()];
    }
    
    $output = ob_get_clean();
    echo $output;
}

// Final summary
echo "\n";
echo str_repeat("=", 50) . "\n";
echo "FINAL TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n\n";

foreach ($allResults as $testFile => $results) {
    if (isset($results['error'])) {
        echo "✗ $testFile: ERROR - {$results['error']}\n";
    } else {
        echo "\n$testFile:\n";
        foreach ($results as $testName => $passed) {
            if ($testName !== 'error') {
                $status = $passed ? "PASSED" : "FAILED";
                $icon = $passed ? "✓" : "✗";
                echo "  $icon $testName: $status\n";
            }
        }
    }
}

echo "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "\n";

$allPassed = ($passedTests === $totalTests && $totalTests > 0);
echo $allPassed ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED";
echo "\n\n";

exit($allPassed ? 0 : 1);

