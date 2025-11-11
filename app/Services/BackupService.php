<?php

namespace App\Services;

use PDO;
use ZipArchive;
use App\Models\Backup;

require_once __DIR__ . '/../../config/database.php';

class BackupService {
    private $db;
    private $backupDir;
    private $backupModel;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->backupDir = __DIR__ . '/../../storage/backups';
        $this->backupModel = new Backup();
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Create backups table if it doesn't exist
        $this->ensureBackupsTable();
    }
    
    /**
     * Ensure backups table exists
     */
    private function ensureBackupsTable() {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'backups'");
            if ($checkTable->rowCount() == 0) {
                // Table doesn't exist, create it
                $createTableSQL = "
                    CREATE TABLE backups (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        company_id BIGINT NULL,
                        file_name VARCHAR(255) NOT NULL,
                        file_path TEXT NOT NULL,
                        file_size BIGINT NULL COMMENT 'File size in bytes',
                        status ENUM('completed', 'failed', 'in_progress') DEFAULT 'completed',
                        record_count INT NULL COMMENT 'Number of records backed up',
                        format VARCHAR(10) NULL COMMENT 'json, sql, zip',
                        created_by BIGINT NULL COMMENT 'user_id who created the backup',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        
                        INDEX idx_company_id (company_id),
                        INDEX idx_status (status),
                        INDEX idx_created_at (created_at),
                        INDEX idx_company_created (company_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Metadata for company data backups'
                ";
                $this->db->exec($createTableSQL);
                error_log("Backups table created successfully");
            }
        } catch (\Exception $e) {
            error_log("Error ensuring backups table exists: " . $e->getMessage());
        }
    }

    /**
     * Export company data to JSON or SQL format
     * 
     * @param int $companyId
     * @param string $format 'json' or 'sql'
     * @param int|null $userId User creating the backup
     * @return array ['success' => bool, 'filepath' => string, 'filename' => string]
     */
    public function exportCompanyData($companyId, $format = 'json', $userId = null) {
        try {
            // Create in_progress backup record
            $backupRecordId = null;
        $timestamp = date('Ymd_His');
            $companyDir = $this->backupDir . '/' . $companyId;
            
            if (!is_dir($companyDir)) {
                mkdir($companyDir, 0755, true);
            }

            $tables = $this->getCompanyTables();
            $data = [
                'company_id' => $companyId,
                'exported_at' => date('Y-m-d H:i:s'),
                'format_version' => '1.0',
                'tables' => []
            ];

            foreach ($tables as $table) {
                try {
                    $tableData = $this->exportTable($companyId, $table);
                    if (!empty($tableData)) {
                        $data['tables'][$table] = $tableData;
                    }
                } catch (\Exception $e) {
                    error_log("Error exporting table {$table}: " . $e->getMessage());
                    // Continue with other tables
                }
            }

            if ($format === 'sql') {
                $filename = "backup_{$companyId}_{$timestamp}.sql";
                $filepath = $companyDir . '/' . $filename;
                $this->exportToSQL($data, $filepath);
            } else {
                $filename = "backup_{$companyId}_{$timestamp}.json";
                $filepath = $companyDir . '/' . $filename;
                file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Create zip archive
            $zipFilename = "backup_{$companyId}_{$timestamp}.zip";
            $zipPath = $companyDir . '/' . $zipFilename;
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($filepath, basename($filepath));
                $zip->addFromString('metadata.txt', "Company ID: {$companyId}\nExported: {$data['exported_at']}\nFormat: {$format}\nRecords: " . $this->countTotalRecords($data));
                $zip->close();
                
                // Delete original file, keep only zip
                unlink($filepath);
                
                $fileSize = filesize($zipPath);
                $recordCount = $this->countTotalRecords($data);
                
                // Save backup metadata
                $backupRecordId = $this->backupModel->create([
                    'company_id' => $companyId,
                    'file_name' => $zipFilename,
                    'file_path' => $zipPath,
                    'file_size' => $fileSize,
                    'status' => 'completed',
                    'record_count' => $recordCount,
                    'format' => 'zip',
                    'created_by' => $userId
                ]);
                
                return [
                    'success' => true,
                    'filepath' => $zipPath,
                    'filename' => $zipFilename,
                    'size' => $fileSize,
                    'record_count' => $recordCount,
                    'backup_id' => $backupRecordId
                ];
            } else {
                throw new \Exception('Failed to create zip archive');
            }
        } catch (\Exception $e) {
            error_log("BackupService::exportCompanyData error: " . $e->getMessage());
            
            // Update backup record status to failed if it was created
            if (isset($backupRecordId)) {
                try {
                    $this->backupModel->updateStatus($backupRecordId, 'failed');
                } catch (\Exception $updateError) {
                    error_log("Error updating backup status: " . $updateError->getMessage());
                }
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Import company data from backup file
     * 
     * @param int $companyId
     * @param string $filePath Path to backup file
     * @param bool $validateOnly If true, only validate without importing
     * @return array
     */
    public function importCompanyData($companyId, $filePath, $validateOnly = false) {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception('Backup file not found');
            }

            // Extract zip if needed
            $tempDir = sys_get_temp_dir() . '/backup_' . uniqid();
            $extractedFile = null;

            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === TRUE) {
                    mkdir($tempDir, 0755, true);
                    $zip->extractTo($tempDir);
                    $zip->close();
                    
                    // Find JSON or SQL file
                    $files = scandir($tempDir);
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'json' || 
                            pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                            $extractedFile = $tempDir . '/' . $file;
                            break;
                        }
                    }
                } else {
                    throw new \Exception('Failed to extract zip file');
                }
            } else {
                $extractedFile = $filePath;
            }

            if (!$extractedFile || !file_exists($extractedFile)) {
                throw new \Exception('Could not find backup data file');
            }

            // Read backup data
            $fileExt = pathinfo($extractedFile, PATHINFO_EXTENSION);
            if ($fileExt === 'json') {
                $backupData = json_decode(file_get_contents($extractedFile), true);
            } else {
                // SQL import would require parsing SQL file - simplified for now
                throw new \Exception('SQL import not yet implemented');
            }

            if (!$backupData || !isset($backupData['tables'])) {
                throw new \Exception('Invalid backup file format');
            }

            // Verify backup integrity
            $integrityCheck = $this->verifyBackupIntegrity($backupData);
            if (!$integrityCheck['valid']) {
                return [
                    'success' => false,
                    'error' => 'Backup integrity check failed: ' . $integrityCheck['error']
                ];
            }

            if ($validateOnly) {
                // Cleanup
                if (isset($tempDir) && is_dir($tempDir)) {
                    $this->removeDirectory($tempDir);
                }
                
                return [
                    'success' => true,
                    'valid' => true,
                    'record_count' => $this->countTotalRecords($backupData),
                    'tables' => array_keys($backupData['tables'])
                ];
            }

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Create staging tables
                $stagingTables = [];
                foreach ($backupData['tables'] as $tableName => $tableData) {
                    $stagingTable = $this->createStagingTable($tableName);
                    $stagingTables[$tableName] = $stagingTable;
                    
                    // Import data into staging
                    $this->importTableData($stagingTable, $tableData, $companyId);
                }

                // Verify staging data
                foreach ($stagingTables as $tableName => $stagingTable) {
                    $count = $this->db->query("SELECT COUNT(*) FROM {$stagingTable}")->fetchColumn();
                    if ($count === 0 && !empty($backupData['tables'][$tableName])) {
                        throw new \Exception("Staging table {$stagingTable} is empty");
                    }
                }

                // Move data from staging to production (using transactions)
                foreach ($stagingTables as $tableName => $stagingTable) {
                    $this->mergeStagingToProduction($tableName, $stagingTable, $companyId);
                }

                // Commit transaction
                $this->db->commit();

                // Cleanup staging tables
                foreach ($stagingTables as $stagingTable) {
                    $this->db->exec("DROP TABLE IF EXISTS {$stagingTable}");
                }

                // Cleanup temp directory
                if (isset($tempDir) && is_dir($tempDir)) {
                    $this->removeDirectory($tempDir);
                }

                return [
                    'success' => true,
                    'record_count' => $this->countTotalRecords($backupData),
                    'tables_imported' => count($stagingTables)
                ];
            } catch (\Exception $e) {
                $this->db->rollBack();
                
                // Cleanup staging tables on error
                foreach ($stagingTables as $stagingTable) {
                    try {
                        $this->db->exec("DROP TABLE IF EXISTS {$stagingTable}");
                    } catch (\Exception $cleanupError) {
                        error_log("Error cleaning up staging table: " . $cleanupError->getMessage());
                    }
                }
                
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("BackupService::importCompanyData error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify backup integrity from file
     */
    public function verifyBackupIntegrityFromFile($filePath) {
        try {
            if (!file_exists($filePath)) {
                return ['valid' => false, 'error' => 'File not found'];
            }

            // Extract and read backup data
            $tempDir = sys_get_temp_dir() . '/verify_' . uniqid();
            $extractedFile = null;

            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === TRUE) {
                    mkdir($tempDir, 0755, true);
                    $zip->extractTo($tempDir);
                    $zip->close();
                    
                    $files = scandir($tempDir);
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                            $extractedFile = $tempDir . '/' . $file;
                            break;
                        }
                    }
                }
            } else {
                $extractedFile = $filePath;
            }

            if (!$extractedFile || !file_exists($extractedFile)) {
                return ['valid' => false, 'error' => 'Could not extract backup data'];
            }

            $backupData = json_decode(file_get_contents($extractedFile), true);
            
            // Cleanup
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            return $this->verifyBackupIntegrity($backupData);
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackupIntegrity($backupData) {
        try {
            if (!isset($backupData['company_id']) || !isset($backupData['tables'])) {
                return ['valid' => false, 'error' => 'Missing required backup fields'];
            }

            $requiredTables = ['products', 'pos_sales', 'customers'];
            foreach ($requiredTables as $table) {
                if (!isset($backupData['tables'][$table])) {
                    // Not all tables are required, but log warning
                    error_log("Warning: Backup missing table {$table}");
                }
            }

            // Check data structure
            $totalRecords = $this->countTotalRecords($backupData);
            if ($totalRecords === 0) {
                return ['valid' => false, 'error' => 'Backup file contains no data'];
            }

            return [
                'valid' => true,
                'record_count' => $totalRecords,
                'tables' => array_keys($backupData['tables'])
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get list of backups for a company
     */
    public function getCompanyBackups($companyId) {
        // Check if backups table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'backups'");
            if ($checkTable->rowCount() == 0) {
                // Table doesn't exist, return empty array
                return [];
            }
        } catch (\Exception $e) {
            error_log("BackupService::getCompanyBackups - Could not check backups table: " . $e->getMessage());
            return [];
        }
        
        // Get from database (preferred)
        try {
            // Try to get backups from database table
        $stmt = $this->db->prepare("
                SELECT * FROM backups 
                WHERE company_id = :company_id 
                ORDER BY created_at DESC 
                LIMIT 100
            ");
            $stmt->execute(['company_id' => $companyId]);
            $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enhance with file info
            foreach ($backups as &$backup) {
                $filepath = $backup['file_path'] ?? $backup['filepath'] ?? null;
                if ($filepath && file_exists($filepath)) {
                    $backup['size'] = filesize($filepath);
                    $backup['file_exists'] = true;
                    $backup['filepath'] = $filepath;
                } else {
                    $backup['file_exists'] = false;
                    $backup['size'] = $backup['file_size'] ?? 0;
                }
            }
            
            return $backups;
        } catch (\Exception $e) {
            error_log("BackupService::getCompanyBackups error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get list of company tables to export
     */
    private function getCompanyTables() {
        return [
            'products',
            'pos_sales',
            'pos_sale_items',
            'customers',
            'repairs_new',
            'repair_accessories',
            'swaps',
            'swapped_items',
            'users',
            'company_sms_accounts',
            'audit_logs'
        ];
    }

    /**
     * Export table data for company
     */
    private function exportTable($companyId, $tableName) {
        try {
            // Check if table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if (!$stmt->fetch()) {
                return [];
            }

            // Get table structure
            $columns = [];
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$tableName}");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Export data based on company_id column
            $hasCompanyId = in_array('company_id', $columns);
            
            if ($hasCompanyId) {
                $stmt = $this->db->prepare("SELECT * FROM {$tableName} WHERE company_id = ?");
                $stmt->execute([$companyId]);
            } else {
                // For tables without company_id (like users linked to company)
                // Export based on relationships
                $stmt = $this->db->prepare("SELECT * FROM {$tableName}");
                $stmt->execute();
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error exporting table {$tableName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Export data to SQL format
     */
    private function exportToSQL($data, $filepath) {
        $sql = "-- Company Data Backup\n";
        $sql .= "-- Company ID: {$data['company_id']}\n";
        $sql .= "-- Exported: {$data['exported_at']}\n\n";
        
        foreach ($data['tables'] as $tableName => $records) {
            if (empty($records)) continue;
            
            $sql .= "-- Table: {$tableName}\n";
            foreach ($records as $record) {
                $columns = implode(', ', array_keys($record));
                $values = implode(', ', array_map(function($v) {
                    return is_null($v) ? 'NULL' : $this->db->quote($v);
                }, array_values($record)));
                $sql .= "INSERT INTO {$tableName} ({$columns}) VALUES ({$values});\n";
            }
            $sql .= "\n";
        }
        
        file_put_contents($filepath, $sql);
    }

    /**
     * Create staging table
     */
    private function createStagingTable($originalTable) {
        $stagingTable = $originalTable . '_staging_' . uniqid();
        
        // Create staging table with same structure
        $stmt = $this->db->prepare("SHOW CREATE TABLE {$originalTable}");
        $stmt->execute();
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $createSql = str_replace($originalTable, $stagingTable, $createTable['Create Table']);
        $this->db->exec($createSql);
        
        return $stagingTable;
    }

    /**
     * Import table data to staging
     */
    private function importTableData($stagingTable, $data, $companyId) {
        if (empty($data)) return;

        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        $columnsStr = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $stmt = $this->db->prepare("INSERT INTO {$stagingTable} ({$columnsStr}) VALUES {$placeholders}");

        foreach ($data as $row) {
            // Ensure company_id is set
            if (isset($row['company_id'])) {
                $row['company_id'] = $companyId;
            }
            $stmt->execute(array_values($row));
        }
    }

    /**
     * Merge staging data to production
     */
    private function mergeStagingToProduction($tableName, $stagingTable, $companyId) {
        // Simple strategy: DELETE existing and INSERT from staging
        // More sophisticated strategies could use UPSERT
        
        $this->db->exec("DELETE FROM {$tableName} WHERE company_id = {$companyId}");
        $this->db->exec("INSERT INTO {$tableName} SELECT * FROM {$stagingTable}");
    }

    /**
     * Count total records in backup
     */
    private function countTotalRecords($backupData) {
        $count = 0;
        foreach ($backupData['tables'] as $tableData) {
            $count += count($tableData);
        }
        return $count;
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
