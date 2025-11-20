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
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
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
}

