<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class CompanyModule {
    private $conn;
    private $table = 'company_modules';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Check if a module is enabled for a company
     * 
     * @param int $company_id
     * @param string $module_key
     * @return bool
     */
    public static function isEnabled($company_id, $module_key) {
        $instance = new self();
        $stmt = $instance->conn->prepare(
            "SELECT enabled FROM {$instance->table} 
             WHERE company_id = :company_id 
             AND module_key = :module_key 
             AND enabled = 1 
             LIMIT 1"
        );
        $stmt->execute([
            'company_id' => $company_id,
            'module_key' => $module_key
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($result);
    }

    /**
     * Find module configuration by company ID and module key
     * 
     * @param int $company_id
     * @param string $module_key
     * @return array|null
     */
    public function find($company_id, $module_key) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} 
             WHERE company_id = :company_id 
             AND module_key = :module_key 
             LIMIT 1"
        );
        $stmt->execute([
            'company_id' => $company_id,
            'module_key' => $module_key
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all modules for a company
     * 
     * @param int $company_id
     * @return array
     */
    public function getByCompany($company_id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} 
             WHERE company_id = :company_id 
             ORDER BY module_key ASC"
        );
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all enabled modules for a company
     * 
     * @param int $company_id
     * @return array Array of module keys
     */
    public function getEnabledModules($company_id) {
        $stmt = $this->conn->prepare(
            "SELECT module_key FROM {$this->table} 
             WHERE company_id = :company_id 
             AND enabled = 1"
        );
        $stmt->execute(['company_id' => $company_id]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $results ?: [];
    }

    /**
     * Get all disabled modules for a company
     * 
     * @param int $company_id
     * @return array Array of module keys
     */
    public function getDisabledModules($company_id) {
        $stmt = $this->conn->prepare(
            "SELECT module_key FROM {$this->table} 
             WHERE company_id = :company_id 
             AND enabled = 0"
        );
        $stmt->execute(['company_id' => $company_id]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $results ?: [];
    }

    /**
     * Enable or disable a module for a company
     * Creates the record if it doesn't exist, updates if it does
     * 
     * @param int $company_id
     * @param string $module_key
     * @param bool $enabled
     * @return bool
     */
    public function setModuleStatus($company_id, $module_key, $enabled = true) {
        // Check if record exists
        $existing = $this->find($company_id, $module_key);
        
        if ($existing) {
            // Update existing record
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table} 
                 SET enabled = :enabled, updated_at = NOW() 
                 WHERE company_id = :company_id 
                 AND module_key = :module_key"
            );
            return $stmt->execute([
                'company_id' => $company_id,
                'module_key' => $module_key,
                'enabled' => $enabled ? 1 : 0
            ]);
        } else {
            // Create new record
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (company_id, module_key, enabled, created_at) 
                 VALUES (:company_id, :module_key, :enabled, NOW())"
            );
            return $stmt->execute([
                'company_id' => $company_id,
                'module_key' => $module_key,
                'enabled' => $enabled ? 1 : 0
            ]);
        }
    }

    /**
     * Enable a module for a company
     * 
     * @param int $company_id
     * @param string $module_key
     * @return bool
     */
    public function enableModule($company_id, $module_key) {
        return $this->setModuleStatus($company_id, $module_key, true);
    }

    /**
     * Disable a module for a company
     * 
     * @param int $company_id
     * @param string $module_key
     * @return bool
     */
    public function disableModule($company_id, $module_key) {
        return $this->setModuleStatus($company_id, $module_key, false);
    }

    /**
     * Bulk enable/disable modules for a company
     * 
     * @param int $company_id
     * @param array $modules Array of ['module_key' => bool] pairs
     * @return bool
     */
    public function bulkUpdateModules($company_id, $modules) {
        $this->conn->beginTransaction();
        try {
            foreach ($modules as $module_key => $enabled) {
                $this->setModuleStatus($company_id, $module_key, $enabled);
            }
            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log("CompanyModule::bulkUpdateModules error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize default modules for a company (enable all by default)
     * 
     * @param int $company_id
     * @param array $module_keys Array of module keys to enable
     * @return bool
     */
    public function initializeCompanyModules($company_id, $module_keys = null) {
        // Default module keys if not provided
        if ($module_keys === null) {
            $module_keys = [
                'products_inventory',
                'pos_sales',
                'swap',
                'repairs',
                'customers',
                'staff_management',
                'reports_analytics',
                'notifications_sms'
            ];
        }

        $this->conn->beginTransaction();
        try {
            foreach ($module_keys as $module_key) {
                // Only insert if it doesn't already exist
                $existing = $this->find($company_id, $module_key);
                if (!$existing) {
                    $stmt = $this->conn->prepare(
                        "INSERT INTO {$this->table} (company_id, module_key, enabled, created_at) 
                         VALUES (:company_id, :module_key, 1, NOW())"
                    );
                    $stmt->execute([
                        'company_id' => $company_id,
                        'module_key' => $module_key
                    ]);
                }
            }
            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log("CompanyModule::initializeCompanyModules error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a module configuration for a company
     * 
     * @param int $company_id
     * @param string $module_key
     * @return bool
     */
    public function delete($company_id, $module_key) {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->table} 
             WHERE company_id = :company_id 
             AND module_key = :module_key"
        );
        $stmt->execute([
            'company_id' => $company_id,
            'module_key' => $module_key
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all modules for a company
     * 
     * @param int $company_id
     * @return bool
     */
    public function deleteByCompany($company_id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE company_id = :company_id");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a company has any modules configured
     * 
     * @param int $company_id
     * @return bool
     */
    public function hasModules($company_id) {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE company_id = :company_id"
        );
        $stmt->execute(['company_id' => $company_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] ?? 0) > 0;
    }
}

