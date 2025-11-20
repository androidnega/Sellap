<?php

namespace App\Controllers;

use App\Models\RestorePoint;
use App\Models\Backup;
use App\Services\RestorePointService;
use App\Middleware\WebAuthMiddleware;

require_once __DIR__ . '/../../config/database.php';

class RestorePointController {
    private $restorePointService;
    private $restorePointModel;
    private $backupModel;

    public function __construct() {
        $this->restorePointService = new RestorePointService();
        $this->restorePointModel = new RestorePoint();
        $this->backupModel = new Backup();
    }

    /**
     * Show restore points management page
     */
    public function index($companyId) {
        WebAuthMiddleware::handle(['system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        // Get company info
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            die("Company not found");
        }

        // Get restore points
        $restorePoints = $this->restorePointModel->getForCompany($companyId);
        
        // Get available backups
        $backups = $this->backupModel->getForCompany($companyId, 20);
        
        // Get statistics
        $stats = $this->restorePointModel->getStats($companyId);

        $pageTitle = "Restore Points - " . htmlspecialchars($company['name']);
        $currentPage = 'companies';
        
        $content = $this->getViewContent($companyId, $company, $restorePoints, $backups, $stats);
        
        // Include layout
        include __DIR__ . '/../Views/layouts/dashboard.php';
    }

    /**
     * Get view content
     */
    private function getViewContent($companyId, $company, $restorePoints, $backups, $stats) {
        ob_start();
        include __DIR__ . '/../Views/restore_points.php';
        return ob_get_clean();
    }

    /**
     * API: Create restore point
     */
    public function create() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'system_admin') {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $companyId = $input['company_id'] ?? null;
        $backupId = $input['backup_id'] ?? null;
        $name = $input['name'] ?? null;
        $description = $input['description'] ?? null;

        if (!$companyId || !$backupId || !$name) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        $result = $this->restorePointService->createRestorePoint(
            $companyId,
            $backupId,
            $name,
            $description,
            $user['id']
        );

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'restore_point_id' => $result['restore_point_id']
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to create restore point'
            ]);
        }
    }

    /**
     * API: Restore from restore point
     */
    public function restore() {
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
                ob_end_clean();
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $restorePointId = $input['restore_point_id'] ?? null;
            $companyId = $input['company_id'] ?? null;
            $restoreType = $input['restore_type'] ?? 'overwrite'; // 'overwrite' or 'merge'

            if (!$restorePointId || !$companyId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }

            if (!in_array($restoreType, ['overwrite', 'merge'])) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid restore type']);
                return;
            }

            $result = $this->restorePointService->restoreFromPoint(
                $restorePointId,
                $companyId,
                $restoreType,
                $user['id']
            );

            ob_end_clean();
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'records_restored' => $result['records_restored'] ?? 0,
                    'tables_restored' => $result['tables_restored'] ?? 0
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to restore'
                ]);
            }
        } catch (\Exception $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            error_log("RestorePointController::restore error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]);
        } catch (\Error $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            error_log("RestorePointController::restore fatal error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'error' => 'Fatal error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API: Get restore points for company
     */
    public function list($companyId) {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'system_admin') {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $restorePoints = $this->restorePointModel->getForCompany($companyId);
        
        echo json_encode([
            'success' => true,
            'restore_points' => $restorePoints
        ]);
    }

    /**
     * API: Delete restore point
     */
    public function delete() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'system_admin') {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $restorePointId = $input['restore_point_id'] ?? null;
        $companyId = $input['company_id'] ?? null;

        if (!$restorePointId || !$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        $result = $this->restorePointModel->delete($restorePointId, $companyId);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete restore point']);
        }
    }
}

