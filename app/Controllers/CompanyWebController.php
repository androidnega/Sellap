<?php

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Models\CompanySMSAccount;
use App\Models\CompanyModule;
use App\Middleware\WebAuthMiddleware;
use App\Services\NotificationService;
use App\Services\BackupService;

class CompanyWebController {
    private $companyModel;
    private $userModel;
    private $smsAccountModel;

    public function __construct() {
        $this->companyModel = new Company();
        $this->userModel = new User();
        $this->smsAccountModel = new CompanySMSAccount();
    }

    /**
     * Display all companies (System Admin only)
     */
    public function index() {
        // Start session to check user role
        // Note: Authentication and role check is already handled by WebAuthMiddleware in routes
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Additional safety check - but don't redirect if middleware already validated
        // Only redirect if somehow session is missing (shouldn't happen if middleware worked)
        if (!isset($_SESSION['user'])) {
            // This should not happen if middleware is working correctly
            // Redirect to login with redirect parameter
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/companies';
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $basePathClean = rtrim($basePath, '/');
            $currentPath = str_replace($basePathClean, '', $currentPath);
            $currentPath = explode('?', $currentPath)[0];
            $currentPath = explode('#', $currentPath)[0];
            header('Location: ' . BASE_URL_PATH . '/?redirect=' . urlencode($currentPath));
            exit;
        }
        
        // Verify role (middleware should have already done this, but double-check)
        if ($_SESSION['user']['role'] !== 'system_admin') {
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        $page = 'companies';
        $title = 'Company Management';
        $companies = $this->companyModel->all();
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_index.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show create company form
     */
    public function create() {
        $page = 'companies';
        $title = 'Add New Company';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_form.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Store new company
     */
    public function store() {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone_number' => $_POST['phone'] ?? null,
            'address' => $_POST['address'] ?? null,
            'contact_person' => $_POST['contact_person'] ?? null,
            'status' => $_POST['status'] ?? 'active',
            'created_by_user_id' => 1 // Default to admin user for now
        ];
        
        // Check for duplicate phone number
        $phoneNumber = trim($data['phone_number'] ?? '');
        if (!empty($phoneNumber)) {
            // Normalize phone number for comparison (remove spaces, dashes, parentheses)
            $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);
            
            // Check for existing company with same phone number
            $db = \Database::getInstance()->getConnection();
            $checkStmt = $db->prepare("SELECT id, name FROM companies WHERE phone_number IS NOT NULL AND phone_number != ''");
            $checkStmt->execute();
            $allCompanies = $checkStmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($allCompanies as $company) {
                $existingNormalized = preg_replace('/[\s\-\(\)]/', '', $company['phone_number'] ?? '');
                if ($existingNormalized === $normalizedPhone) {
                    $_SESSION['error_message'] = 'A company with this phone number already exists: ' . htmlspecialchars($company['name']);
                    header("Location: " . BASE_URL_PATH . "/dashboard/companies/create");
                    exit;
                }
            }
            
            // Also check exact match
            $exactStmt = $db->prepare("SELECT id, name FROM companies WHERE phone_number = ? LIMIT 1");
            $exactStmt->execute([$phoneNumber]);
            $existingCompany = $exactStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingCompany) {
                $_SESSION['error_message'] = 'A company with this phone number already exists: ' . htmlspecialchars($existingCompany['name']);
                header("Location: " . BASE_URL_PATH . "/dashboard/companies/create");
                exit;
            }
        }
        
        $companyId = $this->companyModel->create($data);

        // Auto-create manager user for this company
        $db = \Database::getInstance()->getConnection();
        $passwordHash = password_hash('manager123', PASSWORD_BCRYPT);
        
        $managerData = [
            'unique_id' => 'USR' . strtoupper(uniqid()),
            'username' => $data['email'],
            'email' => $data['email'],
            'full_name' => $data['name'] . ' Manager',
            'password' => $passwordHash,
            'role' => 'manager',
            'company_id' => $companyId,
            'is_active' => 1
        ];
        
        $stmt = $db->prepare("INSERT INTO users (unique_id, username, email, full_name, password, role, company_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $managerData['unique_id'],
            $managerData['username'],
            $managerData['email'],
            $managerData['full_name'],
            $managerData['password'],
            $managerData['role'],
            $managerData['company_id'],
            $managerData['is_active']
        ]);

        // Initialize default modules for the new company
        $defaultModules = ['products_inventory', 'pos_sales', 'customers'];
        $companyModuleModel = new CompanyModule();
        $companyModuleModel->initializeCompanyModules($companyId, $defaultModules);

        // Create automatic backup for the newly onboarded company
        try {
            $backupService = new BackupService();
            $userId = $_SESSION['user']['id'] ?? 1;
            $backupService->createCompanyBackup($companyId, $userId, true);
            error_log("Automatic backup created for newly onboarded company ID: {$companyId}");
        } catch (\Exception $e) {
            // Log error but don't fail company creation if backup fails
            error_log("Failed to create automatic backup for company ID {$companyId}: " . $e->getMessage());
        }

        header("Location: " . BASE_URL_PATH . "/dashboard/companies");
        exit;
    }

    /**
     * Show company modules index - list all companies (System Admin only)
     */
    public function modulesIndex() {
        // Start session (authentication already handled by middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $page = 'company-modules';
        $title = 'Company Modules Management';
        $GLOBALS['currentPage'] = 'company-modules';
        
        // Get all companies
        $companies = $this->companyModel->all();
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_modules_index.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show company modules management page (System Admin only)
     */
    public function modules($id) {
        // Validate company ID
        $id = trim($id ?? '');
        if (empty($id) || !is_numeric($id)) {
            header("Location: " . BASE_URL_PATH . "/dashboard/companies");
            exit;
        }
        
        $id = (int)$id;
        
        // Ensure Database is loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        $company = $this->companyModel->find($id);
        
        if (!$company || empty($company['id'])) {
            header("Location: " . BASE_URL_PATH . "/dashboard/companies");
            exit;
        }
        
        $page = 'company-modules';
        $title = 'Company Modules - ' . htmlspecialchars($company['name']);
        $GLOBALS['currentPage'] = 'company-modules';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/company_modules.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show company details (view only)
     */
    public function view($id) {
        // Validate company ID
        $id = trim($id ?? '');
        if (empty($id) || !is_numeric($id)) {
            header("Location: " . BASE_URL_PATH . "/dashboard/companies");
            exit;
        }
        
        $id = (int)$id;
        
        // Ensure Database is loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        $company = $this->companyModel->find($id);
        
        if (!$company || empty($company['id'])) {
            header("Location: " . BASE_URL_PATH . "/dashboard/companies");
            exit;
        }
        
        // Get SMS account information (ensure account exists)
        try {
            // Ensure the SMS account exists before trying to get balance
            $this->smsAccountModel->getOrCreateAccount($id);
            
            // getSMSBalance automatically creates account if it doesn't exist, but we already ensured it exists
            $smsBalance = $this->smsAccountModel->getSMSBalance($id);
            if (!$smsBalance || !isset($smsBalance['success']) || !$smsBalance['success']) {
                // If still failing, try to create the account again
                error_log("Failed to load SMS balance for company {$id}, attempting to create account");
                $this->smsAccountModel->getOrCreateAccount($id);
                $smsBalance = $this->smsAccountModel->getSMSBalance($id);
                
                if (!$smsBalance || !isset($smsBalance['success']) || !$smsBalance['success']) {
                    error_log("Still failed to load SMS balance for company {$id}");
                    $smsBalance = ['success' => false, 'error' => 'SMS account could not be initialized. Please try again.'];
                }
            }
        } catch (\Exception $e) {
            error_log("Error loading SMS balance for company {$id}: " . $e->getMessage());
            $smsBalance = ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
        
        $page = 'companies';
        $title = 'View Company';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_view.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Show edit company form
     */
    public function edit($id) {
        $company = $this->companyModel->find($id);
        
        if (!$company) {
            header("Location: " . BASE_URL_PATH . "/dashboard/companies");
            exit;
        }
        
        $page = 'companies';
        $title = 'Edit Company';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_form.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Update company
     */
    public function update($id) {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone_number' => $_POST['phone'] ?? null,
            'address' => $_POST['address'] ?? null,
            'contact_person' => $_POST['contact_person'] ?? null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        $this->companyModel->update($id, $data);
        header("Location: " . BASE_URL_PATH . "/dashboard/companies");
        exit;
    }

    /**
     * Delete company
     */
    public function destroy($id) {
        $this->companyModel->delete($id);
        header("Location: " . BASE_URL_PATH . "/dashboard/companies");
        exit;
    }

    /**
     * SMS & Company Configuration - Centralized SMS management for all companies
     */
    public function smsConfig() {
        // Start session (authentication already handled by middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get all companies with their SMS account data
        $companies = $this->companyModel->all();
        $companiesWithSMS = [];
        
        foreach ($companies as $company) {
            $smsData = $this->smsAccountModel->getSMSBalance($company['id']);
            $companiesWithSMS[] = [
                'company' => $company,
                'sms' => $smsData['success'] ? $smsData : ['success' => false, 'error' => 'Not initialized']
            ];
        }
        
        $page = 'sms-config';
        $title = 'SMS & Company Configuration';
        $GLOBALS['currentPage'] = 'sms-config';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/companies_sms_config.php';
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
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Reset company manager password
     * Generates a new password and sends it via SMS to the manager
     */
    public function resetManagerPassword($companyId) {
        // Authentication - System Admin only
        try {
            WebAuthMiddleware::handle(['system_admin']);
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Unauthorized access';
            header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
            exit;
        }

        // Validate company ID
        $companyId = (int)$companyId;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Invalid company ID';
            header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
            exit;
        }

        // Get company information
        $company = $this->companyModel->find($companyId);
        if (!$company) {
            $_SESSION['error_message'] = 'Company not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
            exit;
        }

        // Find the manager user for this company
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, username, email, phone_number, full_name 
            FROM users 
            WHERE company_id = ? AND role = 'manager' AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $manager = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$manager) {
            $_SESSION['error_message'] = 'Manager account not found for this company';
            header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
            exit;
        }

        // Generate a secure random password (8-12 characters)
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $passwordLength = rand(10, 12);
        $newPassword = '';
        for ($i = 0; $i < $passwordLength; $i++) {
            $newPassword .= $chars[rand(0, strlen($chars) - 1)];
        }

        // Hash and update the password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $manager['id']]);

        // Determine phone number to use: Prefer manager phone_number (personal), fallback to company phone_number
        // Manager phone is preferred because password reset is personal/account-related
        $phoneNumberToUse = null;
        if (!empty($manager['phone_number'])) {
            $phoneNumberToUse = trim($manager['phone_number']);
        } elseif (!empty($company['phone_number'])) {
            $phoneNumberToUse = trim($company['phone_number']);
        }

        // Send password via SMS to manager
        if (!empty($phoneNumberToUse)) {
            try {
                $notificationService = new NotificationService();
                
                // Administrative message - should not mention company name, comes from SellApp
                $appUrl = defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: 'http://localhost');
                $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
                $loginUrl = rtrim($appUrl . $basePath, '/');
                
                $message = "Your manager password has been reset.\n\n";
                $message .= "Username: {$manager['username']}\n";
                $message .= "New Password: {$newPassword}\n\n";
                $message .= "Login at: {$loginUrl}\n\n";
                $message .= "Please change your password after logging in.\n\n";
                $message .= "SECURITY: If you did not request this reset, please ignore this message and contact your administrator immediately.";
                
                // Log phone number details before sending
                $phoneSource = !empty($manager['phone_number']) ? 'manager phone' : 'company phone';
                error_log("CompanyWebController: Preparing to send password reset SMS to {$phoneNumberToUse} ({$phoneSource}) for company {$companyId}");
                
                // Send SMS - use administrative SMS (system balance, not company credits)
                // Administrative messages always use "SellApp" as sender
                $smsService = new \App\Services\SMSService();
                $smsResult = $smsService->sendAdministrativeSMS($phoneNumberToUse, $message);

                // Log detailed result
                error_log("CompanyWebController: SMS send result for company {$companyId}: " . json_encode($smsResult));

                if ($smsResult['success'] && !($smsResult['simulated'] ?? false)) {
                    $phoneSource = !empty($manager['phone_number']) ? 'manager phone' : 'company phone';
                    $_SESSION['success_message'] = "Password reset successfully! The new password has been sent to {$phoneNumberToUse} via SMS.";
                    error_log("CompanyWebController: Password reset SMS sent successfully to {$phoneNumberToUse} ({$phoneSource}) for company {$companyId}");
                } else {
                    // Password was reset but SMS failed - show detailed error
                    $errorMsg = $smsResult['error'] ?? 'Unknown error';
                    $isSimulated = isset($smsResult['simulated']) && $smsResult['simulated'];
                    $httpCode = $smsResult['http_code'] ?? null;
                    $responseDetails = $smsResult['response'] ?? null;
                    
                    // Build detailed error message
                    $detailedError = $errorMsg;
                    if ($httpCode) {
                        $detailedError .= " (HTTP {$httpCode})";
                    }
                    if ($responseDetails) {
                        $responsePreview = is_string($responseDetails) ? substr($responseDetails, 0, 100) : json_encode($responseDetails);
                        $detailedError .= " - Response: {$responsePreview}";
                    }
                    
                    if ($isSimulated || strpos($errorMsg, 'API key') !== false || strpos($errorMsg, 'not configured') !== false || strpos($errorMsg, 'required') !== false) {
                        $_SESSION['success_message'] = "Password reset successfully, but SMS could not be sent.";
                        $_SESSION['warning_message'] = "SMS service is not configured. Please configure your SMS API key in system settings. Password: {$newPassword} (Please share this securely with the manager)";
                    } elseif ($httpCode == 401) {
                        $_SESSION['success_message'] = "Password reset successfully, but SMS delivery failed.";
                        $_SESSION['warning_message'] = "SMS Error: Invalid API key (HTTP 401). Please check your Arkasel API key in system settings. Password: {$newPassword} (Please share this securely with the manager)";
                    } elseif ($httpCode == 400) {
                        $_SESSION['success_message'] = "Password reset successfully, but SMS delivery failed.";
                        $_SESSION['warning_message'] = "SMS Error: Bad request (HTTP 400) - Phone number format may be incorrect. Phone: {$phoneNumberToUse}, Formatted: " . ($smsResult['phone_formatted'] ?? 'N/A') . ". Password: {$newPassword} (Please share this securely with the manager)";
                    } else {
                        $_SESSION['success_message'] = "Password reset successfully, but SMS delivery failed.";
                        $_SESSION['warning_message'] = "SMS Error: {$detailedError} (Phone: {$phoneNumberToUse}). Password: {$newPassword} (Please share this securely with the manager)";
                    }
                    error_log("CompanyWebController: Password reset succeeded but SMS failed for company {$companyId} to {$phoneNumberToUse}: {$detailedError}");
                    error_log("CompanyWebController: Full SMS result: " . json_encode($smsResult));
                }
            } catch (\Exception $smsException) {
                // Password was reset but SMS failed - still show success but log error
                $_SESSION['success_message'] = "Password reset successfully, but SMS delivery failed. Please contact the manager manually.";
                $_SESSION['warning_message'] = "SMS Error: " . $smsException->getMessage();
                error_log("CompanyWebController: Password reset succeeded but SMS exception for company {$companyId}: " . $smsException->getMessage());
            }
        } else {
            // No phone number - password reset but can't send SMS
            // Do NOT show password to admin - they must contact manager directly
            $_SESSION['success_message'] = "Password reset successfully, but manager phone number not found. Please contact the manager directly to share the new password securely.";
            error_log("CompanyWebController: Password reset succeeded but no phone number for manager of company {$companyId}. Password: {$newPassword} (logged for admin reference only)");
        }

        // Redirect back to companies list
        header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
        exit;
    }

    /**
     * Company Settings page for managers
     * Allows managers to configure which services use company SMS credits
     */
    public function companySettings() {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $userRole = $userData['role'] ?? 'salesperson';
        $companyId = $userData['company_id'] ?? null;
        
        // Allow manager and system_admin to access settings
        if (!in_array($userRole, ['manager', 'system_admin'], true)) {
            $_SESSION['flash_error'] = 'Access Denied: You do not have permission to access company settings. Only managers and system administrators can access this page.';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        // System admin can view any company, manager can only view their own
        if ($userRole === 'system_admin' && isset($_GET['company_id'])) {
            $companyId = (int)$_GET['company_id'];
        }
        
        if (!$companyId) {
            $_SESSION['flash_error'] = 'Company ID is required.';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        // Get company SMS settings
        try {
            $smsAccount = $this->smsAccountModel->getSMSBalance($companyId);
            $settings = [
                'sms_purchase_enabled' => $smsAccount['sms_purchase_enabled'] ?? 1,
                'sms_repair_enabled' => $smsAccount['sms_repair_enabled'] ?? 1,
                'sms_swap_enabled' => $smsAccount['sms_swap_enabled'] ?? 1
            ];
        } catch (\Exception $e) {
            error_log("Error loading company SMS settings: " . $e->getMessage());
            $settings = [
                'sms_purchase_enabled' => 1,
                'sms_repair_enabled' => 1,
                'sms_swap_enabled' => 1
            ];
        }
        
        $pageTitle = 'Company SMS Settings';
        $GLOBALS['currentPage'] = 'company-settings';
        
        // Start output buffering
        ob_start();
        
        // Include company settings view with variables
        include __DIR__ . '/../Views/company_settings.php';
        
        $content = ob_get_clean();
        
        // Include the dashboard layout
        include __DIR__ . '/../Views/layouts/dashboard.php';
    }
}

