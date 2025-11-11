<?php

namespace App\Services;

/**
 * Paystack Service
 * Handles Paystack payment integration for SMS credit purchases
 */
class PaystackService {
    
    private $secretKey;
    private $publicKey;
    private $mode; // 'test' or 'live'
    private $baseUrl = 'https://api.paystack.co';
    
    public function __construct() {
        $this->loadCredentials();
    }
    
    /**
     * Load Paystack credentials from system_settings
     */
    private function loadCredentials() {
        try {
            $db = \Database::getInstance()->getConnection();
            $query = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'paystack_%'");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $this->secretKey = $settings['paystack_secret_key'] ?? '';
            $this->publicKey = $settings['paystack_public_key'] ?? '';
            $this->mode = $settings['paystack_mode'] ?? 'test';
        } catch (\Exception $e) {
            error_log("PaystackService: Error loading credentials: " . $e->getMessage());
            $this->mode = 'test';
        }
    }
    
    /**
     * Get public key for frontend
     * 
     * @return string Public key
     */
    public function getPublicKey() {
        return $this->publicKey;
    }
    
    /**
     * Initialize payment transaction
     * 
     * @param array $params Payment parameters
     * @return array Transaction response
     */
    public function initializeTransaction($params) {
        try {
            if (empty($this->secretKey)) {
                throw new \Exception('Paystack secret key not configured');
            }
            
            $requiredFields = ['email', 'amount'];
            foreach ($requiredFields as $field) {
                if (!isset($params[$field])) {
                    throw new \Exception("Missing required parameter: $field");
                }
            }
            
            // Convert amount to kobo (lowest currency unit for Naira, same for GHS)
            $amountInKobo = (int)round($params['amount'] * 100);
            
            $data = [
                'email' => $params['email'],
                'amount' => $amountInKobo,
                'currency' => $params['currency'] ?? 'GHS',
                'reference' => $params['reference'] ?? $this->generateReference(),
                'callback_url' => $params['callback_url'] ?? null,
                'metadata' => $params['metadata'] ?? []
            ];
            
            // Add metadata if provided
            if (isset($params['metadata'])) {
                $data['metadata'] = array_merge($data['metadata'], $params['metadata']);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/transaction/initialize');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception('CURL error: ' . $curlError);
            }
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200 || !isset($result['status']) || !$result['status']) {
                $errorMessage = $result['message'] ?? 'Failed to initialize Paystack transaction';
                error_log("Paystack Initialize Error: HTTP $httpCode - " . $response);
                throw new \Exception($errorMessage);
            }
            
            return [
                'success' => true,
                'reference' => $result['data']['reference'],
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code']
            ];
        } catch (\Exception $e) {
            error_log("PaystackService initializeTransaction error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify transaction
     * 
     * @param string $reference Transaction reference
     * @return array Verification response
     */
    public function verifyTransaction($reference) {
        try {
            if (empty($this->secretKey)) {
                throw new \Exception('Paystack secret key not configured');
            }
            
            if (empty($reference)) {
                throw new \Exception('Transaction reference is required');
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/transaction/verify/' . $reference);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception('CURL error: ' . $curlError);
            }
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200 || !isset($result['status']) || !$result['status']) {
                $errorMessage = $result['message'] ?? 'Failed to verify Paystack transaction';
                error_log("Paystack Verify Error: HTTP $httpCode - " . $response);
                throw new \Exception($errorMessage);
            }
            
            $data = $result['data'];
            
            // Check if transaction was successful
            if ($data['status'] !== 'success') {
                return [
                    'success' => false,
                    'status' => $data['status'],
                    'message' => 'Transaction not successful: ' . $data['status']
                ];
            }
            
            // Extract payment details
            $amountPaid = (float)($data['amount'] / 100); // Convert from kobo to currency unit
            $currency = $data['currency'] ?? 'GHS';
            $customer = $data['customer'] ?? [];
            $metadata = $data['metadata'] ?? [];
            
            return [
                'success' => true,
                'status' => $data['status'],
                'reference' => $data['reference'],
                'amount' => $amountPaid,
                'currency' => $currency,
                'paid_at' => $data['paid_at'] ?? null,
                'channel' => $data['channel'] ?? null,
                'customer' => [
                    'email' => $customer['email'] ?? null,
                    'id' => $customer['id'] ?? null
                ],
                'metadata' => $metadata,
                'full_response' => $data
            ];
        } catch (\Exception $e) {
            error_log("PaystackService verifyTransaction error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify webhook signature
     * 
     * @param string $signature Webhook signature from header
     * @param string $payload Raw request body
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature($signature, $payload) {
        try {
            if (empty($this->secretKey)) {
                error_log("PaystackService: Secret key not configured for webhook verification");
                return false;
            }
            
            // Paystack webhook signature format: hash_hmac('sha512', $payload, $secretKey)
            $computedHash = hash_hmac('sha512', $payload, $this->secretKey);
            
            return hash_equals($computedHash, $signature);
        } catch (\Exception $e) {
            error_log("PaystackService verifyWebhookSignature error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique reference
     * 
     * @return string Unique reference
     */
    private function generateReference() {
        return 'SMS_' . time() . '_' . uniqid();
    }
}

