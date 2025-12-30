<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Services\BackupService;
use App\Services\AuditService;
use App\Models\Backup;
use PDO;

class BackupController {
    private $backupService;

    public function __construct() {
        $this->backupService = new BackupService();
    }

    /**
     * Show backup page
     * GET /dashboard/backup
     */
    public function index() {
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }

        // Get companies list for system_admin
        $companies = [];
        if ($user['role'] === 'system_admin') {
            $companyModel = new \App\Models\Company();
            try {
                $stmt = \Database::getInstance()->getConnection()->query("SELECT id, name FROM companies ORDER BY name ASC");
                $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log("Error loading companies: " . $e->getMessage());
            }
        }

        $title = 'Data Backup & Restore';
        $GLOBALS['pageTitle'] = $title;
        $GLOBALS['companies'] = $companies;
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/backup_manager.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        // Pass user data to layout for sidebar role detection
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Export backup
     * POST /dashboard/backup/export
     */
    public function export() {
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            $userRole = $user['role'] ?? 'manager';
            $companyId = $user['company_id'] ?? null;
            
            // For system_admin, allow company_id to be passed in POST data
            // or create a system backup if no company_id is provided
            if (!$companyId && $userRole === 'system_admin') {
                $companyId = $_POST['company_id'] ?? null;
                
                // If still no company_id, create a system backup instead
                if (!$companyId) {
                    $format = $_POST['format'] ?? 'json';
                    $userId = $user['id'] ?? null;
                    
                    $backupId = $this->backupService->createSystemBackup($userId, false);
                    
                    // Get backup details for response
                    $backupModel = new Backup();
                    $backup = $backupModel->find($backupId);
                    
                    if ($backup) {
                        $result = [
                            'success' => true,
                            'filename' => $backup['file_name'],
                            'size' => $backup['file_size'] ?? 0,
                            'record_count' => $backup['record_count'] ?? 0,
                            'backup_id' => $backupId,
                            'backup_type' => 'system'
                        ];
                        
                        // Log audit event for system backup (company_id is null for system backups)
                        try {
                            AuditService::log(
                                null, // System backup has no company_id
                                $userId,
                                'backup.exported',
                                'backup',
                                $backupId,
                                [
                                    'filename' => $backup['file_name'],
                                    'format' => $format,
                                    'size' => $backup['file_size'] ?? 0,
                                    'record_count' => $backup['record_count'] ?? 0,
                                    'backup_type' => 'system'
                                ]
                            );
                        } catch (\Exception $auditError) {
                            // Log audit error but don't fail the backup
                            error_log("Failed to log audit for system backup: " . $auditError->getMessage());
                        }
                    } else {
                        $result = [
                            'success' => true,
                            'backup_id' => $backupId,
                            'backup_type' => 'system',
                            'message' => 'System backup created successfully'
                        ];
                    }
                    
                    ob_end_clean();
                    echo json_encode($result);
                    exit;
                }
            }
            
            // For non-system_admin users, company_id is required
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            $format = $_POST['format'] ?? 'json';
            $userId = $user['id'] ?? null;

            $result = $this->backupService->exportCompanyData($companyId, $format, $userId);

            if ($result['success']) {
                // Log audit event
                AuditService::log(
                    $companyId,
                    $userId,
                    'backup.exported',
                    'backup',
                    $result['backup_id'] ?? null,
                    [
                        'filename' => $result['filename'],
                        'format' => $format,
                        'size' => $result['size'],
                        'record_count' => $result['record_count']
                    ]
                );
            }

            ob_end_clean();
            echo json_encode($result);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Export backup error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get all backups (for system admin) or company backups
     * GET /api/backups or GET /api/company/{id}/backups
     */
    public function getAllBackups() {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            WebAuthMiddleware::handle(['system_admin']);
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user || $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            
            $companyId = $_GET['company_id'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $filters = [
                'backup_type' => $_GET['backup_type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            // Remove empty filters
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $result = $this->backupService->getAllBackups($companyId, $page, $limit, $filters);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'backups' => $result['backups'],
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total_pages' => $result['total_pages']
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Get all backups error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get backups list
     * GET /api/company/{id}/backups
     */
    public function getBackups($companyId) {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }

            // Check if user can access this company's backups
            $userCompanyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            if ($userRole !== 'system_admin' && $userCompanyId != $companyId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $filters = [
                'backup_type' => $_GET['backup_type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            // Remove empty filters
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $result = $this->backupService->getCompanyBackups($companyId, $page, $limit, $filters);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'backups' => $result['backups'],
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total_pages' => $result['total_pages']
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Get backups error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Download backup file
     * GET /dashboard/backup/download/{id}
     */
    public function download($backupId) {
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                die('Authentication required');
            }

            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            // Load backup record
            $backupModel = new Backup();
            $backup = $backupModel->find($backupId, $userRole === 'system_admin' ? null : $companyId);

            if (!$backup) {
                http_response_code(404);
                die('Backup not found');
            }

            // Check access
            if ($userRole !== 'system_admin' && $backup['company_id'] != $companyId) {
                http_response_code(403);
                die('Access denied');
            }

            $filePath = $backup['file_path'];
            
            if (!file_exists($filePath)) {
                http_response_code(404);
                die('Backup file not found');
            }

            // Set headers for download
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $backup['file_name'] . '"');
            header('Content-Length: ' . filesize($filePath));
            
            // Clean output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            readfile($filePath);
            exit;
        } catch (\Exception $e) {
            error_log("Download backup error: " . $e->getMessage());
            http_response_code(500);
            die('Error downloading backup');
        }
    }
    
    /**
     * Delete backup
     * DELETE /api/backups/{id}
     */
    public function delete($backupId) {
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            // Check access
            $backupModel = new Backup();
            $backup = $backupModel->find($backupId, $userRole === 'system_admin' ? null : $companyId);
            
            if (!$backup) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Backup not found']);
                exit;
            }
            
            if ($userRole !== 'system_admin' && $backup['company_id'] != $companyId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            
            $result = $this->backupService->deleteBackup($backupId, $userRole === 'system_admin' ? null : $companyId);
            
            ob_end_clean();
            echo json_encode($result);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Delete backup error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Delete multiple backups
     * POST /api/backups/bulk-delete
     */
    public function bulkDelete() {
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $backupIds = $input['backup_ids'] ?? [];
            
            if (empty($backupIds) || !is_array($backupIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid backup IDs']);
                exit;
            }
            
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? 'manager';
            
            // Verify access to all backups
            $backupModel = new Backup();
            foreach ($backupIds as $backupId) {
                $backup = $backupModel->find($backupId, $userRole === 'system_admin' ? null : $companyId);
                if (!$backup) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => "Backup #{$backupId} not found"]);
                    exit;
                }
                if ($userRole !== 'system_admin' && $backup['company_id'] != $companyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => "Access denied to backup #{$backupId}"]);
                    exit;
                }
            }
            
            $result = $this->backupService->deleteBackups($backupIds, $userRole === 'system_admin' ? null : $companyId);
            
            ob_end_clean();
            echo json_encode($result);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Bulk delete backups error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get backup settings for a company
     * GET /api/company/{id}/backup-settings
     */
    public function getBackupSettings($companyId) {
        WebAuthMiddleware::handle(['system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user || $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT * FROM company_backup_settings 
                WHERE company_id = ?
            ");
            $stmt->execute([$companyId]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            // If no settings exist, return defaults
            if (!$settings) {
                $settings = [
                    'company_id' => $companyId,
                    'auto_backup_enabled' => 0,
                    'backup_time' => '02:00:00',
                    'backup_destination' => 'email'
                ];
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Get backup settings error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update backup settings for a company
     * POST /api/company/{id}/backup-settings
     */
    public function updateBackupSettings($companyId) {
        WebAuthMiddleware::handle(['system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user || $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $autoBackupEnabled = isset($input['auto_backup_enabled']) ? (int)$input['auto_backup_enabled'] : 0;
            $backupTime = $input['backup_time'] ?? '02:00:00';
            $backupDestination = $input['backup_destination'] ?? 'email';

            // Validate backup destination
            if (!in_array($backupDestination, ['email', 'cloudinary', 'both'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid backup destination']);
                exit;
            }

            // Validate time format
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $backupTime)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid time format']);
                exit;
            }

            $db = \Database::getInstance()->getConnection();
            
            // Check if settings exist
            $checkStmt = $db->prepare("SELECT id FROM company_backup_settings WHERE company_id = ?");
            $checkStmt->execute([$companyId]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                // Update existing
                $stmt = $db->prepare("
                    UPDATE company_backup_settings 
                    SET auto_backup_enabled = ?, backup_time = ?, backup_destination = ?
                    WHERE company_id = ?
                ");
                $stmt->execute([$autoBackupEnabled, $backupTime, $backupDestination, $companyId]);
            } else {
                // Insert new
                $stmt = $db->prepare("
                    INSERT INTO company_backup_settings 
                    (company_id, auto_backup_enabled, backup_time, backup_destination)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$companyId, $autoBackupEnabled, $backupTime, $backupDestination]);
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Backup settings updated successfully'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Update backup settings error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get all company backup settings
     * GET /api/backup-settings
     */
    public function getAllBackupSettings() {
        WebAuthMiddleware::handle(['system_admin']);
        
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $user = $_SESSION['user'] ?? null;
            if (!$user || $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT cbs.*, c.name as company_name
                FROM company_backup_settings cbs
                LEFT JOIN companies c ON cbs.company_id = c.id
                ORDER BY c.name ASC
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Get all backup settings error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

