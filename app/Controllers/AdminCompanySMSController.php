<?php

namespace App\Controllers;

use App\Models\CompanySMSAccount;
use App\Middleware\AuthMiddleware;

/**
 * Admin Company SMS Management Controller
 * Handles SMS quota, allocation, and settings per company
 */
class AdminCompanySMSController {
    
    private $smsAccountModel;
    
    public function __construct() {
        $this->smsAccountModel = new CompanySMSAccount();
    }

    /**
     * Check authentication (session or JWT)
     * Returns user data or exits with 401
     * Prioritizes session over JWT to avoid expired token issues
     */
    private function checkAuth() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first (for web routes)
        // This prevents expired JWT tokens from causing issues when session is valid
        if (isset($_SESSION['user']) && is_array($_SESSION['user']) && 
            isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'system_admin') {
            return $_SESSION['user'];
        }
        
        // Fall back to JWT authentication only if no valid session
        // Check for Authorization header
        $headers = getallheaders() ?: [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? 
                      $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            try {
                $token = substr($authHeader, 7);
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                
                if ($payload->role !== 'system_admin') {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized role', 
                        'required' => 'system_admin'
                    ]);
                    exit;
                }
                
                // Store in session for future requests
                $_SESSION['user'] = [
                    'id' => $payload->sub ?? null,
                    'role' => $payload->role ?? null,
                    'username' => $payload->username ?? null,
                    'company_id' => $payload->company_id ?? null,
                    'company_name' => $payload->company_name ?? null
                ];
                
                return $_SESSION['user'];
            } catch (\Exception $e) {
                // JWT token expired or invalid - check session again
                // Sometimes session might have been set by another request
                if (isset($_SESSION['user']) && is_array($_SESSION['user']) && 
                    isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'system_admin') {
                    return $_SESSION['user'];
                }
                
                // No valid authentication found
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Session expired. Please login again.'
                ]);
                exit;
            }
        }
        
        // No valid authentication found (no session and no token)
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'message' => 'Please login to access this resource'
        ]);
        exit;
    }

    /**
     * Get SMS details for a company
     * GET /api/admin/company/{id}/sms/details
     */
    public function getDetails($companyId) {
        // Set JSON header first to prevent HTML errors
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');
        
        try {
            // Ensure Database class is loaded
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            
            // Check authentication - System Admin only (supports both session and JWT)
            $this->checkAuth();
            
            // Validate company ID
            $companyId = trim($companyId ?? '');
            if (empty($companyId) || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID: ' . ($companyId ?: 'empty')
                ]);
                return;
            }
            
            $companyId = (int)$companyId;
            
            // Verify company exists
            $db = \Database::getInstance()->getConnection();
            $companyCheck = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            $company = $companyCheck->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID not found: ' . $companyId
                ]);
                return;
            }
            
            $balance = $this->smsAccountModel->getSMSBalance($companyId);
            
            if (!$balance || !isset($balance['success']) || !$balance['success']) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $balance['error'] ?? 'Failed to retrieve SMS details'
                ]);
                return;
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::getDetails error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve SMS details: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle custom SMS enabled/disabled
     * POST /api/admin/company/{id}/sms/toggle
     */
    public function toggleCustomSMS($companyId) {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        try {
            // Check authentication - System Admin only (supports both session and JWT)
            $this->checkAuth();
            
            // Validate company ID
            if (!$companyId || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : false;
            
            $result = $this->smsAccountModel->toggleCustomSMSEnabled($companyId, $enabled);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update custom SMS setting'
                ]);
                return;
            }
            
            // Also update sender name if provided
            if (isset($input['sender_name']) && $enabled) {
                $this->smsAccountModel->setSenderName($companyId, $input['sender_name']);
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Custom SMS setting updated successfully'
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::toggleCustomSMS error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update setting: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Allocate/add SMS credits to a company
     * POST /api/admin/company/{id}/sms/topup
     */
    public function topUpSMS($companyId) {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        try {
            // Check authentication - System Admin only (supports both session and JWT)
            $this->checkAuth();
            
            // Validate company ID
            if (!$companyId || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $amount = isset($input['amount']) ? (int)$input['amount'] : 0;
            
            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid SMS amount. Must be greater than 0.'
                ]);
                return;
            }
            
            $result = $this->smsAccountModel->allocateSMS($companyId, $amount);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to allocate SMS credits'
                ]);
                return;
            }
            
            // Get updated balance
            $balance = $this->smsAccountModel->getSMSBalance($companyId);
            
            // Send SMS notification to manager/company about credit addition
            $smsNotificationResult = $this->notifyCompanyOfCreditAddition($companyId, $amount, $balance);
            
            // Log notification result for debugging
            if (!$smsNotificationResult['sent']) {
                error_log("AdminCompanySMSController::topUpSMS: SMS notification not sent - " . ($smsNotificationResult['error'] ?? 'Unknown reason'));
            } else {
                error_log("AdminCompanySMSController::topUpSMS: SMS notification sent successfully to " . ($smsNotificationResult['phone'] ?? 'unknown') . " (" . ($smsNotificationResult['source'] ?? 'unknown source') . ")");
            }
            
            // Include notification status in response for debugging
            $responseMessage = "Successfully allocated {$amount} SMS credits";
            if (!$smsNotificationResult['sent']) {
                $responseMessage .= ". Note: SMS notification could not be sent - " . ($smsNotificationResult['error'] ?? 'Please check manager phone number');
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $responseMessage,
                'data' => $balance,
                'sms_notification' => [
                    'sent' => $smsNotificationResult['sent'] ?? false,
                    'error' => $smsNotificationResult['error'] ?? null,
                    'phone' => $smsNotificationResult['phone'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::topUpSMS error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to allocate SMS credits: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Set total SMS credits (replace existing)
     * POST /api/admin/company/{id}/sms/set-total
     */
    public function setTotalSMS($companyId) {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        try {
            // Check authentication - System Admin only
            AuthMiddleware::handle(['system_admin']);
            
            // Validate company ID
            if (!$companyId || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $totalSMS = isset($input['total_sms']) ? (int)$input['total_sms'] : 0;
            
            if ($totalSMS < 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid SMS amount. Must be 0 or greater.'
                ]);
                return;
            }
            
            $result = $this->smsAccountModel->setTotalSMS($companyId, $totalSMS);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to set SMS credits'
                ]);
                return;
            }
            
            // Get updated balance
            $balance = $this->smsAccountModel->getSMSBalance($companyId);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => "SMS credits set to {$totalSMS}",
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::setTotalSMS error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to set SMS credits: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Set custom sender name
     * POST /api/admin/company/{id}/sms/sender-name
     */
    public function setSenderName($companyId) {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        try {
            // Check authentication - System Admin only (uses session or JWT)
            $this->checkAuth();
            
            // Validate company ID
            if (!$companyId || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $senderName = isset($input['sender_name']) ? trim($input['sender_name']) : '';
            
            if (empty($senderName)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Sender name is required'
                ]);
                return;
            }
            
            if (strlen($senderName) > 11) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Sender name must be 11 characters or less (Arkassel API requirement)'
                ]);
                return;
            }
            
            $result = $this->smsAccountModel->setSenderName($companyId, $senderName);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update sender name'
                ]);
                return;
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Sender name updated successfully'
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::setSenderName error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update sender name: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get SMS usage logs for a company
     * GET /api/admin/company/{id}/sms/logs
     */
    public function getLogs($companyId) {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        try {
            // Check authentication - System Admin only (uses session or JWT)
            $this->checkAuth();
            
            // Validate company ID
            if (!$companyId || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Check if sms_logs table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
            $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
            
            if (!$tableExists) {
                // Table doesn't exist, return empty result
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'logs' => [],
                    'message' => 'SMS logs table not yet created. Please run database migration.',
                    'pagination' => [
                        'page' => 1,
                        'limit' => 50,
                        'total' => 0,
                        'pages' => 0
                    ]
                ]);
                return;
            }
            
            // Get query parameters
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
            $offset = ($page - 1) * $limit;
            
            // Get total count
            try {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM sms_logs WHERE company_id = ?");
                $countStmt->execute([$companyId]);
                $total = (int)$countStmt->fetchColumn();
            } catch (\Exception $e) {
                error_log("Error counting SMS logs: " . $e->getMessage());
                $total = 0;
            }
            
            // Get logs
            try {
                // Use direct integer values in LIMIT to avoid binding issues
                $limitInt = (int)$limit;
                $offsetInt = (int)$offset;
                $stmt = $db->prepare("
                    SELECT 
                        message_type,
                        recipient,
                        message,
                        status,
                        sender_id,
                        sent_at
                    FROM sms_logs 
                    WHERE company_id = ? 
                    ORDER BY sent_at DESC 
                    LIMIT {$limitInt} OFFSET {$offsetInt}
                ");
                $stmt->execute([$companyId]);
                $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                error_log("AdminCompanySMSController::getLogs - Found " . count($logs) . " logs for company {$companyId}");
            } catch (\Exception $e) {
                error_log("Error fetching SMS logs: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
                $logs = [];
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => $total > 0 ? ceil($total / $limit) : 0
                ]
            ]);
        } catch (\Exception $e) {
            error_log("AdminCompanySMSController::getLogs error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve SMS logs: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Notify company manager about SMS credit addition
     * Sends administrative SMS from "SellApp" to manager/company only
     * 
     * @param int $companyId Company ID
     * @param int $amount Amount of credits added
     * @param array $balance Updated balance information
     * @return array Result with 'sent' boolean and optional 'error' message
     */
    private function notifyCompanyOfCreditAddition($companyId, $amount, $balance) {
        // Log that notification method is being called
        error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: START - Company ID: {$companyId}, Amount: {$amount}");
        
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Get company information
            $companyStmt = $db->prepare("SELECT id, name, phone_number FROM companies WHERE id = ?");
            $companyStmt->execute([$companyId]);
            $company = $companyStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                $error = "Company {$companyId} not found";
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: {$error}");
                return ['sent' => false, 'error' => $error];
            }
            
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Company found - Name: {$company['name']}, Phone: " . ($company['phone_number'] ?? 'not set'));
            
            // Get manager information (including email for fallback logging)
            $managerStmt = $db->prepare("
                SELECT id, full_name, phone_number, email, username
                FROM users 
                WHERE company_id = ? AND role = 'manager' AND is_active = 1 
                LIMIT 1
            ");
            $managerStmt->execute([$companyId]);
            $manager = $managerStmt->fetch(\PDO::FETCH_ASSOC);
            
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Manager lookup - " . ($manager ? "Found: {$manager['full_name']} ({$manager['email']}), Phone: " . ($manager['phone_number'] ?? 'not set') : 'Not found'));
            
            // Determine phone number: Prefer manager phone, fallback to company phone
            $phoneNumberToUse = null;
            $phoneSource = '';
            
            if (!empty($manager['phone_number'])) {
                $phoneNumberToUse = trim($manager['phone_number']);
                $phoneSource = "manager ({$manager['full_name']} - {$manager['email']})";
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Using manager phone: {$phoneNumberToUse}");
            } elseif (!empty($company['phone_number'])) {
                $phoneNumberToUse = trim($company['phone_number']);
                $phoneSource = "company ({$company['name']})";
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Using company phone: {$phoneNumberToUse}");
            }
            
            if (empty($phoneNumberToUse)) {
                $error = "No phone number found for company {$companyId}. Manager: " . ($manager ? ($manager['full_name'] ?? 'found but no phone') : 'not found') . ", Company phone: " . ($company['phone_number'] ?? 'not set');
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: {$error}");
                return ['sent' => false, 'error' => $error];
            }
            
            // Validate phone number format before sending
            // Phone should be in format: 0XXXXXXXXX, 233XXXXXXXXX, or +233XXXXXXXXX
            $phoneClean = preg_replace('/[^0-9+]/', '', $phoneNumberToUse);
            if (empty($phoneClean) || (strlen($phoneClean) < 9 || strlen($phoneClean) > 13)) {
                $error = "Invalid phone number format: {$phoneNumberToUse} (cleaned: {$phoneClean}). Expected format: 0XXXXXXXXX, 233XXXXXXXXX, or +233XXXXXXXXX";
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: {$error}");
                return ['sent' => false, 'error' => $error, 'phone' => $phoneNumberToUse];
            }
            
            // Prepare message - administrative message from SellApp
            $remaining = isset($balance['sms_remaining']) ? (int)$balance['sms_remaining'] : 0;
            $message = "SMS Credits Top-Up Notification\n\n";
            $message .= "Your SMS account has been topped up with {$amount} SMS credits.\n\n";
            $message .= "Credits Added: {$amount} SMS\n";
            $message .= "New Balance: {$remaining} SMS credits\n\n";
            $message .= "Thank you for using SellApp.";
            
            // Log before sending
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Preparing to send SMS to {$phoneNumberToUse} ({$phoneSource}) for company {$companyId}");
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Message: " . substr($message, 0, 100) . "...");
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Balance data: " . json_encode($balance));
            
            // Send administrative SMS (uses system balance, sender: "SellApp")
            try {
                $smsService = new \App\Services\SMSService();
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: SMS service instantiated, calling sendAdministrativeSMS...");
                $smsResult = $smsService->sendAdministrativeSMS($phoneNumberToUse, $message);
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: SMS service call completed");
            } catch (\Exception $smsException) {
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Exception calling SMS service: " . $smsException->getMessage());
                error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: Exception trace: " . $smsException->getTraceAsString());
                return ['sent' => false, 'error' => 'SMS service exception: ' . $smsException->getMessage()];
            }
            
            // Log detailed result
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition: SMS result for company {$companyId}: " . json_encode($smsResult));
            
            // Check if SMS was actually sent successfully
            $smsSuccess = isset($smsResult['success']) && $smsResult['success'] === true;
            $isSimulated = isset($smsResult['simulated']) && $smsResult['simulated'] === true;
            
            if ($smsSuccess && !$isSimulated) {
                error_log("AdminCompanySMSController: SMS credit notification sent successfully to {$phoneNumberToUse} ({$phoneSource}) for company {$companyId}");
                return ['sent' => true, 'phone' => $phoneNumberToUse, 'source' => $phoneSource, 'message_id' => $smsResult['message_id'] ?? null];
            } else {
                $errorMsg = $smsResult['error'] ?? 'Unknown error';
                $fullError = $isSimulated ? "SMS service is in simulation mode or not configured. Please configure your Arkasel API key in system settings." : $errorMsg;
                error_log("AdminCompanySMSController: Failed to send SMS credit notification for company {$companyId} to {$phoneNumberToUse}: {$fullError}");
                error_log("AdminCompanySMSController: SMS result details - success: " . ($smsSuccess ? 'true' : 'false') . ", simulated: " . ($isSimulated ? 'true' : 'false') . ", error: " . $errorMsg);
                return ['sent' => false, 'error' => $fullError, 'phone' => $phoneNumberToUse, 'source' => $phoneSource, 'simulated' => $isSimulated];
            }
        } catch (\Exception $e) {
            $error = "Exception: " . $e->getMessage();
            error_log("AdminCompanySMSController::notifyCompanyOfCreditAddition error: {$error}");
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['sent' => false, 'error' => $error];
        }
    }
}

