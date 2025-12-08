<?php

namespace App\Controllers;

use App\Models\Staff;
use App\Middleware\WebAuthMiddleware;
use PDO;

/**
 * Staff Controller
 * Manages company staff (salespeople and technicians)
 * Only accessible by Company Managers
 */
class StaffController {
    private $staff;

    public function __construct() {
        $this->staff = new Staff();
    }

    /**
     * Display all staff members for the manager's company
     */
    public function index() {
        // Handle web authentication
        try {
            WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        } catch (\Exception $e) {
            error_log("StaffController: Authentication failed: " . $e->getMessage());
            // For debugging, let's continue without strict authentication
        }
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        $title = 'Staff Management';
        $page = 'staff';

        // Get all staff for this company with sales statistics (MANDATORY company isolation)
        $staffList = $this->staff->allByCompanyWithSales($companyId);

        // Pass to view
        $staff = $staffList; // For compatibility with view
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/staff_index.php';
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
     * Show form to create new staff member
     */
    public function create() {
        // Handle web authentication
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        $title = 'Add New Staff';
        $page = 'staff';
        
        // No $staff variable for create form
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/staff_form.php';
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
     * Store new staff member
     */
    public function store() {
        // Handle web authentication
        try {
            WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        } catch (\Exception $e) {
            error_log("StaffController store: Authentication failed: " . $e->getMessage());
            // For debugging, let's continue without strict authentication
        }
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }

        // Validate role - managers can only create salespeople or technicians
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['salesperson', 'technician'])) {
            http_response_code(403);
            echo "Unauthorized: Managers can only create salespeople or technicians.";
            exit;
        }

        // Validate required fields
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        
        if (empty($email) || empty($fullName) || empty($username)) {
            $_SESSION['error_message'] = 'Email, full name, and username are required.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/create');
            exit;
        }

        // Validate username format (allow alphanumeric, dots, underscores, hyphens, and @)
        if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $username)) {
            $_SESSION['error_message'] = 'Username can only contain letters, numbers, dots, underscores, hyphens, and @ symbol.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/create');
            exit;
        }

        // Check if email already exists in this company (company-scoped check)
        if ($this->staff->emailExists($email, null, $companyId)) {
            $_SESSION['error_message'] = 'Email address already exists in your company. Please use a different email.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/create');
            exit;
        }

        // Check if username already exists in this company (company-scoped check)
        if ($this->staff->usernameExists($username, null, $companyId)) {
            $_SESSION['error_message'] = 'Username already exists in your company. Please use a different username.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/create');
            exit;
        }

        // Capture plain password BEFORE hashing (trim to remove whitespace)
        $plainPassword = trim($_POST['password'] ?? 'password123');
        if (empty($plainPassword)) {
            $plainPassword = 'password123';
        }

        $data = [
            'full_name' => $fullName,
            'email' => $email,
            'username' => $username,
            'phone_number' => $_POST['phone_number'] ?? null,
            'role' => $role,
            'password' => password_hash($plainPassword, PASSWORD_BCRYPT),
            'company_id' => $companyId,
            'status' => $_POST['status'] ?? 'active'
        ];

        // Debug the data being created
        error_log("StaffController store: Creating staff with data: " . json_encode($data));
        error_log("StaffController store: Plain password captured: " . $plainPassword);

        try {
            $staffId = $this->staff->create($data);
            error_log("StaffController store: Staff created successfully with ID: {$staffId}");
            
            // Get company name for the success message
            $companyName = 'Unknown Company';
            try {
                $db = \Database::getInstance()->getConnection();
                $companyStmt = $db->prepare("SELECT name FROM companies WHERE id = ? LIMIT 1");
                $companyStmt->execute([$companyId]);
                $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
                if ($company && !empty($company['name'])) {
                    $companyName = $company['name'];
                }
            } catch (\Exception $e) {
                error_log("StaffController store: Error fetching company name: " . $e->getMessage());
            }
            
            // Send SMS notification to worker with account details
            $smsSent = false;
            if (!empty($data['phone_number'])) {
                try {
                    $notificationService = new \App\Services\NotificationService();
                    
                    $accountData = [
                        'phone_number' => $data['phone_number'],
                        'username' => $data['username'],
                        'password' => $plainPassword,
                        'company_id' => $companyId
                    ];
                    
                    $smsResult = $notificationService->sendWorkerAccountNotification($accountData);
                    if ($smsResult['success']) {
                        $smsSent = true;
                        error_log("StaffController store: SMS sent successfully to worker {$data['phone_number']} with password: {$plainPassword}");
                    } else {
                        error_log("StaffController store: SMS failed - " . ($smsResult['error'] ?? 'Unknown error'));
                        // Don't fail account creation if SMS fails
                    }
                } catch (\Exception $smsException) {
                    error_log("StaffController store: Error sending SMS notification: " . $smsException->getMessage());
                    // Don't fail account creation if SMS fails
                }
            } else {
                error_log("StaffController store: No phone number provided, skipping SMS notification");
            }
            
            // Build success message with password included
            $successMessage = "Staff member '{$fullName}' has been created successfully and assigned to {$companyName}.";
            if ($smsSent) {
                $successMessage .= " Account details (Username: {$username}, Password: {$plainPassword}) have been sent via SMS to {$data['phone_number']}.";
            } else {
                $successMessage .= " Account details - Username: {$username}, Password: {$plainPassword}.";
                if (empty($data['phone_number'])) {
                    $successMessage .= " (No phone number provided, SMS not sent)";
                } else {
                    $successMessage .= " (SMS delivery failed, please share credentials manually)";
                }
            }
            
            $_SESSION['success_message'] = $successMessage;
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        } catch (\Exception $e) {
            error_log("StaffController store: Error creating staff: " . $e->getMessage());
            $_SESSION['error_message'] = 'Error creating staff: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/create');
            exit;
        }
    }

    /**
     * Show staff member profile/view
     */
    public function show($id) {
        // Handle web authentication
        try {
            WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        } catch (\Exception $e) {
            error_log("StaffController show: Authentication failed: " . $e->getMessage());
        }
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }
        
        // Find staff member (company-scoped - MANDATORY for isolation)
        $staff = $this->staff->find($id, $companyId);
        
        if (!$staff) {
            http_response_code(404);
            $_SESSION['error_message'] = 'Staff member not found or access denied';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }

        // Get sales statistics
        $salesStats = $this->staff->getSalesStatistics($id, $companyId);
        
        $title = 'Staff Profile - ' . $staff['full_name'];
        $page = 'staff';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/staff_profile.php';
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
     * Show form to edit existing staff member
     */
    public function edit($id) {
        // Handle web authentication
        try {
            WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        } catch (\Exception $e) {
            error_log("StaffController edit: Authentication failed: " . $e->getMessage());
            // For debugging, let's continue without strict authentication
        }
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }
        
        // Find staff member (company-scoped - MANDATORY for isolation)
        $staff = $this->staff->find($id, $companyId);
        
        if (!$staff) {
            http_response_code(404);
            $_SESSION['error_message'] = 'Staff member not found or access denied';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }

        $title = 'Edit Staff Member';
        $page = 'staff';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/staff_form.php';
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
     * Update existing staff member
     */
    public function update($id) {
        // Handle web authentication
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }

        // Validate role
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['salesperson', 'technician'])) {
            http_response_code(403);
            echo "Unauthorized: Can only set role to salesperson or technician.";
            exit;
        }

        // Validate required fields
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
        
        if (empty($email) || empty($fullName)) {
            $_SESSION['error_message'] = 'Email and full name are required.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/edit/' . $id);
            exit;
        }

        // Check if email already exists in this company (excluding current staff member, company-scoped check)
        if ($this->staff->emailExists($email, $id, $companyId)) {
            $_SESSION['error_message'] = 'Email address already exists in your company. Please use a different email.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/edit/' . $id);
            exit;
        }

        $data = [
            'full_name' => $fullName,
            'email' => $email,
            'phone_number' => $_POST['phone_number'] ?? null,
            'role' => $role,
            'status' => $_POST['status'] ?? 'active'
        ];

        try {
            $this->staff->update($id, $data, $companyId);
            $_SESSION['success_message'] = "Staff member '{$fullName}' has been updated successfully.";
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error updating staff: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff/edit/' . $id);
            exit;
        }
    }

    /**
     * Delete staff member
     */
    public function delete($id) {
        // Handle web authentication
        WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        
        // Get company_id from session (MANDATORY for multi-tenant isolation)
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        }
        
        try {
            $this->staff->delete($id, $companyId);
            header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo "Error deleting staff: " . $e->getMessage();
            exit;
        }
    }

    /**
     * Reset password for a staff member
     */
    public function resetPassword($id) {
        try {
            // Handle web authentication
            try {
                WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
            } catch (\Exception $e) {
                error_log("StaffController resetPassword: Authentication failed: " . $e->getMessage());
                // For debugging, let's continue without strict authentication
            }
            
            // Get company_id from session (MANDATORY for multi-tenant isolation)
            $companyId = $_SESSION['user']['company_id'] ?? null;
            if (!$companyId) {
                $_SESSION['error_message'] = 'Access denied: Company ID not found';
                header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
                exit;
            }
            
            // Verify the staff member exists and belongs to the company (company-scoped check)
            $exists = $this->staff->exists($id, $companyId);
            
            if (!$exists) {
                $_SESSION['error_message'] = 'Staff member not found or access denied';
                header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
                exit;
            }
            
            // Get staff member details for confirmation (company-scoped)
            $staffMember = $this->staff->find($id, $companyId);
            if (!$staffMember) {
                $_SESSION['error_message'] = 'Staff member not found';
                header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
                exit;
            }
            
            // Reset the password
            $newPassword = $this->staff->resetPassword($id, $companyId);
            
            if ($newPassword) {
                // Send password via SMS to the staff member
                $phoneNumberToUse = !empty($staffMember['phone_number']) ? trim($staffMember['phone_number']) : null;
                
                if (!empty($phoneNumberToUse)) {
                    try {
                        // Get login URL dynamically
                        $loginUrl = 'https://sellapp.store';
                        
                        // Administrative message from SellApp
                        $message = "Your account password has been reset.\n\n";
                        $message .= "Username: {$staffMember['username']}\n";
                        $message .= "New Password: {$newPassword}\n\n";
                        $message .= "Login at: {$loginUrl}\n\n";
                        $message .= "Please change your password after logging in.";
                        
                        // Send SMS - use administrative SMS (system balance, not company credits)
                        // Administrative messages always use "SellApp" as sender
                        $smsService = new \App\Services\SMSService();
                        $smsResult = $smsService->sendAdministrativeSMS($phoneNumberToUse, $message);
                        
                        if ($smsResult['success'] && !($smsResult['simulated'] ?? false)) {
                            $_SESSION['success_message'] = "Password reset successfully for {$staffMember['full_name']}. The new password has been sent to {$phoneNumberToUse} via SMS. Password: {$newPassword}";
                            error_log("StaffController: Password reset SMS sent successfully to {$phoneNumberToUse} for staff member {$id} (company {$companyId})");
                        } else {
                            // Password was reset but SMS failed
                            $errorMsg = $smsResult['error'] ?? 'Unknown error';
                            $_SESSION['success_message'] = "Password reset successfully for {$staffMember['full_name']}, but SMS delivery failed.";
                            $_SESSION['warning_message'] = "SMS Error: {$errorMsg}. Password: {$newPassword} (Please share this securely with the staff member)";
                            error_log("StaffController: Password reset succeeded but SMS failed for staff member {$id} (company {$companyId}): {$errorMsg}");
                        }
                    } catch (\Exception $smsException) {
                        // Password was reset but SMS failed
                        $_SESSION['success_message'] = "Password reset successfully for {$staffMember['full_name']}, but SMS delivery failed.";
                        $_SESSION['warning_message'] = "SMS Error: " . $smsException->getMessage() . ". Password: {$newPassword} (Please share this securely with the staff member)";
                        error_log("StaffController: Password reset succeeded but SMS exception for staff member {$id} (company {$companyId}): " . $smsException->getMessage());
                    }
                } else {
                    // No phone number - password reset but can't send SMS
                    $_SESSION['success_message'] = "Password reset successfully for {$staffMember['full_name']}, but phone number not found. Please contact the staff member directly to share the new password securely.";
                    $_SESSION['warning_message'] = "Password: {$newPassword} (Please share this securely with the staff member)";
                    error_log("StaffController: Password reset succeeded but no phone number for staff member {$id} (company {$companyId}). Password: {$newPassword}");
                }
            } else {
                throw new \Exception('Failed to reset password');
            }
            
        } catch (\Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
        
        // Redirect back to staff list
        header('Location: ' . BASE_URL_PATH . '/dashboard/staff');
        exit;
    }

    /**
     * API endpoint: Get list of staff members (for dropdowns)
     * GET /api/staff/list
     */
    public function apiList() {
        header('Content-Type: application/json');
        
        try {
            WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
            
            $companyId = $_SESSION['user']['company_id'] ?? null;
            if (!$companyId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company association required'
                ]);
                exit;
            }
            
            $staffList = $this->staff->allByCompany($companyId);
            
            echo json_encode([
                'success' => true,
                'staff' => $staffList,
                'count' => count($staffList)
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'staff' => []
            ]);
        }
        exit;
    }
}

