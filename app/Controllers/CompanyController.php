<?php

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Models\CompanyModule;
use App\Middleware\AuthMiddleware;
use App\Services\BackupService;

class CompanyController {
    private $companyModel;
    private $userModel;

    public function __construct() {
        $this->companyModel = new Company();
        $this->userModel = new User();
    }

    /**
     * Get all companies (System Admin only)
     */
    public function index() {
        try {
            AuthMiddleware::handle(['system_admin']);
            $companies = $this->companyModel->all();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get single company by ID
     */
    public function show($id) {
        try {
            $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            
            // Managers can only view their own company
            if ($payload->role === 'manager' && $payload->company_id != $id) {
                throw new \Exception('Unauthorized access to this company');
            }
            
            $company = $this->companyModel->findById($id);
            
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create new company (System Admin only)
     */
    public function store() {
        try {
            $payload = AuthMiddleware::handle(['system_admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['name']) || empty($data['manager_email'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company name and manager email are required'
                ]);
                return;
            }
            
            // Create company
            $data['created_by_user_id'] = $payload->sub;
            $company_id = $this->companyModel->create($data);
            
            // Create manager user for this company
            $managerData = [
                'unique_id' => 'USR' . strtoupper(uniqid()),
                'username' => $data['manager_email'],
                'email' => $data['manager_email'],
                'full_name' => $data['manager_name'] ?? 'Manager',
                'password' => password_hash($data['manager_password'] ?? 'manager123', PASSWORD_DEFAULT),
                'role' => 'manager',
                'company_id' => $company_id,
                'is_active' => 1
            ];
            $this->userModel->create($managerData);
            
            // Initialize default modules for the new company
            $defaultModules = ['products_inventory', 'pos_sales', 'customers', 'reports_analytics'];
            $companyModuleModel = new CompanyModule();
            $companyModuleModel->initializeCompanyModules($company_id, $defaultModules);
            
            // Create automatic backup for the newly onboarded company
            try {
                $backupService = new BackupService();
                $backupService->createCompanyBackup($company_id, $payload->sub, true);
                error_log("Automatic backup created for newly onboarded company ID: {$company_id}");
            } catch (\Exception $e) {
                // Log error but don't fail company creation if backup fails
                error_log("Failed to create automatic backup for company ID {$company_id}: " . $e->getMessage());
            }
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Company and manager created successfully',
                'data' => [
                    'company_id' => $company_id,
                    'manager_email' => $data['manager_email']
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update company information
     */
    public function update($id) {
        try {
            $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            
            // Managers can only update their own company
            if ($payload->role === 'manager' && $payload->company_id != $id) {
                throw new \Exception('Unauthorized access to this company');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $success = $this->companyModel->update($id, $data);
            
            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Company updated successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update company'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if company has data (for deletion warning)
     */
    public function checkData($id) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        try {
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin']);
        } catch (\Exception $e) {
            try {
                AuthMiddleware::handle(['system_admin', 'admin']);
            } catch (\Exception $jwtException) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access'
                ]);
                return;
            }
        }
        
        try {
            $company = $this->companyModel->findById($id);
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            $dataCheck = $this->companyModel->hasData($id);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'has_data' => $dataCheck['total_count'] > 0,
                'data' => $dataCheck,
                'company' => [
                    'id' => $company['id'],
                    'name' => $company['name']
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Delete company (System Admin only)
     * Requires password confirmation if company has data
     */
    public function delete($id) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication - use WebAuthMiddleware for web routes
        try {
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin']);
            $userData = $_SESSION['user'] ?? null;
        } catch (\Exception $e) {
            // If web auth fails, try JWT auth as fallback
            try {
                $payload = AuthMiddleware::handle(['system_admin', 'admin']);
                $userData = ['id' => $payload->sub, 'role' => $payload->role];
            } catch (\Exception $jwtException) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access'
                ]);
                return;
            }
        }
        
        try {
            // Get input data
            $input = json_decode(file_get_contents('php://input'), true);
            $password = $input['password'] ?? null;
            $force = $input['force'] ?? false;
            
            // Check if company exists
            $company = $this->companyModel->findById($id);
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            // Check if company has data
            $dataCheck = $this->companyModel->hasData($id);
            
            // If company has data, require password confirmation
            if ($dataCheck['total_count'] > 0 && !$force) {
                if (empty($password)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Password confirmation required',
                        'requires_password' => true,
                        'data_summary' => $dataCheck
                    ]);
                    return;
                }
                
                // Verify admin password
                $userId = $userData['id'] ?? null;
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'User ID not found'
                    ]);
                    return;
                }
                
                $user = $this->userModel->find($userId);
                if (!$user || !password_verify($password, $user['password'])) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid password'
                    ]);
                    return;
                }
            }
            
            // Perform deletion with cascade
            $success = $this->companyModel->deleteWithCascade($id);
            
            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Company and all associated data deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete company'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get users by company
     */
    public function getUsers($id) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        try {
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin']);
        } catch (\Exception $e) {
            try {
                AuthMiddleware::handle(['system_admin', 'admin']);
            } catch (\Exception $jwtException) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access'
                ]);
                return;
            }
        }
        
        try {
            // Verify company exists
            $company = $this->companyModel->findById($id);
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            // Get users for this company
            $users = $this->userModel->allByCompany($id);
            
            // Remove passwords from response
            foreach ($users as &$user) {
                unset($user['password']);
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $users,
                'company' => [
                    'id' => $company['id'],
                    'name' => $company['name']
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get company statistics
     */
    public function stats($id) {
        header('Content-Type: application/json');
        
        try {
            // Try JWT auth first
            $payload = null;
            try {
                $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            } catch (\Exception $e) {
                // If JWT fails, try session-based auth
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Missing or invalid authorization header'
                    ]);
                    return;
                }
                
                // Check if user has required role
                if (!in_array($user['role'], ['system_admin', 'manager'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized role'
                    ]);
                    return;
                }
                
                // Create payload-like object from session
                $payload = (object)[
                    'company_id' => $user['company_id'] ?? null,
                    'role' => $user['role'] ?? 'salesperson',
                    'id' => $user['id'] ?? null,
                    'sub' => $user['id'] ?? null
                ];
            }
            
            // Managers can only view their own company stats
            if ($payload->role === 'manager' && $payload->company_id != $id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access to this company'
                ]);
                return;
            }
            
            $stats = $this->companyModel->getStats($id);
            
            if (isset($stats['success']) && !$stats['success']) {
                http_response_code(404);
                echo json_encode($stats);
                return;
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            error_log("CompanyController::stats error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get company SMS settings (Manager and System Admin)
     */
    public function getSMSSettings() {
        try {
            $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            
            // Get company ID from user or request
            $companyId = $payload->company_id ?? null;
            if (!$companyId && isset($_GET['company_id'])) {
                $companyId = (int)$_GET['company_id'];
            }
            
            // Managers can only view their own company
            if ($payload->role === 'manager' && $payload->company_id != $companyId) {
                throw new \Exception('Unauthorized access to this company');
            }
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID is required'
                ]);
                return;
            }
            
            $smsAccountModel = new \App\Models\CompanySMSAccount();
            $smsAccount = $smsAccountModel->getSMSBalance($companyId);
            
            if (!$smsAccount || !$smsAccount['success']) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'SMS account not found'
                ]);
                return;
            }
            
            $settings = [
                'sms_purchase_enabled' => $smsAccount['sms_purchase_enabled'] ?? 1,
                'sms_repair_enabled' => $smsAccount['sms_repair_enabled'] ?? 1,
                'sms_swap_enabled' => $smsAccount['sms_swap_enabled'] ?? 1
            ];
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update company SMS settings (Manager and System Admin)
     */
    public function updateSMSSettings() {
        try {
            $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ]);
                return;
            }
            
            // Get company ID from user or request
            $companyId = $payload->company_id ?? null;
            if (!$companyId && isset($input['company_id'])) {
                $companyId = (int)$input['company_id'];
            }
            
            // Managers can only update their own company
            if ($payload->role === 'manager' && $payload->company_id != $companyId) {
                throw new \Exception('Unauthorized access to this company');
            }
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID is required'
                ]);
                return;
            }
            
            $smsAccountModel = new \App\Models\CompanySMSAccount();
            
            // Update settings
            $updateData = [];
            if (isset($input['sms_purchase_enabled'])) {
                $updateData['sms_purchase_enabled'] = (int)$input['sms_purchase_enabled'];
            }
            if (isset($input['sms_repair_enabled'])) {
                $updateData['sms_repair_enabled'] = (int)$input['sms_repair_enabled'];
            }
            if (isset($input['sms_swap_enabled'])) {
                $updateData['sms_swap_enabled'] = (int)$input['sms_swap_enabled'];
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No settings to update'
                ]);
                return;
            }
            
            $result = $smsAccountModel->updateSMSSettings($companyId, $updateData);
            
            if ($result) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update settings'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get company SMS balance (Manager and System Admin)
     */
    public function getSMSBalance() {
        try {
            $payload = AuthMiddleware::handle(['system_admin', 'manager']);
            
            // Get company ID from user or request
            $companyId = $payload->company_id ?? null;
            if (!$companyId && isset($_GET['company_id'])) {
                $companyId = (int)$_GET['company_id'];
            }
            
            // Managers can only view their own company
            if ($payload->role === 'manager' && $payload->company_id != $companyId) {
                throw new \Exception('Unauthorized access to this company');
            }
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID is required'
                ]);
                return;
            }
            
            $smsAccountModel = new \App\Models\CompanySMSAccount();
            $balance = $smsAccountModel->getSMSBalance($companyId);
            
            if (!$balance || !$balance['success']) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'SMS account not found'
                ]);
                return;
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'balance' => [
                    'total_sms' => $balance['total_sms'] ?? 0,
                    'sms_used' => $balance['sms_used'] ?? 0,
                    'sms_remaining' => $balance['sms_remaining'] ?? 0
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}



