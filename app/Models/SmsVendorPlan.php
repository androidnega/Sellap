<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * SMS Vendor Plan Model
 * Manages SMS vendor bundles/plans
 */
class SmsVendorPlan {
    private $conn;
    private $table = 'sms_vendor_plans';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Get all vendor plans
     * 
     * @param bool $activeOnly Only return active plans
     * @return array List of vendor plans
     */
    public function getAll($activeOnly = false) {
        try {
            // Check if table exists first
            $tableCheck = $this->conn->query("SHOW TABLES LIKE '{$this->table}'");
            if (!$tableCheck || $tableCheck->rowCount() == 0) {
                error_log("SmsVendorPlan::getAll - Table {$this->table} does not exist. Please run migrations.");
                return [];
            }
            
            $sql = "SELECT * FROM {$this->table}";
            if ($activeOnly) {
                $sql .= " WHERE active = 1";
            }
            $sql .= " ORDER BY cost_amount ASC";
            
            $stmt = $this->conn->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            if (empty($results)) {
                error_log("SmsVendorPlan::getAll - No vendor plans found in table. Please seed vendor plans.");
            }
            
            return $results;
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vendor plan by ID
     * 
     * @param int $id Plan ID
     * @return array|false Plan data or false
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: false;
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::getById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get vendor plans by vendor name
     * 
     * @param string $vendorName Vendor name
     * @return array List of plans
     */
    public function getByVendor($vendorName) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE vendor_name = ? ORDER BY cost_amount ASC");
            $stmt->execute([$vendorName]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::getByVendor error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate vendor cost per SMS
     * 
     * @param array $plan Plan data
     * @return float Cost per SMS
     */
    public function getCostPerSms($plan) {
        if (!isset($plan['cost_amount']) || !isset($plan['messages']) || $plan['messages'] <= 0) {
            return 0;
        }
        return (float)$plan['cost_amount'] / (int)$plan['messages'];
    }

    /**
     * Create a new vendor plan
     * 
     * @param array $data Plan data
     * @return int|false New plan ID or false
     */
    public function create($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (vendor_name, label, cost_amount, messages, expires_in_days, meta)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['vendor_name'] ?? '',
                $data['label'] ?? '',
                $data['cost_amount'] ?? 0,
                $data['messages'] ?? 0,
                $data['expires_in_days'] ?? null,
                isset($data['meta']) ? json_encode($data['meta']) : null
            ]);
            return $this->conn->lastInsertId();
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update vendor plan
     * 
     * @param int $id Plan ID
     * @param array $data Plan data
     * @return bool Success status
     */
    public function update($id, $data) {
        try {
            $updates = [];
            $params = [];
            
            $allowedFields = ['vendor_name', 'label', 'cost_amount', 'messages', 'expires_in_days', 'meta'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = ($field === 'meta' && is_array($data[$field])) 
                        ? json_encode($data[$field]) 
                        : $data[$field];
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $params[] = $id;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete vendor plan
     * 
     * @param int $id Plan ID
     * @return bool Success status
     */
    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            error_log("SmsVendorPlan::delete error: " . $e->getMessage());
            return false;
        }
    }
}

