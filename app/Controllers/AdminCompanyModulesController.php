<?php

namespace App\Controllers;

use App\Models\CompanyModule;
use App\Middleware\AuthMiddleware;

/**
 * Admin Company Modules Controller
 * Handles module toggling for companies (System Admin only)
 */
class AdminCompanyModulesController {
    
    private $companyModuleModel;
    
    public function __construct() {
        $this->companyModuleModel = new CompanyModule();
    }
    
    /**
     * Get all modules for a company
     * GET /api/admin/company/{id}/modules
     * System Admins can view any company's modules
     * Managers, Salespersons, and Technicians can view their own company's modules (read-only)
     */
    public function getModules($companyId) {
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Validate company ID first
            $companyId = trim($companyId ?? '');
            if (empty($companyId) || !is_numeric($companyId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $companyId = (int)$companyId;
            
            // Check if user is logged in
            $user = $_SESSION['user'] ?? null;
            if (!$user || !is_array($user)) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access this resource'
                ]);
                return;
            }
            
            $userRole = $user['role'] ?? '';
            $userCompanyId = isset($user['company_id']) ? (int)$user['company_id'] : null;
            
            // If manager or salesperson is requesting their own company, allow read-only access
            if (($userRole === 'manager' || $userRole === 'salesperson' || $userRole === 'technician') && $userCompanyId === $companyId && $userCompanyId !== null) {
                // Allow read-only access for managers/salespersons/technicians viewing their own company
                // No need to call checkAuth() which requires system_admin
            } elseif ($userRole === 'system_admin' || $userRole === 'admin') {
                // System Admin or Admin can view any company's modules
                // Already authenticated via session check above
            } else {
                // Other roles or user trying to access different company - deny
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'You do not have permission to access this resource'
                ]);
                return;
            }
            
            // Verify company exists
            $db = \Database::getInstance()->getConnection();
            $companyCheck = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            $company = $companyCheck->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            // Get all available modules (from SYSTEM_MODULE_AUDIT.json recommended keys)
            $availableModules = [
                'products_inventory' => 'Products & Inventory',
                'pos_sales' => 'POS / Sales',
                'partial_payments' => 'Partial Payments',
                'swap' => 'Swap',
                'repairs' => 'Repairs',
                'customers' => 'Customers',
                'staff_management' => 'Staff Management',
                'reports_analytics' => 'Reports & Analytics',
                'notifications_sms' => 'Notifications & SMS',
                'suppliers' => 'Suppliers',
                'purchase_orders' => 'Purchase Orders',
                'manager_delete_sales' => 'Manager Delete Sales',
                'manager_bulk_delete_sales' => 'Manager Bulk Delete Sales',
                'manager_can_sell' => 'Manager Can Sell',
                'manager_create_contact' => 'Manager Create Contact',
                'manager_delete_contact' => 'Manager Delete Contact',
                'charts' => 'Dashboard Charts'
            ];
            
            // Get enabled modules for this company
            $enabledModules = $this->companyModuleModel->getEnabledModules($companyId);
            
            // Build response with module status
            $modules = [];
            foreach ($availableModules as $moduleKey => $moduleName) {
                $modules[] = [
                    'key' => $moduleKey,
                    'name' => $moduleName,
                    'enabled' => in_array($moduleKey, $enabledModules)
                ];
            }
            
            echo json_encode([
                'success' => true,
                'company_id' => $companyId,
                'company_name' => $company['name'],
                'modules' => $modules
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
     * Toggle module for a company
     * POST /api/admin/company/{id}/modules/toggle
     */
    public function toggleModule($companyId) {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        header('Content-Type: application/json');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check authentication - System Admin only
            $this->checkAuth();
            
            // Validate company ID
            $companyId = trim($companyId ?? '');
            if (empty($companyId) || !is_numeric($companyId)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid company ID'
                ]);
                return;
            }
            
            $companyId = (int)$companyId;
            
            // Get request data - try JSON first, then fall back to POST data
            $input = null;
            $rawInput = file_get_contents('php://input');
            
            if (!empty($rawInput)) {
                $input = json_decode($rawInput, true);
                // If JSON decode failed, try to parse as form data
                if (json_last_error() !== JSON_ERROR_NONE) {
                    parse_str($rawInput, $input);
                }
            }
            
            // Fall back to $_POST if JSON input is empty
            if (empty($input) && !empty($_POST)) {
                $input = $_POST;
            }
            
            // If still empty, return error
            if (empty($input)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Request data is required',
                    'debug' => 'No input data received'
                ]);
                return;
            }
            
            $moduleKey = $input['module_key'] ?? null;
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : null;
            
            if (!$moduleKey) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Module key is required',
                    'debug' => 'Received input: ' . json_encode($input)
                ]);
                return;
            }
            
            if ($enabled === null) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Enabled status is required',
                    'debug' => 'Received input: ' . json_encode($input)
                ]);
                return;
            }
            
            // Verify company exists
            $db = \Database::getInstance()->getConnection();
            $companyCheck = $db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            $company = $companyCheck->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company not found'
                ]);
                return;
            }
            
            // Validate module key
            $validModules = [
                'products_inventory',
                'pos_sales',
                'partial_payments',
                'swap',
                'repairs',
                'customers',
                'staff_management',
                'reports_analytics',
                'notifications_sms',
                'suppliers',
                'purchase_orders',
                'charts',
                'manager_delete_sales',
                'manager_bulk_delete_sales',
                'manager_can_sell',
                'manager_create_contact',
                'manager_delete_contact'
            ];
            
            if (!in_array($moduleKey, $validModules)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid module key'
                ]);
                return;
            }
            
            // Update or create module status
            $result = $this->companyModuleModel->setModuleStatus($companyId, $moduleKey, $enabled);
            
            if ($result) {
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Module status updated successfully',
                    'data' => [
                        'company_id' => $companyId,
                        'module_key' => $moduleKey,
                        'enabled' => $enabled
                    ]
                ]);
            } else {
                throw new \Exception('Failed to update module status');
            }
            
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            error_log("AdminCompanyModulesController::toggleModule error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check authentication - System Admin only (or Manager for their own company in read mode)
     * Checks session first, then falls back to JWT
     */
    private function checkAuth() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first (for web routes)
        // This is more reliable for pages accessed through the browser
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $userRole = $_SESSION['user']['role'] ?? '';
            if ($userRole === 'system_admin') {
                return (object)$_SESSION['user'];
            }
        }
        
        // Fall back to JWT authentication (for API routes or when session not available)
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            try {
                $token = substr($authHeader, 7);
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                
                // Check role-based access
                if ($payload->role !== 'system_admin') {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized: System Admin access required'
                    ]);
                    exit;
                }
                
                return $payload;
            } catch (\Exception $e) {
                // JWT validation failed - check if we have session data anyway
                if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $userRole = $_SESSION['user']['role'] ?? '';
                    if ($userRole !== 'system_admin') {
                        http_response_code(403);
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => 'Unauthorized: System Admin access required'
                        ]);
                        exit;
                    }
                    // Return session user if valid
                    return (object)$_SESSION['user'];
                }
                
                // No valid session or JWT - return authentication error
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or expired token',
                    'message' => 'Please login again'
                ]);
                exit;
            }
        } else {
            // No auth header - check if we have session data
            if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                $userRole = $_SESSION['user']['role'] ?? '';
                if ($userRole !== 'system_admin') {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized: System Admin access required'
                    ]);
                    exit;
                }
                // Return session user if valid
                return (object)$_SESSION['user'];
            }
            
            // No valid session or JWT - return authentication error
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'message' => 'Please login again'
            ]);
            exit;
        }
    }
}

