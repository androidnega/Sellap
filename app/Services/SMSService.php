<?php

namespace App\Services;

use App\Models\CompanySMSAccount;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

/**
 * SMS Service using Arkasel API
 * Handles SMS notifications for various system events
 * Supports company-level SMS quota management
 */
class SMSService {
    
    private $apiKey;
    private $senderId;
    private $baseUrl;
    
    public function __construct($apiKey = null, $senderId = null) {
        // Ensure database is loaded before using CompanySMSAccount
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        // Priority: 1. Constructor parameters, 2. Database settings, 3. Environment variables, 4. Defaults
        $dbSettings = null;
        
        // Load from database if either parameter is not provided
        if ($apiKey === null || $senderId === null) {
            $dbSettings = $this->loadSettingsFromDatabase();
        }
        
        if ($apiKey !== null) {
            $this->apiKey = $apiKey;
        } else {
            // Try to load from database first if no parameter provided
            $this->apiKey = $dbSettings['sms_api_key'] ?? getenv('ARKASEL_API_KEY') ?: '';
        }
        
        if ($senderId !== null) {
            $senderIdRaw = $senderId;
        } else {
            // Try to load from database first if no parameter provided
            $senderIdRaw = $dbSettings['sms_sender_id'] ?? getenv('ARKASEL_SENDER_ID') ?: 'SellApp';
        }
        
        // Arkassel API requires sender ID to be maximum 11 characters
        $this->senderId = $this->validateAndTruncateSenderId($senderIdRaw);
        
        // Use the correct Arkasel SMS API endpoint
        // According to Arkasel documentation: https://docs.arkesel.com
        $this->baseUrl = getenv('ARKASEL_API_URL') ?: 'https://sms.arkesel.com/api/v2/sms/send';
    }
    
    /**
     * Load settings from database
     * 
     * @return array Settings array
     */
    private function loadSettingsFromDatabase() {
        try {
            $db = \Database::getInstance()->getConnection();
            $query = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_api_key', 'sms_sender_id')");
            $results = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            return is_array($results) ? $results : [];
        } catch (\Exception $e) {
            error_log("SMSService: Failed to load settings from database: " . $e->getMessage());
            return [];
        } catch (\Error $e) {
            error_log("SMSService: Fatal error loading settings from database: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate and truncate sender ID to maximum 11 characters (Arkassel API requirement)
     * 
     * @param string $senderId Original sender ID
     * @return string Validated and truncated sender ID (max 11 characters)
     */
    private function validateAndTruncateSenderId($senderId) {
        if (empty($senderId)) {
            return 'SellApp';
        }
        
        // Remove whitespace and ensure it's a string
        $senderId = trim((string)$senderId);
        
        // Arkassel API maximum sender ID length is 11 characters
        // Truncate if longer than 11 characters
        if (strlen($senderId) > 11) {
            $truncated = substr($senderId, 0, 11);
            error_log("SMSService: Sender ID '{$senderId}' truncated to 11 characters: '{$truncated}' (Arkassel API requirement)");
            return $truncated;
        }
        
        return $senderId;
    }
    
    /**
     * Send SMS message
     * 
     * @param string $phoneNumber Recipient phone number (with country code)
     * @param string $message SMS message content
     * @param string $type Message type (transactional, promotional)
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendSMS($phoneNumber, $message, $type = 'transactional', $companyId = null) {
        try {
            // Check company SMS balance if companyId is provided
            if ($companyId !== null) {
                $smsAccount = new CompanySMSAccount();
                if (!$smsAccount->hasEnoughCredits($companyId, 1)) {
                    $balance = $smsAccount->getSMSBalance($companyId);
                    return [
                        'success' => false,
                        'error' => 'Insufficient SMS credits. Remaining: ' . ($balance['sms_remaining'] ?? 0),
                        'company_id' => $companyId,
                        'insufficient_credits' => true
                    ];
                }
                
                // Get company-specific sender ID - use company-branded name if custom SMS is enabled
                try {
                    $account = $smsAccount->getOrCreateAccount($companyId);
                    
                    // Check if custom SMS is enabled for this company
                    if ($account && ($account['custom_sms_enabled'] ?? false)) {
                        // Custom SMS is enabled - use the custom sender name if set, otherwise use company name
                        if (!empty($account['sms_sender_name'])) {
                            $companySenderId = $account['sms_sender_name'];
                        } else {
                            // No custom sender name set, use company name
                            $companyModel = new \App\Models\Company();
                            $company = $companyModel->find($companyId);
                            if ($company && !empty($company['name'])) {
                                $companySenderId = $this->validateAndTruncateSenderId($company['name']);
                            } else {
                                $companySenderId = $this->senderId;
                            }
                        }
                    } else {
                        // Custom SMS is NOT enabled - use default "SellApp"
                        $companySenderId = $this->senderId;
                    }
                } catch (\Exception $e) {
                    error_log("SMSService::sendSMS: Could not fetch company SMS account: " . $e->getMessage());
                    $companySenderId = $this->senderId;
                }
                
                $senderIdToUse = $this->validateAndTruncateSenderId($companySenderId);
            } else {
                $senderIdToUse = $this->validateAndTruncateSenderId($this->senderId);
            }
            
            // Validate phone number format
            $phoneNumberFormatted = $this->formatPhoneNumber($phoneNumber, true); // Get format for API (without +)
            if (!$phoneNumberFormatted) {
                return [
                    'success' => false,
                    'error' => 'Invalid phone number format'
                ];
            }
            
            // If no API key is configured, simulate SMS sending for testing
            if (empty($this->apiKey) || $this->apiKey === 'test') {
                // For simulation, use formatted number with +
                $result = $this->simulateSMSSending($this->formatPhoneNumber($phoneNumber, false), $message);
                // Decrement company SMS if in simulation mode and companyId provided
                if ($companyId !== null && $result['success']) {
                    try {
                        $smsAccount = new CompanySMSAccount();
                        $balanceBefore = $smsAccount->getSMSBalance($companyId);
                        $remainingBefore = $balanceBefore['sms_remaining'] ?? 0;
                        
                        $decrementResult = $smsAccount->decrementSMS($companyId, 1);
                        if ($decrementResult) {
                            error_log("SMSService::sendSMS (simulation): Successfully decremented SMS for company {$companyId}");
                            $balanceAfter = $smsAccount->getSMSBalance($companyId);
                            if ($balanceAfter['success']) {
                                $remainingAfter = $balanceAfter['sms_remaining'] ?? 0;
                                error_log("SMSService::sendSMS (simulation): Company {$companyId} SMS balance: {$remainingBefore} -> {$remainingAfter}");
                            }
                        } else {
                            error_log("SMSService::sendSMS (simulation): WARNING - Failed to decrement SMS for company {$companyId}");
                        }
                        
                        // Log SMS to sms_logs table immediately (even in simulation mode)
                        try {
                            $messageType = $type === 'transactional' ? 'purchase' : ($type === 'promotional' ? 'custom' : 'system');
                            $this->logSMSToDatabase($companyId, $messageType, $phoneNumberFormatted, true, $message, $senderIdToUse);
                        } catch (\Exception $e) {
                            error_log("SMSService::sendSMS (simulation): Failed to log SMS to database: " . $e->getMessage());
                        }
                    } catch (\Exception $e) {
                        error_log("SMSService::sendSMS (simulation): Exception while decrementing SMS: " . $e->getMessage());
                        error_log("SMSService::sendSMS (simulation): Exception trace: " . $e->getTraceAsString());
                    }
                }
                return $result;
            }
            
            // Prepare request data for Arkasel API v2
            // Format recipients as array (API format without +)
            $recipients = is_array($phoneNumberFormatted) ? $phoneNumberFormatted : [$phoneNumberFormatted];
            
            // For Arkasel API v2, API key goes in header, not body
            // Recipients should be without + prefix (e.g., "233544919953" not "+233544919953")
            // Ensure sender ID is validated one more time before sending (double-check)
            $finalSenderId = $this->validateAndTruncateSenderId($senderIdToUse);
            $data = [
                'sender' => $finalSenderId,
                'message' => $message,
                'recipients' => $recipients
            ];
            
            // Make API request with API key in header
            error_log("SMSService::sendSMS: Sending SMS with sender ID '{$finalSenderId}' to {$phoneNumberFormatted} for company {$companyId}");
            
            $response = $this->makeRequest($data, trim($this->apiKey));
            
            error_log("SMSService::sendSMS: API response - success: " . ($response['success'] ? 'true' : 'false'));
            if (!$response['success']) {
                error_log("SMSService::sendSMS: API error - " . ($response['error'] ?? 'Unknown error'));
            }
            
            // CRITICAL: Only deduct credits if SMS was actually sent successfully
            // Double-check that response indicates true success
            $isTrulySuccessful = $response['success'] === true && 
                                !isset($response['error']) && 
                                !isset($response['simulated']);
            
            if ($isTrulySuccessful) {
                // Decrement company SMS credits ONLY on successful send
                if ($companyId !== null) {
                    try {
                        $smsAccount = new CompanySMSAccount();
                        // Get balance before decrement
                        $balanceBefore = $smsAccount->getSMSBalance($companyId);
                        $remainingBefore = $balanceBefore['sms_remaining'] ?? 0;
                        
                        error_log("SMSService::sendSMS: SMS sent successfully, deducting 1 credit from company {$companyId}");
                        error_log("SMSService::sendSMS: Before decrement - company {$companyId} has {$remainingBefore} SMS remaining");
                        
                        $decrementResult = $smsAccount->decrementSMS($companyId, 1);
                        if ($decrementResult) {
                            error_log("SMSService::sendSMS: Successfully decremented SMS for company {$companyId}");
                            // Get updated balance for logging
                            $balanceAfter = $smsAccount->getSMSBalance($companyId);
                            if ($balanceAfter['success']) {
                                $remainingAfter = $balanceAfter['sms_remaining'] ?? 0;
                                error_log("SMSService::sendSMS: Company {$companyId} SMS balance: {$remainingBefore} -> {$remainingAfter}");
                            }
                        } else {
                            error_log("SMSService::sendSMS: WARNING - Failed to decrement SMS for company {$companyId} - decrementSMS returned false");
                        }
                    } catch (\Exception $e) {
                        error_log("SMSService::sendSMS: Exception while decrementing SMS: " . $e->getMessage());
                        error_log("SMSService::sendSMS: Exception trace: " . $e->getTraceAsString());
                        // Don't fail the SMS send if decrement fails - log it but continue
                    }
                }
            } else {
                error_log("SMSService::sendSMS: SMS send FAILED - NOT deducting credits. Response: " . json_encode($response));
            }
            
            if ($isTrulySuccessful) {
                
                // Extract message ID from response - Arkasel might return different formats
                $messageId = null;
                if (isset($response['data']['sid'])) {
                    $messageId = $response['data']['sid'];
                } elseif (isset($response['data']['message_id'])) {
                    $messageId = $response['data']['message_id'];
                } elseif (isset($response['data']['id'])) {
                    $messageId = $response['data']['id'];
                } elseif (isset($response['data']['data']) && isset($response['data']['data']['sid'])) {
                    $messageId = $response['data']['data']['sid'];
                }
                
                // Log SMS to sms_logs table immediately (synchronously, instantly)
                // This ensures logs appear immediately in SMS history
                if ($companyId !== null) {
                    try {
                        // Determine message type from context (default to 'purchase' for sales)
                        $messageType = $type === 'transactional' ? 'purchase' : ($type === 'promotional' ? 'custom' : 'system');
                        $this->logSMSToDatabase($companyId, $messageType, $phoneNumberFormatted, true, $message, $senderIdToUse);
                    } catch (\Exception $e) {
                        error_log("SMSService::sendSMS: Failed to log SMS to database: " . $e->getMessage());
                        // Don't fail the SMS send if logging fails
                    }
                }
                
                return [
                    'success' => true,
                    'message_id' => $messageId ?? 'sent',
                    'recipients' => 1,
                    'cost' => 0,
                    'sender_id' => $senderIdToUse,
                    'company_id' => $companyId
                ];
            } else {
                // Log failed SMS attempt
                if ($companyId !== null) {
                    try {
                        $phoneNumberFormatted = $this->formatPhoneNumber($phoneNumber, true);
                        if ($phoneNumberFormatted) {
                            $this->logSMSToDatabase($companyId, 'purchase', $phoneNumberFormatted, false, $response['error'] ?? 'Failed to send SMS', $senderIdToUse);
                        }
                    } catch (\Exception $e) {
                        error_log("SMSService::sendSMS: Failed to log failed SMS to database: " . $e->getMessage());
                    }
                }
                
                // Return error instead of falling back to simulation
                // This ensures users know when SMS is not actually being sent
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to send SMS',
                    'details' => $response,
                    'http_code' => $response['http_code'] ?? null,
                    'response' => $response['response'] ?? null
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Simulate SMS sending for testing purposes
     * 
     * @param string $phoneNumber Recipient phone number
     * @param string $message SMS message content
     * @return array Simulated send result
     */
    private function simulateSMSSending($phoneNumber, $message) {
        // Only log in debug mode for faster execution
        if (getenv('APP_DEBUG') === 'true') {
            error_log("SIMULATED SMS: To: $phoneNumber, Message: $message");
        }
        
        return [
            'success' => true,
            'message_id' => 'SIM_' . uniqid(),
            'recipients' => 1,
            'cost' => 0,
            'simulated' => true
        ];
    }
    
    /**
     * Send purchase confirmation SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param array $purchaseData Purchase information
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendPurchaseConfirmation($phoneNumber, $purchaseData, $companyId = null) {
        // Get company name for personalized message
        $companyName = 'SellApp';
        if ($companyId !== null) {
            try {
                $companyModel = new \App\Models\Company();
                $company = $companyModel->find($companyId);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("SMSService::sendPurchaseConfirmation: Could not fetch company name: " . $e->getMessage());
            }
        }
        
        $message = "Hello! Your purchase has been confirmed.\n";
        $message .= "Order ID: {$purchaseData['order_id']}\n";
        $message .= "Total Amount: ₵{$purchaseData['amount']}\n";
        
        // Include payment information if partial payments are involved
        if (!empty($purchaseData['payment_info'])) {
            $paymentInfo = $purchaseData['payment_info'];
            $totalPaid = floatval($paymentInfo['total_paid'] ?? 0);
            $remaining = floatval($paymentInfo['remaining'] ?? 0);
            $status = strtoupper($paymentInfo['payment_status'] ?? 'PAID');
            
            if ($status === 'PARTIAL' && $remaining > 0) {
                $message .= "Amount Paid: ₵" . number_format($totalPaid, 2) . "\n";
                $message .= "Remaining: ₵" . number_format($remaining, 2) . "\n";
                $message .= "Status: Partial Payment\n";
            } elseif ($status === 'PAID') {
                $message .= "Payment: Fully Paid\n";
            }
        }
        
        if (!empty($purchaseData['items'])) {
            $message .= "Items: {$purchaseData['items']}\n";
        }
        $message .= "Thank you for choosing {$companyName}!";
        
        // Use sendRealSMS for instant delivery without simulation fallback
        // This will use company-branded sender name if custom SMS is enabled
        return $this->sendRealSMS($phoneNumber, $message, $companyId);
    }
    
    /**
     * Send repair status update SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param array $repairData Repair information
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendRepairStatusUpdate($phoneNumber, $repairData, $companyId = null) {
        $messageType = $repairData['message_type'] ?? 'generic';
        $repairId = $repairData['repair_id'] ?? 'N/A';
        $device = $repairData['device'] ?? 'Device';
        
        // Get company name for personalized message
        $companyName = 'SellApp';
        if ($companyId !== null) {
            try {
                $companyModel = new \App\Models\Company();
                $company = $companyModel->find($companyId);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("SMSService::sendRepairStatusUpdate: Could not fetch company name: " . $e->getMessage());
            }
        }
        
        // Different messages based on message type
        switch ($messageType) {
            case 'repair_started':
                $message = "Hello! Your repair has started.\n\n";
                $message .= "Repair ID: {$repairId}\n";
                $message .= "Device: {$device}\n";
                $message .= "Status: Repair in progress\n\n";
                $message .= "We'll notify you when it's ready for pickup.\n";
                $message .= "Thank you for choosing {$companyName}!";
                break;
                
            case 'repair_ready':
                $message = "Good news! Your repair is ready for pickup.\n\n";
                $message .= "Repair ID: {$repairId}\n";
                $message .= "Device: {$device}\n";
                $message .= "Status: Completed\n\n";
                $message .= "Please visit us to collect your device.\n";
                $message .= "Thank you for choosing {$companyName}!";
                break;
                
            case 'repair_failed':
                $message = "We regret to inform you that your repair was unsuccessful.\n\n";
                $message .= "Repair ID: {$repairId}\n";
                $message .= "Device: {$device}\n";
                $message .= "Status: Repair Failed\n\n";
                $message .= "Please contact us to discuss next steps.\n";
                $message .= "Thank you for choosing {$companyName}!";
                break;
                
            default:
                $message = "Repair Status Update\n";
                $message .= "Repair ID: {$repairId}\n";
                $message .= "Device: {$device}\n";
                $message .= "Status: {$repairData['status']}\n";
                $message .= "Estimated completion: {$repairData['completion_date']}\n";
                $message .= "Thank you for choosing {$companyName}!";
                break;
        }
        
        // Use sendRealSMS for instant delivery without simulation fallback
        return $this->sendRealSMS($phoneNumber, $message, $companyId);
    }
    
    /**
     * Send swap notification SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param array $swapData Swap information
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendSwapNotification($phoneNumber, $swapData, $companyId = null) {
        // Get company name for personalized message
        $companyName = 'SellApp';
        if ($companyId !== null) {
            try {
                $companyModel = new \App\Models\Company();
                $company = $companyModel->find($companyId);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("SMSService::sendSwapNotification: Could not fetch company name: " . $e->getMessage());
            }
        }
        
        $message = "Hello! Your swap transaction has been completed.\n\n";
        if (isset($swapData['transaction_code'])) {
            $message .= "Transaction Code: {$swapData['transaction_code']}\n";
        }
        if (isset($swapData['customer_brand']) && isset($swapData['customer_model'])) {
            $message .= "Your Device: {$swapData['customer_brand']} {$swapData['customer_model']}\n";
        }
        if (isset($swapData['company_product_name'])) {
            $message .= "Swapped For: {$swapData['company_product_name']}\n";
        }
        if (isset($swapData['added_cash']) && $swapData['added_cash'] > 0) {
            $message .= "Cash Added: ₵" . number_format($swapData['added_cash'], 2) . "\n";
        }
        $message .= "\nThank you for choosing {$companyName}!";
        
        // Use sendRealSMS for instant delivery without simulation fallback
        // Pass 'swap' as message type for proper logging
        return $this->sendRealSMS($phoneNumber, $message, $companyId, 'swap');
    }
    
    /**
     * Send payment reminder SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param array $paymentData Payment information
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendPaymentReminder($phoneNumber, $paymentData, $companyId = null) {
        $message = "Payment Reminder\n";
        $message .= "Order ID: {$paymentData['order_id']}\n";
        $message .= "Amount Due: ₵{$paymentData['amount_due']}\n";
        $message .= "Due Date: {$paymentData['due_date']}\n";
        $message .= "Please complete your payment to avoid delays.";
        
        // Use sendRealSMS for instant delivery without simulation fallback
        return $this->sendRealSMS($phoneNumber, $message, $companyId);
    }
    
    /**
     * Send worker account creation SMS (Administrative)
     * Uses system SMS balance and "SellApp" as sender
     * 
     * @param string $phoneNumber Worker phone number
     * @param array $accountData Account information (username, password, company_id)
     * @param int|null $companyId Optional company ID (for message personalization only, not for credit deduction)
     * @return array Send result
     */
    public function sendWorkerAccountNotification($phoneNumber, $accountData, $companyId = null) {
        // Get company name for personalized message (but SMS is administrative, uses system balance)
        $companyName = 'SellApp';
        if ($companyId !== null) {
            try {
                $companyModel = new \App\Models\Company();
                $company = $companyModel->find($companyId);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("SMSService::sendWorkerAccountNotification: Could not fetch company name: " . $e->getMessage());
            }
        }
        
        // Get login URL dynamically
        $appUrl = defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: 'http://localhost');
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        $loginUrl = rtrim($appUrl . $basePath, '/');
        $message = "Hello! Your {$companyName} account has been created.\n\n";
        $message .= "Username: {$accountData['username']}\n";
        $message .= "Password: {$accountData['password']}\n\n";
        $message .= "Login at: {$loginUrl}\n\n";
        $message .= "Welcome to the team!";
        
        // Use administrative SMS method - uses system balance, not company credits
        // Always uses "SellApp" as sender
        return $this->sendAdministrativeSMS($phoneNumber, $message);
    }
    
    /**
     * Send administrative SMS (password reset, account notifications, etc.)
     * Uses system SMS balance (not company credits) and "SellApp" as sender
     * 
     * @param string $phoneNumber Recipient phone number
     * @param string $message SMS message content
     * @return array Send result
     */
    public function sendAdministrativeSMS($phoneNumber, $message) {
        // Administrative SMS always uses "SellApp" as sender and system balance
        // Pass null for companyId to avoid deducting from company credits
        error_log("SMSService::sendAdministrativeSMS: Sending administrative SMS to {$phoneNumber} using system balance");
        return $this->sendRealSMS($phoneNumber, $message, null, 'system');
    }
    
    /**
     * Send partial payment notification SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param array $paymentData Payment information (sale_id, amount_paid, remaining, total, is_complete)
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendPartialPaymentNotification($phoneNumber, $paymentData, $companyId = null) {
        // Get company name for personalized message
        $companyName = 'SellApp';
        if ($companyId !== null) {
            try {
                $companyModel = new \App\Models\Company();
                $company = $companyModel->find($companyId);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("SMSService::sendPartialPaymentNotification: Could not fetch company name: " . $e->getMessage());
            }
        }
        
        $saleId = $paymentData['sale_id'] ?? 'N/A';
        $amountPaid = number_format($paymentData['amount_paid'] ?? 0, 2);
        $remaining = number_format($paymentData['remaining'] ?? 0, 2);
        $total = number_format($paymentData['total'] ?? 0, 2);
        $isComplete = $paymentData['is_complete'] ?? false;
        
        if ($isComplete) {
            // Payment completed
            $message = "Payment Complete!\n\n";
            $message .= "Sale ID: {$saleId}\n";
            $message .= "Total Amount: ₵{$total}\n";
            $message .= "Thank you for your payment!\n\n";
            $message .= "{$companyName}";
        } else {
            // Partial payment
            $message = "Payment Received\n\n";
            $message .= "Sale ID: {$saleId}\n";
            $message .= "Amount Paid: ₵{$amountPaid}\n";
            $message .= "Remaining Balance: ₵{$remaining}\n";
            $message .= "Total Amount: ₵{$total}\n\n";
            $message .= "Please complete your payment.\n\n";
            $message .= "{$companyName}";
        }
        
        // Use sendRealSMS for instant delivery without simulation fallback
        // Pass 'payment' as message type for proper logging
        return $this->sendRealSMS($phoneNumber, $message, $companyId, 'payment');
    }
    
    /**
     * Send custom notification SMS
     * 
     * @param string $phoneNumber Customer phone number
     * @param string $message Custom message
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendCustomNotification($phoneNumber, $message, $companyId = null) {
        // Use sendRealSMS for instant delivery without simulation fallback
        // Pass 'custom' as message type for proper logging
        return $this->sendRealSMS($phoneNumber, $message, $companyId, 'custom');
    }
    
    /**
     * Send real SMS without simulation fallback (for testing)
     * This method will return error if API key is missing or request fails
     * 
     * @param string $phoneNumber Customer phone number
     * @param string $message SMS message content
     * @param int|null $companyId Optional company ID for quota management
     * @param string $messageType Message type for logging ('purchase', 'swap', 'repair', 'system', 'custom', 'test_sms')
     * @return array Send result
     */
    public function sendRealSMS($phoneNumber, $message, $companyId = null, $messageType = 'purchase') {
        try {
            // Check company SMS balance if companyId is provided
            if ($companyId !== null) {
                $smsAccount = new CompanySMSAccount();
                if (!$smsAccount->hasEnoughCredits($companyId, 1)) {
                    $balance = $smsAccount->getSMSBalance($companyId);
                    return [
                        'success' => false,
                        'error' => 'Insufficient SMS credits. Remaining: ' . ($balance['sms_remaining'] ?? 0),
                        'company_id' => $companyId,
                        'insufficient_credits' => true
                    ];
                }
                
                // Get company-specific sender ID - use company-branded name if custom SMS is enabled
                try {
                    $account = $smsAccount->getOrCreateAccount($companyId);
                    
                    // Check if custom SMS is enabled for this company
                    if ($account && ($account['custom_sms_enabled'] ?? false)) {
                        // Custom SMS is enabled - use the custom sender name if set, otherwise use company name
                        if (!empty($account['sms_sender_name'])) {
                            $companySenderId = $account['sms_sender_name'];
                        } else {
                            // No custom sender name set, use company name
                            $companyModel = new \App\Models\Company();
                            $company = $companyModel->find($companyId);
                            if ($company && !empty($company['name'])) {
                                $companySenderId = $this->validateAndTruncateSenderId($company['name']);
                            } else {
                                $companySenderId = $this->senderId;
                            }
                        }
                    } else {
                        // Custom SMS is NOT enabled - use default "SellApp"
                        $companySenderId = $this->senderId;
                    }
                } catch (\Exception $e) {
                    error_log("SMSService::sendRealSMS: Could not fetch company SMS account: " . $e->getMessage());
                    $companySenderId = $this->senderId;
                }
                
                $senderIdToUse = $this->validateAndTruncateSenderId($companySenderId);
            } else {
                // Administrative SMS - always use "SellApp" as sender and system balance (no company credits)
                $senderIdToUse = $this->validateAndTruncateSenderId($this->senderId);
                error_log("SMSService::sendRealSMS: Administrative SMS - using system balance, sender: {$senderIdToUse}");
            }
            
            // Validate phone number format
            $phoneNumberFormatted = $this->formatPhoneNumber($phoneNumber, true); // Get format for API (without +)
            if (!$phoneNumberFormatted) {
                return [
                    'success' => false,
                    'error' => 'Invalid phone number format'
                ];
            }
            
            // Require API key for real SMS sending
            if (empty($this->apiKey) || $this->apiKey === 'test' || trim($this->apiKey) === '') {
                return [
                    'success' => false,
                    'error' => 'SMS API key is required for sending real SMS. Please configure your Arkasel API key in system settings.',
                    'simulated' => false
                ];
            }
            
            // Prepare request data for Arkasel API v2
            // Format recipients as array (API format without +)
            $recipients = is_array($phoneNumberFormatted) ? $phoneNumberFormatted : [$phoneNumberFormatted];
            
            // For Arkasel API v2, API key goes in header, not body
            // Recipients should be without + prefix (e.g., "233544919953" not "+233544919953")
            // Ensure sender ID is validated one more time before sending (double-check)
            $finalSenderId = $this->validateAndTruncateSenderId($senderIdToUse);
            $data = [
                'sender' => $finalSenderId,
                'message' => $message,
                'recipients' => $recipients
            ];
            
            if ($companyId !== null) {
                error_log("SMSService::sendRealSMS: Sending BUSINESS SMS with sender ID '{$finalSenderId}' to {$phoneNumberFormatted} for company {$companyId}");
            } else {
                error_log("SMSService::sendRealSMS: Sending ADMINISTRATIVE SMS with sender ID '{$finalSenderId}' to {$phoneNumberFormatted} (using system balance)");
            }
            error_log("SMSService::sendRealSMS: Original phone number: {$phoneNumber}, Formatted: {$phoneNumberFormatted}");
            
            // Make API request with API key in header
            $response = $this->makeRequest($data, trim($this->apiKey));
            
            error_log("SMSService::sendRealSMS: API response - success: " . ($response['success'] ? 'true' : 'false'));
            if (!$response['success']) {
                error_log("SMSService::sendRealSMS: API error details: " . json_encode($response));
            }
            
            // CRITICAL: Only deduct credits if SMS was actually sent successfully
            // Trust makeRequest's success determination - if it says success=true, treat as success
            // makeRequest already checked HTTP codes and error indicators
            $isTrulySuccessful = false;
            
            if (isset($response['success']) && $response['success'] === true) {
                // makeRequest returned success=true - trust it (it already validated HTTP codes and error indicators)
                $isTrulySuccessful = true;
                error_log("SMSService::sendRealSMS: makeRequest returned success=true - treating as successful");
            } else {
                // makeRequest returned success=false or not set - this is a failure
                $isTrulySuccessful = false;
                // Try to extract a meaningful error message
                $errorMsg = 'Unknown error';
                if (isset($response['error']) && !empty($response['error'])) {
                    $errorMsg = is_string($response['error']) ? $response['error'] : json_encode($response['error']);
                } elseif (isset($response['http_code'])) {
                    $errorMsg = 'HTTP Error ' . $response['http_code'];
                    if (isset($response['data']['message'])) {
                        $errorMsg .= ': ' . $response['data']['message'];
                    } elseif (isset($response['data']['error'])) {
                        $errorMsg .= ': ' . (is_string($response['data']['error']) ? $response['data']['error'] : json_encode($response['data']['error']));
                    }
                } elseif (isset($response['response'])) {
                    // Try to extract error from raw response
                    $rawResponse = is_string($response['response']) ? $response['response'] : json_encode($response['response']);
                    if (!empty($rawResponse)) {
                        $errorMsg = 'API Error: ' . substr($rawResponse, 0, 100);
                    }
                }
                error_log("SMSService::sendRealSMS: makeRequest returned success=false or not set - treating as failure. Error: {$errorMsg}, Full response: " . json_encode($response));
            }
            
            // Log the decision
            error_log("SMSService::sendRealSMS: Success check - response['success']: " . ($response['success'] ?? 'not set') . 
                     ", isTrulySuccessful: " . ($isTrulySuccessful ? 'true' : 'false') .
                     ", companyId: " . ($companyId ?? 'null (administrative)'));
            
            if ($isTrulySuccessful) {
                // Only deduct company SMS credits if companyId is provided (business SMS)
                // Administrative SMS (companyId = null) uses system balance directly from Arkasel
                if ($companyId !== null) {
                    try {
                        $smsAccount = new CompanySMSAccount();
                        // Get balance before decrement
                        $balanceBefore = $smsAccount->getSMSBalance($companyId);
                        $remainingBefore = $balanceBefore['sms_remaining'] ?? 0;
                        
                        error_log("SMSService::sendRealSMS: Business SMS sent successfully, deducting 1 credit from company {$companyId}");
                        $decrementResult = $smsAccount->decrementSMS($companyId, 1);
                        if ($decrementResult) {
                            error_log("SMSService::sendRealSMS: Successfully decremented SMS for company {$companyId}");
                            // Get updated balance for logging
                            $balanceAfter = $smsAccount->getSMSBalance($companyId);
                            if ($balanceAfter['success']) {
                                $remainingAfter = $balanceAfter['sms_remaining'] ?? 0;
                                error_log("SMSService::sendRealSMS: Company {$companyId} SMS balance: {$remainingBefore} -> {$remainingAfter}");
                            }
                        } else {
                            error_log("SMSService::sendRealSMS: WARNING - Failed to decrement SMS for company {$companyId} - decrementSMS returned false");
                        }
                    } catch (\Exception $e) {
                        error_log("SMSService::sendRealSMS: Exception while decrementing SMS: " . $e->getMessage());
                        error_log("SMSService::sendRealSMS: Exception trace: " . $e->getTraceAsString());
                        // Don't fail the SMS send if decrement fails - log it but continue
                    }
                } else {
                    error_log("SMSService::sendRealSMS: Administrative SMS sent successfully - using system balance (NOT deducting from company credits)");
                }
            } else {
                error_log("SMSService::sendRealSMS: SMS send FAILED - NOT deducting credits. Full response: " . json_encode($response));
                if ($companyId !== null) {
                    error_log("SMSService::sendRealSMS: Company {$companyId} credits will NOT be deducted due to SMS failure");
                }
            }
            
            if ($isTrulySuccessful) {
                
                // Extract message ID from Arkasel response
                $messageId = null;
                if (isset($response['data']['sid'])) {
                    $messageId = $response['data']['sid'];
                } elseif (isset($response['data']['message_id'])) {
                    $messageId = $response['data']['message_id'];
                } elseif (isset($response['data']['id'])) {
                    $messageId = $response['data']['id'];
                } elseif (isset($response['data']['data']) && isset($response['data']['data']['sid'])) {
                    $messageId = $response['data']['data']['sid'];
                } else {
                    // Use response code or status if available
                    $messageId = $response['data']['status'] ?? 'sent';
                }
                
                // Log SMS to sms_logs table immediately (synchronously, instantly)
                // Only log to company logs if companyId is provided (business SMS)
                // Administrative SMS (companyId = null) are logged but not associated with a company
                try {
                    // For administrative SMS, use companyId = 0 or null to indicate system SMS
                    $logCompanyId = $companyId ?? 0;
                    $this->logSMSToDatabase($logCompanyId, $messageType, $phoneNumberFormatted, true, $message, $senderIdToUse);
                } catch (\Exception $e) {
                    error_log("SMSService::sendRealSMS: Failed to log SMS to database: " . $e->getMessage());
                    // Don't fail the SMS send if logging fails
                }
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'recipients' => 1,
                    'cost' => 0,
                    'sender_id' => $senderIdToUse,
                    'company_id' => $companyId,
                    'simulated' => false
                ];
            } else {
                // Log failed SMS attempt
                // Log to company logs if companyId provided, otherwise log as system SMS (companyId = 0)
                try {
                    $logCompanyId = $companyId ?? 0;
                    $errorMsg = $response['error'] ?? 'Failed to send SMS';
                    $this->logSMSToDatabase($logCompanyId, $messageType, $phoneNumberFormatted, false, $errorMsg, $senderIdToUse);
                } catch (\Exception $e) {
                    error_log("SMSService::sendRealSMS: Failed to log failed SMS to database: " . $e->getMessage());
                }
                
                // Return actual error with detailed information
                // Extract error from multiple possible locations in response
                $errorMessage = 'Failed to send SMS';
                
                // Check response structure for error details
                if (isset($response['error']) && !empty($response['error'])) {
                    $errorMessage = is_string($response['error']) ? $response['error'] : json_encode($response['error']);
                } elseif (isset($response['data']['error'])) {
                    $errorMessage = is_string($response['data']['error']) ? $response['data']['error'] : json_encode($response['data']['error']);
                } elseif (isset($response['data']['message']) && (stripos($response['data']['message'], 'error') !== false || stripos($response['data']['message'], 'fail') !== false)) {
                    $errorMessage = $response['data']['message'];
                } elseif (isset($response['http_code'])) {
                    $errorMessage = 'HTTP Error ' . $response['http_code'];
                    if (isset($response['response'])) {
                        $responseText = is_string($response['response']) ? $response['response'] : json_encode($response['response']);
                        // Try to extract error from response text
                        $decoded = json_decode($responseText, true);
                        if ($decoded && isset($decoded['message'])) {
                            $errorMessage .= ': ' . $decoded['message'];
                        } elseif ($decoded && isset($decoded['error'])) {
                            $errorMessage .= ': ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                        } else {
                            $errorMessage .= ' - ' . substr($responseText, 0, 200);
                        }
                    }
                } elseif (isset($response['message'])) {
                    $errorMessage = $response['message'];
                } elseif (isset($response['data']['message'])) {
                    $errorMessage = $response['data']['message'];
                }
                
                // Log full response for debugging
                error_log("SMSService::sendRealSMS: Full error response: " . json_encode($response));
                
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'details' => $response,
                    'http_code' => $response['http_code'] ?? null,
                    'response' => $response['response'] ?? (isset($response['data']) ? json_encode($response['data']) : null),
                    'phone_number' => $phoneNumber,
                    'phone_formatted' => $phoneNumberFormatted,
                    'simulated' => false
                ];
            }
        } catch (\Exception $e) {
            // Log exception as failed SMS
            // Do NOT deduct credits on exception
            error_log("SMSService::sendRealSMS: Exception occurred - NOT deducting credits. Error: " . $e->getMessage());
            try {
                $phoneNumberFormatted = $this->formatPhoneNumber($phoneNumber, true);
                if ($phoneNumberFormatted) {
                    $logCompanyId = $companyId ?? 0;
                    $this->logSMSToDatabase($logCompanyId, $messageType, $phoneNumberFormatted, false, $e->getMessage(), $senderIdToUse ?? null);
                }
            } catch (\Exception $logEx) {
                error_log("SMSService::sendRealSMS: Failed to log exception SMS to database: " . $logEx->getMessage());
            }
            
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'simulated' => false
            ];
        }
    }
    
    /**
     * Log SMS to sms_logs table (synchronously, instantly)
     * 
     * @param int $companyId Company ID
     * @param string $messageType Message type (purchase, swap, repair, system, custom, test_sms)
     * @param string $recipient Recipient phone number (formatted, without +)
     * @param bool $success Whether SMS was sent successfully
     * @param string $message SMS message content or error message
     * @param string|null $senderId Sender ID used
     * @return void
     */
    private function logSMSToDatabase($companyId, $messageType, $recipient, $success, $message = '', $senderId = null) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if sms_logs table exists, create if not
            $checkTable = $db->query("SHOW TABLES LIKE 'sms_logs'");
            if ($checkTable->rowCount() === 0) {
                // Create table if it doesn't exist
                // Use BIGINT UNSIGNED for company_id to match companies.id type
                $createTable = $db->exec("
                    CREATE TABLE IF NOT EXISTS sms_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        company_id BIGINT UNSIGNED NOT NULL,
                        message_type ENUM('purchase', 'swap', 'repair', 'system', 'custom', 'test_sms') NOT NULL,
                        recipient VARCHAR(15) NOT NULL,
                        message TEXT NOT NULL,
                        status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
                        sender_id VARCHAR(15) DEFAULT NULL,
                        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_company_id (company_id),
                        INDEX idx_message_type (message_type),
                        INDEX idx_status (status),
                        INDEX idx_sent_at (sent_at),
                        INDEX idx_recipient (recipient)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                if ($createTable === false) {
                    error_log("SMSService::logSMSToDatabase: Failed to create sms_logs table");
                    return;
                }
                error_log("SMSService::logSMSToDatabase: Created sms_logs table");
            }
            
            $status = $success ? 'sent' : 'failed';
            // Ensure sender ID is validated (max 11 characters) before logging
            $validatedSenderId = $senderId ? $this->validateAndTruncateSenderId($senderId) : null;
            
            // For administrative SMS (companyId = 0), we need to handle it properly
            // Use 0 to indicate system/administrative SMS
            $logCompanyId = $companyId ?? 0;
            
            $stmt = $db->prepare("
                INSERT INTO sms_logs (company_id, message_type, recipient, message, status, sender_id, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $logCompanyId,
                $messageType,
                $recipient,
                substr($message, 0, 5000), // Limit message length
                $status,
                $validatedSenderId
            ]);
            
            error_log("SMSService::logSMSToDatabase: Successfully logged SMS - company: {$companyId}, type: {$messageType}, status: {$status}, recipient: {$recipient}");
        } catch (\Exception $e) {
            error_log("SMSService::logSMSToDatabase: Failed to log SMS to database: " . $e->getMessage());
        }
    }
    
    /**
     * Send SMS instantly with optimized settings - bypasses simulation fallback
     * 
     * @param string $phoneNumber Customer phone number
     * @param string $message SMS message
     * @param string $type Message type
     * @param int|null $companyId Optional company ID for quota management
     * @return array Send result
     */
    public function sendSMSInstant($phoneNumber, $message, $type = 'transactional', $companyId = null) {
        // Use sendRealSMS to ensure instant delivery without simulation fallback
        // This ensures SMS is sent immediately via API, not simulated
        return $this->sendRealSMS($phoneNumber, $message, $companyId);
    }
    
    /**
     * Send SMS in simulation mode only (fastest possible)
     * 
     * @param string $phoneNumber Customer phone number
     * @param string $message SMS message
     * @return array Send result
     */
    public function sendSMSSimulation($phoneNumber, $message) {
        // Format phone number
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        if (!$phoneNumber) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format'
            ];
        }
        
        // Send directly in simulation mode for instant delivery
        return $this->simulateSMSSending($phoneNumber, $message);
    }
    
    /**
     * Format phone number to international format
     * 
     * @param string $phoneNumber Raw phone number
     * @param bool $forApi If true, return format for API (without +), otherwise return with +
     * @return string|false Formatted phone number or false if invalid
     */
    private function formatPhoneNumber($phoneNumber, $forApi = false) {
        // Check if already in correct format with +
        if (strpos($phoneNumber, '+') === 0) {
            // Remove all non-numeric characters except +
            $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
            if (strlen($cleanNumber) == 13 && substr($cleanNumber, 0, 4) == '+233') {
                return $forApi ? substr($cleanNumber, 1) : $cleanNumber;
            }
        }
        
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Handle Ghana phone numbers
        $formatted = false;
        if (strlen($phoneNumber) == 10 && substr($phoneNumber, 0, 1) == '0') {
            // Convert 0XXXXXXXXX to 233XXXXXXXXX
            $formatted = '233' . substr($phoneNumber, 1);
        } elseif (strlen($phoneNumber) == 9 && substr($phoneNumber, 0, 1) == '2') {
            // Convert 2XXXXXXXX to 233XXXXXXXXX
            $formatted = '233' . $phoneNumber;
        } elseif (strlen($phoneNumber) == 12 && substr($phoneNumber, 0, 3) == '233') {
            // Already in 233XXXXXXXXX format
            $formatted = $phoneNumber;
        } elseif (strlen($phoneNumber) == 13 && substr($phoneNumber, 0, 4) == '+233') {
            // Already has +, remove it for API
            $formatted = substr($phoneNumber, 1);
        }
        
        if ($formatted) {
            return $forApi ? $formatted : '+' . $formatted;
        }
        
        // If it doesn't match any pattern, return false
        return false;
    }
    
    /**
     * Make HTTP request to Arkasel SMS API
     * 
     * @param array $data Request data (without api_key)
     * @param string $apiKey API key to include in header
     * @return array API response
     */
    private function makeRequest($data, $apiKey = null) {
        // Use configured API key if not provided
        $apiKey = $apiKey ?: $this->apiKey;
        
        // Use the configured Arkasel API endpoint
        $url = $this->baseUrl;
        
        $ch = curl_init();
        
        // For Arkasel API v2, we send data as JSON (without api_key in body)
        $postData = json_encode($data);
        
        // Prepare headers - API key goes in header for v2
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Add API key to header (Arkasel API v2 requires it in header, not body)
        // Based on Arkasel documentation, API key should be in 'api-key' header
        if (!empty($apiKey)) {
            $apiKeyTrimmed = trim($apiKey);
            $headers[] = 'api-key: ' . $apiKeyTrimmed;
        }
        
        // Log request for debugging (hide full API key)
        error_log("SMS API Request - URL: " . $url);
        error_log("SMS API Request - Data: " . json_encode([
            'sender' => $data['sender'], 
            'message_length' => strlen($data['message']), 
            'recipient' => is_array($data['recipients']) ? $data['recipients'][0] : $data['recipients'],
            'api_key_header' => !empty($apiKey) ? 'present' : 'missing'
        ]));
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,  // Balanced timeout for reasonable response time
            CURLOPT_CONNECTTIMEOUT => 8,  // Connection timeout to handle network delays without being too slow
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,  // Allow redirects
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY => true,  // Disable Nagle's algorithm for instant packet sending
            CURLOPT_NOSIGNAL => true,
            CURLOPT_FRESH_CONNECT => true,  // Use fresh connection, don't reuse stale connections
            CURLOPT_FORBID_REUSE => false,  // Allow connection reuse after this request (for efficiency)
            CURLOPT_DNS_CACHE_TIMEOUT => 60,  // Cache DNS for 60 seconds for faster subsequent requests
            CURLOPT_VERBOSE => false  // Set to true for detailed curl debug info
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Always log for debugging SMS issues
        error_log("SMS API Response - HTTP Code: " . $httpCode);
        error_log("SMS API Response - Response: " . substr($response, 0, 500));
        if ($error) {
            error_log("SMS API Response - cURL Error: " . $error);
        }
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        // Handle empty or null response
        if (empty($response) || trim($response) === '') {
            // Empty response with HTTP 200-299 is often success (some APIs return empty body on success)
            if ($httpCode >= 200 && $httpCode < 300) {
                error_log("SMS API: HTTP {$httpCode} with empty response - treating as success (SMS likely sent)");
                return [
                    'success' => true,
                    'data' => []
                ];
            } else {
                error_log("SMS API: HTTP {$httpCode} with empty response - treating as error");
                return [
                    'success' => false,
                    'error' => 'HTTP Error ' . $httpCode . ' - Empty response',
                    'http_code' => $httpCode,
                    'response' => $response
                ];
            }
        }
        
        $decodedResponse = json_decode($response, true);
        
        // If JSON decode failed but we have HTTP 200, treat as success (some APIs return non-JSON success)
        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE && $httpCode >= 200 && $httpCode < 300) {
            error_log("SMS API: HTTP {$httpCode} with non-JSON response - treating as success. Response: " . substr($response, 0, 200));
            return [
                'success' => true,
                'data' => ['raw_response' => $response]
            ];
        }
        
        // Log full response for debugging
        error_log("SMS API: Full decoded response: " . json_encode($decodedResponse));
        
        // Check if response indicates success (both HTTP code and response body)
        if ($httpCode >= 200 && $httpCode < 300) {
            // Check for explicit error indicators first
            $hasError = false;
            $errorMessage = 'SMS API returned an error';
            
            // Only treat as error if there are EXPLICIT error indicators
            if (isset($decodedResponse['status']) && (strtolower($decodedResponse['status']) === 'error' || strtolower($decodedResponse['status']) === 'failed')) {
                $hasError = true;
                $errorMessage = $decodedResponse['message'] ?? $decodedResponse['error'] ?? $errorMessage;
            } elseif (isset($decodedResponse['success']) && $decodedResponse['success'] === false) {
                $hasError = true;
                $errorMessage = $decodedResponse['message'] ?? $decodedResponse['error'] ?? $errorMessage;
            } elseif (isset($decodedResponse['error']) && !empty($decodedResponse['error'])) {
                // Only treat as error if error field is not empty
                $hasError = true;
                $errorMessage = is_string($decodedResponse['error']) ? $decodedResponse['error'] : ($decodedResponse['message'] ?? $errorMessage);
            }
            
            // Check for positive success indicators (message_id, sid, etc.)
            $hasSuccessIndicator = false;
            if (isset($decodedResponse['sid']) || 
                isset($decodedResponse['message_id']) || 
                isset($decodedResponse['id']) ||
                isset($decodedResponse['data']['sid']) ||
                isset($decodedResponse['data']['message_id']) ||
                isset($decodedResponse['data']['id']) ||
                (isset($decodedResponse['status']) && strtolower($decodedResponse['status']) === 'success') ||
                (isset($decodedResponse['success']) && $decodedResponse['success'] === true)) {
                $hasSuccessIndicator = true;
            }
            
            // If HTTP 200 and no explicit error, treat as success (even if no success indicator)
            // Many APIs return 200 with just a message_id or sid, which is success
            if ($hasError) {
                error_log("SMS API: HTTP {$httpCode} but response indicates error: " . $errorMessage);
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'data' => $decodedResponse,
                    'http_code' => $httpCode,
                    'response' => $response
                ];
            }
            
            // HTTP 200-299 with no explicit error = success
            error_log("SMS API: HTTP {$httpCode} - Treating as success (hasSuccessIndicator: " . ($hasSuccessIndicator ? 'yes' : 'no') . ", decodedResponse: " . json_encode($decodedResponse) . ")");
            return [
                'success' => true,
                'data' => $decodedResponse
            ];
        } else {
            // Handle different types of errors with more detail
            $errorMessage = 'HTTP Error ' . $httpCode;
            
            if ($decodedResponse) {
                if (isset($decodedResponse['message'])) {
                    $errorMessage .= ': ' . $decodedResponse['message'];
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage .= ': ' . (is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']));
                } elseif (isset($decodedResponse['status']) && isset($decodedResponse['statusText'])) {
                    $errorMessage .= ': ' . $decodedResponse['statusText'];
                }
            }
            
            if ($httpCode === 401) {
                $errorMessage .= ' - Invalid API key. Please check your Arkasel API credentials.';
            } elseif ($httpCode === 404) {
                $errorMessage .= ' - API endpoint not found. Please verify the Arkasel API endpoint is correct.';
            } elseif ($httpCode === 400) {
                $errorMessage .= ' - Bad request. Please check phone number format and message content.';
            }
            
            // Include full response in details for debugging
            return [
                'success' => false,
                'error' => $errorMessage,
                'http_code' => $httpCode,
                'response' => $response
            ];
        }
    }
    
    /**
     * Load configuration from database settings
     * 
     * @param array $settings Database settings array
     * @return void
     */
    public function loadFromSettings($settings) {
        $this->apiKey = $settings['sms_api_key'] ?? '';
        $this->senderId = $settings['sms_sender_id'] ?? 'SellApp';
    }
    
    /**
     * Check if SMS service is properly configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->apiKey) && !empty($this->senderId);
    }
    
    /**
     * Test SMS configuration
     * 
     * @return array Test result
     */
    public function testConfiguration() {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMS service not configured. Please check API key and sender ID.'
            ];
        }
        
        // If no API key or test mode, simulate SMS sending
        if (empty($this->apiKey) || $this->apiKey === 'test') {
            return [
                'success' => true,
                'message' => 'SMS service is in simulation mode. SMS will be logged but not actually sent.'
            ];
        }
        
        // Check if the API endpoint is reachable (with better error handling)
        $endpointReachable = $this->isEndpointReachable();
        if (!$endpointReachable) {
            // Don't fail the test if endpoint is not reachable - just warn
            // The actual SMS sending will handle this gracefully
            return [
                'success' => true,
                'message' => 'SMS API endpoint connection test timed out. This may be due to network issues. The system will attempt to send SMS when needed. If SMS sending fails, please check your internet connection and SMS API configuration.',
                'warning' => true
            ];
        }
        
        // Test with a dummy number (won't actually send)
        // For Arkasel API v2, API key goes in header, not body
        $testData = [
            'sender' => trim($this->senderId),
            'message' => 'Test message',
            'recipients' => ['+233000000000']
        ];
        
        $response = $this->makeRequest($testData, trim($this->apiKey));
        
        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'SMS service is properly configured and working'
            ];
        } else {
            // If API fails (e.g., 404), fall back to simulation mode
            if (strpos($response['error'], '404') !== false || strpos($response['error'], 'not reachable') !== false) {
                return [
                    'success' => true,
                    'message' => 'SMS API endpoint not available. System will use simulation mode for testing. Please configure a valid SMS service provider.'
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?: 'SMS service configuration failed'
            ];
        }
    }
    
    /**
     * Check if the SMS API endpoint is reachable
     * 
     * @return bool
     */
    private function isEndpointReachable() {
        // Use a simpler endpoint check - just check if we can resolve DNS and connect
        $parsedUrl = parse_url($this->baseUrl);
        $host = $parsedUrl['host'] ?? 'sms.arkesel.com';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://' . $host,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,  // Increased timeout
            CURLOPT_CONNECTTIMEOUT => 8,  // Increased connection timeout
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_NOBODY => true, // HEAD request only
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,  // Use IPv4 to avoid IPv6 DNS issues
            CURLOPT_DNS_CACHE_TIMEOUT => 300,  // Cache DNS for 5 minutes
            CURLOPT_FRESH_CONNECT => false  // Reuse connections
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        // Check for timeout errors specifically
        if ($error) {
            if (strpos($error, 'timed out') !== false || 
                strpos($error, 'Resolving timed out') !== false || 
                $curlErrno === CURLE_OPERATION_TIMEDOUT || 
                $curlErrno === CURLE_OPERATION_TIMEOUTED ||
                $curlErrno === CURLE_COULDNT_CONNECT) {
                error_log("SMS Endpoint Reachability Check: Connection timeout - " . $error);
                return false;
            }
        }
        
        // If we get any response (even 404), the endpoint is reachable
        // If we get a DNS error, it's not reachable
        return empty($error) && $httpCode > 0;
    }
    
    /**
     * Get SMS balance from Arkasel API
     * 
     * @return array Balance information or error
     */
    public function getBalance() {
        try {
            // Check if API key is configured
            if (empty($this->apiKey) || $this->apiKey === 'test' || trim($this->apiKey) === '') {
                return [
                    'success' => false,
                    'error' => 'SMS API key is required to check balance. Please configure your Arkasel API key in system settings.'
                ];
            }
            
            // Arkasel balance endpoint
            $url = 'https://sms.arkesel.com/api/v2/clients/balance-details';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'api-key: ' . trim($this->apiKey),
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 15,  // Increased from 10 to 15 seconds
                CURLOPT_CONNECTTIMEOUT => 10,  // Increased from 5 to 10 seconds for DNS resolution
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_DNS_CACHE_TIMEOUT => 300,  // Cache DNS for 5 minutes
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,  // Use IPv4 to avoid IPv6 DNS issues
                CURLOPT_FRESH_CONNECT => false,  // Reuse connections
                CURLOPT_FORBID_REUSE => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            // Handle timeout and connection errors gracefully
            if ($error) {
                // Check for specific timeout errors
                if (strpos($error, 'timed out') !== false || strpos($error, 'Resolving timed out') !== false || $curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === CURLE_OPERATION_TIMEOUTED) {
                    return [
                        'success' => false,
                        'error' => 'Connection timeout: Unable to reach SMS API. Please check your internet connection or try again later.',
                        'timeout' => true
                    ];
                }
                
                // Check for DNS resolution errors
                if (strpos($error, 'Resolving') !== false || $curlErrno === CURLE_COULDNT_RESOLVE_HOST) {
                    return [
                        'success' => false,
                        'error' => 'DNS resolution failed: Unable to resolve SMS API server. Please check your internet connection.',
                        'dns_error' => true
                    ];
                }
                
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $error
                ];
            }
            
            $decodedResponse = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                // Parse balance response - Arkasel API format may vary
                // Balance is SMS credits/units, not monetary amount
                $balance = 0;
                
                // Try different response formats
                if (isset($decodedResponse['data']['balance'])) {
                    $balance = (float)$decodedResponse['data']['balance'];
                } elseif (isset($decodedResponse['balance'])) {
                    $balance = (float)$decodedResponse['balance'];
                } elseif (isset($decodedResponse['data']['amount'])) {
                    $balance = (float)$decodedResponse['data']['amount'];
                } elseif (isset($decodedResponse['data']['sms_balance'])) {
                    $balance = (float)$decodedResponse['data']['sms_balance'];
                } elseif (isset($decodedResponse['sms_balance'])) {
                    $balance = (float)$decodedResponse['sms_balance'];
                } elseif (isset($decodedResponse['data']['credits'])) {
                    $balance = (float)$decodedResponse['data']['credits'];
                } elseif (isset($decodedResponse['credits'])) {
                    $balance = (float)$decodedResponse['credits'];
                }
                
                return [
                    'success' => true,
                    'balance' => $balance,
                    'formatted' => number_format($balance, 0) . ' SMS',
                    'raw_response' => $decodedResponse
                ];
            } else {
                $errorMessage = 'HTTP Error ' . $httpCode;
                if ($decodedResponse) {
                    if (isset($decodedResponse['message'])) {
                        $errorMessage .= ': ' . $decodedResponse['message'];
                    } elseif (isset($decodedResponse['error'])) {
                        $errorMessage .= ': ' . (is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']));
                    }
                }
                
                if ($httpCode === 401) {
                    $errorMessage .= ' - Invalid API key. Please check your Arkasel API credentials.';
                }
                
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'http_code' => $httpCode,
                    'response' => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}

