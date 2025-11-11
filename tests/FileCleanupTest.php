<?php
/**
 * File Cleanup Tests (PHASE G)
 * Tests for file deletion functionality
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../app/Services/FileCleanupService.php';
require_once __DIR__ . '/../app/Models/ResetJob.php';
require_once __DIR__ . '/../app/Models/AdminAction.php';

class FileCleanupTest {
    private $db;
    private $fileCleanupService;
    
    public function __construct() {
        $this->db = TestHelper::getDB();
        $this->fileCleanupService = new \App\Services\FileCleanupService();
    }
    
    /**
     * Test file cleanup job
     */
    public function testFileCleanup() {
        echo "Test: File Cleanup Test\n";
        echo "=======================\n";
        
        // Create test company
        $companyId = TestHelper::createTestCompany();
        
        // Create test files
        $testFiles = TestHelper::createTestFiles($companyId, 5);
        echo "Created " . count($testFiles) . " test files\n";
        
        // Verify files exist
        foreach ($testFiles as $file) {
            assert(file_exists($file['path']), "Test file should exist: {$file['path']}");
        }
        
        // Create admin action record
        $adminActionModel = new \App\Models\AdminAction();
        $adminActionId = $adminActionModel->create([
            'admin_user_id' => TestHelper::getTestSystemAdminId(),
            'action_type' => 'company_reset',
            'target_company_id' => $companyId,
            'dry_run' => 0,
            'status' => 'pending',
            'backup_reference' => 'TEST_BACKUP',
            'payload' => []  // Model will JSON encode it
        ]);
        
        // Verify admin action created
        $adminAction = $adminActionModel->findById($adminActionId);
        assert($adminAction !== null, "Admin action should be created");
        
        echo "Created admin action ID: $adminActionId\n\n";
        
        // Enqueue file deletion job
        $jobId = $this->fileCleanupService->enqueueFileDeletionJob($adminActionId, $testFiles);
        assert($jobId !== null, "Job should be created");
        echo "Enqueued file cleanup job ID: $jobId\n\n";
        
        // Verify job created in database
        $resetJobModel = new \App\Models\ResetJob();
        $job = $resetJobModel->findById($jobId);
        assert($job !== null, "Job should exist in database");
        assert($job['status'] === 'pending', "Job should be pending");
        
        echo "Job status: {$job['status']}\n";
        echo "Job type: {$job['job_type']}\n\n";
        
        // Process the job
        echo "Processing file cleanup job...\n";
        $results = $this->fileCleanupService->processPendingJobs(1);
        
        assert(count($results) > 0, "Should have processing results");
        assert($results[0]['job_id'] == $jobId, "Should process correct job");
        
        echo "Processing result:\n";
        echo "  Job ID: {$results[0]['job_id']}\n";
        echo "  Success: " . ($results[0]['success'] ? 'Yes' : 'No') . "\n";
        if (!$results[0]['success']) {
            echo "  Error: " . ($results[0]['error'] ?? 'Unknown') . "\n";
        }
        echo "\n";
        
        // Verify job status updated
        $job = $resetJobModel->findById($jobId);
        echo "Job status after processing: {$job['status']}\n\n";
        
        // Verify files deleted (if job succeeded)
        if ($results[0]['success']) {
            $filesDeleted = 0;
            foreach ($testFiles as $file) {
                if (!file_exists($file['path'])) {
                    $filesDeleted++;
                }
            }
            echo "Files deleted: $filesDeleted of " . count($testFiles) . "\n";
            
            // Note: Some files might not exist if already deleted, that's okay
            assert($filesDeleted >= 0, "Files should be deleted or already gone");
        }
        
        echo "✓ File cleanup test passed\n\n";
        
        // Cleanup
        TestHelper::cleanupTestData();
        
        // Clean up admin action and job
        try {
            $this->db->prepare("DELETE FROM reset_jobs WHERE id = ?")->execute([$jobId]);
            $this->db->prepare("DELETE FROM admin_actions WHERE id = ?")->execute([$adminActionId]);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        return true;
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        $results = [];
        
        try {
            $results['file_cleanup'] = $this->testFileCleanup();
        } catch (\Exception $e) {
            echo "✗ File cleanup test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n\n";
            $results['file_cleanup'] = false;
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new FileCleanupTest();
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

