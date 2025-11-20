<?php

namespace App\Services;

use App\Services\BackupService;
use App\Models\Backup;

require_once __DIR__ . '/../../config/database.php';

class BackupScheduler {
    private $backupService;
    private $db;

    public function __construct() {
        $this->backupService = new BackupService();
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Run scheduled backups for all companies
     * This should be called daily via cron job
     */
    public function runScheduledBackups() {
        $results = [
            'companies' => [],
            'system' => null,
            'errors' => []
        ];

        try {
            // Get all active companies
            $stmt = $this->db->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY id");
            $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Backup each company
            foreach ($companies as $company) {
                try {
                    $backupId = $this->backupService->createCompanyBackup(
                        $company['id'],
                        null, // System user
                        true  // Automatic backup
                    );

                    // Update backup record to mark as automatic
                    $this->markAsAutomatic($backupId);

                    $results['companies'][] = [
                        'company_id' => $company['id'],
                        'company_name' => $company['name'],
                        'backup_id' => $backupId,
                        'success' => true
                    ];

                    error_log("Scheduled backup created for company {$company['id']} ({$company['name']}): Backup ID {$backupId}");
                } catch (\Exception $e) {
                    $error = "Failed to backup company {$company['id']} ({$company['name']}): " . $e->getMessage();
                    error_log($error);
                    $results['errors'][] = $error;
                    $results['companies'][] = [
                        'company_id' => $company['id'],
                        'company_name' => $company['name'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Create system-wide backup
            try {
                $systemBackupId = $this->backupService->createSystemBackup(null, true);
                $this->markAsAutomatic($systemBackupId);
                $results['system'] = [
                    'backup_id' => $systemBackupId,
                    'success' => true
                ];
                error_log("Scheduled system backup created: Backup ID {$systemBackupId}");
            } catch (\Exception $e) {
                $error = "Failed to create system backup: " . $e->getMessage();
                error_log($error);
                $results['errors'][] = $error;
                $results['system'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Cleanup old automatic backups (keep last 30 days)
            $this->cleanupOldBackups(30);

            return $results;
        } catch (\Exception $e) {
            error_log("BackupScheduler error: " . $e->getMessage());
            $results['errors'][] = "Scheduler error: " . $e->getMessage();
            return $results;
        }
    }

    /**
     * Mark backup as automatic
     */
    private function markAsAutomatic($backupId) {
        try {
            $backupModel = new Backup();
            // Update backup record to mark as automatic
            // We'll add a 'is_automatic' column or use the description field
            $stmt = $this->db->prepare("
                UPDATE backups 
                SET description = CONCAT(COALESCE(description, ''), ' [AUTOMATIC DAILY BACKUP]'),
                    backup_type = 'automatic'
                WHERE id = ?
            ");
            $stmt->execute([$backupId]);
        } catch (\Exception $e) {
            // If column doesn't exist, try without backup_type
            try {
                $stmt = $this->db->prepare("
                    UPDATE backups 
                    SET description = CONCAT(COALESCE(description, ''), ' [AUTOMATIC DAILY BACKUP]')
                    WHERE id = ?
                ");
                $stmt->execute([$backupId]);
            } catch (\Exception $e2) {
                error_log("Error marking backup as automatic: " . $e2->getMessage());
            }
        }
    }

    /**
     * Cleanup old automatic backups
     * Keeps the most recent N days of automatic backups
     */
    public function cleanupOldBackups($keepDays = 30) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$keepDays} days"));
            
            // Find old automatic backups
            $stmt = $this->db->prepare("
                SELECT id, file_path 
                FROM backups 
                WHERE (description LIKE '%[AUTOMATIC DAILY BACKUP]%' OR backup_type = 'automatic')
                AND created_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            $oldBackups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $deletedCount = 0;
            foreach ($oldBackups as $backup) {
                try {
                    // Delete file if it exists
                    if (!empty($backup['file_path']) && file_exists($backup['file_path'])) {
                        @unlink($backup['file_path']);
                    }

                    // Delete backup record
                    $deleteStmt = $this->db->prepare("DELETE FROM backups WHERE id = ?");
                    $deleteStmt->execute([$backup['id']]);
                    $deletedCount++;
                } catch (\Exception $e) {
                    error_log("Error deleting old backup {$backup['id']}: " . $e->getMessage());
                }
            }

            error_log("Cleaned up {$deletedCount} old automatic backups (older than {$keepDays} days)");
            return $deletedCount;
        } catch (\Exception $e) {
            error_log("Error cleaning up old backups: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        try {
            $stats = [];

            // Total automatic backups
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM backups 
                WHERE description LIKE '%[AUTOMATIC DAILY BACKUP]%' OR backup_type = 'automatic'
            ");
            $stats['total_automatic'] = (int)$stmt->fetchColumn();

            // Automatic backups by company
            $stmt = $this->db->query("
                SELECT company_id, COUNT(*) as count 
                FROM backups 
                WHERE (description LIKE '%[AUTOMATIC DAILY BACKUP]%' OR backup_type = 'automatic')
                AND company_id IS NOT NULL
                GROUP BY company_id
            ");
            $stats['by_company'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // System backups
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM backups 
                WHERE (description LIKE '%[AUTOMATIC DAILY BACKUP]%' OR backup_type = 'automatic')
                AND company_id IS NULL
            ");
            $stats['system_backups'] = (int)$stmt->fetchColumn();

            // Total size of automatic backups
            $stmt = $this->db->query("
                SELECT SUM(file_size) as total_size 
                FROM backups 
                WHERE (description LIKE '%[AUTOMATIC DAILY BACKUP]%' OR backup_type = 'automatic')
            ");
            $stats['total_size'] = (int)($stmt->fetchColumn() ?? 0);

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting backup stats: " . $e->getMessage());
            return [];
        }
    }
}

