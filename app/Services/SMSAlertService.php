<?php

namespace App\Services;

use App\Models\CompanySMSAccount;
use App\Models\Company;

/**
 * SMS Alert Service
 * Automatically sends alerts when company SMS balance is low
 */
class SMSAlertService {
    
    private $smsAccountModel;
    private $companyModel;
    private $notificationService;
    
    public function __construct() {
        $this->smsAccountModel = new CompanySMSAccount();
        $this->companyModel = new Company();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Check if company needs low balance alert and send if necessary
     * Call this after SMS is sent/decremented
     * 
     * @param int $companyId Company ID
     * @param int $thresholdPercent Alert threshold (default: 10%)
     * @return array Result of alert check/sending
     */
    public function checkAndSendLowBalanceAlert($companyId, $thresholdPercent = 10) {
        try {
            // Get current balance
            $balance = $this->smsAccountModel->getSMSBalance($companyId);
            
            if (!$balance['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve SMS balance',
                    'alert_sent' => false
                ];
            }
            
            $usagePercent = $balance['usage_percent'] ?? 0;
            $remaining = $balance['sms_remaining'] ?? 0;
            
            // Check if balance is at or below threshold
            if ($usagePercent >= (100 - $thresholdPercent) || $remaining <= 10) {
                // Check if we've already sent an alert recently (within last 24 hours)
                // This prevents spam alerts
                $alertAlreadySent = $this->hasRecentAlert($companyId);
                
                if (!$alertAlreadySent) {
                    // Send alerts
                    $result = $this->sendLowBalanceAlerts($companyId, $balance);
                    return [
                        'success' => true,
                        'alert_sent' => $result['sent'],
                        'alert_type' => $result['type'],
                        'usage_percent' => $usagePercent,
                        'remaining' => $remaining
                    ];
                } else {
                    return [
                        'success' => true,
                        'alert_sent' => false,
                        'reason' => 'Alert already sent recently',
                        'usage_percent' => $usagePercent,
                        'remaining' => $remaining
                    ];
                }
            }
            
            return [
                'success' => true,
                'alert_sent' => false,
                'reason' => 'Balance above threshold',
                'usage_percent' => $usagePercent,
                'remaining' => $remaining
            ];
        } catch (\Exception $e) {
            error_log("SMSAlertService checkAndSendLowBalanceAlert error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alert_sent' => false
            ];
        }
    }
    
    /**
     * Send low balance alerts to manager and admin
     * 
     * @param int $companyId Company ID
     * @param array $balance Balance data
     * @return array Alert sending result
     */
    private function sendLowBalanceAlerts($companyId, $balance) {
        try {
            $company = $this->companyModel->find($companyId);
            $companyName = $company['name'] ?? 'Company #' . $companyId;
            
            $remaining = $balance['sms_remaining'] ?? 0;
            $usagePercent = $balance['usage_percent'] ?? 0;
            
            // Get company manager email/phone for notification
            $db = \Database::getInstance()->getConnection();
            $managerStmt = $db->prepare("
                SELECT email, phone_number 
                FROM users 
                WHERE company_id = ? AND role = 'manager' AND is_active = 1 
                LIMIT 1
            ");
            $managerStmt->execute([$companyId]);
            $manager = $managerStmt->fetch(\PDO::FETCH_ASSOC);
            
            // Create system notification for manager (in-app notification)
            $this->createManagerNotification($companyId, $remaining, $usagePercent);
            
            // Create system notification for admin (in-app notification)
            $this->createAdminNotification($companyId, $companyName, $remaining, $usagePercent);
            
            // Log the alert
            $this->logAlert($companyId, $remaining, $usagePercent);
            
            return [
                'sent' => true,
                'type' => 'in_app_notification'
            ];
        } catch (\Exception $e) {
            error_log("SMSAlertService sendLowBalanceAlerts error: " . $e->getMessage());
            return [
                'sent' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create in-app notification for manager
     * 
     * @param int $companyId Company ID
     * @param int $remaining SMS remaining
     * @param float $usagePercent Usage percentage
     */
    private function createManagerNotification($companyId, $remaining, $usagePercent) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Get all managers for this company
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE company_id = ? AND role = 'manager' AND is_active = 1
            ");
            $stmt->execute([$companyId]);
            $managers = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Check if notifications table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'notifications'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                foreach ($managers as $managerId) {
                    $insertStmt = $db->prepare("
                        INSERT INTO notifications (user_id, company_id, message, type, data, created_at)
                        VALUES (?, ?, ?, 'sms_alert', ?, NOW())
                    ");
                    $message = "Your SMS credits are running low. Remaining: {$remaining} SMS ({$usagePercent}% used). Please contact your administrator to top up.";
                    $data = json_encode([
                        'alert_type' => 'low_sms_balance',
                        'remaining' => $remaining,
                        'usage_percent' => $usagePercent,
                        'company_id' => $companyId
                    ]);
                    $insertStmt->execute([$managerId, $companyId, $message, $data]);
                }
            }
        } catch (\Exception $e) {
            error_log("Error creating manager notification: " . $e->getMessage());
        }
    }
    
    /**
     * Create in-app notification for system admin
     * 
     * @param int $companyId Company ID
     * @param string $companyName Company name
     * @param int $remaining SMS remaining
     * @param float $usagePercent Usage percentage
     */
    private function createAdminNotification($companyId, $companyName, $remaining, $usagePercent) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Get all system admins
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE role = 'system_admin' AND is_active = 1
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Check if notifications table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'notifications'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                foreach ($admins as $adminId) {
                    $insertStmt = $db->prepare("
                        INSERT INTO notifications (user_id, company_id, message, type, data, created_at)
                        VALUES (?, ?, ?, 'sms_alert', ?, NOW())
                    ");
                    $message = "Company '{$companyName}' SMS balance is below 10%. Remaining: {$remaining} SMS ({$usagePercent}% used).";
                    $data = json_encode([
                        'alert_type' => 'company_low_sms_balance',
                        'company_id' => $companyId,
                        'company_name' => $companyName,
                        'remaining' => $remaining,
                        'usage_percent' => $usagePercent
                    ]);
                    $insertStmt->execute([$adminId, null, $message, $data]);
                }
            }
        } catch (\Exception $e) {
            error_log("Error creating admin notification: " . $e->getMessage());
        }
    }
    
    /**
     * Check if alert was sent recently (within last 24 hours)
     * Prevents duplicate alerts
     * 
     * @param int $companyId Company ID
     * @return bool True if alert was sent recently
     */
    private function hasRecentAlert($companyId) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if sms_alerts table exists (for tracking alerts)
            // If not, use notifications table
            $tableCheck = $db->query("SHOW TABLES LIKE 'sms_alerts'");
            $useAlertsTable = $tableCheck && $tableCheck->rowCount() > 0;
            
            if ($useAlertsTable) {
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM sms_alerts 
                    WHERE company_id = ? 
                    AND alert_type = 'low_balance'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ");
                $stmt->execute([$companyId]);
                $count = (int)$stmt->fetchColumn();
                return $count > 0;
            } else {
                // Fallback: check notifications table
                $tableCheck2 = $db->query("SHOW TABLES LIKE 'notifications'");
                if ($tableCheck2 && $tableCheck2->rowCount() > 0) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) FROM notifications 
                        WHERE company_id = ? 
                        AND type = 'sms_alert'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ");
                    $stmt->execute([$companyId]);
                    $count = (int)$stmt->fetchColumn();
                    return $count > 0;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Error checking recent alerts: " . $e->getMessage());
            return false; // Allow alert if we can't check
        }
    }
    
    /**
     * Log alert to database (for tracking)
     * 
     * @param int $companyId Company ID
     * @param int $remaining SMS remaining
     * @param float $usagePercent Usage percentage
     */
    private function logAlert($companyId, $remaining, $usagePercent) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Try to create sms_alerts table if it doesn't exist
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS sms_alerts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        company_id INT NOT NULL,
                        alert_type VARCHAR(50) NOT NULL,
                        remaining_sms INT NOT NULL,
                        usage_percent DECIMAL(5,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        INDEX idx_company_id (company_id),
                        INDEX idx_created_at (created_at)
                    )
                ");
            } catch (\Exception $e) {
                // Table might already exist or creation failed
                error_log("SMS alerts table creation note: " . $e->getMessage());
            }
            
            // Insert alert log
            $stmt = $db->prepare("
                INSERT INTO sms_alerts (company_id, alert_type, remaining_sms, usage_percent, created_at)
                VALUES (?, 'low_balance', ?, ?, NOW())
            ");
            $stmt->execute([$companyId, $remaining, $usagePercent]);
        } catch (\Exception $e) {
            error_log("Error logging SMS alert: " . $e->getMessage());
            // Don't fail if logging fails
        }
    }
}

