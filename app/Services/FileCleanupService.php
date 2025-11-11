<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

use App\Models\ResetJob;
use Exception;

/**
 * File Cleanup Service (PHASE D)
 * Handles physical file deletion after database reset
 * Supports both local files and Cloudinary cloud storage
 * Runs asynchronously via queued jobs
 */
class FileCleanupService {
    private $db;
    private $resetJobModel;
    private $cloudinaryService;
    private $useCloudinary;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->resetJobModel = new ResetJob();
        
        // Check if Cloudinary is configured
        try {
            $this->cloudinaryService = new CloudinaryService();
            $this->useCloudinary = $this->isCloudinaryConfigured();
        } catch (\Exception $e) {
            $this->cloudinaryService = null;
            $this->useCloudinary = false;
            error_log("CloudinaryService not available: " . $e->getMessage());
        }
    }

    /**
     * Check if Cloudinary is properly configured
     */
    private function isCloudinaryConfigured() {
        if (!$this->cloudinaryService) {
            return false;
        }
        
        // Check environment variables or database settings
        $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = getenv('CLOUDINARY_API_KEY');
        $apiSecret = getenv('CLOUDINARY_API_SECRET');
        
        return !empty($cloudName) && !empty($apiKey) && !empty($apiSecret);
    }

    /**
     * PHASE D: Enqueue file deletion job
     * Stores job in reset_jobs table with file list
     * 
     * @param int $adminActionId Admin action ID
     * @param array $fileList Array of file information:
     *   - Local files: ['path' => 'assets/images/product.jpg', 'type' => 'local']
     *   - Cloudinary files: ['public_id' => 'sellapp/product_123', 'type' => 'cloudinary']
     *   - Or mixed: ['path' => '...', 'type' => 'local'], ['public_id' => '...', 'type' => 'cloudinary']
     * @return int Job ID
     */
    public function enqueueFileDeletionJob($adminActionId, $fileList) {
        if (empty($fileList)) {
            // No files to delete, skip job creation
            return null;
        }

        // Categorize files by type
        $localFiles = [];
        $cloudinaryFiles = [];
        
        foreach ($fileList as $file) {
            if (is_string($file)) {
                // Legacy: simple string path, assume local
                $localFiles[] = ['path' => $file, 'type' => 'local'];
            } elseif (is_array($file)) {
                if (isset($file['type']) && $file['type'] === 'cloudinary' && isset($file['public_id'])) {
                    $cloudinaryFiles[] = $file;
                } elseif (isset($file['path'])) {
                    // Check if it's a Cloudinary URL
                    if (strpos($file['path'], 'cloudinary.com') !== false || strpos($file['path'], 'res.cloudinary.com') !== false) {
                        // Extract public_id from Cloudinary URL
                        $publicId = $this->extractPublicIdFromUrl($file['path']);
                        if ($publicId) {
                            $cloudinaryFiles[] = ['public_id' => $publicId, 'type' => 'cloudinary'];
                        } else {
                            $localFiles[] = ['path' => $file['path'], 'type' => 'local'];
                        }
                    } else {
                        $localFiles[] = ['path' => $file['path'], 'type' => 'local'];
                    }
                }
            }
        }

        // Create job with categorized file list
        $jobId = $this->resetJobModel->create([
            'admin_action_id' => $adminActionId,
            'job_type' => 'file_cleanup',
            'status' => 'pending',
            'details' => [
                'total_files' => count($fileList),
                'local_files' => $localFiles,
                'cloudinary_files' => $cloudinaryFiles,
                'local_count' => count($localFiles),
                'cloudinary_count' => count($cloudinaryFiles),
                'created_at' => date('Y-m-d H:i:s')
            ],
            'max_retries' => 3
        ]);

        return $jobId;
    }

    /**
     * Queue file cleanup job for company reset (legacy method - now uses enqueueFileDeletionJob)
     */
    public function queueCompanyFileCleanup($adminActionId, $companyId, $productIds = []) {
        // Get file list from product_ids or database
        $fileList = $this->getCompanyFileList($companyId, $productIds);
        return $this->enqueueFileDeletionJob($adminActionId, $fileList);
    }

    /**
     * Queue file cleanup job for system reset (legacy method - now uses enqueueFileDeletionJob)
     */
    public function queueSystemFileCleanup($adminActionId, $productIds = []) {
        // Get all file paths that need cleanup
        $fileList = $this->getSystemFileList($productIds);
        return $this->enqueueFileDeletionJob($adminActionId, $fileList);
    }

    /**
     * PHASE D: Process pending file cleanup jobs
     * Should be called by a cron job or background worker (CLI script)
     * Handles both local files and Cloudinary deletion
     */
    public function processPendingJobs($limit = 10) {
        $jobs = $this->resetJobModel->getPendingJobs('file_cleanup', $limit);
        $results = [];
        
        foreach ($jobs as $job) {
            try {
                // Update job status to running
                $this->resetJobModel->update($job['id'], [
                    'status' => 'running',
                    'started_at' => date('Y-m-d H:i:s')
                ]);
                
                $details = json_decode($job['details'], true) ?: [];
                $deletedCount = 0;
                $failedFiles = [];
                
                // Process local files
                $localFiles = $details['local_files'] ?? [];
                foreach ($localFiles as $file) {
                    $filePath = $file['path'] ?? (is_string($file) ? $file : '');
                    if (empty($filePath)) {
                        continue;
                    }
                    
                    $fullPath = __DIR__ . '/../../' . ltrim($filePath, '/');
                    if (file_exists($fullPath)) {
                        if (@unlink($fullPath)) {
                            $deletedCount++;
                        } else {
                            $failedFiles[] = ['path' => $filePath, 'type' => 'local', 'error' => 'Failed to delete file'];
                        }
                    } else {
                        // File doesn't exist - consider it deleted (may have been manually removed)
                        $deletedCount++;
                    }
                }
                
                // Process Cloudinary files
                $cloudinaryFiles = $details['cloudinary_files'] ?? [];
                if (!empty($cloudinaryFiles) && $this->useCloudinary && $this->cloudinaryService) {
                    foreach ($cloudinaryFiles as $file) {
                        $publicId = $file['public_id'] ?? '';
                        if (empty($publicId)) {
                            continue;
                        }
                        
                        try {
                            $result = $this->cloudinaryService->deleteImage($publicId);
                            if ($result['success'] && ($result['result'] === 'ok' || $result['result'] === 'not found')) {
                                $deletedCount++;
                            } else {
                                $failedFiles[] = [
                                    'public_id' => $publicId,
                                    'type' => 'cloudinary',
                                    'error' => $result['error'] ?? 'Unknown error'
                                ];
                            }
                        } catch (\Exception $e) {
                            $failedFiles[] = [
                                'public_id' => $publicId,
                                'type' => 'cloudinary',
                                'error' => $e->getMessage()
                            ];
                        }
                    }
                } elseif (!empty($cloudinaryFiles) && !$this->useCloudinary) {
                    // Cloudinary not configured - mark as skipped
                    foreach ($cloudinaryFiles as $file) {
                        $failedFiles[] = [
                            'public_id' => $file['public_id'] ?? 'unknown',
                            'type' => 'cloudinary',
                            'error' => 'Cloudinary not configured'
                        ];
                    }
                }
                
                // Update job status
                $jobStatus = empty($failedFiles) ? 'completed' : 'failed';
                $this->resetJobModel->update($job['id'], [
                    'status' => $jobStatus,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'details' => array_merge($details, [
                        'deleted_count' => $deletedCount,
                        'failed_files' => $failedFiles,
                        'failed_count' => count($failedFiles),
                        'processed_at' => date('Y-m-d H:i:s')
                    ]),
                    'error_message' => empty($failedFiles) ? null : 
                        'Failed to delete ' . count($failedFiles) . ' files'
                ]);
                
                // Emit notification if job completed or failed
                $this->emitNotification($job['id'], $jobStatus, $deletedCount, count($failedFiles), $job['admin_action_id']);
                
                $results[] = [
                    'job_id' => $job['id'],
                    'success' => empty($failedFiles),
                    'deleted_count' => $deletedCount,
                    'failed_count' => count($failedFiles),
                    'status' => $jobStatus
                ];
                
            } catch (Exception $e) {
                // Update job with error
                $retryCount = ($details['retry_count'] ?? 0) + 1;
                $maxRetries = $job['max_retries'] ?? 3;
                
                $jobStatus = ($retryCount >= $maxRetries) ? 'failed' : 'pending';
                
                $this->resetJobModel->update($job['id'], [
                    'status' => $jobStatus,
                    'error_message' => $e->getMessage(),
                    'completed_at' => ($retryCount >= $maxRetries) ? date('Y-m-d H:i:s') : null,
                    'retry_count' => $retryCount
                ]);
                
                // Emit notification on final failure
                if ($retryCount >= $maxRetries) {
                    $this->emitNotification($job['id'], 'failed', 0, 0, $job['admin_action_id'], $e->getMessage());
                }
                
                $results[] = [
                    'job_id' => $job['id'],
                    'success' => false,
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get file list for company (returns structured file info)
     * Public method for use by controllers
     */
    public function getCompanyFileList($companyId, $productIds = []) {
        $fileList = [];
        
        // Get product images from product_ids if provided, otherwise query by company
        if (!empty($productIds)) {
            $in = str_repeat('?,', count($productIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT file_path FROM product_images WHERE product_id IN ($in)");
            $stmt->execute($productIds);
        } else {
            $stmt = $this->db->prepare("
                SELECT file_path FROM product_images 
                WHERE product_id IN (SELECT id FROM products WHERE company_id = ?)
            ");
            $stmt->execute([$companyId]);
        }
        
        $images = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($images as $filePath) {
            if ($filePath) {
                $fileList[] = ['path' => $filePath, 'type' => 'local'];
            }
        }
        
        // TODO: Get repair photos and customer uploaded files if tracked separately
        
        return $fileList;
    }

    /**
     * Get file list for system reset
     * Public method for use by controllers
     */
    public function getSystemFileList($productIds = []) {
        $fileList = [];
        
        // Get all product images
        if (!empty($productIds)) {
            $in = str_repeat('?,', count($productIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT DISTINCT file_path FROM product_images WHERE file_path IS NOT NULL AND product_id IN ($in)");
            $stmt->execute($productIds);
        } else {
            $stmt = $this->db->query("SELECT DISTINCT file_path FROM product_images WHERE file_path IS NOT NULL");
        }
        
        $images = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($images as $filePath) {
            if ($filePath) {
                $fileList[] = ['path' => $filePath, 'type' => 'local'];
            }
        }
        
        // Scan assets/images directory for other local files
        $assetsDir = __DIR__ . '/../../assets/images/';
        if (is_dir($assetsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($assetsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace(__DIR__ . '/../../', '', $file->getPathname());
                    $fileList[] = ['path' => $relativePath, 'type' => 'local'];
                }
            }
        }
        
        return $fileList;
    }

    /**
     * Extract public_id from Cloudinary URL
     */
    private function extractPublicIdFromUrl($url) {
        // Cloudinary URL format: https://res.cloudinary.com/cloud_name/image/upload/v1234567890/folder/public_id.jpg
        // Extract public_id (folder/public_id without extension)
        if (preg_match('/\/upload\/[^\/]+\/(.+?)(?:\.[^.]+)?$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Emit notification to admin about job completion/failure
     */
    private function emitNotification($jobId, $status, $deletedCount, $failedCount, $adminActionId, $errorMessage = null) {
        try {
            // Send notification via ResetNotificationService (PHASE E)
            require_once __DIR__ . '/ResetNotificationService.php';
            $notificationService = new \App\Services\ResetNotificationService();
            
            // Get admin action to determine action type
            $adminActionStmt = $this->db->prepare("SELECT action_type FROM admin_actions WHERE id = ?");
            $adminActionStmt->execute([$adminActionId]);
            $adminAction = $adminActionStmt->fetch(\PDO::FETCH_ASSOC);
            
            $notificationService->notifyResetCompletion(
                $adminActionId,
                $adminAction['action_type'] ?? 'company_reset',
                $status === 'completed',
                [
                    'job_id' => $jobId,
                    'job_type' => 'file_cleanup',
                    'files_deleted' => $deletedCount,
                    'files_failed' => $failedCount,
                    'error_message' => $errorMessage
                ]
            );
            
            // Legacy logging for debugging
            error_log("File cleanup job {$jobId} {$status}: {$deletedCount} deleted, {$failedCount} failed. Admin Action: {$adminActionId}");
            
        } catch (\Exception $e) {
            error_log("Failed to emit notification for job {$jobId}: " . $e->getMessage());
            // Fallback logging
            error_log("File cleanup job {$jobId} {$status}: {$deletedCount} deleted, {$failedCount} failed");
        }
    }

    /**
     * Execute file cleanup immediately (synchronous)
     * Use for testing or immediate cleanup
     */
    public function executeCleanup($filePaths) {
        $deletedCount = 0;
        $failedPaths = [];
        
        foreach ($filePaths as $filePath) {
            $fullPath = __DIR__ . '/../../' . $filePath;
            if (file_exists($fullPath)) {
                if (@unlink($fullPath)) {
                    $deletedCount++;
                } else {
                    $failedPaths[] = $filePath;
                }
            }
        }
        
        return [
            'deleted_count' => $deletedCount,
            'failed_paths' => $failedPaths,
            'success' => empty($failedPaths)
        ];
    }
}

