<?php

namespace App\Models;

use PDO;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

/**
 * Company SMS Account Model
 * Manages SMS credits allocation and tracking per company
 */
class CompanySMSAccount {
    private $conn;
    private $table = 'company_sms_accounts';

    public function __construct() {
        // Ensure database class is available
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        try {
            $this->conn = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log("CompanySMSAccount: Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get or create SMS account for a company
     * 
     * @param int $companyId Company ID
     * @return array|false SMS account data or false if error
     */
    public function getOrCreateAccount($companyId) {
        try {
            // Validate company ID
            if (empty($companyId) || !is_numeric($companyId)) {
                error_log("CompanySMSAccount::getOrCreateAccount - Invalid company ID: " . var_export($companyId, true));
                return false;
            }
            
            $companyId = (int)$companyId;
            
            // Check if table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE '{$this->table}'");
            if (!$tableCheck || $tableCheck->rowCount() == 0) {
                error_log("CompanySMSAccount::getOrCreateAccount - Table {$this->table} does not exist");
                // Try to create the table
                $this->createTableIfNotExists();
                // Re-check after creation attempt
                $tableCheck = $this->conn->query("SHOW TABLES LIKE '{$this->table}'");
                if (!$tableCheck || $tableCheck->rowCount() == 0) {
                    error_log("CompanySMSAccount::getOrCreateAccount - Failed to create table {$this->table}");
                    return false;
                }
            }
            
            // Skip company verification for admin account (company_id = 0)
            if ($companyId !== 0) {
                // Verify company exists in companies table
                $companyCheck = $this->conn->prepare("SELECT id FROM companies WHERE id = ? LIMIT 1");
                $companyCheck->execute([$companyId]);
                if (!$companyCheck->fetch()) {
                    error_log("CompanySMSAccount::getOrCreateAccount - Company ID {$companyId} does not exist in companies table");
                    return false;
                }
            }
            
            // Try to get existing account
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = ? LIMIT 1");
            $stmt->execute([$companyId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                return $account;
            }
            
            // Create new account if doesn't exist
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (company_id, total_sms, sms_used, status, custom_sms_enabled, sms_sender_name) 
                VALUES (?, 0, 0, 'active', 0, 'SellApp')
            ");
            $stmt->execute([$companyId]);
            
            // Return the newly created account
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = ? LIMIT 1");
            $stmt->execute([$companyId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                error_log("CompanySMSAccount::getOrCreateAccount - Failed to create account for company {$companyId}");
                return false;
            }
            
            return $account;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::getOrCreateAccount error for company {$companyId}: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Create the company_sms_accounts table if it doesn't exist
     */
    private function createTableIfNotExists() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_id INT NOT NULL UNIQUE,
                    total_sms INT NOT NULL DEFAULT 0,
                    sms_used INT NOT NULL DEFAULT 0,
                    sms_remaining INT AS (total_sms - sms_used) STORED,
                    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
                    sms_sender_name VARCHAR(15) NOT NULL DEFAULT 'SellApp',
                    custom_sms_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->conn->exec($sql);
            error_log("CompanySMSAccount: Created table {$this->table}");
        } catch (\Exception $e) {
            error_log("CompanySMSAccount: Failed to create table {$this->table}: " . $e->getMessage());
        }
    }

    /**
     * Get SMS balance for a company
     * 
     * @param int $companyId Company ID
     * @return array Balance information
     */
    public function getSMSBalance($companyId) {
        try {
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve SMS account'
                ];
            }
            
            $remaining = max(0, $account['total_sms'] - $account['sms_used']);
            $usagePercent = $account['total_sms'] > 0 
                ? round(($account['sms_used'] / $account['total_sms']) * 100, 2) 
                : 0;
            
            return [
                'success' => true,
                'total_sms' => (int)$account['total_sms'],
                'sms_used' => (int)$account['sms_used'],
                'sms_remaining' => $remaining,
                'usage_percent' => $usagePercent,
                'status' => $account['status'],
                'custom_sms_enabled' => (bool)$account['custom_sms_enabled'],
                'sms_sender_name' => $account['sms_sender_name']
            ];
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::getSMSBalance error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Decrement SMS count for a company
     * 
     * @param int $companyId Company ID
     * @param int $count Number of SMS to decrement (default: 1)
     * @return bool Success status
     */
    public function decrementSMS($companyId, $count = 1) {
        try {
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            // Check if account is active
            if ($account['status'] !== 'active') {
                error_log("Cannot decrement SMS for company {$companyId}: account is {$account['status']}");
                return false;
            }
            
            // Check if enough SMS remaining
            $remaining = $account['total_sms'] - $account['sms_used'];
            if ($remaining < $count) {
                error_log("Insufficient SMS credits for company {$companyId}: {$remaining} remaining, {$count} needed");
                return false;
            }
            
            // Get current balance before decrement for logging
            $balanceBefore = $this->getSMSBalance($companyId);
            $remainingBefore = $balanceBefore['sms_remaining'] ?? 0;
            $usedBefore = $account['sms_used'] ?? 0;
            
            error_log("CompanySMSAccount::decrementSMS - Before: company_id={$companyId}, remaining={$remainingBefore}, used={$usedBefore}, total={$account['total_sms']}");
            
            // Increment sms_used
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET sms_used = sms_used + ?, updated_at = NOW() 
                WHERE company_id = ? AND status = 'active'
            ");
            $result = $stmt->execute([$count, $companyId]);
            
            error_log("CompanySMSAccount::decrementSMS - Execute result: " . ($result ? 'true' : 'false'));
            error_log("CompanySMSAccount::decrementSMS - Rows affected: " . $stmt->rowCount());
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("CompanySMSAccount::decrementSMS - SQL Error: " . json_encode($errorInfo));
                return false;
            }
            
            // Verify the update actually happened
            if ($stmt->rowCount() === 0) {
                error_log("CompanySMSAccount::decrementSMS - WARNING: No rows updated! Check if company_id={$companyId} and status='active'");
                // Try to get account again to see current state
                $accountAfter = $this->getOrCreateAccount($companyId);
                error_log("CompanySMSAccount::decrementSMS - Account after update attempt: status=" . ($accountAfter['status'] ?? 'unknown'));
                return false;
            }
            
            // Get updated balance to verify decrement
            $balanceAfter = $this->getSMSBalance($companyId);
            $remainingAfter = $balanceAfter['sms_remaining'] ?? 0;
            $expectedRemaining = $remainingBefore - $count;
            error_log("CompanySMSAccount::decrementSMS - After: remaining={$remainingAfter}, expected={$expectedRemaining}");
            
            if ($remainingAfter != $expectedRemaining) {
                error_log("CompanySMSAccount::decrementSMS - WARNING: Balance mismatch! Expected {$expectedRemaining}, got {$remainingAfter}");
            }
            
            // Check if balance is low and trigger alert
            if ($result && $stmt->rowCount() > 0) {
                try {
                    $newBalance = $this->getSMSBalance($companyId);
                    if ($newBalance['success']) {
                        $usagePercent = $newBalance['usage_percent'] ?? 0;
                        $remaining = $newBalance['sms_remaining'] ?? 0;
                        
                        // Trigger alert if balance is low (< 10% or <= 10 SMS)
                        if ($usagePercent >= 90 || $remaining <= 10) {
                            $alertService = new \App\Services\SMSAlertService();
                            $alertService->checkAndSendLowBalanceAlert($companyId, 10);
                        }
                    }
                } catch (\Exception $e) {
                    // Don't fail SMS decrement if alert fails
                    error_log("SMS alert check error: " . $e->getMessage());
                }
            }
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::decrementSMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Allocate SMS credits to a company
     * 
     * @param int $companyId Company ID
     * @param int $amount Number of SMS credits to allocate
     * @return bool Success status
     */
    public function allocateSMS($companyId, $amount) {
        try {
            if ($amount <= 0) {
                return false;
            }
            
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            // Update total_sms by adding the new allocation
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET total_sms = total_sms + ?, updated_at = NOW() 
                WHERE company_id = ?
            ");
            $result = $stmt->execute([$amount, $companyId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::allocateSMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set total SMS credits (replace existing total)
     * 
     * @param int $companyId Company ID
     * @param int $totalSMS Total SMS credits to set
     * @return bool Success status
     */
    public function setTotalSMS($companyId, $totalSMS) {
        try {
            if ($totalSMS < 0) {
                return false;
            }
            
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            // Reset sms_used if new total is less than used
            $smsUsed = max(0, min($account['sms_used'], $totalSMS));
            
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET total_sms = ?, sms_used = ?, updated_at = NOW() 
                WHERE company_id = ?
            ");
            $result = $stmt->execute([$totalSMS, $smsUsed, $companyId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::setTotalSMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle custom SMS sender name enabled status
     * 
     * @param int $companyId Company ID
     * @param bool $enabled Enable/disable custom SMS
     * @return bool Success status
     */
    public function toggleCustomSMSEnabled($companyId, $enabled) {
        try {
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET custom_sms_enabled = ?, updated_at = NOW() 
                WHERE company_id = ?
            ");
            $result = $stmt->execute([$enabled ? 1 : 0, $companyId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::toggleCustomSMSEnabled error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set custom SMS sender name
     * 
     * @param int $companyId Company ID
     * @param string $senderName Sender name (max 11 chars - Arkassel API requirement)
     * @return bool Success status
     */
    public function setSenderName($companyId, $senderName) {
        try {
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            // Truncate to 11 characters (Arkassel API requirement - max 11 chars)
            $senderName = substr(trim($senderName), 0, 11);
            
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET sms_sender_name = ?, updated_at = NOW() 
                WHERE company_id = ?
            ");
            $result = $stmt->execute([$senderName, $companyId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::setSenderName error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get SMS account status
     * 
     * @param int $companyId Company ID
     * @return string|false Status or false if error
     */
    public function getStatus($companyId) {
        try {
            $account = $this->getOrCreateAccount($companyId);
            return $account ? $account['status'] : false;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::getStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update account status
     * 
     * @param int $companyId Company ID
     * @param string $status New status ('active' or 'suspended')
     * @return bool Success status
     */
    public function setStatus($companyId, $status) {
        try {
            if (!in_array($status, ['active', 'suspended'])) {
                return false;
            }
            
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET status = ?, updated_at = NOW() 
                WHERE company_id = ?
            ");
            $result = $stmt->execute([$status, $companyId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::setStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if company has enough SMS credits
     * 
     * @param int $companyId Company ID
     * @param int $required Number of SMS required
     * @return bool True if enough credits available
     */
    public function hasEnoughCredits($companyId, $required = 1) {
        try {
            $balance = $this->getSMSBalance($companyId);
            
            if (!$balance['success']) {
                return false;
            }
            
            return $balance['sms_remaining'] >= $required && $balance['status'] === 'active';
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::hasEnoughCredits error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sender ID for a company
     * Returns custom sender name if enabled, otherwise returns default
     * 
     * @param int $companyId Company ID
     * @param string $defaultSender Default sender ID (e.g., 'SellApp')
     * @return string Sender ID to use
     */
    public function getSenderId($companyId, $defaultSender = 'SellApp') {
        try {
            $account = $this->getOrCreateAccount($companyId);
            
            if (!$account || !$account['custom_sms_enabled']) {
                // Ensure default sender is also validated
                return substr(trim($defaultSender), 0, 11);
            }
            
            $senderName = $account['sms_sender_name'] ?: $defaultSender;
            // Ensure sender name is always max 11 characters (Arkassel API requirement)
            return substr(trim($senderName), 0, 11);
        } catch (\Exception $e) {
            error_log("CompanySMSAccount::getSenderId error: " . $e->getMessage());
            return substr(trim($defaultSender), 0, 11);
        }
    }
}

