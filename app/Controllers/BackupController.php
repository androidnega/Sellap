<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Services\BackupService;
use App\Services\AuditService;
use App\Models\Backup;

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

        $title = 'Data Backup & Restore';
        $GLOBALS['pageTitle'] = $title;
        
        require __DIR__ . '/../Views/backup_manager.php';
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

            $companyId = $user['company_id'] ?? null;
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
     * Import backup
     * POST /dashboard/backup/import
     */
    public function import() {
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
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company association required']);
                exit;
            }

            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
                exit;
            }

            $uploadedFile = $_FILES['backup_file']['tmp_name'];
            $userId = $user['id'] ?? null;

            // Validate file before import
            $validationResult = $this->backupService->verifyBackupIntegrityFromFile($uploadedFile);
            if (!$validationResult['valid']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid backup file: ' . ($validationResult['error'] ?? 'Unknown error')
                ]);
                exit;
            }

            // Import data
            $result = $this->backupService->importCompanyData($companyId, $uploadedFile, false);

            if ($result['success']) {
                // Log audit event
                AuditService::log(
                    $companyId,
                    $userId,
                    'backup.imported',
                    'backup',
                    null,
                    [
                        'record_count' => $result['record_count'] ?? 0,
                        'tables_imported' => $result['tables_imported'] ?? 0
                    ]
                );
            }

            ob_end_clean();
            echo json_encode($result);
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("Import backup error: " . $e->getMessage());
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

            $backups = $this->backupService->getCompanyBackups($companyId);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'backups' => $backups,
                'count' => count($backups)
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
}

