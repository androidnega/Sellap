<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\PayPalService;
use App\Services\PaystackService;
use App\Services\SmsPricingService;
use App\Models\CompanySMSAccount;
use App\Models\Company;
use App\Models\SmsVendorPlan;

/**
 * Payment Controller
 * Handles SMS credit purchases via PayPal and Paystack
 */
class PaymentController {
    
    private $db;
    private $paypalService;
    private $paystackService;
    private $pricingService;
    private $smsAccountModel;
    
    public function __construct() {
        // Database will be initialized when needed
        // Ensure it's available when called
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        $this->db = \Database::getInstance()->getConnection();
        $this->paypalService = new PayPalService();
        $this->paystackService = new PaystackService();
        $this->pricingService = new SmsPricingService();
        $this->smsAccountModel = new CompanySMSAccount();
    }
    
    /**
     * Get database connection (lazy initialization)
     */
    private function getDb() {
        if (!$this->db) {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            $this->db = \Database::getInstance()->getConnection();
        }
        return $this->db;
    }
    
    /**
     * Initialize SMS credit purchase
     * POST /api/payments/sms/initiate
     */
    public function initiatePurchase() {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        // Authenticate - manager or system_admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sms_credits']) || $input['sms_credits'] <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid SMS credits amount']);
            return;
        }
        
        $companyId = $user['company_id'] ?? null;
        if (!$companyId && $user['role'] !== 'system_admin') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        // Allow system_admin to specify company_id
        if ($user['role'] === 'system_admin' && isset($input['company_id'])) {
            $companyId = $input['company_id'];
        }
        
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        try {
            // Verify company exists
            $db = $this->getDb();
            $companyCheck = $db->prepare("SELECT id FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            if (!$companyCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Company ID not found']);
                return;
            }
            
            $smsCredits = (int)$input['sms_credits'];
            $currency = $input['currency'] ?? 'GHS';
            
            // Calculate price
            $amount = $this->paypalService->calculatePrice($smsCredits);
            
            // Generate payment ID
            $paymentId = 'SMS-' . time() . '-' . uniqid();
            
            // Create payment record in database
            $stmt = $db->prepare("
                INSERT INTO sms_payments (
                    company_id, 
                    payment_id, 
                    payment_provider, 
                    amount, 
                    currency, 
                    sms_credits, 
                    status, 
                    created_at
                ) VALUES (?, ?, 'paypal', ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $companyId,
                $paymentId,
                $amount,
                $currency,
                $smsCredits
            ]);
            
            $paymentDbId = $db->lastInsertId();
            
            // Create PayPal order
            $baseUrl = BASE_URL_PATH ?? '';
            $returnUrl = $baseUrl . '/api/payments/sms/success?payment_id=' . $paymentId;
            $cancelUrl = $baseUrl . '/api/payments/sms/cancel?payment_id=' . $paymentId;
            
            $paypalOrder = $this->paypalService->createOrder($amount, $smsCredits, $returnUrl, $cancelUrl, $currency);
            
            if (!$paypalOrder['success']) {
                // Update payment status to failed
                $db = $this->getDb();
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'failed', 
                        payment_details = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    json_encode(['error' => $paypalOrder['error']]),
                    $paymentDbId
                ]);
                
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create PayPal order: ' . ($paypalOrder['error'] ?? 'Unknown error')
                ]);
                return;
            }
            
            // Update payment record with PayPal order ID
            $stmt = $db->prepare("
                UPDATE sms_payments 
                SET paypal_order_id = ?, 
                    payment_details = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $paypalOrder['order_id'],
                json_encode($paypalOrder),
                $paymentDbId
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'order_id' => $paypalOrder['order_id'],
                'approval_url' => $paypalOrder['approval_url'],
                'amount' => $amount,
                'sms_credits' => $smsCredits
            ]);
        } catch (\Exception $e) {
            error_log("PaymentController initiatePurchase error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Payment initialization failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle PayPal payment success callback
     * GET /api/payments/sms/success
     */
    public function handleSuccess() {
        // Suppress errors to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $paymentId = $_GET['payment_id'] ?? null;
        $orderId = $_GET['token'] ?? null;
        
        if (!$paymentId) {
            $baseUrl = BASE_URL_PATH ?? '';
            header('Location: ' . $baseUrl . '/dashboard/sms/payment-failure?error=' . urlencode('Payment ID required'));
            exit;
        }
        
        try {
            // Get payment record
            $db = $this->getDb();
            $stmt = $db->prepare("SELECT * FROM sms_payments WHERE payment_id = ?");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }
            
            if ($payment['status'] === 'completed') {
                // Payment already processed, redirect to success page
                $this->redirectToSuccessPage($paymentId);
                return;
            }
            
            // Use order_id from database if not in URL
            $paypalOrderId = $payment['paypal_order_id'] ?? $orderId;
            
            if (!$paypalOrderId) {
                throw new \Exception('PayPal order ID not found');
            }
            
            // Capture the order
            $captureResult = $this->paypalService->captureOrder($paypalOrderId);
            
            if (!$captureResult['success']) {
                // Update payment status to failed
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'failed', 
                        payment_details = ?,
                        completed_at = NOW()
                    WHERE payment_id = ?
                ");
                $stmt->execute([
                    json_encode(['error' => $captureResult['error']]),
                    $paymentId
                ]);
                
                $this->redirectToFailurePage($paymentId, $captureResult['error']);
                return;
            }
            
            // Update payment record
            $stmt = $db->prepare("
                UPDATE sms_payments 
                SET status = 'completed',
                    paypal_payer_id = ?,
                    payment_details = ?,
                    completed_at = NOW()
                WHERE payment_id = ?
            ");
            $stmt->execute([
                $captureResult['payer_id'],
                json_encode($captureResult),
                $paymentId
            ]);
            
            // Update company SMS balance
            $this->smsAccountModel->allocateSMS($payment['company_id'], $payment['sms_credits']);
            
            // Redirect to success page
            $this->redirectToSuccessPage($paymentId);
        } catch (\Exception $e) {
            error_log("PaymentController handleSuccess error: " . $e->getMessage());
            $this->redirectToFailurePage($paymentId ?? '', $e->getMessage());
        }
    }
    
    /**
     * Handle PayPal payment cancellation
     * GET /api/payments/sms/cancel
     */
    public function handleCancel() {
        $paymentId = $_GET['payment_id'] ?? null;
        
        if ($paymentId) {
            try {
                $db = $this->getDb();
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'cancelled',
                        completed_at = NOW()
                    WHERE payment_id = ?
                ");
                $stmt->execute([$paymentId]);
            } catch (\Exception $e) {
                error_log("PaymentController handleCancel error: " . $e->getMessage());
            }
        }
        
        $this->redirectToCancelPage($paymentId);
    }
    
    /**
     * Redirect to success page
     */
    private function redirectToSuccessPage($paymentId) {
        $baseUrl = BASE_URL_PATH ?? '';
        header('Location: ' . $baseUrl . '/dashboard/sms/payment-success?payment_id=' . $paymentId);
        exit;
    }
    
    /**
     * Redirect to failure page
     */
    private function redirectToFailurePage($paymentId, $error) {
        $baseUrl = BASE_URL_PATH ?? '';
        header('Location: ' . $baseUrl . '/dashboard/sms/payment-failure?payment_id=' . urlencode($paymentId) . '&error=' . urlencode($error));
        exit;
    }
    
    /**
     * Redirect to cancel page
     */
    private function redirectToCancelPage($paymentId) {
        $baseUrl = BASE_URL_PATH ?? '';
        header('Location: ' . $baseUrl . '/dashboard/sms/payment-cancelled?payment_id=' . ($paymentId ?? ''));
        exit;
    }
    
    /**
     * Get payment history for company
     * GET /api/payments/sms/history
     */
    public function getPaymentHistory() {
        // Set JSON header first to prevent HTML errors
        header('Content-Type: application/json');
        
        // Authenticate
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $companyId = $user['company_id'] ?? null;
        if (!$companyId && $user['role'] !== 'system_admin') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        // System admin can view all payments or filter by company_id
        $filterCompanyId = $_GET['company_id'] ?? $companyId;
        
        try {
            $db = $this->getDb();
            
            // Check if sms_payments table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'sms_payments'");
            $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
            
            if (!$tableExists) {
                // Table doesn't exist, return empty result
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'payments' => [],
                    'message' => 'SMS payments table not yet created. Please run database migration.'
                ]);
                return;
            }
            
            if ($filterCompanyId) {
                $stmt = $db->prepare("
                    SELECT 
                        id,
                        payment_id,
                        payment_provider,
                        amount,
                        currency,
                        sms_credits,
                        status,
                        created_at,
                        completed_at
                    FROM sms_payments 
                    WHERE company_id = ?
                    ORDER BY created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$filterCompanyId]);
            } else {
                // System admin viewing all payments
                $stmt = $db->query("
                    SELECT 
                        id,
                        payment_id,
                        payment_provider,
                        amount,
                        currency,
                        sms_credits,
                        status,
                        company_id,
                        created_at,
                        completed_at
                    FROM sms_payments 
                    ORDER BY created_at DESC
                    LIMIT 100
                ");
            }
            
            $payments = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'payments' => $payments
            ]);
        } catch (\Exception $e) {
            error_log("PaymentController getPaymentHistory error: " . $e->getMessage());
            error_log("PaymentController getPaymentHistory trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch payment history: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get SMS rate per message
     * GET /api/sms/pricing/rate
     */
    public function getSMSRate() {
        header('Content-Type: application/json');
        
        // Authenticate
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        try {
            // Get rate from system settings
            $db = $this->getDb();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_credit_rate'");
            $stmt->execute();
            $rate = $stmt->fetchColumn();
            
            // Default: ₵0.05891 per SMS (38 GHS / 645 messages = 0.05891)
            $ratePerSMS = $rate ? (float)$rate : 0.05891;
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'rate_per_sms' => $ratePerSMS
            ]);
        } catch (\Exception $e) {
            error_log("PaymentController getSMSRate error: " . $e->getMessage());
            http_response_code(200); // Return default rate even on error
            echo json_encode([
                'success' => true,
                'rate_per_sms' => 0.05891 // Default fallback (38 GHS / 645 messages)
            ]);
        }
    }

    /**
     * Get available SMS bundles with pricing
     * GET /api/sms/pricing
     */
    public function getPricing() {
        header('Content-Type: application/json');
        
        // Authenticate
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $companyId = $user['company_id'] ?? null;
        if (!$companyId && $user['role'] !== 'system_admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        // Allow system_admin to specify company_id
        if ($user['role'] === 'system_admin' && isset($_GET['company_id'])) {
            $companyId = (int)$_GET['company_id'];
        }
        
        try {
            $bundles = $this->pricingService->getAvailableBundles($companyId);
            http_response_code(200);
            echo json_encode($bundles);
        } catch (\Exception $e) {
            error_log("PaymentController getPricing error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch pricing: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get pricing for specific bundle
     * GET /api/sms/pricing?company=5&plan=1
     */
    public function getBundlePricing() {
        header('Content-Type: application/json');
        
        // Authenticate
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $companyId = $user['company_id'] ?? null;
        $planId = isset($_GET['plan']) ? (int)$_GET['plan'] : null;
        
        if ($user['role'] === 'system_admin' && isset($_GET['company'])) {
            $companyId = (int)$_GET['company'];
        }
        
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        if (!$planId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Plan ID required']);
            return;
        }
        
        try {
            $pricing = $this->pricingService->computeCompanyPrice($companyId, $planId);
            if ($pricing['success']) {
                http_response_code(200);
                echo json_encode($pricing);
            } else {
                http_response_code(404);
                echo json_encode($pricing);
            }
        } catch (\Exception $e) {
            error_log("PaymentController getBundlePricing error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch bundle pricing: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Initialize Paystack payment for SMS purchase (custom quantity)
     * POST /api/sms/paystack/initiate
     */
    public function initiatePaystackPurchase() {
        header('Content-Type: application/json');
        
        // Authenticate
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['manager', 'system_admin'], true)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $companyId = $user['company_id'] ?? null;
        if (!$companyId && $user['role'] !== 'system_admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company ID required']);
            return;
        }
        
        // Allow system_admin to specify company_id
        if ($user['role'] === 'system_admin' && isset($input['company_id'])) {
            $companyId = (int)$input['company_id'];
        }
        
        if (!isset($input['sms_quantity']) || !isset($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'SMS quantity and email are required']);
            return;
        }
        
        $smsQuantity = (int)$input['sms_quantity'];
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        
        if ($smsQuantity < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'SMS quantity must be at least 1']);
            return;
        }
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid email is required']);
            return;
        }
        
        try {
            $db = $this->getDb();
            
            // Verify company exists
            $companyCheck = $db->prepare("SELECT id FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            if (!$companyCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Company not found']);
                return;
            }
            
            // Get SMS rate from system settings
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_credit_rate'");
            $stmt->execute();
            $rate = $stmt->fetchColumn();
            // Default: ₵0.05891 per SMS (based on 38 GHS for 645 messages)
            $ratePerSMS = $rate ? (float)$rate : 0.05891;
            
            // Calculate total price
            $totalPrice = round($smsQuantity * $ratePerSMS, 2);
            
            // Generate payment ID
            $paymentId = 'SMS-' . time() . '-' . uniqid();
            $reference = 'SMS_' . time() . '_' . uniqid();
            
            // Create payment record
            $stmt = $db->prepare("
                INSERT INTO sms_payments (
                    company_id, user_id, payment_id, payment_provider,
                    amount, company_price, currency, sms_credits, paystack_reference, status, created_at
                ) VALUES (?, ?, ?, 'paystack', ?, ?, 'GHS', ?, ?, 'initiated', NOW())
            ");
            $stmt->execute([
                $companyId,
                $user['id'] ?? null,
                $paymentId,
                $totalPrice, // amount
                $totalPrice, // company_price (same since per-SMS pricing)
                $smsQuantity,
                $reference
            ]);
            
            $paymentDbId = $db->lastInsertId();
            
            // Initialize Paystack transaction
            $baseUrl = BASE_URL_PATH ?? '';
            $callbackUrl = $baseUrl . '/api/sms/paystack/callback?payment_id=' . $paymentId;
            
            $paystackResult = $this->paystackService->initializeTransaction([
                'email' => $email,
                'amount' => $totalPrice,
                'currency' => 'GHS',
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'sms_quantity' => $smsQuantity,
                    'rate_per_sms' => $ratePerSMS
                ]
            ]);
            
            if (!$paystackResult['success']) {
                // Update payment status to failed
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'failed', 
                        payment_details = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    json_encode(['error' => $paystackResult['error']]),
                    $paymentDbId
                ]);
                
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to initialize Paystack transaction: ' . ($paystackResult['error'] ?? 'Unknown error')
                ]);
                return;
            }
            
            // Update payment record with Paystack details
            $stmt = $db->prepare("
                UPDATE sms_payments 
                SET paystack_reference = ?, 
                    payment_details = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $paystackResult['reference'],
                json_encode($paystackResult),
                $paymentDbId
            ]);
            
            echo json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'reference' => $paystackResult['reference'],
                'authorization_url' => $paystackResult['authorization_url'],
                'public_key' => $this->paystackService->getPublicKey(),
                'amount' => $totalPrice,
                'messages' => $smsQuantity
            ]);
        } catch (\Exception $e) {
            error_log("PaymentController initiatePaystackPurchase error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Payment initialization failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Verify Paystack payment (callback from Paystack)
     * GET /api/sms/paystack/verify
     */
    public function verifyPaystackPayment() {
        header('Content-Type: application/json');
        
        $reference = $_GET['reference'] ?? null;
        $paymentId = $_GET['payment_id'] ?? null;
        
        if (!$reference && !$paymentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Reference or Payment ID required']);
            return;
        }
        
        try {
            $db = $this->getDb();
            
            // Get payment record
            if ($paymentId) {
                $stmt = $db->prepare("SELECT * FROM sms_payments WHERE payment_id = ?");
                $stmt->execute([$paymentId]);
            } else {
                $stmt = $db->prepare("SELECT * FROM sms_payments WHERE paystack_reference = ?");
                $stmt->execute([$reference]);
            }
            
            $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$payment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Payment record not found']);
                return;
            }
            
            // Check if already processed
            if (in_array($payment['status'], ['success', 'completed'])) {
                echo json_encode([
                    'success' => true,
                    'status' => $payment['status'],
                    'message' => 'Payment already processed'
                ]);
                return;
            }
            
            // Verify with Paystack
            $verifyRef = $payment['paystack_reference'] ?? $reference;
            $verifyResult = $this->paystackService->verifyTransaction($verifyRef);
            
            if (!$verifyResult['success'] || $verifyResult['status'] !== 'success') {
                // Update payment status to failed
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'failed', 
                        payment_details = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    json_encode(['error' => $verifyResult['error'] ?? 'Payment verification failed']),
                    $payment['id']
                ]);
                
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $verifyResult['error'] ?? 'Payment verification failed'
                ]);
                return;
            }
            
            // Update payment record
            $stmt = $db->prepare("
                UPDATE sms_payments 
                SET status = 'success',
                    payment_details = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($verifyResult),
                $payment['id']
            ]);
            
            // Credit SMS to company account
            $this->smsAccountModel->allocateSMS($payment['company_id'], $payment['sms_credits']);
            
            // Deduct from admin/system SMS balance (company_id = 0 represents system/admin)
            $this->deductAdminSMSBalance($payment['sms_credits']);
            
            // Create admin notification for SMS credit purchase
            $this->notifyAdminsOfSMSPurchase($payment);
            
            // Return JSON response (frontend will handle redirect)
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'payment_id' => $payment['payment_id'],
                'message' => 'Payment verified and SMS credits added successfully'
            ]);
            return;
        } catch (\Exception $e) {
            error_log("PaymentController verifyPaystackPayment error: " . $e->getMessage());
            http_response_code(500);
            $baseUrl = BASE_URL_PATH ?? '';
            $paymentIdParam = $paymentId ?? '';
            header('Location: ' . $baseUrl . '/dashboard/sms/payment-failure?payment_id=' . $paymentIdParam . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Handle Paystack webhook
     * POST /api/sms/paystack/webhook
     */
    public function handlePaystackWebhook() {
        // Get raw payload and signature
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        
        // Verify webhook signature
        if (!$this->paystackService->verifyWebhookSignature($signature, $payload)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid signature']);
            return;
        }
        
        $event = json_decode($payload, true);
        
        if (!isset($event['event']) || $event['event'] !== 'charge.success') {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Event ignored']);
            return;
        }
        
        try {
            $data = $event['data'];
            $reference = $data['reference'] ?? null;
            
            if (!$reference) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Reference not found']);
                return;
            }
            
            $db = $this->getDb();
            $stmt = $db->prepare("SELECT * FROM sms_payments WHERE paystack_reference = ?");
            $stmt->execute([$reference]);
            $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$payment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Payment record not found']);
                return;
            }
            
            // Check if already processed
            if (in_array($payment['status'], ['success', 'completed'])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Payment already processed']);
                return;
            }
            
            // Verify transaction
            $verifyResult = $this->paystackService->verifyTransaction($reference);
            
            if (!$verifyResult['success'] || $verifyResult['status'] !== 'success') {
                // Update payment status to failed
                $stmt = $db->prepare("
                    UPDATE sms_payments 
                    SET status = 'failed', 
                        payment_details = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    json_encode(['error' => 'Webhook verification failed']),
                    $payment['id']
                ]);
                
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Verification failed']);
                return;
            }
            
            // Update payment record
            $stmt = $db->prepare("
                UPDATE sms_payments 
                SET status = 'success',
                    payment_details = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($verifyResult),
                $payment['id']
            ]);
            
            // Credit SMS to company account
            $this->smsAccountModel->allocateSMS($payment['company_id'], $payment['sms_credits']);
            
            // Deduct from admin/system SMS balance (company_id = 0 represents system/admin)
            $this->deductAdminSMSBalance($payment['sms_credits']);
            
            // Create admin notification for SMS credit purchase
            $this->notifyAdminsOfSMSPurchase($payment);
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
        } catch (\Exception $e) {
            error_log("PaymentController handlePaystackWebhook error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Notify all system admins about SMS credit purchase
     * 
     * @param array $payment Payment data
     * @return void
     */
    private function notifyAdminsOfSMSPurchase($payment) {
        try {
            $db = $this->getDb();
            
            // Get company and user info
            $companyStmt = $db->prepare("SELECT name FROM companies WHERE id = ?");
            $companyStmt->execute([$payment['company_id']]);
            $company = $companyStmt->fetch(\PDO::FETCH_ASSOC);
            $companyName = $company['name'] ?? 'Unknown Company';
            
            $userStmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
            $userStmt->execute([$payment['user_id']]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
            $username = $user['username'] ?? $user['email'] ?? 'Unknown User';
            
            // Get all system admins
            $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'system_admin' AND is_active = 1");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Check if notifications table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'notifications'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $message = "{$username} from {$companyName} purchased " . ($payment['sms_credits'] ?? 0) . " SMS credits for ₵" . number_format($payment['amount'] ?? 0, 2);
                $data = json_encode([
                    'payment_id' => $payment['payment_id'],
                    'company_id' => $payment['company_id'],
                    'company_name' => $companyName,
                    'user_id' => $payment['user_id'],
                    'username' => $username,
                    'sms_credits' => $payment['sms_credits'] ?? 0,
                    'amount' => $payment['amount'] ?? 0
                ]);
                
                foreach ($admins as $adminId) {
                    $insertStmt = $db->prepare("
                        INSERT INTO notifications (user_id, company_id, message, type, data, created_at)
                        VALUES (?, NULL, ?, 'sms_purchase', ?, NOW())
                    ");
                    $insertStmt->execute([$adminId, $message, $data]);
                }
            }
        } catch (\Exception $e) {
            error_log("Error creating admin notification for SMS purchase: " . $e->getMessage());
        }
    }
    
    /**
     * Deduct SMS from admin/system balance when company purchases SMS
     * Admin balance is tracked in company_sms_accounts with company_id = 0
     * 
     * @param int $amount Amount to deduct
     * @return bool Success status
     */
    private function deductAdminSMSBalance($amount) {
        try {
            $db = $this->getDb();
            
            // Get or create admin account (company_id = 0)
            $adminAccountModel = new CompanySMSAccount();
            $adminAccount = $adminAccountModel->getOrCreateAccount(0);
            
            if (!$adminAccount) {
                // Try to create admin account manually (bypassing FK constraint if needed)
                try {
                    // First, get admin balance from SMS provider
                    $smsService = new \App\Services\SMSService();
                    $balanceResult = $smsService->getBalance();
                    
                    $initialBalance = 0;
                    if ($balanceResult['success'] && isset($balanceResult['balance'])) {
                        $initialBalance = (int)$balanceResult['balance'];
                    }
                    
                    // Try to insert admin account (company_id = 0)
                    // Note: This may fail if FK constraint prevents company_id = 0
                    // In that case, we'll track balance separately or skip tracking
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO company_sms_accounts (company_id, total_sms, sms_used, status, created_at)
                        VALUES (0, ?, 0, 'active', NOW())
                    ");
                    $stmt->execute([$initialBalance]);
                    
                    // Re-fetch after insert attempt
                    $stmt = $db->prepare("SELECT * FROM company_sms_accounts WHERE company_id = 0");
                    $stmt->execute();
                    $adminAccount = $stmt->fetch(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("PaymentController deductAdminSMSBalance - Could not create admin account: " . $e->getMessage());
                    // Continue - we'll just log that we can't track admin balance
                }
            }
            
            // If admin account exists, deduct from it
            if ($adminAccount) {
                $stmt = $db->prepare("
                    UPDATE company_sms_accounts 
                    SET total_sms = GREATEST(0, total_sms - ?), updated_at = NOW()
                    WHERE company_id = 0
                ");
                $result = $stmt->execute([$amount]);
                if ($result) {
                    error_log("PaymentController: Deducted {$amount} SMS from admin balance. New balance: " . max(0, ($adminAccount['total_sms'] - $amount)));
                }
                return $result;
            } else {
                // Admin account doesn't exist and couldn't be created (likely FK constraint)
                // Log warning but don't fail the payment
                error_log("PaymentController: Warning - Cannot track admin SMS balance deduction. Admin account (company_id=0) not available.");
                return true; // Return true so payment doesn't fail
            }
        } catch (\Exception $e) {
            error_log("PaymentController deductAdminSMSBalance error: " . $e->getMessage());
            // Don't fail the payment if admin balance deduction fails - log it
            return true; // Return true so payment doesn't fail
        }
    }
}

