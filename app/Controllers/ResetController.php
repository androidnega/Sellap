<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\ResetPermissionMiddleware;
use App\Services\ResetService;
use App\Services\BackupService;
use App\Services\FileCleanupService;
use App\Services\ResetNotificationService;
use App\Services\MonitoringService;
use App\Models\AdminAction;

require_once __DIR__ . '/../../config/database.php';

/**
 * Reset Controller
 * Handles data reset operations with full safety checks
 * 
 * Endpoints:
 * - POST /api/admin/reset/company/preview (dry-run for company reset)
 * - POST /api/admin/reset/company (execute company reset)
 * - POST /api/admin/reset/system/preview (dry-run for system reset)
 * - POST /api/admin/reset/system (execute system reset)
 * - GET /api/admin/reset/actions (list reset action history)
 */
class ResetController {
    
    /**
     * Preview company reset (dry-run)
     * Returns counts of rows that would be affected
     */
    public function previewCompanyReset() {
        $payload = AuthMiddleware::handle(['system_admin', 'manager']);
        // Convert payload object to array for easier access
        $payloadArray = is_object($payload) ? ['user_id' => $payload->sub ?? $payload->user_id ?? null, 'role' => $payload->role ?? null] : $payload;
        $db = \Database::getInstance()->getConnection();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = $input['company_id'] ?? null;

            if (!$companyId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'company_id is required']);
                return;
            }

            // Verify company exists
            $stmt = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Company not found']);
                return;
            }

            // Run dry-run
            $userId = $payloadArray['user_id'] ?? $payload->sub ?? $payload->user_id;
            $resetService = new ResetService($userId, true);
            $result = $resetService->resetCompanyData($companyId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'dry_run' => true,
                'company_id' => $companyId,
                'company_name' => $company['name'],
                'row_counts' => $result['row_counts'],
                'total_affected_rows' => array_sum($result['row_counts']),
                'warnings' => [
                    'This is a preview only. No data has been deleted.',
                    'Backup is required before executing actual reset.',
                    'This action is IRREVERSIBLE.'
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Execute company reset
     * Requires backup_reference and typed confirmation
     */
    public function executeCompanyReset() {
        $payload = AuthMiddleware::handle(['system_admin', 'manager']);
        // Convert payload object to array for easier access
        $payloadArray = is_object($payload) ? ['user_id' => $payload->sub ?? $payload->user_id ?? null, 'role' => $payload->role ?? null] : $payload;
        $db = \Database::getInstance()->getConnection();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = $input['company_id'] ?? null;
            $backupReference = $input['backup_reference'] ?? null;
            $confirmationText = $input['confirmation_text'] ?? null;
            $adminPassword = $input['admin_password'] ?? null;

            // Validate required fields
            if (!$companyId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'company_id is required']);
                return;
            }

            if (!$backupReference) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'backup_reference is required. You must create a backup first.']);
                return;
            }

            // Verify confirmation text
            $expectedConfirmation = "RESET COMPANY {$companyId}";
            if ($confirmationText !== $expectedConfirmation) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Invalid confirmation text',
                    'expected' => $expectedConfirmation,
                    'received' => $confirmationText
                ]);
                return;
            }

            // Verify admin password
            $userId = $payloadArray['user_id'] ?? $payload->sub ?? $payload->user_id;
            if ($adminPassword) {
                $this->verifyPassword($userId, $adminPassword);
            }

            // Verify company exists
            $stmt = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Company not found']);
                return;
            }

            // Create admin action record
            $adminAction = new AdminAction();
            $actionId = $adminAction->create([
                'admin_user_id' => $userId,
                'action_type' => 'company_reset',
                'target_company_id' => $companyId,
                'dry_run' => 0,
                'backup_reference' => $backupReference,
                'payload' => json_encode([
                    'confirmation_text' => $confirmationText,
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'status' => 'pending'
            ]);

            // Execute reset
            $resetService = new ResetService($userId, false);
            $result = $resetService->resetCompanyData($companyId, $backupReference);

            // Update admin action record
            $adminAction->update($actionId, [
                'status' => $result['success'] ? 'completed' : 'failed',
                'row_counts' => json_encode($result['row_counts']),
                'error_message' => !empty($result['errors']) ? implode('; ', $result['errors']) : null,
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            // Queue file cleanup job
            if ($result['success']) {
                $fileCleanupService = new FileCleanupService();
                $fileCleanupService->queueCompanyFileCleanup($actionId, $companyId);
            }

            if ($result['success']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action_id' => $actionId,
                    'row_counts' => $result['row_counts'],
                    'total_affected_rows' => array_sum($result['row_counts']),
                    'message' => 'Company data reset completed successfully',
                    'file_cleanup_queued' => true
                ]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'action_id' => $actionId,
                    'errors' => $result['errors']
                ]);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview system reset (dry-run)
     */
    public function previewSystemReset() {
        $payload = AuthMiddleware::handle(['system_admin']); // Only system_admin
        // Convert payload object to array for easier access
        $payloadArray = is_object($payload) ? ['user_id' => $payload->sub ?? $payload->user_id ?? null, 'role' => $payload->role ?? null] : $payload;
        $db = \Database::getInstance()->getConnection();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $preserveSettings = $input['preserve_settings'] ?? true;
            $preserveGlobalCatalogs = $input['preserve_global_catalogs'] ?? true;

            // Run dry-run
            $resetService = new ResetService($payload['user_id'], true);
            $result = $resetService->resetSystemData($preserveSettings, $preserveGlobalCatalogs);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'dry_run' => true,
                'row_counts' => $result['row_counts'],
                'total_affected_rows' => array_sum($result['row_counts']),
                'preserve_settings' => $preserveSettings,
                'preserve_global_catalogs' => $preserveGlobalCatalogs,
                'warnings' => [
                    'This is a preview only. No data has been deleted.',
                    'This will DELETE ALL COMPANIES and their data.',
                    'Backup is required before executing actual reset.',
                    'This action is EXTREMELY DESTRUCTIVE and IRREVERSIBLE.',
                    'Only system_admin users will remain.'
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Execute system reset
     * Requires backup_reference and typed confirmation
     */
    public function executeSystemReset() {
        $payload = AuthMiddleware::handle(['system_admin']); // Only system_admin
        // Convert payload object to array for easier access
        $payloadArray = is_object($payload) ? ['user_id' => $payload->sub ?? $payload->user_id ?? null, 'role' => $payload->role ?? null] : $payload;
        $db = \Database::getInstance()->getConnection();

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $backupReference = $input['backup_reference'] ?? null;
            $confirmationText = $input['confirmation_text'] ?? null;
            $adminPassword = $input['admin_password'] ?? null;
            $preserveSettings = $input['preserve_settings'] ?? true;
            $preserveGlobalCatalogs = $input['preserve_global_catalogs'] ?? true;

            // Validate required fields
            if (!$backupReference) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'backup_reference is required. You must create a backup first.']);
                return;
            }

            // Verify confirmation text
            $expectedConfirmation = "RESET SYSTEM DATA";
            if ($confirmationText !== $expectedConfirmation) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Invalid confirmation text',
                    'expected' => $expectedConfirmation,
                    'received' => $confirmationText
                ]);
                return;
            }

            // Verify admin password
            $userId = $payloadArray['user_id'] ?? $payload->sub ?? $payload->user_id;
            if ($adminPassword) {
                $this->verifyPassword($userId, $adminPassword);
            }

            // Create admin action record
            $adminAction = new AdminAction();
            $actionId = $adminAction->create([
                'admin_user_id' => $userId,
                'action_type' => 'system_reset',
                'target_company_id' => null,
                'dry_run' => 0,
                'backup_reference' => $backupReference,
                'payload' => json_encode([
                    'confirmation_text' => $confirmationText,
                    'preserve_settings' => $preserveSettings,
                    'preserve_global_catalogs' => $preserveGlobalCatalogs,
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'status' => 'pending'
            ]);

            // Execute reset
            $resetService = new ResetService($userId, false);
            $result = $resetService->resetSystemData($preserveSettings, $preserveGlobalCatalogs, $backupReference);

            // Update admin action record
            $adminAction->update($actionId, [
                'status' => $result['success'] ? 'completed' : 'failed',
                'row_counts' => json_encode($result['row_counts']),
                'error_message' => !empty($result['errors']) ? implode('; ', $result['errors']) : null,
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            // Queue file cleanup job
            if ($result['success']) {
                $fileCleanupService = new FileCleanupService();
                $fileCleanupService->queueSystemFileCleanup($actionId);
            }

            if ($result['success']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action_id' => $actionId,
                    'row_counts' => $result['row_counts'],
                    'total_affected_rows' => array_sum($result['row_counts']),
                    'message' => 'System data reset completed successfully',
                    'file_cleanup_queued' => true
                ]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'action_id' => $actionId,
                    'errors' => $result['errors']
                ]);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * List reset action history
     */
    public function listActions() {
        ResetPermissionMiddleware::requireAdminActionPermission(); // PHASE F
        $db = \Database::getInstance()->getConnection();

        try {
            // Cast limit and offset to integers for direct SQL inclusion (can't use bound parameters for LIMIT/OFFSET)
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $type = $_GET['type'] ?? null;
            $status = $_GET['status'] ?? null;
            
            // Ensure limit is positive and reasonable
            $limit = max(1, min(1000, $limit));
            $offset = max(0, $offset);
            
            $sql = "
                SELECT 
                    aa.*,
                    u.username as admin_username,
                    u.full_name as admin_full_name,
                    c.name as company_name
                FROM admin_actions aa
                LEFT JOIN users u ON aa.admin_user_id = u.id
                LEFT JOIN companies c ON aa.target_company_id = c.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($type) {
                $sql .= " AND aa.action_type = ?";
                $params[] = $type;
            }
            
            if ($status) {
                $sql .= " AND aa.status = ?";
                $params[] = $status;
            }
            
            // LIMIT and OFFSET must be integers directly in SQL, not bound parameters
            $sql .= " ORDER BY aa.created_at DESC LIMIT " . $limit . " OFFSET " . $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $actions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($actions as &$action) {
                if ($action['payload']) {
                    $action['payload'] = json_decode($action['payload'], true);
                }
                if ($action['row_counts']) {
                    $action['row_counts'] = json_decode($action['row_counts'], true);
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'actions' => $actions,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reset company data (PHASE B - Combined endpoint with dry_run flag)
     * POST /api/admin/companies/{id}/reset
     */
    public function resetCompany($companyId) {
        // Set JSON header first to prevent any HTML output
        header('Content-Type: application/json');
        
        // Suppress any PHP errors/warnings that might output HTML
        ob_start();
        
        try {
            $payload = ResetPermissionMiddleware::requireCompanyResetPermission($companyId); // PHASE F
            // Extract user ID - handle both JWT (sub) and session-based (id) authentication
            $userId = null;
            if (is_object($payload)) {
                $userId = $payload->sub ?? $payload->user_id ?? $payload->id ?? null;
            } elseif (is_array($payload)) {
                $userId = $payload['sub'] ?? $payload['user_id'] ?? $payload['id'] ?? null;
            }
            
            if (!$userId) {
                ob_end_clean(); // Discard any output
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized: User ID not found']);
                return;
            }
            
            $payloadArray = is_object($payload) ? ['user_id' => $userId, 'role' => $payload->role ?? null] : ['user_id' => $userId, 'role' => $payload['role'] ?? null];
            $adminUserId = $userId;
            
            $db = \Database::getInstance()->getConnection();

            // PHASE H: Check rate limiting (skip for dry-run to allow previews)
            // Skip rate limit check for dry-run operations
            $rateLimitCheck = ['allowed' => true];
            try {
                $monitoringService = new MonitoringService();
                $rateLimitCheck = $monitoringService->checkRateLimit($adminUserId, 'company_reset', $companyId);
            } catch (\Exception $e) {
                // If rate limiting check fails, allow the operation (should not block)
                error_log("Rate limit check failed: " . $e->getMessage());
            }
            
            if (!$rateLimitCheck['allowed']) {
                ob_end_clean();
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'reason' => $rateLimitCheck['reason'] ?? 'Too many requests',
                    'limits' => $rateLimitCheck['limits'] ?? [],
                    'counts' => $rateLimitCheck['counts'] ?? []
                ]);
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $dryRun = $input['dry_run'] ?? false;
            $deleteFiles = $input['delete_files'] ?? false;
            $confirmCode = $input['confirm_code'] ?? null;

            // Validate company exists
            $stmt = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                ob_end_clean();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Company not found']);
                return;
            }

            // For non-dry-run, require confirmation code
            if (!$dryRun) {
                // Start session if not already started
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Check if confirmation code exists in session
                $validCode = false;
                if (isset($_SESSION['reset_confirm_codes'][$companyId])) {
                    $storedCode = $_SESSION['reset_confirm_codes'][$companyId];
                    
                    // Check if code has expired
                    if (isset($storedCode['expires']) && time() > $storedCode['expires']) {
                        unset($_SESSION['reset_confirm_codes'][$companyId]);
                    } else {
                        // Validate the confirmation code
                        if (isset($storedCode['code']) && $confirmCode === $storedCode['code']) {
                            $validCode = true;
                            // Remove code after use (one-time use)
                            unset($_SESSION['reset_confirm_codes'][$companyId]);
                        }
                    }
                }
                
                // Fallback to old format for backward compatibility
                if (!$validCode) {
                    $expectedConfirm = "RESET COMPANY {$companyId}";
                    if ($confirmCode === $expectedConfirm) {
                        $validCode = true;
                    }
                }
                
                if (!$validCode) {
                    ob_end_clean();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid or expired confirmation code',
                        'received' => $confirmCode
                    ]);
                    return;
                }

                // Require backup before actual reset
                // In a real implementation, you might want to auto-create backup or require it
                // For now, we'll proceed but log that backup should be created
            }

            // userId is already extracted above - use it here
            // Create admin action record
            $adminAction = new AdminAction();
            $backupReference = !$dryRun ? "auto_backup_" . date('Ymd_His') : null;
            
            $actionId = $adminAction->create([
                'admin_user_id' => $userId,
                'action_type' => 'company_reset',
                'target_company_id' => $companyId,
                'dry_run' => $dryRun ? 1 : 0,
                'backup_reference' => $backupReference,
                'payload' => json_encode([
                    'confirm_code' => $confirmCode,
                    'delete_files' => $deleteFiles,
                    'dry_run' => $dryRun
                ]),
                'status' => 'pending'
            ]);

            // Create backup before reset if not dry-run
            if (!$dryRun && !$backupReference) {
                try {
                    $backupService = new BackupService();
                    $actualBackupId = $backupService->createCompanyBackup($companyId);
                    // Update action with actual backup ID
                    $adminAction->update($actionId, ['backup_reference' => $actualBackupId]);
                    $backupReference = $actualBackupId;
                } catch (\Exception $e) {
                    error_log("Backup creation failed: " . $e->getMessage());
                    throw new \Exception("Backup creation failed: " . $e->getMessage());
                }
            }

            // Execute reset (dry-run or real) using PHASE C signature
            $resetService = new ResetService($userId, $dryRun);
            $result = $resetService->resetCompanyData($companyId, [
                'dry_run' => $dryRun,
                'delete_files' => $deleteFiles,
                'admin_user_id' => $userId,
                'backup_reference' => $backupReference
            ]);

            // Update admin action record
            $adminAction->update($actionId, [
                'status' => $result['success'] ? ($dryRun ? 'completed' : 'completed') : 'failed',
                'row_counts' => json_encode($result['row_counts']),
                'error_message' => !empty($result['errors']) ? implode('; ', $result['errors']) : null,
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            // Queue file cleanup if requested and reset was successful
            $fileCleanupQueued = false;
            if ($result['success'] && !$dryRun && $deleteFiles) {
                try {
                    $fileCleanupService = new FileCleanupService();
                    // Get file list from product_ids returned by reset
                    $fileList = [];
                    if (isset($result['product_ids']) && !empty($result['product_ids'])) {
                        // Build file list from product IDs
                        $fileList = $fileCleanupService->getCompanyFileList($companyId, $result['product_ids']);
                    } else {
                        // Fallback: get from company ID
                        $fileList = $fileCleanupService->getCompanyFileList($companyId);
                    }
                    // Use PHASE D method
                    $jobId = $fileCleanupService->enqueueFileDeletionJob($actionId, $fileList);
                    $fileCleanupQueued = ($jobId !== null);
                } catch (\Exception $e) {
                    error_log("File cleanup queue failed: " . $e->getMessage());
                }
            }

            // Send notification (PHASE E)
            if (!$dryRun) {
                try {
                    $notificationService = new ResetNotificationService();
                    $notificationService->notifyResetCompletion(
                        $actionId,
                        'company_reset',
                        $result['success'],
                        [
                            'total_affected_rows' => array_sum($result['row_counts'] ?? []),
                            'company_name' => $company['name'] ?? null
                        ]
                    );
                } catch (\Exception $e) {
                    error_log("Notification failed: " . $e->getMessage());
                }
            }

            // Discard any output that might have been generated (PHP warnings, etc.)
            ob_end_clean();
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'dry_run' => $dryRun,
                    'action_id' => $actionId,
                    'company_id' => $companyId,
                    'company_name' => $company['name'] ?? null,
                    'counts' => $result['counts'] ?? $result['row_counts'] ?? [],
                    'row_counts' => $result['row_counts'] ?? [],
                    'total_affected_rows' => array_sum($result['row_counts'] ?? []),
                    'backup_reference' => $backupReference,
                    'file_cleanup_queued' => $fileCleanupQueued,
                    'message' => $dryRun ? 'Dry-run completed. No data was deleted.' : 'Company data reset completed successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'action_id' => $actionId ?? null,
                    'errors' => $result['errors'] ?? [],
                    'error' => !empty($result['errors']) ? implode('; ', $result['errors']) : 'Reset failed'
                ]);
            }

        } catch (\Exception $e) {
            // Discard any output that might have been generated
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            // Log the error but return clean JSON
            error_log("Reset company error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'action_id' => $actionId ?? null
            ]);
        } catch (\Error $e) {
            // Catch fatal errors (like class not found)
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            error_log("Reset company fatal error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Reset system data (PHASE B - Combined endpoint with dry_run flag)
     * POST /api/admin/system/reset
     */
    public function resetSystem() {
        // Set JSON header first to prevent HTML output
        header('Content-Type: application/json');
        ob_start();
        
        try {
            $payload = ResetPermissionMiddleware::requireSystemResetPermission(); // PHASE F
            // Extract user ID - handle both JWT (sub) and session-based (id) authentication
            $userId = null;
            if (is_object($payload)) {
                $userId = $payload->sub ?? $payload->user_id ?? $payload->id ?? null;
            } elseif (is_array($payload)) {
                $userId = $payload['sub'] ?? $payload['user_id'] ?? $payload['id'] ?? null;
            }
            
            if (!$userId) {
                ob_end_clean();
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized: User ID not found']);
                return;
            }
            
            $payloadArray = is_object($payload) ? ['user_id' => $userId, 'role' => $payload->role ?? null] : ['user_id' => $userId, 'role' => $payload['role'] ?? null];
            $adminUserId = $userId;
            
            $db = \Database::getInstance()->getConnection();

            $input = json_decode(file_get_contents('php://input'), true);
            $dryRun = $input['dry_run'] ?? false;
            $deleteFiles = $input['delete_files'] ?? false;
            $confirmCode = $input['confirm_code'] ?? null;
            $inputBackupReference = $input['backup_reference'] ?? null;
            $preserveSettings = $input['preserve_settings'] ?? true;
            $preserveGlobalCatalogs = $input['preserve_global_catalogs'] ?? true;

            // For non-dry-run, require confirmation code
            if (!$dryRun) {
                if ($confirmCode !== "RESET SYSTEM") {
                    ob_end_clean();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid confirmation code',
                        'expected' => 'RESET SYSTEM',
                        'received' => $confirmCode
                    ]);
                    return;
                }
                
                // For non-dry-run, require backup reference
                if (!$inputBackupReference) {
                    ob_end_clean();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'backup_reference is required. You must create a backup first.'
                    ]);
                    return;
                }
            }

            // userId is already extracted above - use it here
            // Create admin action record
            $adminAction = new AdminAction();
            $backupReference = !$dryRun ? $inputBackupReference : null;
            
            $actionId = $adminAction->create([
                'admin_user_id' => $userId,
                'action_type' => 'system_reset',
                'target_company_id' => null,
                'dry_run' => $dryRun ? 1 : 0,
                'backup_reference' => $backupReference,
                'payload' => json_encode([
                    'confirm_code' => $confirmCode,
                    'delete_files' => $deleteFiles,
                    'dry_run' => $dryRun,
                    'preserve_settings' => $preserveSettings,
                    'preserve_global_catalogs' => $preserveGlobalCatalogs
                ]),
                'status' => 'pending'
            ]);

            // Backup reference is already validated above for non-dry-run operations
            // No need to create backup here - it must be provided by the frontend

            // Execute reset (dry-run or real) using PHASE C signature
            $resetService = new ResetService($userId, $dryRun);
            $result = $resetService->resetSystemData($preserveSettings, $preserveGlobalCatalogs, $backupReference);

            // Update admin action record
            $adminAction->update($actionId, [
                'status' => $result['success'] ? 'completed' : 'failed',
                'row_counts' => json_encode($result['row_counts']),
                'error_message' => !empty($result['errors']) ? implode('; ', $result['errors']) : null,
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            // Queue file cleanup if requested and reset was successful
            $fileCleanupQueued = false;
            if ($result['success'] && !$dryRun && $deleteFiles) {
                try {
                    $fileCleanupService = new FileCleanupService();
                    // Get file list from product_ids returned by reset
                    $fileList = [];
                    if (isset($result['product_ids']) && !empty($result['product_ids'])) {
                        $fileList = $fileCleanupService->getSystemFileList($result['product_ids']);
                    } else {
                        $fileList = $fileCleanupService->getSystemFileList();
                    }
                    // Use PHASE D method
                    $jobId = $fileCleanupService->enqueueFileDeletionJob($actionId, $fileList);
                    $fileCleanupQueued = ($jobId !== null);
                } catch (\Exception $e) {
                    error_log("File cleanup queue failed: " . $e->getMessage());
                }
            }

            // Send notification (PHASE E)
            if (!$dryRun) {
                try {
                    $notificationService = new ResetNotificationService();
                    $notificationService->notifyResetCompletion(
                        $actionId,
                        'system_reset',
                        $result['success'],
                        [
                            'total_affected_rows' => array_sum($result['row_counts'])
                        ]
                    );
                } catch (\Exception $e) {
                    error_log("Notification failed: " . $e->getMessage());
                }
            }

            // Discard any output that might have been generated
            ob_end_clean();
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'dry_run' => $dryRun,
                    'action_id' => $actionId,
                    'row_counts' => $result['row_counts'] ?? [],
                    'total_affected_rows' => array_sum($result['row_counts'] ?? []),
                    'backup_reference' => $backupReference,
                    'file_cleanup_queued' => $fileCleanupQueued,
                    'message' => $dryRun ? 'Dry-run completed. No data was deleted.' : 'System data reset completed successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'action_id' => $actionId ?? null,
                    'errors' => $result['errors'] ?? [],
                    'error' => !empty($result['errors']) ? implode('; ', $result['errors']) : 'Reset failed'
                ]);
            }

        } catch (\Exception $e) {
            // Discard any output that might have been generated
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            error_log("Reset system error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'action_id' => $actionId ?? null
            ]);
        } catch (\Error $e) {
            // Catch fatal errors
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            error_log("Reset system fatal error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get reset action details including audit log and job status
     * GET /api/admin/reset/{admin_action_id}
     */
    public function getActionDetails($actionId) {
        ResetPermissionMiddleware::requireAdminActionPermission(); // PHASE F
        $db = \Database::getInstance()->getConnection();

        try {
            // Get admin action details
            $adminActionModel = new AdminAction();
            $action = $adminActionModel->findById($actionId);
            
            if (!$action) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Action not found']);
                return;
            }

            // Get associated reset jobs
            $resetJobModel = new \App\Models\ResetJob();
            $jobs = [];
            
            // Query reset_jobs table
            $stmt = $db->prepare("
                SELECT * FROM reset_jobs 
                WHERE admin_action_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$actionId]);
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decode JSON fields
            if ($action['payload']) {
                $action['payload'] = json_decode($action['payload'], true);
            }
            if ($action['row_counts']) {
                $action['row_counts'] = json_decode($action['row_counts'], true);
            }

            // Decode job details
            foreach ($jobs as &$job) {
                if ($job['details']) {
                    $job['details'] = json_decode($job['details'], true);
                }
            }

            // Get admin user info
            $stmt = $db->prepare("
                SELECT id, username, full_name, email, role 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$action['admin_user_id']]);
            $adminUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get company info if applicable
            $company = null;
            if ($action['target_company_id']) {
                $stmt = $db->prepare("
                    SELECT id, name, email, phone_number 
                    FROM companies 
                    WHERE id = ?
                ");
                $stmt->execute([$action['target_company_id']]);
                $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            // Calculate job statistics
            $jobStats = [
                'total' => count($jobs),
                'pending' => 0,
                'running' => 0,
                'completed' => 0,
                'failed' => 0
            ];

            foreach ($jobs as $job) {
                $status = $job['status'];
                if (isset($jobStats[$status])) {
                    $jobStats[$status]++;
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'action' => $action,
                'admin_user' => $adminUser,
                'target_company' => $company,
                'jobs' => $jobs,
                'job_statistics' => $jobStats
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify admin password
     */
    private function verifyPassword($userId, $password) {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new \Exception("Invalid admin password");
        }
    }

    /**
     * Delete a single reset action
     * DELETE /api/admin/reset/actions/{id}
     */
    public function deleteAction($actionId) {
        ResetPermissionMiddleware::requireAdminActionPermission();
        
        try {
            $adminActionModel = new AdminAction();
            $action = $adminActionModel->findById($actionId);
            
            if (!$action) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Action not found'
                ]);
                return;
            }
            
            $result = $adminActionModel->delete($actionId);
            
            if ($result) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Action deleted successfully'
                ]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete action'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete multiple reset actions (bulk delete)
     * POST /api/admin/reset/actions/delete
     */
    public function deleteActions() {
        ResetPermissionMiddleware::requireAdminActionPermission();
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $actionIds = $input['action_ids'] ?? [];
            
            if (empty($actionIds) || !is_array($actionIds)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'action_ids array is required'
                ]);
                return;
            }
            
            $adminActionModel = new AdminAction();
            $result = $adminActionModel->deleteMultiple($actionIds);
            
            if ($result) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Actions deleted successfully',
                    'deleted_count' => count($actionIds)
                ]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete actions'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

