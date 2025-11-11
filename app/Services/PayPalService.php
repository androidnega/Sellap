<?php

namespace App\Services;

/**
 * PayPal Service
 * Handles PayPal payment integration for SMS credit purchases
 */
class PayPalService {
    
    private $clientId;
    private $clientSecret;
    private $mode; // 'sandbox' or 'live'
    private $baseUrl;
    private $accessToken;
    
    public function __construct() {
        $this->loadCredentials();
    }
    
    /**
     * Load PayPal credentials from system_settings
     */
    private function loadCredentials() {
        try {
            $db = \Database::getInstance()->getConnection();
            $query = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'paypal_%'");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $this->clientId = $settings['paypal_client_id'] ?? '';
            $this->clientSecret = $settings['paypal_client_secret'] ?? '';
            $this->mode = $settings['paypal_mode'] ?? 'sandbox';
            
            // Set base URL based on mode
            if ($this->mode === 'live') {
                $this->baseUrl = 'https://api-m.paypal.com';
            } else {
                $this->baseUrl = 'https://api-m.sandbox.paypal.com';
            }
        } catch (\Exception $e) {
            error_log("PayPalService: Error loading credentials: " . $e->getMessage());
            $this->mode = 'sandbox';
            $this->baseUrl = 'https://api-m.sandbox.paypal.com';
        }
    }
    
    /**
     * Get OAuth access token from PayPal
     * 
     * @return array Access token response
     */
    private function getAccessToken() {
        if ($this->accessToken && isset($this->accessToken['expires_at']) && $this->accessToken['expires_at'] > time()) {
            return $this->accessToken['access_token'];
        }
        
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('PayPal credentials not configured');
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("PayPal OAuth Error: HTTP $httpCode - $response");
            throw new \Exception('Failed to get PayPal access token');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['access_token'])) {
            throw new \Exception('Invalid PayPal access token response');
        }
        
        // Cache token (expires in ~9 hours, cache for 8 hours)
        $this->accessToken = [
            'access_token' => $data['access_token'],
            'expires_at' => time() + ($data['expires_in'] ?? 32400) - 3600
        ];
        
        return $this->accessToken['access_token'];
    }
    
    /**
     * Create PayPal order for SMS credits purchase
     * 
     * @param float $amount Payment amount
     * @param int $smsCredits Number of SMS credits
     * @param string $returnUrl Return URL after payment
     * @param string $cancelUrl Cancel URL
     * @param string $currency Currency code (default: GHS)
     * @return array Order creation response
     */
    public function createOrder($amount, $smsCredits, $returnUrl, $cancelUrl, $currency = 'GHS') {
        try {
            $accessToken = $this->getAccessToken();
            
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'sms_credits_' . time(),
                        'description' => "SMS Credits Purchase - {$smsCredits} credits",
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', '')
                        ],
                        'custom_id' => "sms_credits_{$smsCredits}"
                    ]
                ],
                'application_context' => [
                    'brand_name' => 'SellApp SMS Credits',
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/v2/checkout/orders');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . uniqid()
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 201) {
                error_log("PayPal Create Order Error: HTTP $httpCode - $response");
                throw new \Exception('Failed to create PayPal order');
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['id'])) {
                throw new \Exception('Invalid PayPal order response');
            }
            
            return [
                'success' => true,
                'order_id' => $data['id'],
                'status' => $data['status'],
                'approval_url' => $this->findApprovalUrl($data['links'] ?? [])
            ];
        } catch (\Exception $e) {
            error_log("PayPalService createOrder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Capture PayPal order payment
     * 
     * @param string $orderId PayPal order ID
     * @return array Capture response
     */
    public function captureOrder($orderId) {
        try {
            $accessToken = $this->getAccessToken();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . uniqid()
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 201) {
                error_log("PayPal Capture Order Error: HTTP $httpCode - $response");
                throw new \Exception('Failed to capture PayPal order');
            }
            
            $data = json_decode($response, true);
            
            if (!$data || $data['status'] !== 'COMPLETED') {
                throw new \Exception('PayPal order not completed: ' . ($data['status'] ?? 'Unknown'));
            }
            
            // Extract payment details
            $purchaseUnit = $data['purchase_units'][0] ?? null;
            $capture = $purchaseUnit['payments']['captures'][0] ?? null;
            
            return [
                'success' => true,
                'order_id' => $data['id'],
                'status' => $data['status'],
                'payer_id' => $data['payer']['payer_id'] ?? null,
                'amount' => $capture['amount']['value'] ?? null,
                'currency' => $capture['amount']['currency_code'] ?? null,
                'transaction_id' => $capture['id'] ?? null,
                'payer_email' => $data['payer']['email_address'] ?? null,
                'payer_name' => ($data['payer']['name']['given_name'] ?? '') . ' ' . ($data['payer']['name']['surname'] ?? ''),
                'full_response' => $data
            ];
        } catch (\Exception $e) {
            error_log("PayPalService captureOrder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get PayPal order details
     * 
     * @param string $orderId PayPal order ID
     * @return array Order details
     */
    public function getOrder($orderId) {
        try {
            $accessToken = $this->getAccessToken();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/v2/checkout/orders/' . $orderId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new \Exception('Failed to get PayPal order details');
            }
            
            $data = json_decode($response, true);
            return [
                'success' => true,
                'order' => $data
            ];
        } catch (\Exception $e) {
            error_log("PayPalService getOrder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Find approval URL from PayPal links array
     * 
     * @param array $links PayPal links array
     * @return string|null Approval URL
     */
    private function findApprovalUrl($links) {
        foreach ($links as $link) {
            if (isset($link['rel']) && $link['rel'] === 'approve') {
                return $link['href'] ?? null;
            }
        }
        return null;
    }
    
    /**
     * Get SMS credit rate from system settings
     * 
     * @return float Rate per SMS in GHS
     */
    private function getSMSCreditRate() {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_credit_rate'");
            $stmt->execute();
            $result = $stmt->fetchColumn();
            // Default: 0.05891 GHS per SMS (based on 38 GHS for 645 messages)
            return $result ? (float)$result : 0.05891;
        } catch (\Exception $e) {
            error_log("PayPalService: Error getting SMS credit rate: " . $e->getMessage());
            return 0.05891; // Default fallback (38 GHS / 645 messages)
        }
    }
    
    /**
     * Calculate SMS credits based on amount
     * Pricing: Configurable via system_settings
     * 
     * @param float $amount Payment amount
     * @return int Number of SMS credits
     */
    public function calculateSMSCredits($amount) {
        $ratePerSMS = $this->getSMSCreditRate();
        if ($ratePerSMS <= 0) {
            $ratePerSMS = 0.05891; // Safety fallback (38 GHS / 645 messages)
        }
        return (int)floor($amount / $ratePerSMS);
    }
    
    /**
     * Calculate price for SMS credits
     * 
     * @param int $smsCredits Number of SMS credits
     * @return float Price in GHS
     */
    public function calculatePrice($smsCredits) {
        $ratePerSMS = $this->getSMSCreditRate();
        if ($ratePerSMS <= 0) {
            $ratePerSMS = 0.05891; // Safety fallback (38 GHS / 645 messages)
        }
        return round($smsCredits * $ratePerSMS, 2);
    }
}

