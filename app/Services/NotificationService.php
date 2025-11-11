<?php

namespace App\Services;

use App\Services\SMSService;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

/**
 * Notification Service
 * Centralized service for sending various types of notifications
 */
class NotificationService {
    
    private $smsService;
    
    public function __construct() {
        // Ensure database is loaded before using services that need it
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        $this->smsService = new SMSService();
        
        // Load SMS configuration from database (Arkassel API settings)
        try {
            $settings = $this->getSettings();
            if (!empty($settings)) {
                $this->smsService->loadFromSettings($settings);
            }
        } catch (\Exception $e) {
            error_log("NotificationService: Failed to load SMS settings from database: " . $e->getMessage());
        }
    }
    
    /**
     * Send purchase confirmation notification
     * 
     * @param array $purchaseData Purchase information (must include phone_number and optionally company_id)
     * @return array Notification result
     */
    public function sendPurchaseConfirmation($purchaseData) {
        error_log("NotificationService::sendPurchaseConfirmation called with data: " . json_encode($purchaseData));
        
        if (!$this->smsService->isConfigured()) {
            error_log("NotificationService::sendPurchaseConfirmation - SMS service not configured");
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        // Check if SMS notifications are enabled for purchases
        $settings = $this->getSettings();
        error_log("NotificationService::sendPurchaseConfirmation - SMS settings: " . json_encode($settings));
        
        if (!isset($settings['sms_purchase_enabled']) || $settings['sms_purchase_enabled'] !== '1') {
            error_log("NotificationService::sendPurchaseConfirmation - SMS notifications disabled for purchases. sms_purchase_enabled: " . ($settings['sms_purchase_enabled'] ?? 'not set'));
            return [
                'success' => false,
                'error' => 'SMS notifications disabled for purchases'
            ];
        }
        
        $companyId = $purchaseData['company_id'] ?? null;
        
        // Check company SMS account status and balance
        if ($companyId !== null) {
            try {
                $smsAccount = new \App\Models\CompanySMSAccount();
                $balance = $smsAccount->getSMSBalance($companyId);
                error_log("NotificationService::sendPurchaseConfirmation - Company {$companyId} SMS balance: " . json_encode($balance));
                
                if (!$balance['success']) {
                    error_log("NotificationService::sendPurchaseConfirmation - Failed to get SMS balance for company {$companyId}");
                    return [
                        'success' => false,
                        'error' => 'Failed to check SMS balance'
                    ];
                }
                
                if ($balance['status'] !== 'active') {
                    error_log("NotificationService::sendPurchaseConfirmation - Company {$companyId} SMS account is not active. Status: " . ($balance['status'] ?? 'unknown'));
                    return [
                        'success' => false,
                        'error' => 'Company SMS account is not active. Status: ' . ($balance['status'] ?? 'unknown')
                    ];
                }
                
                if ($balance['sms_remaining'] < 1) {
                    error_log("NotificationService::sendPurchaseConfirmation - Company {$companyId} has insufficient SMS credits. Remaining: " . ($balance['sms_remaining'] ?? 0));
                    return [
                        'success' => false,
                        'error' => 'Insufficient SMS credits. Remaining: ' . ($balance['sms_remaining'] ?? 0),
                        'insufficient_credits' => true
                    ];
                }
            } catch (\Exception $e) {
                error_log("NotificationService::sendPurchaseConfirmation - Error checking company SMS account: " . $e->getMessage());
                // Continue anyway, let SMSService handle it
            }
        }
        
        error_log("NotificationService::sendPurchaseConfirmation - Calling SMSService::sendPurchaseConfirmation");
        $result = $this->smsService->sendPurchaseConfirmation(
            $purchaseData['phone_number'],
            $purchaseData,
            $companyId
        );
        
        error_log("NotificationService::sendPurchaseConfirmation - SMS result: " . json_encode($result));
        
        // Note: SMS logging is now handled directly in SMSService::sendRealSMS() for instant logging
        // This ensures logs are saved immediately when SMS is sent, synchronously
        
        return $result;
    }
    
    /**
     * Send repair status update notification
     * 
     * @param array $repairData Repair information (must include phone_number and optionally company_id)
     * @return array Notification result
     */
    public function sendRepairStatusUpdate($repairData) {
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        // Check if SMS notifications are enabled for repairs
        $settings = $this->getSettings();
        if (!isset($settings['sms_repair_enabled']) || $settings['sms_repair_enabled'] !== '1') {
            return [
                'success' => false,
                'error' => 'SMS notifications disabled for repairs'
            ];
        }
        
        $companyId = $repairData['company_id'] ?? null;
        $result = $this->smsService->sendRepairStatusUpdate(
            $repairData['phone_number'],
            $repairData,
            $companyId
        );
        
        // Log to sms_logs table if company_id is provided
        if ($companyId !== null) {
            $message = "Repair status update: " . ($repairData['status'] ?? 'Unknown');
            $this->logSMS(
                $companyId,
                'repair',
                $repairData['phone_number'],
                $result['success'] ?? false,
                $result['success'] ? $message : ($result['error'] ?? 'Unknown error'),
                $result['sender_id'] ?? null
            );
        }
        
        return $result;
    }
    
    /**
     * Send swap notification
     * 
     * @param array $swapData Swap information (must include phone_number and optionally company_id)
     * @return array Notification result
     */
    public function sendSwapNotification($swapData) {
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        // Check if SMS notifications are enabled for swaps
        $settings = $this->getSettings();
        if (!isset($settings['sms_swap_enabled']) || $settings['sms_swap_enabled'] !== '1') {
            return [
                'success' => false,
                'error' => 'SMS notifications disabled for swaps'
            ];
        }
        
        $companyId = $swapData['company_id'] ?? null;
        $result = $this->smsService->sendSwapNotification(
            $swapData['phone_number'],
            $swapData,
            $companyId
        );
        
        // Note: SMS logging is now handled directly in SMSService::sendRealSMS() for instant logging
        // This ensures logs are saved immediately when SMS is sent, synchronously
        // The message type 'swap' will be determined by the SMSService based on the method called
        
        return $result;
    }
    
    /**
     * Send payment reminder notification
     * 
     * @param array $paymentData Payment information (must include phone_number and optionally company_id)
     * @return array Notification result
     */
    public function sendPaymentReminder($paymentData) {
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        $companyId = $paymentData['company_id'] ?? null;
        $result = $this->smsService->sendPaymentReminder(
            $paymentData['phone_number'],
            $paymentData,
            $companyId
        );
        
        // Log to sms_logs table if company_id is provided
        if ($companyId !== null) {
            $message = "Payment reminder: Order {$paymentData['order_id']}, Amount Due: ₵{$paymentData['amount_due']}";
            $this->logSMS(
                $companyId,
                'system',
                $paymentData['phone_number'],
                $result['success'] ?? false,
                $result['success'] ? $message : ($result['error'] ?? 'Unknown error'),
                $result['sender_id'] ?? null
            );
        }
        
        return $result;
    }
    
    /**
     * Send worker account creation notification
     * 
     * @param array $accountData Account information (must include phone_number, username, password, and optionally company_id)
     * @return array Notification result
     */
    public function sendWorkerAccountNotification($accountData) {
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        // Check if SMS notifications are enabled (default to enabled if not set)
        $settings = $this->getSettings();
        if (isset($settings['sms_system_enabled']) && $settings['sms_system_enabled'] !== '1') {
            return [
                'success' => false,
                'error' => 'SMS notifications disabled'
            ];
        }
        
        $companyId = $accountData['company_id'] ?? null;
        
        // Worker account notifications are administrative SMS
        // They use system balance (not company credits) and "SellApp" as sender
        // No need to check company SMS balance for administrative messages
        
        $result = $this->smsService->sendWorkerAccountNotification(
            $accountData['phone_number'],
            $accountData,
            $companyId
        );
        
        // Note: SMS logging is handled directly in SMSService::sendRealSMS() for instant logging
        
        return $result;
    }
    
    /**
     * Send custom notification
     * 
     * @param string $phoneNumber Recipient phone number
     * @param string $message Custom message
     * @param int|null $companyId Optional company ID for quota management
     * @return array Notification result
     */
    public function sendCustomNotification($phoneNumber, $message, $companyId = null) {
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured'
            ];
        }
        
        $result = $this->smsService->sendCustomNotification($phoneNumber, $message, $companyId);
        
        // Log to sms_logs table if company_id is provided
        if ($companyId !== null) {
            $this->logSMS(
                $companyId,
                'custom',
                $phoneNumber,
                $result['success'] ?? false,
                $result['success'] ? $message : ($result['error'] ?? 'Unknown error'),
                $result['sender_id'] ?? null
            );
        }
        
        return $result;
    }
    
    /**
     * Get system settings
     * 
     * @return array Settings array
     */
    private function getSettings() {
        try {
            $db = \Database::getInstance()->getConnection();
            $query = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            return $settings;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Log notification attempt to notification_logs table
     * 
     * @param string $type Notification type
     * @param string $phoneNumber Recipient phone number
     * @param bool $success Whether notification was sent successfully
     * @param string $message Optional message or error
     * @param int|null $companyId Optional company ID
     */
    public function logNotification($type, $phoneNumber, $success, $message = '', $companyId = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if company_id column exists (for backward compatibility)
            $hasCompanyId = false;
            try {
                $checkStmt = $db->query("SHOW COLUMNS FROM notification_logs LIKE 'company_id'");
                $hasCompanyId = $checkStmt->rowCount() > 0;
            } catch (\Exception $e) {
                // Column doesn't exist, skip it
            }
            
            if ($hasCompanyId && $companyId !== null) {
                $stmt = $db->prepare("
                    INSERT INTO notification_logs (type, phone_number, company_id, success, message, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$type, $phoneNumber, $companyId, $success ? 1 : 0, $message]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO notification_logs (type, phone_number, success, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$type, $phoneNumber, $success ? 1 : 0, $message]);
            }
        } catch (\Exception $e) {
            // Log error silently to avoid breaking main functionality
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
    
    /**
     * Log SMS to sms_logs table (company-specific SMS logging)
     * 
     * @param int $companyId Company ID
     * @param string $messageType Message type (purchase, swap, repair, system, custom)
     * @param string $recipient Recipient phone number
     * @param bool $success Whether SMS was sent successfully
     * @param string $message SMS message content or error message
     * @param string|null $senderId Sender ID used
     */
    public function logSMS($companyId, $messageType, $recipient, $success, $message = '', $senderId = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if sms_logs table exists
            $tableExists = false;
            try {
                $checkStmt = $db->query("SHOW TABLES LIKE 'sms_logs'");
                $tableExists = $checkStmt->rowCount() > 0;
            } catch (\Exception $e) {
                // Table doesn't exist
            }
            
            if (!$tableExists) {
                // Fall back to notification_logs if sms_logs doesn't exist
                error_log("sms_logs table does not exist, falling back to notification_logs");
                $this->logNotification($messageType, $recipient, $success, $message, $companyId);
                return;
            }
            
            $status = $success ? 'sent' : 'failed';
            $stmt = $db->prepare("
                INSERT INTO sms_logs (company_id, message_type, recipient, message, status, sender_id, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $companyId,
                $messageType,
                $recipient,
                substr($message, 0, 5000), // Limit message length
                $status,
                $senderId
            ]);
        } catch (\Exception $e) {
            // Log error silently to avoid breaking main functionality
            error_log("Failed to log SMS: " . $e->getMessage());
            // Fall back to notification_logs
            try {
                $this->logNotification($messageType, $recipient, $success, $message, $companyId);
            } catch (\Exception $e2) {
                error_log("Failed to log to notification_logs as fallback: " . $e2->getMessage());
            }
        }
    }
}

