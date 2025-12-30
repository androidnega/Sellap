<?php

namespace App\Services;

use App\Services\BackupService;
use App\Services\EmailService;
use App\Services\CloudinaryService;
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
            // Get all active companies with auto-backup enabled
            // Only backup companies that have auto_backup_enabled = 1
            $stmt = $this->db->query("
                SELECT c.id, c.name, 
                       COALESCE(cbs.auto_backup_enabled, 0) as auto_backup_enabled,
                       COALESCE(cbs.backup_time, '02:00:00') as backup_time,
                       COALESCE(cbs.backup_destination, 'email') as backup_destination
                FROM companies c
                LEFT JOIN company_backup_settings cbs ON c.id = cbs.company_id
                WHERE c.status = 'active' 
                AND COALESCE(cbs.auto_backup_enabled, 0) = 1
                ORDER BY c.id
            ");
            $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Backup each company that has auto-backup enabled
            foreach ($companies as $company) {
                try {
                    $backupId = $this->backupService->createCompanyBackup(
                        $company['id'],
                        null, // System user
                        true, // Automatic backup
                        $company['backup_destination'] // Pass destination preference
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
            
            // Send backups based on company settings (email, cloudinary, or both)
            $this->processBackupDestinations($results);
            
            // Sync backups from Cloudinary
            $this->syncBackupsFromCloudinary();

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
    
    /**
     * Process backup destinations based on company settings
     */
    private function processBackupDestinations($results) {
        try {
            // Get backup destination settings for each company
            $stmt = $this->db->query("
                SELECT company_id, backup_destination 
                FROM company_backup_settings 
                WHERE auto_backup_enabled = 1
            ");
            $destinationSettings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $destinationSettings[$row['company_id']] = $row['backup_destination'];
            }

            // Process each company backup
            foreach ($results['companies'] as $companyBackup) {
                if (!empty($companyBackup['success']) && !empty($companyBackup['backup_id'])) {
                    $companyId = $companyBackup['company_id'];
                    $destination = $destinationSettings[$companyId] ?? 'email';
                    
                    // Send to email if destination is 'email' or 'both'
                    if ($destination === 'email' || $destination === 'both') {
                        $this->sendCompanyBackupViaEmail($companyBackup);
                    }
                    
                    // Upload to Cloudinary if destination is 'cloudinary' or 'both'
                    if ($destination === 'cloudinary' || $destination === 'both') {
                        $this->uploadCompanyBackupToCloudinary($companyBackup);
                    }
                }
            }
            
            // System backup - send to email by default (can be configured later)
            if (!empty($results['system']['success']) && !empty($results['system']['backup_id'])) {
                $this->sendSystemBackupViaEmail($results['system']);
            }
        } catch (\Exception $e) {
            error_log("Error processing backup destinations: " . $e->getMessage());
        }
    }

    /**
     * Send company backup via email
     */
    private function sendCompanyBackupViaEmail($companyBackup) {
        try {
            $emailService = new EmailService();
            $backupService = $this->backupService;
            $backupEmail = 'backup@sellapp.store';
            
            if (!empty($companyBackup['backup_id'])) {
                $backup = $backupService->getBackupById($companyBackup['backup_id']);
                if ($backup && !empty($backup['file_path']) && file_exists($backup['file_path'])) {
                    $subject = "Daily Backup - " . ($companyBackup['company_name'] ?? 'Company #' . $companyBackup['company_id']) . " - " . date('Y-m-d');
                    $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .header { background-color: #3b82f6; color: white; padding: 20px; }
                                .content { padding: 20px; }
                                .footer { background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='header'>
                                <h2>SellApp Daily Backup</h2>
                            </div>
                            <div class='content'>
                                <p>This is an automated daily backup from SellApp.</p>
                                <p><strong>Backup Type:</strong> Company</p>
                                <p><strong>Company:</strong> " . htmlspecialchars($companyBackup['company_name'] ?? 'Unknown') . " (ID: " . htmlspecialchars($companyBackup['company_id']) . ")</p>
                                <p><strong>Backup File:</strong> " . htmlspecialchars($backup['file_name']) . "</p>
                                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                                <p>The backup file is attached to this email.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from SellApp Backup System.</p>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $emailResult = $emailService->sendEmail(
                        $backupEmail,
                        $subject,
                        $message,
                        $backup['file_path'],
                        $backup['file_name'],
                        'backup',
                        $companyBackup['company_id'],
                        null,
                        null
                    );
                    
                    if ($emailResult['success']) {
                        error_log("Backup email sent successfully: {$backup['file_name']} to {$backupEmail}");
                    } else {
                        error_log("Failed to send backup email: {$backup['file_name']} - " . $emailResult['message']);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error sending company backup email: " . $e->getMessage());
        }
    }

    /**
     * Send system backup via email
     */
    private function sendSystemBackupViaEmail($systemBackup) {
        try {
            $emailService = new EmailService();
            $backupService = $this->backupService;
            $backupEmail = 'backup@sellapp.store';
            
            if (!empty($systemBackup['backup_id'])) {
                $backup = $backupService->getBackupById($systemBackup['backup_id']);
                if ($backup && !empty($backup['file_path']) && file_exists($backup['file_path'])) {
                    $subject = "Daily Backup - System - " . date('Y-m-d');
                    $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .header { background-color: #3b82f6; color: white; padding: 20px; }
                                .content { padding: 20px; }
                                .footer { background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='header'>
                                <h2>SellApp Daily Backup</h2>
                            </div>
                            <div class='content'>
                                <p>This is an automated daily backup from SellApp.</p>
                                <p><strong>Backup Type:</strong> System</p>
                                <p><strong>Backup File:</strong> " . htmlspecialchars($backup['file_name']) . "</p>
                                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                                <p>The backup file is attached to this email.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from SellApp Backup System.</p>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $emailResult = $emailService->sendEmail(
                        $backupEmail,
                        $subject,
                        $message,
                        $backup['file_path'],
                        $backup['file_name'],
                        'backup',
                        null,
                        null,
                        null
                    );
                    
                    if ($emailResult['success']) {
                        error_log("System backup email sent successfully: {$backup['file_name']} to {$backupEmail}");
                    } else {
                        error_log("Failed to send system backup email: {$backup['file_name']} - " . $emailResult['message']);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error sending system backup email: " . $e->getMessage());
        }
    }

    /**
     * Upload company backup to Cloudinary
     * Note: BackupService already handles Cloudinary upload during backup creation
     * This method is kept for potential re-upload scenarios
     */
    private function uploadCompanyBackupToCloudinary($companyBackup) {
        // Cloudinary upload is already handled in BackupService during backup creation
        // based on the backup_destination setting
        // This method is a placeholder for future re-upload functionality if needed
        return;
    }

    /**
     * Send backups via email to backup@sellapp.store (legacy method - kept for compatibility)
     */
    private function sendBackupsViaEmail($results) {
        try {
            $emailService = new EmailService();
            $backupService = $this->backupService;
            $backupEmail = 'backup@sellapp.store';
            
            $backupFiles = [];
            
            // Collect all backup files
            foreach ($results['companies'] as $companyBackup) {
                if (!empty($companyBackup['success']) && !empty($companyBackup['backup_id'])) {
                    $backup = $backupService->getBackupById($companyBackup['backup_id']);
                    if ($backup && !empty($backup['file_path']) && file_exists($backup['file_path'])) {
                        $backupFiles[] = [
                            'path' => $backup['file_path'],
                            'name' => $backup['file_name'],
                            'type' => 'company',
                            'company_id' => $companyBackup['company_id'] ?? null,
                            'company_name' => $companyBackup['company_name'] ?? 'Unknown'
                        ];
                    }
                }
            }
            
            // Add system backup
            if (!empty($results['system']['success']) && !empty($results['system']['backup_id'])) {
                $backup = $backupService->getBackupById($results['system']['backup_id']);
                if ($backup && !empty($backup['file_path']) && file_exists($backup['file_path'])) {
                    $backupFiles[] = [
                        'path' => $backup['file_path'],
                        'name' => $backup['file_name'],
                        'type' => 'system'
                    ];
                }
            }
            
            // Send each backup as separate email
            foreach ($backupFiles as $backupFile) {
                $subject = "Daily Backup - " . ($backupFile['type'] === 'system' ? 'System' : $backupFile['company_name']) . " - " . date('Y-m-d');
                $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .header { background-color: #3b82f6; color: white; padding: 20px; }
                            .content { padding: 20px; }
                            .footer { background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='header'>
                            <h2>SellApp Daily Backup</h2>
                        </div>
                        <div class='content'>
                            <p>This is an automated daily backup from SellApp.</p>
                            <p><strong>Backup Type:</strong> " . htmlspecialchars($backupFile['type']) . "</p>
                            " . ($backupFile['type'] === 'company' ? "<p><strong>Company:</strong> " . htmlspecialchars($backupFile['company_name']) . " (ID: " . htmlspecialchars($backupFile['company_id']) . ")</p>" : "") . "
                            <p><strong>Backup File:</strong> " . htmlspecialchars($backupFile['name']) . "</p>
                            <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                            <p>The backup file is attached to this email.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from SellApp Backup System.</p>
                        </div>
                    </body>
                    </html>
                ";
                
                $emailResult = $emailService->sendEmail(
                    $backupEmail,
                    $subject,
                    $message,
                    $backupFile['path'],
                    $backupFile['name'],
                    'backup',
                    $backupFile['type'] === 'company' ? $backupFile['company_id'] : null,
                    null,
                    null
                );
                
                if ($emailResult['success']) {
                    error_log("Backup email sent successfully: {$backupFile['name']} to {$backupEmail}");
                } else {
                    error_log("Failed to send backup email: {$backupFile['name']} - " . $emailResult['message']);
                }
            }
        } catch (\Exception $e) {
            error_log("Error sending backup emails: " . $e->getMessage());
        }
    }
    
    /**
     * Sync backups from Cloudinary to local system
     */
    private function syncBackupsFromCloudinary() {
        try {
            // Get Cloudinary settings
            $settingsQuery = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            if (empty($settings['cloudinary_cloud_name'])) {
                return; // Cloudinary not configured
            }
            
            $cloudinaryService = new CloudinaryService();
            $cloudinaryService->loadFromSettings($settings);
            
            if (!$cloudinaryService->isConfigured()) {
                return;
            }
            
            // List all backups from Cloudinary
            $cloudinaryBackups = $cloudinaryService->listBackups('sellapp/backups', 200);
            
            if (!$cloudinaryBackups['success'] || empty($cloudinaryBackups['backups'])) {
                return;
            }
            
            $backupDir = __DIR__ . '/../../storage/backups/cloudinary_sync';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $syncedCount = 0;
            
            foreach ($cloudinaryBackups['backups'] as $cloudinaryBackup) {
                try {
                    // Check if backup already exists in database
                    $stmt = $this->db->prepare("
                        SELECT id FROM backups 
                        WHERE cloudinary_url = ? OR file_name = ?
                        LIMIT 1
                    ");
                    $stmt->execute([
                        $cloudinaryBackup['secure_url'],
                        $cloudinaryBackup['filename']
                    ]);
                    
                    if ($stmt->fetch()) {
                        continue; // Already synced
                    }
                    
                    // Download backup from Cloudinary
                    $localPath = $backupDir . '/' . $cloudinaryBackup['filename'];
                    $downloadResult = $cloudinaryService->downloadBackup(
                        $cloudinaryBackup['public_id'],
                        $localPath
                    );
                    
                    if (!$downloadResult['success']) {
                        error_log("Failed to download backup from Cloudinary: {$cloudinaryBackup['public_id']}");
                        continue;
                    }
                    
                    // Create backup record in database
                    $backupData = [
                        'company_id' => null, // Will be determined from backup content if possible
                        'file_name' => $cloudinaryBackup['filename'],
                        'file_path' => $localPath,
                        'file_size' => $cloudinaryBackup['bytes'],
                        'status' => 'completed',
                        'format' => 'zip',
                        'cloudinary_url' => $cloudinaryBackup['secure_url'],
                        'backup_type' => 'automatic',
                        'description' => '[SYNCED FROM CLOUDINARY]'
                    ];
                    
                    $backupModel = new Backup();
                    $backupModel->create($backupData);
                    
                    $syncedCount++;
                    error_log("Synced backup from Cloudinary: {$cloudinaryBackup['filename']}");
                    
                } catch (\Exception $e) {
                    error_log("Error syncing backup from Cloudinary: " . $e->getMessage());
                }
            }
            
            if ($syncedCount > 0) {
                error_log("Synced {$syncedCount} backups from Cloudinary");
            }
            
        } catch (\Exception $e) {
            error_log("Error syncing backups from Cloudinary: " . $e->getMessage());
        }
    }
}

