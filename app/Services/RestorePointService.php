<?php

namespace App\Services;

use PDO;
use App\Models\RestorePoint;
use App\Models\Backup;
use App\Services\BackupService;

require_once __DIR__ . '/../../config/database.php';

class RestorePointService {
    private $db;
    private $restorePointModel;
    private $backupModel;
    private $backupService;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->restorePointModel = new RestorePoint();
        $this->backupModel = new Backup();
        $this->backupService = new BackupService();
        
        // Ensure restore_points table exists
        $this->ensureRestorePointsTable();
    }
    
    /**
     * Ensure restore_points table exists
     */
    private function ensureRestorePointsTable() {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'restore_points'");
            if ($checkTable->rowCount() == 0) {
                // Read and execute migration
                $migrationFile = __DIR__ . '/../../database/migrations/create_restore_points_table.sql';
                if (file_exists($migrationFile)) {
                    $sql = file_get_contents($migrationFile);
                    // Remove comments and execute
                    $sql = preg_replace('/--.*$/m', '', $sql);
                    $this->db->exec($sql);
                    error_log("Restore points table created successfully");
                }
            }
        } catch (\Exception $e) {
            error_log("Error ensuring restore_points table exists: " . $e->getMessage());
        }
    }

    /**
     * Create a restore point from a backup
     * 
     * @param int $companyId
     * @param int $backupId Backup ID to use for restore point
     * @param string $name Name for the restore point
     * @param string|null $description Optional description
     * @param int|null $userId User creating the restore point
     * @return array ['success' => bool, 'restore_point_id' => int, 'error' => string]
     */
    public function createRestorePoint($companyId, $backupId, $name, $description = null, $userId = null) {
        try {
            // Get backup information
            $backup = $this->backupModel->find($backupId, $companyId);
            
            if (!$backup) {
                return [
                    'success' => false,
                    'error' => 'Backup not found'
                ];
            }

            // Get snapshot data (current state metrics)
            $snapshotData = $this->getCompanySnapshot($companyId);
            
            // Count total records in backup
            $totalRecords = $backup['record_count'] ?? null;
            if (!$totalRecords && file_exists($backup['file_path'])) {
                // Try to count from backup file
                $totalRecords = $this->countBackupRecords($backup['file_path']);
            }

            // Get tables included
            $tablesIncluded = $this->getBackupTables($backup['file_path']);

            // Create restore point
            $restorePointId = $this->restorePointModel->create([
                'company_id' => $companyId,
                'name' => $name,
                'description' => $description,
                'backup_id' => $backupId,
                'backup_file_path' => $backup['file_path'],
                'snapshot_data' => $snapshotData,
                'total_records' => $totalRecords,
                'tables_included' => $tablesIncluded,
                'created_by' => $userId
            ]);

            return [
                'success' => true,
                'restore_point_id' => $restorePointId
            ];
        } catch (\Exception $e) {
            error_log("Error creating restore point: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore company data from a restore point
     * 
     * @param int $restorePointId
     * @param int $companyId
     * @param string $restoreType 'overwrite' or 'merge'
     * @param int|null $userId User performing the restore
     * @return array ['success' => bool, 'error' => string, 'records_restored' => int]
     */
    public function restoreFromPoint($restorePointId, $companyId, $restoreType = 'overwrite', $userId = null) {
        try {
            // Get restore point
            $restorePoint = $this->restorePointModel->find($restorePointId, $companyId);
            
            if (!$restorePoint) {
                return [
                    'success' => false,
                    'error' => 'Restore point not found'
                ];
            }

            if (empty($restorePoint['backup_file_path']) || !file_exists($restorePoint['backup_file_path'])) {
                return [
                    'success' => false,
                    'error' => 'Backup file not found for this restore point'
                ];
            }

            // If overwrite, create a backup of current state first
            if ($restoreType === 'overwrite') {
                $currentBackup = $this->backupService->createCompanyBackup($companyId);
                if (!$currentBackup) {
                    return [
                        'success' => false,
                        'error' => 'Failed to create backup of current state before restore'
                    ];
                }
            }

            // Import backup data
            // Note: importCompanyData's mergeStagingToProduction already deletes existing data
            // before inserting, so it effectively overwrites. For true merge, we'd need to
            // modify the import logic, but for now both modes work as overwrite.
            $importResult = $this->backupService->importCompanyData(
                $companyId,
                $restorePoint['backup_file_path'],
                false // validateOnly = false (actually import)
            );

            if (!$importResult['success']) {
                return [
                    'success' => false,
                    'error' => $importResult['error'] ?? 'Failed to restore data'
                ];
            }

            // Update restore point statistics
            $this->restorePointModel->incrementRestoreCount($restorePointId);

            return [
                'success' => true,
                'records_restored' => $importResult['record_count'] ?? 0,
                'tables_restored' => $importResult['tables_imported'] ?? 0
            ];
        } catch (\Exception $e) {
            error_log("Error restoring from point: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get company snapshot (current metrics)
     */
    private function getCompanySnapshot($companyId) {
        try {
            $snapshot = [
                'customers' => 0,
                'products' => 0,
                'sales' => 0,
                'repairs' => 0,
                'swaps' => 0,
                'users' => 0
            ];

            // Count customers
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $snapshot['customers'] = (int)$stmt->fetchColumn();

            // Count products
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $snapshot['products'] = (int)$stmt->fetchColumn();

            // Count sales
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM pos_sales WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $snapshot['sales'] = (int)$stmt->fetchColumn();

            // Count repairs
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM repairs WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $snapshot['repairs'] = (int)$stmt->fetchColumn();

            // Count swaps
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM swaps WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $snapshot['swaps'] = (int)$stmt->fetchColumn();

            // Count users
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND role != 'system_admin'");
            $stmt->execute([$companyId]);
            $snapshot['users'] = (int)$stmt->fetchColumn();

            return $snapshot;
        } catch (\Exception $e) {
            error_log("Error getting company snapshot: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count records in backup file
     */
    private function countBackupRecords($filePath) {
        try {
            if (!file_exists($filePath)) {
                return 0;
            }

            // Handle zip files
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === TRUE) {
                    $jsonFile = null;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (pathinfo($filename, PATHINFO_EXTENSION) === 'json') {
                            $jsonFile = $zip->getFromIndex($i);
                            break;
                        }
                    }
                    $zip->close();
                    
                    if ($jsonFile) {
                        $data = json_decode($jsonFile, true);
                        return $this->countRecordsInBackupData($data);
                    }
                }
            } else {
                // Direct JSON file
                $data = json_decode(file_get_contents($filePath), true);
                return $this->countRecordsInBackupData($data);
            }

            return 0;
        } catch (\Exception $e) {
            error_log("Error counting backup records: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Count records in backup data structure
     */
    private function countRecordsInBackupData($data) {
        if (!isset($data['tables']) || !is_array($data['tables'])) {
            return 0;
        }

        $total = 0;
        foreach ($data['tables'] as $tableData) {
            if (is_array($tableData)) {
                $total += count($tableData);
            }
        }

        return $total;
    }

    /**
     * Get list of tables in backup
     */
    private function getBackupTables($filePath) {
        try {
            if (!file_exists($filePath)) {
                return '';
            }

            // Handle zip files
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === TRUE) {
                    $jsonFile = null;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (pathinfo($filename, PATHINFO_EXTENSION) === 'json') {
                            $jsonFile = $zip->getFromIndex($i);
                            break;
                        }
                    }
                    $zip->close();
                    
                    if ($jsonFile) {
                        $data = json_decode($jsonFile, true);
                        if (isset($data['tables']) && is_array($data['tables'])) {
                            return implode(',', array_keys($data['tables']));
                        }
                    }
                }
            } else {
                // Direct JSON file
                $data = json_decode(file_get_contents($filePath), true);
                if (isset($data['tables']) && is_array($data['tables'])) {
                    return implode(',', array_keys($data['tables']));
                }
            }

            return '';
        } catch (\Exception $e) {
            error_log("Error getting backup tables: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Clear company data before overwrite restore
     * This is a simplified version - in production, use ResetService
     * Note: This does NOT manage transactions - it's called before importCompanyData
     * which will start its own transaction
     */
    private function clearCompanyData($companyId) {
        try {
            // Make sure we're not in a transaction
            if ($this->db->inTransaction()) {
                error_log("Warning: clearCompanyData called within a transaction");
                // Don't proceed if we're in a transaction - let importCompanyData handle it
                return;
            }
            
            // Disable foreign key checks
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Delete company data in correct order
            $tables = [
                'swap_profit_links',
                'swapped_items',
                'pos_sale_items',
                'swaps',
                'pos_sales',
                'repair_accessories',
                'repairs_new',
                'repairs',
                'customer_products',
                'customers',
                'restock_logs',
                'product_images',
                'product_specs',
                'products',
                'products_new',
                'sms_logs',
                'notification_logs',
                'sms_payments'
            ];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $this->db->prepare("DELETE FROM {$table} WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                } catch (\Exception $e) {
                    // Table might not exist or have different structure, skip
                    error_log("Could not clear table {$table}: " . $e->getMessage());
                }
            }
            
            // Re-enable foreign key checks
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Exception $e) {
            error_log("Error clearing company data: " . $e->getMessage());
            // Re-enable FK checks even on error
            try {
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (\Exception $fkError) {
                error_log("Error re-enabling FK checks: " . $fkError->getMessage());
            }
            throw $e;
        }
    }
}

