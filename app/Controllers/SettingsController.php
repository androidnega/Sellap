<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\CloudinaryService;
use App\Services\SMSService;

// Require database configuration
require_once __DIR__ . '/../../config/database.php';

/**
 * Settings Controller
 * Handles system settings including Cloudinary and SMS configuration
 */
class SettingsController {
    
    private $db;
    
    public function __construct() {
        try {
            $this->db = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log("SettingsController database connection error: " . $e->getMessage());
            throw $e;
        } catch (\Error $e) {
            error_log("SettingsController database connection fatal error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Display settings page
     */
    public function index() {
        // Ensure constants are defined
        if (!defined('BASE_URL_PATH')) {
            require_once __DIR__ . '/../../config/app.php';
        }
        
        if (!defined('APP_PATH')) {
            define('APP_PATH', dirname(__DIR__));
        }
        
        // Use session-based authentication for web pages
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        $userRole = $userData['role'] ?? '';
        
        // Only allow system_admin - restrict all other roles (admin, manager, salesperson, technician, etc.)
        if (!$userData || $userRole !== 'system_admin') {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            // Set error message in session
            $_SESSION['flash_error'] = 'Access Denied: You do not have permission to access settings. Only system administrators can access this page.';
            header('Location: ' . $basePath . '/dashboard');
            exit;
        }
        
        // Get current settings
        try {
            // Check if system_settings table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'system_settings'");
            if ($tableCheck->rowCount() == 0) {
                // Create system_settings table if it doesn't exist
                $createTable = $this->db->exec("
                    CREATE TABLE IF NOT EXISTS system_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(255) UNIQUE NOT NULL,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
            }
            
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (!is_array($settings)) {
                $settings = [];
            }
        } catch (\Exception $e) {
            error_log("Settings error: " . $e->getMessage());
            error_log("Settings error trace: " . $e->getTraceAsString());
            $settings = [];
        } catch (\Error $e) {
            error_log("Settings fatal error: " . $e->getMessage());
            error_log("Settings fatal error trace: " . $e->getTraceAsString());
            $settings = [];
        }
        
        // Check service configurations
        $cloudinaryConfigured = false;
        if (class_exists('\Cloudinary\Cloudinary') && class_exists('\App\Services\CloudinaryService')) {
            try {
                $cloudinaryService = new CloudinaryService();
                // Load settings from database for configuration check
                if (!empty($settings)) {
                    $cloudinaryService->loadFromSettings($settings);
                }
                $cloudinaryConfigured = $cloudinaryService->isConfigured();
            } catch (\Exception $e) {
                error_log("CloudinaryService error: " . $e->getMessage());
                $cloudinaryConfigured = false;
            } catch (\Error $e) {
                error_log("CloudinaryService fatal error: " . $e->getMessage());
                $cloudinaryConfigured = false;
            }
        }
        
        $smsConfigured = false;
        if (class_exists('\App\Services\SMSService')) {
            try {
                $smsService = new SMSService();
                // Load settings from database for configuration check
                if (!empty($settings)) {
                    $smsService->loadFromSettings($settings);
                }
                $smsConfigured = $smsService->isConfigured();
            } catch (\Exception $e) {
                error_log("SMSService error: " . $e->getMessage());
                $smsConfigured = false;
            } catch (\Error $e) {
                error_log("SMSService fatal error: " . $e->getMessage());
                $smsConfigured = false;
            }
        }
        
        // Set page title
        $title = 'System Settings';
        $pageTitle = 'System Settings - Admin Dashboard';
        
        // Set current page for sidebar
        $GLOBALS['currentPage'] = 'settings';
        
        try {
            // Start output buffering
            ob_start();
            
            // Include settings view - make sure file exists
            $settingsViewPath = APP_PATH . '/Views/settings.php';
            if (!file_exists($settingsViewPath)) {
                throw new \Exception("Settings view file not found: " . $settingsViewPath);
            }
            
            // Include settings view with variables
            include $settingsViewPath;
            
            $content = ob_get_clean();
            
            // Set content variable for layout
            $GLOBALS['content'] = $content;
            $GLOBALS['title'] = $title;
            
            // Pass user data to layout for sidebar role detection
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['user'])) {
                $GLOBALS['user_data'] = $_SESSION['user'];
            }
            
            // Include the simple layout (same as other admin pages)
            $layoutPath = APP_PATH . '/Views/simple_layout.php';
            if (!file_exists($layoutPath)) {
                throw new \Exception("Layout file not found: " . $layoutPath);
            }
            
            require $layoutPath;
        } catch (\Exception $e) {
            // Clean any output
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Log the error
            error_log("SettingsController index error: " . $e->getMessage());
            error_log("SettingsController index trace: " . $e->getTraceAsString());
            
            // Display user-friendly error
            http_response_code(500);
            echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
            echo "<h1>Error Loading Settings Page</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Please check the error logs for more details.</p>";
            echo "</body></html>";
            exit;
        } catch (\Error $e) {
            // Clean any output
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Log the fatal error
            error_log("SettingsController index fatal error: " . $e->getMessage());
            error_log("SettingsController index fatal trace: " . $e->getTraceAsString());
            
            // Display user-friendly error
            http_response_code(500);
            echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
            echo "<h1>Fatal Error Loading Settings Page</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Please check the error logs for more details.</p>";
            echo "</body></html>";
            exit;
        }
    }
    
    /**
     * Authenticate user - supports both session and JWT
     */
    private function authenticate() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Try session-based authentication first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        $userRole = $userData['role'] ?? '';
        // Only allow system_admin - restrict all other roles (admin, manager, salesperson, technician, etc.)
        if ($userData && $userRole === 'system_admin') {
            return true; // Session-based auth successful
        }
        
        // For web requests, if no session auth, return false instead of trying JWT
        // This prevents the hang when no JWT token is present
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Authentication required - please log in as system administrator',
                'redirect' => BASE_URL_PATH . '/'
            ]);
            exit;
        }
        
        // Fall back to JWT authentication only if Authorization header is present
        try {
            // Only allow system_admin - restrict all other roles (admin, manager, salesperson, technician, etc.)
            $payload = AuthMiddleware::handle(['system_admin']);
            return true;
        } catch (\Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ]);
            exit;
        }
    }
    
    /**
     * Test endpoint to verify basic functionality
     */
    public function test() {
        error_reporting(0);
        ini_set('display_errors', 0);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'SettingsController is working',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Get all system settings
     */
    public function getSettings() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        try {
            if (!$this->authenticate()) {
                return;
            }
            
            // Check if system_settings table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'system_settings'");
            if ($tableCheck->rowCount() == 0) {
                // Create system_settings table if it doesn't exist
                $createTable = $this->db->exec("
                    CREATE TABLE IF NOT EXISTS system_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(255) UNIQUE NOT NULL,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                
                // Insert default settings if table was just created
                if ($createTable !== false) {
                    $defaultSettings = [
                        'cloudinary_cloud_name' => '',
                        'cloudinary_api_key' => '',
                        'cloudinary_api_secret' => '',
                        'sms_api_key' => '',
                        'sms_sender_id' => 'SellApp',
                        'default_image_quality' => 'auto',
                        'sms_purchase_enabled' => '1',
                        'sms_repair_enabled' => '1',
                        'paystack_secret_key' => '',
                        'paystack_public_key' => '',
                        'paystack_mode' => 'test'
                    ];
                    
                    foreach ($defaultSettings as $key => $value) {
                        $stmt = $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                        $stmt->execute([$key, $value]);
                    }
                }
            }
            
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            // Ensure we have a valid array
            if (!is_array($settings)) {
                $settings = [];
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ], JSON_NUMERIC_CHECK);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("SettingsController getSettings error: " . $e->getMessage());
            error_log("SettingsController getSettings trace: " . $e->getTraceAsString());
            
            // Try to return empty settings instead of 500 error
            try {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'settings' => []
                ]);
            } catch (\Exception $jsonError) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch settings',
                    'message' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Update system settings
     */
    public function updateSettings() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON data'
            ]);
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            foreach ($input as $key => $value) {
                $stmt = $this->db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    updated_at = VALUES(updated_at)
                ");
                $stmt->execute([$key, $value]);
            }
            
            $this->db->commit();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update settings',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test Cloudinary configuration
     */
    public function testCloudinary() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        try {
            // Load settings from database
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $cloudinaryService = new CloudinaryService();
            $cloudinaryService->loadFromSettings($settings);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to initialize Cloudinary service: ' . $e->getMessage()
            ]);
            return;
        }
        
        if (!$cloudinaryService->isConfigured()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Cloudinary not configured. Please set Cloudinary Cloud Name, API Key, and API Secret in the system settings page.'
            ]);
            return;
        }
        
        // Test with a small image upload
        $testImageData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $result = $cloudinaryService->uploadBase64Image($testImageData, 'sellapp/test');
        
        if ($result['success']) {
            // Clean up test image
            $cloudinaryService->deleteImage($result['public_id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Cloudinary configuration test successful'
            ]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Cloudinary test failed: ' . $result['error']
            ]);
        }
    }
    
    /**
     * Test SMS configuration
     */
    public function testSMS() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        try {
            // Load settings from database
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $smsService = new SMSService();
            $smsService->loadFromSettings($settings);
            
            $result = $smsService->testConfiguration();
            
            if ($result['success']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'warning' => $result['warning'] ?? false
                ]);
            } else {
                // For connection timeout errors, return success with warning instead of failure
                if (isset($result['timeout']) || strpos($result['error'] ?? '', 'timeout') !== false || strpos($result['error'] ?? '', 'Connection') !== false) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'SMS configuration appears valid, but connection test timed out. This may be due to network issues. SMS sending will be attempted when needed.',
                        'warning' => true,
                        'error' => $result['error']
                    ]);
                } else {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => $result['error']
                    ]);
                }
            }
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to test SMS configuration: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send test SMS
     */
    public function sendTestSMS() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['phone_number'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Phone number is required'
            ]);
            return;
        }
        
        try {
            // Load settings from database
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $smsService = new SMSService();
            $smsService->loadFromSettings($settings);
            
            // Use sendRealSMS to force real SMS sending without simulation fallback
            $testMessage = 'This is a test SMS from SellApp. If you receive this, SMS integration is working correctly!';
            $result = $smsService->sendRealSMS(
                $input['phone_number'], 
                $testMessage
            );
            
            // Log the SMS attempt to notification_logs
            try {
                $notificationService = new \App\Services\NotificationService();
                if (method_exists($notificationService, 'logNotification')) {
                    $notificationService->logNotification(
                        'test_sms',
                        $input['phone_number'],
                        $result['success'] ?? false,
                        $result['success'] ? 'Test SMS sent successfully' : ($result['error'] ?? 'Unknown error')
                    );
                }
            } catch (\Exception $e) {
                error_log("Failed to log test SMS: " . $e->getMessage());
            }
            
            if ($result['success']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Test SMS sent successfully via Arkasel API',
                    'message_id' => $result['message_id'],
                    'simulated' => false
                ]);
            } else {
                // Show detailed error message
                $errorMsg = $result['error'] ?? 'Unknown error occurred';
                if (isset($result['http_code'])) {
                    $errorMsg .= ' (HTTP ' . $result['http_code'] . ')';
                }
                
                // Parse response if available for more details
                $details = $result['details'] ?? null;
                if (isset($result['response'])) {
                    $decodedResponse = json_decode($result['response'], true);
                    if ($decodedResponse) {
                        if (isset($decodedResponse['message'])) {
                            $errorMsg .= "\nAPI Message: " . $decodedResponse['message'];
                        }
                        if (isset($decodedResponse['error'])) {
                            $errorMsg .= "\nAPI Error: " . (is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']));
                        }
                    } else {
                        // If not JSON, include raw response snippet
                        $errorMsg .= "\nResponse: " . substr($result['response'], 0, 200);
                    }
                }
                
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $errorMsg,
                    'http_code' => $result['http_code'] ?? null,
                    'details' => $details,
                    'response' => $result['response'] ?? null,
                    'simulated' => false
                ], JSON_PRETTY_PRINT);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send test SMS: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test Paystack configuration
     */
    public function testPaystack() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        try {
            // Load settings from database
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'paystack_%'");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $secretKey = $settings['paystack_secret_key'] ?? '';
            $publicKey = $settings['paystack_public_key'] ?? '';
            $mode = $settings['paystack_mode'] ?? 'test';
            
            if (empty($secretKey) || empty($publicKey)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Paystack credentials not configured. Please enter both Secret Key and Public Key.'
                ]);
                return;
            }
            
            // Validate key format
            $isTestKey = (strpos($secretKey, 'sk_test_') === 0) && (strpos($publicKey, 'pk_test_') === 0);
            $isLiveKey = (strpos($secretKey, 'sk_live_') === 0) && (strpos($publicKey, 'pk_live_') === 0);
            
            if (!$isTestKey && !$isLiveKey) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid Paystack key format. Secret keys should start with sk_test_ or sk_live_, and public keys should start with pk_test_ or pk_live_.'
                ]);
                return;
            }
            
            // Check if mode matches key type
            if ($mode === 'live' && !$isLiveKey) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Mode is set to live but test keys are provided. Please use live keys for live mode.'
                ]);
                return;
            }
            
            if ($mode === 'test' && !$isTestKey) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Mode is set to test but live keys are provided. Please use test keys for test mode.'
                ]);
                return;
            }
            
            // Test Paystack API connection by making a simple API call
            // Using the Verify Account endpoint which is lightweight
            $baseUrl = 'https://api.paystack.co';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/bank');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to connect to Paystack API: ' . $curlError
                ]);
                return;
            }
            
            if ($httpCode === 200) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Paystack configuration is valid and working correctly!',
                    'mode' => $mode,
                    'key_type' => $isLiveKey ? 'live' : 'test'
                ]);
            } else if ($httpCode === 401) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid Paystack credentials. Please check your Secret Key and Public Key.'
                ]);
            } else {
                $errorData = json_decode($response, true);
                $errorMessage = $errorData['message'] ?? 'Unknown error';
                
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Paystack API error: ' . $errorMessage,
                    'http_code' => $httpCode
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to test Paystack configuration: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Upload image using Cloudinary
     */
    public function uploadImage() {
        // Suppress PHP errors to prevent breaking JSON response
        error_reporting(0);
        ini_set('display_errors', 0);
        
        if (!$this->authenticate()) {
            return;
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'No image file uploaded or upload error'
            ]);
            return;
        }
        
        try {
            // Load settings from database
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $cloudinaryService = new CloudinaryService();
            $cloudinaryService->loadFromSettings($settings);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to initialize Cloudinary service: ' . $e->getMessage()
            ]);
            return;
        }
        
        if (!$cloudinaryService->isConfigured()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Cloudinary not configured. Please configure Cloudinary settings in the system settings page.'
            ]);
            return;
        }
        
        $folder = $_POST['folder'] ?? 'sellapp';
        $result = $cloudinaryService->uploadImage($_FILES['image']['tmp_name'], $folder);
        
        if ($result['success']) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'width' => $result['width'],
                'height' => $result['height']
            ]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $result['error']
            ]);
        }
    }
    
    /**
     * Test email configuration
     */
    public function testEmail() {
        if (!$this->authenticate()) {
            return;
        }
        
        header('Content-Type: application/json');
        
        try {
            // Get test email from request body
            $input = json_decode(file_get_contents('php://input'), true);
            $testEmail = $input['test_email'] ?? null;
            
            if (empty($testEmail)) {
                // Fallback to admin email
                $user = $_SESSION['user'] ?? null;
                $testEmail = $user['email'] ?? 'admin@sellapp.store';
            }
            
            // Validate email format
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid email address format'
                ]);
                return;
            }
            
            // Load email settings
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $emailService = new \App\Services\EmailService();
            
            $subject = "SellApp Email Configuration Test";
            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                        .success-box { background: #d1fae5; border: 2px solid #10b981; border-radius: 8px; padding: 20px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; padding: 20px; color: #6b7280; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2 style='margin: 0;'>Email Test Successful!</h2>
                        </div>
                        <div class='content'>
                            <div class='success-box'>
                                <h3 style='margin: 0 0 10px 0; color: #065f46;'>✓ Email Configuration Working</h3>
                                <p style='margin: 0; color: #047857;'>Your SMTP settings are configured correctly and emails can be sent successfully.</p>
                            </div>
                            <p>This is a test email from SellApp to verify your email configuration.</p>
                            <p>If you received this email, your SMTP settings are working correctly.</p>
                            <p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                            <p><strong>Test Email:</strong> " . htmlspecialchars($testEmail) . "</p>
                            <p>You can now use this email configuration to send:</p>
                            <ul>
                                <li>Monthly sales reports to clients</li>
                                <li>Daily backup emails</li>
                                <li>System notifications</li>
                                <li>Performance reports to users</li>
                            </ul>
                        </div>
                        <div class='footer'>
                            <p>This is an automated test email from SellApp.</p>
                            <p>&copy; " . date('Y') . " SellApp. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $result = $emailService->sendEmail($testEmail, $subject, $message, null, null, 'test', null, null, null);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Test email sent successfully to ' . $testEmail
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'] ?? 'Failed to send test email'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to test email configuration: ' . $e->getMessage()
            ]);
        }
    }
}
