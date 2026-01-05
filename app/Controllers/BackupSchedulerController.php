<?php

namespace App\Controllers;

use App\Services\BackupScheduler;
use App\Middleware\WebAuthMiddleware;

class BackupSchedulerController {
    private $scheduler;

    public function __construct() {
        $this->scheduler = new BackupScheduler();
    }

    /**
     * Run scheduled backups manually (for testing/admin)
     */
    public function run() {
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

        try {
            $results = $this->scheduler->runScheduledBackups();
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'message' => 'Scheduled backups completed'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("BackupSchedulerController::run error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get backup statistics
     */
    public function stats() {
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

        try {
            $stats = $this->scheduler->getBackupStats();
            $lastRunTime = $this->scheduler->getLastBackupRunTime();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'last_run_time' => $lastRunTime
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("BackupSchedulerController::stats error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Run scheduled backups via GET (for cron jobs/webhooks)
     * Requires a secret token for security
     */
    public function runCron() {
        header('Content-Type: application/json');
        
        // Check for secret token (can be set in environment or system_settings)
        $secretToken = getenv('BACKUP_CRON_SECRET') ?: 'sellapp_backup_cron_2024';
        
        // Get token from query parameter or header
        $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_BACKUP_TOKEN'] ?? '';
        
        if (empty($providedToken) || $providedToken !== $secretToken) {
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid or missing token',
                'message' => 'Please provide a valid token parameter: ?token=sellapp_backup_cron_2024'
            ]);
            return;
        }

        try {
            $results = $this->scheduler->runScheduledBackups();
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'message' => 'Scheduled backups completed',
                'started_at' => $results['started_at'] ?? null,
                'completed_at' => $results['completed_at'] ?? null,
                'companies_backed_up' => count(array_filter($results['companies'] ?? [], function($c) { return !empty($c['success']); })),
                'system_backup_created' => !empty($results['system']['success'])
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("BackupSchedulerController::runCron error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test cron endpoint (without running backups)
     * Useful for verifying the cron job is configured correctly
     */
    public function testCron() {
        header('Content-Type: application/json');
        
        // Check for secret token
        $secretToken = getenv('BACKUP_CRON_SECRET') ?: 'sellapp_backup_cron_2024';
        $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_BACKUP_TOKEN'] ?? '';
        
        if (empty($providedToken) || $providedToken !== $secretToken) {
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid or missing token',
                'message' => 'Please provide a valid token parameter: ?token=sellapp_backup_cron_2024'
            ]);
            return;
        }

        // Get enabled companies count
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM companies c
                LEFT JOIN company_backup_settings cbs ON c.id = cbs.company_id
                WHERE c.status = 'active' 
                AND COALESCE(cbs.auto_backup_enabled, 0) = 1
            ");
            $enabledCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'message' => 'Cron endpoint is accessible and configured correctly',
                'enabled_companies' => (int)$enabledCount,
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'note' => 'This is a test endpoint. Use /run-cron to actually run backups.'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

