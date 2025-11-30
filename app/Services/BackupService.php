<?php

namespace App\Services;

use PDO;
use ZipArchive;
use App\Models\Backup;
use App\Services\CloudinaryService;

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
     * @param bool $isAutomatic Whether this is an automatic scheduled backup
     * @return array ['success' => bool, 'filepath' => string, 'filename' => string]
     */
    public function exportCompanyData($companyId, $format = 'json', $userId = null, $isAutomatic = false) {
        try {
            // Create in_progress backup record
            $backupRecordId = null;
        $timestamp = date('Ymd_His');
            $companyDir = $this->backupDir . '/' . $companyId;
            
            if (!is_dir($companyDir)) {
                mkdir($companyDir, 0755, true);
            }

            // Get company name for filename
            $companyName = 'company_' . $companyId; // Default fallback
            try {
                $stmt = $this->db->prepare("SELECT name FROM companies WHERE id = :id");
                $stmt->execute(['id' => $companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($company && !empty($company['name'])) {
                    // Sanitize company name for filename (remove special chars, spaces, etc.)
                    $companyName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company['name']);
                    $companyName = strtolower($companyName);
                    $companyName = preg_replace('/_+/', '_', $companyName); // Replace multiple underscores with single
                    $companyName = trim($companyName, '_'); // Remove leading/trailing underscores
                    if (empty($companyName)) {
                        $companyName = 'company_' . $companyId; // Fallback if name becomes empty after sanitization
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching company name: " . $e->getMessage());
                // Use default fallback
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
                $filename = "{$companyName}_{$timestamp}.sql";
                $filepath = $companyDir . '/' . $filename;
                $this->exportToSQL($data, $filepath);
            } else {
                $filename = "{$companyName}_{$timestamp}.json";
                $filepath = $companyDir . '/' . $filename;
                file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Create zip archive
            $zipFilename = "{$companyName}_{$timestamp}.zip";
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
                $backupData = [
                    'company_id' => $companyId,
                    'file_name' => $zipFilename,
                    'file_path' => $zipPath,
                    'file_size' => $fileSize,
                    'status' => 'completed',
                    'record_count' => $recordCount,
                    'format' => 'zip',
                    'created_by' => $userId
                ];
                
                // Add backup_type if column exists
                if ($isAutomatic) {
                    $backupData['backup_type'] = 'automatic';
                    $backupData['description'] = '[AUTOMATIC DAILY BACKUP]';
                } else {
                    $backupData['backup_type'] = 'manual';
                }
                
                $backupRecordId = $this->backupModel->create($backupData);
                
                // Upload to Cloudinary automatically
                $cloudinaryUrl = null;
                try {
                    $cloudinaryUrl = $this->uploadBackupToCloudinary($zipPath, $zipFilename, $companyId, $backupRecordId);
                    if ($cloudinaryUrl) {
                        // Update backup record with Cloudinary URL
                        $this->updateBackupCloudinaryUrl($backupRecordId, $cloudinaryUrl);
                    }
                } catch (\Exception $e) {
                    error_log("Failed to upload backup to Cloudinary: " . $e->getMessage());
                    // Don't fail the backup if Cloudinary upload fails
                }
                
                return [
                    'success' => true,
                    'filepath' => $zipPath,
                    'filename' => $zipFilename,
                    'size' => $fileSize,
                    'record_count' => $recordCount,
                    'backup_id' => $backupRecordId,
                    'cloudinary_url' => $cloudinaryUrl
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

            // Check if already in a transaction
            $wasInTransaction = $this->db->inTransaction();
            
            // Begin transaction only if not already in one
            if (!$wasInTransaction) {
                $this->db->beginTransaction();
            }
            
            // Disable foreign key checks for the entire import process
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");

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
                // Foreign key checks are still disabled from earlier
                foreach ($stagingTables as $tableName => $stagingTable) {
                    $this->mergeStagingToProduction($tableName, $stagingTable, $companyId);
                }

                // Re-enable foreign key checks
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                // Commit transaction only if we started it
                if (!$wasInTransaction && $this->db->inTransaction()) {
                    $this->db->commit();
                }

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
                // Re-enable foreign key checks even on error
                try {
                    $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
                } catch (\Exception $fkError) {
                    error_log("Error re-enabling FK checks: " . $fkError->getMessage());
                }
                
                // Only rollback if we started the transaction and it's still active
                if (!$wasInTransaction && $this->db->inTransaction()) {
                    try {
                        $this->db->rollBack();
                    } catch (\Exception $rollbackError) {
                        error_log("Error rolling back transaction: " . $rollbackError->getMessage());
                    }
                }
                
                // Cleanup staging tables on error
                if (isset($stagingTables)) {
                    foreach ($stagingTables as $stagingTable) {
                        try {
                            $this->db->exec("DROP TABLE IF EXISTS {$stagingTable}");
                        } catch (\Exception $cleanupError) {
                            error_log("Error cleaning up staging table: " . $cleanupError->getMessage());
                        }
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
    public function getCompanyBackups($companyId, $page = 1, $limit = 20, $filters = []) {
        // Check if backups table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'backups'");
            if ($checkTable->rowCount() == 0) {
                return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
            }
        } catch (\Exception $e) {
            error_log("BackupService::getCompanyBackups - Could not check backups table: " . $e->getMessage());
            return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        try {
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $whereConditions = ["b.company_id = :company_id"];
            $params = ['company_id' => $companyId];
            
            // Filter by backup type
            if (!empty($filters['backup_type'])) {
                $whereConditions[] = "b.backup_type = :backup_type";
                $params['backup_type'] = $filters['backup_type'];
            }
            
            // Filter by status
            if (!empty($filters['status'])) {
                $whereConditions[] = "b.status = :status";
                $params['status'] = $filters['status'];
            }
            
            // Filter by date range
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(b.created_at) >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(b.created_at) <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
            
            // Search by filename
            if (!empty($filters['search'])) {
                $whereConditions[] = "b.file_name LIKE :search";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "
                SELECT COUNT(*) as total
                FROM backups b
                {$whereClause}
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated backups
            $sql = "
                SELECT 
                    b.*,
                    u.username as created_by_name,
                    u.full_name as created_by_full_name,
                    c.name as company_name
                FROM backups b
                LEFT JOIN users u ON b.created_by = u.id
                LEFT JOIN companies c ON b.company_id = c.id
                {$whereClause}
                ORDER BY b.created_at DESC 
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
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
            
            $totalPages = ceil($total / $limit);
            
            return [
                'backups' => $backups,
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => (int)$totalPages
            ];
        } catch (\Exception $e) {
            error_log("BackupService::getCompanyBackups error: " . $e->getMessage());
            return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
    }
    
    /**
     * Get all backups (for system admin) with pagination
     */
    public function getAllBackups($companyId = null, $page = 1, $limit = 20, $filters = []) {
        // Check if backups table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'backups'");
            if ($checkTable->rowCount() == 0) {
                return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
            }
        } catch (\Exception $e) {
            error_log("BackupService::getAllBackups - Could not check backups table: " . $e->getMessage());
            return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
        
        try {
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $whereConditions = [];
            $params = [];
            
            if ($companyId) {
                $whereConditions[] = "b.company_id = :company_id";
                $params['company_id'] = $companyId;
            }
            
            // Filter by backup type
            if (!empty($filters['backup_type'])) {
                $whereConditions[] = "b.backup_type = :backup_type";
                $params['backup_type'] = $filters['backup_type'];
            }
            
            // Filter by status
            if (!empty($filters['status'])) {
                $whereConditions[] = "b.status = :status";
                $params['status'] = $filters['status'];
            }
            
            // Filter by date range
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(b.created_at) >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(b.created_at) <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
            
            // Search by filename or company name
            if (!empty($filters['search'])) {
                $whereConditions[] = "(b.file_name LIKE :search OR c.name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countSql = "
                SELECT COUNT(*) as total
                FROM backups b
                LEFT JOIN companies c ON b.company_id = c.id
                {$whereClause}
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated backups
            $sql = "
                SELECT 
                    b.*,
                    u.username as created_by_name,
                    u.full_name as created_by_full_name,
                    c.name as company_name
                FROM backups b
                LEFT JOIN users u ON b.created_by = u.id
                LEFT JOIN companies c ON b.company_id = c.id
                {$whereClause}
                ORDER BY b.created_at DESC 
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
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
            
            $totalPages = ceil($total / $limit);
            
            return [
                'backups' => $backups,
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => (int)$totalPages
            ];
        } catch (\Exception $e) {
            error_log("BackupService::getAllBackups error: " . $e->getMessage());
            return ['backups' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }
    }
    
    /**
     * Delete backup by ID
     */
    public function deleteBackup($backupId, $companyId = null) {
        try {
            // Get backup record first
            $backupModel = new Backup();
            $backup = $backupModel->find($backupId, $companyId);
            
            if (!$backup) {
                return ['success' => false, 'error' => 'Backup not found'];
            }
            
            // Delete file if it exists
            $filePath = $backup['file_path'] ?? null;
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath);
            }
            
            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM backups WHERE id = :id");
            $stmt->execute(['id' => $backupId]);
            
            return ['success' => true, 'message' => 'Backup deleted successfully'];
        } catch (\Exception $e) {
            error_log("BackupService::deleteBackup error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete multiple backups
     */
    public function deleteBackups($backupIds, $companyId = null) {
        try {
            if (empty($backupIds) || !is_array($backupIds)) {
                return ['success' => false, 'error' => 'Invalid backup IDs'];
            }
            
            $deleted = 0;
            $errors = [];
            
            foreach ($backupIds as $backupId) {
                $result = $this->deleteBackup($backupId, $companyId);
                if ($result['success']) {
                    $deleted++;
                } else {
                    $errors[] = "Backup #{$backupId}: " . ($result['error'] ?? 'Unknown error');
                }
            }
            
            return [
                'success' => true,
                'deleted' => $deleted,
                'total' => count($backupIds),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            error_log("BackupService::deleteBackups error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create company backup (wrapper method)
     * 
     * @param int $companyId
     * @param int|null $userId User creating the backup
     * @param bool $isAutomatic Whether this is an automatic scheduled backup
     * @return int Backup ID
     */
    public function createCompanyBackup($companyId, $userId = null, $isAutomatic = false) {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!$userId && isset($_SESSION['user']['id'])) {
                $userId = $_SESSION['user']['id'];
            }
            
            $result = $this->exportCompanyData($companyId, 'json', $userId, $isAutomatic);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to create backup');
            }
            
            return $result['backup_id'];
        } catch (\Exception $e) {
            error_log("BackupService::createCompanyBackup error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create system-wide backup (all companies and system data)
     * 
     * @param int|null $userId User creating the backup
     * @param bool $isAutomatic Whether this is an automatic scheduled backup
     * @return int Backup ID
     */
    public function createSystemBackup($userId = null, $isAutomatic = false) {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!$userId && isset($_SESSION['user']['id'])) {
                $userId = $_SESSION['user']['id'];
            }
            
            $timestamp = date('Ymd_His');
            $systemDir = $this->backupDir . '/system';
            
            if (!is_dir($systemDir)) {
                mkdir($systemDir, 0755, true);
            }

            $tables = $this->getSystemTables();
            $data = [
                'backup_type' => 'system',
                'exported_at' => date('Y-m-d H:i:s'),
                'format_version' => '1.0',
                'tables' => []
            ];

            // Export all system tables (no company_id filter)
            foreach ($tables as $table) {
                try {
                    $tableData = $this->exportSystemTable($table);
                    if (!empty($tableData)) {
                        $data['tables'][$table] = $tableData;
                    }
                } catch (\Exception $e) {
                    error_log("Error exporting system table {$table}: " . $e->getMessage());
                    // Continue with other tables
                }
            }

            // Create JSON file
            $filename = "sellapp_{$timestamp}.json";
            $filepath = $systemDir . '/' . $filename;
            file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Create zip archive
            $zipFilename = "sellapp_{$timestamp}.zip";
            $zipPath = $systemDir . '/' . $zipFilename;
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($filepath, basename($filepath));
                $zip->addFromString('metadata.txt', "Backup Type: System\nExported: {$data['exported_at']}\nFormat: json\nRecords: " . $this->countTotalRecords($data));
                $zip->close();
                
                // Delete original file, keep only zip
                unlink($filepath);
                
                $fileSize = filesize($zipPath);
                $recordCount = $this->countTotalRecords($data);
                
                // Save backup metadata (company_id is NULL for system backups)
                $backupData = [
                    'company_id' => null,
                    'file_name' => $zipFilename,
                    'file_path' => $zipPath,
                    'file_size' => $fileSize,
                    'status' => 'completed',
                    'record_count' => $recordCount,
                    'format' => 'zip',
                    'created_by' => $userId
                ];
                
                // Add backup_type if column exists
                if ($isAutomatic) {
                    $backupData['backup_type'] = 'automatic';
                    $backupData['description'] = '[AUTOMATIC DAILY BACKUP]';
                } else {
                    $backupData['backup_type'] = 'manual';
                }
                
                $backupRecordId = $this->backupModel->create($backupData);
                
                // Upload to Cloudinary automatically
                try {
                    $cloudinaryUrl = $this->uploadBackupToCloudinary($zipPath, $zipFilename, null, $backupRecordId, 'system');
                    if ($cloudinaryUrl) {
                        // Update backup record with Cloudinary URL
                        $this->updateBackupCloudinaryUrl($backupRecordId, $cloudinaryUrl);
                    }
                } catch (\Exception $e) {
                    error_log("Failed to upload system backup to Cloudinary: " . $e->getMessage());
                    // Don't fail the backup if Cloudinary upload fails
                }
                
                return $backupRecordId;
            } else {
                throw new \Exception('Failed to create zip archive');
            }
        } catch (\Exception $e) {
            error_log("BackupService::createSystemBackup error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of system tables to export (all tables, not company-specific)
     */
    private function getSystemTables() {
        return [
            'companies',
            'users',
            'products',
            'pos_sales',
            'pos_sale_items',
            'customers',
            'repairs_new',
            'repair_accessories',
            'swaps',
            'swapped_items',
            'company_sms_accounts',
            'company_modules',
            'admin_actions',
            'audit_logs',
            'backups'
        ];
    }

    /**
     * Export system table data (all records, no company filter)
     */
    private function exportSystemTable($tableName) {
        try {
            // Check if table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if (!$stmt->fetch()) {
                return [];
            }

            // Export all data from table
            $stmt = $this->db->prepare("SELECT * FROM {$tableName}");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error exporting system table {$tableName}: " . $e->getMessage());
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
        
        // Get table structure
        $stmt = $this->db->prepare("SHOW CREATE TABLE {$originalTable}");
        $stmt->execute();
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$createTable) {
            throw new \Exception("Could not get table structure for {$originalTable}");
        }
        
        $createSql = $createTable['Create Table'];
        
        // Better approach: Split by lines and remove lines containing FOREIGN KEY constraints
        $lines = explode("\n", $createSql);
        $filteredLines = [];
        $skipNext = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip lines that are foreign key constraints
            if (preg_match('/\bFOREIGN\s+KEY\b/i', $trimmed)) {
                continue; // Skip this line
            }
            
            // Skip CONSTRAINT lines that might be part of foreign key (multi-line)
            if (preg_match('/\bCONSTRAINT\b/i', $trimmed) && preg_match('/\bREFERENCES\b/i', $trimmed)) {
                continue; // Skip constraint with references
            }
            
            // Remove REFERENCES from column definitions
            $trimmed = preg_replace('/\s+REFERENCES\s+`?[^\s`]+`?\s*\([^)]+\)(\s+ON\s+(DELETE|UPDATE)\s+[^\s,)]+)*/i', '', $trimmed);
            
            $filteredLines[] = $trimmed;
        }
        
        $createSql = implode("\n", $filteredLines);
        
        // Clean up: Remove trailing commas before closing parenthesis or ENGINE
        $createSql = preg_replace('/,\s*(\n\s*\))/', '$1', $createSql);
        $createSql = preg_replace('/,\s*(\n\s*ENGINE)/', '$1', $createSql);
        
        // Replace table name
        $createSql = str_replace($originalTable, $stagingTable, $createSql);
        
        // Disable foreign key checks before creating staging table
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        try {
            $this->db->exec($createSql);
        } catch (\Exception $e) {
            // Log the SQL for debugging
            error_log("Failed to create staging table. SQL: " . substr($createSql, 0, 500));
            throw $e;
        } finally {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
        
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
        
        // Foreign key checks should already be disabled, but ensure they are
        // This is a safety measure in case the setting didn't persist
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        try {
            // Get column names to build proper INSERT
            $columns = $this->getTableColumns($tableName);
            
            // Check if table has company_id column before deleting
            $hasCompanyId = in_array('company_id', $columns);
            
            // Find primary key column(s)
            $primaryKeyColumns = $this->getPrimaryKeyColumns($tableName);
            $hasPrimaryKey = !empty($primaryKeyColumns);
            
            if ($hasCompanyId) {
                // Delete existing records for this company
                $this->db->exec("DELETE FROM `{$tableName}` WHERE company_id = {$companyId}");
            } else {
                // For tables without company_id, we need to delete records that match the staging data
                // Get IDs from staging table to delete matching records
                if ($hasPrimaryKey && count($primaryKeyColumns) === 1) {
                    // Single primary key - delete by ID
                    $pkColumn = $primaryKeyColumns[0];
                    $this->db->exec("DELETE FROM `{$tableName}` WHERE `{$pkColumn}` IN (SELECT `{$pkColumn}` FROM `{$stagingTable}`)");
                } else {
                    // Multiple primary keys or no primary key - delete all (for company-specific tables)
                    $companySpecificTables = ['products', 'customers', 'pos_sales', 'repairs', 'swaps', 'customer_products', 'product_images', 'product_specs'];
                    if (in_array($tableName, $companySpecificTables)) {
                        $this->db->exec("DELETE FROM `{$tableName}`");
                    }
                }
            }
            
            // Build INSERT statement with explicit column names
            $columnList = implode(', ', array_map(function($col) {
                return "`{$col}`";
            }, $columns));
            
            // For foreign key columns that reference users, set to NULL if user doesn't exist
            $selectColumns = [];
            foreach ($columns as $col) {
                // Check if this column might be a foreign key to users table
                // Common patterns: created_by, updated_by, user_id, created_by_user, etc.
                if (preg_match('/^(created_by|updated_by|user_id|created_by_user|updated_by_user)$/i', $col)) {
                    // Validate foreign key: if user exists, use the staging value; otherwise NULL
                    $selectColumns[] = "CASE 
                        WHEN (SELECT id FROM users WHERE id = `{$stagingTable}`.`{$col}` LIMIT 1) IS NOT NULL 
                        THEN `{$stagingTable}`.`{$col}` 
                        ELSE NULL 
                    END AS `{$col}`";
                } else {
                    $selectColumns[] = "`{$stagingTable}`.`{$col}`";
                }
            }
            
            $selectList = implode(', ', $selectColumns);
            
            // Insert data with ON DUPLICATE KEY UPDATE to handle any remaining conflicts
            // This will update existing records if primary key conflicts occur
            if ($hasPrimaryKey && count($primaryKeyColumns) === 1) {
                // Single primary key - use INSERT ... ON DUPLICATE KEY UPDATE
                $pkColumn = $primaryKeyColumns[0];
                $updateClause = [];
                foreach ($columns as $col) {
                    if ($col !== $pkColumn) {
                        $updateClause[] = "`{$col}` = VALUES(`{$col}`)";
                    }
                }
                $updateList = implode(', ', $updateClause);
                $this->db->exec("INSERT INTO `{$tableName}` ({$columnList}) SELECT {$selectList} FROM `{$stagingTable}` ON DUPLICATE KEY UPDATE {$updateList}");
            } else {
                // Multiple primary keys or no primary key - use INSERT IGNORE
                $this->db->exec("INSERT IGNORE INTO `{$tableName}` ({$columnList}) SELECT {$selectList} FROM `{$stagingTable}`");
            }
            
        } catch (\Exception $e) {
            error_log("Error merging {$tableName} from {$stagingTable}: " . $e->getMessage());
            throw $e;
        }
        // Note: Foreign key checks will be re-enabled by the caller after all merges
    }
    
    /**
     * Get column names for a table
     */
    private function getTableColumns($tableName) {
        try {
            $stmt = $this->db->query("DESCRIBE `{$tableName}`");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            return $columns;
        } catch (\Exception $e) {
            error_log("Error getting columns for {$tableName}: " . $e->getMessage());
            // Fallback: try to get columns from information_schema
            try {
                $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
                $stmt->execute([$tableName]);
                $columns = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = $row['COLUMN_NAME'];
                }
                return $columns;
            } catch (\Exception $e2) {
                error_log("Error getting columns from information_schema: " . $e2->getMessage());
                throw new \Exception("Could not get column list for table {$tableName}");
            }
        }
    }
    
    /**
     * Get primary key columns for a table
     */
    private function getPrimaryKeyColumns($tableName) {
        try {
            $stmt = $this->db->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            $primaryKeys = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $primaryKeys[] = $row['Column_name'];
            }
            return $primaryKeys;
        } catch (\Exception $e) {
            error_log("Error getting primary keys for {$tableName}: " . $e->getMessage());
            // Fallback: try information_schema
            try {
                $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
                $stmt->execute([$tableName]);
                $primaryKeys = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $primaryKeys[] = $row['COLUMN_NAME'];
                }
                return $primaryKeys;
            } catch (\Exception $e2) {
                error_log("Error getting primary keys from information_schema: " . $e2->getMessage());
                return [];
            }
        }
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
     * Upload backup to Cloudinary
     * 
     * @param string $zipPath Path to the zip file
     * @param string $zipFilename Name of the zip file
     * @param int|null $companyId Company ID (null for system backups)
     * @param int $backupId Backup record ID
     * @param string $type 'company' or 'system'
     * @return string|null Cloudinary URL or null if upload fails
     */
    private function uploadBackupToCloudinary($zipPath, $zipFilename, $companyId = null, $backupId = null, $type = 'company') {
        try {
            // Check if Cloudinary is configured
            if (!class_exists('\App\Services\CloudinaryService')) {
                return null;
            }
            
            // Get Cloudinary settings from database
            $settingsQuery = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            if (empty($settings) || empty($settings['cloudinary_cloud_name'])) {
                return null;
            }
            
            $cloudinaryService = new CloudinaryService();
            $cloudinaryService->loadFromSettings($settings);
            
            if (!$cloudinaryService->isConfigured()) {
                return null;
            }
            
            // Determine folder based on backup type
            $folder = $type === 'system' ? 'sellapp/backups/system' : "sellapp/backups/company_{$companyId}";
            
            // Generate unique public_id
            $publicId = $type === 'system' 
                ? "backup_system_{$backupId}_{$zipFilename}"
                : "backup_company_{$companyId}_{$backupId}_{$zipFilename}";
            
            // Remove .zip extension from public_id
            $publicId = preg_replace('/\.zip$/', '', $publicId);
            
            // Upload to Cloudinary
            $result = $cloudinaryService->uploadRawFile($zipPath, $folder, [
                'public_id' => $publicId,
                'use_filename' => false,
                'unique_filename' => false,
                'overwrite' => true
            ]);
            
            if ($result['success'] && !empty($result['secure_url'])) {
                error_log("Backup uploaded to Cloudinary: {$result['secure_url']}");
                return $result['secure_url'];
            } else {
                error_log("Cloudinary upload failed: " . ($result['error'] ?? 'Unknown error'));
                return null;
            }
        } catch (\Exception $e) {
            error_log("Error uploading backup to Cloudinary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update backup record with Cloudinary URL
     * 
     * @param int $backupId Backup record ID
     * @param string $cloudinaryUrl Cloudinary URL
     */
    private function updateBackupCloudinaryUrl($backupId, $cloudinaryUrl) {
        try {
            // Check if cloudinary_url column exists, if not, add it
            $checkColumn = $this->db->query("SHOW COLUMNS FROM backups LIKE 'cloudinary_url'");
            if ($checkColumn->rowCount() == 0) {
                // Add column if it doesn't exist
                $this->db->exec("ALTER TABLE backups ADD COLUMN cloudinary_url TEXT NULL AFTER file_path");
            }
            
            // Update backup record
            $stmt = $this->db->prepare("UPDATE backups SET cloudinary_url = ? WHERE id = ?");
            $stmt->execute([$cloudinaryUrl, $backupId]);
        } catch (\Exception $e) {
            error_log("Error updating backup Cloudinary URL: " . $e->getMessage());
            // Don't throw - this is not critical
        }
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
