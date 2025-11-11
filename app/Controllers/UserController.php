<?php

namespace App\Controllers;

use App\Models\User;
use App\Middleware\AuthMiddleware;

class UserController {
    private $model;

    public function __construct() {
        $this->model = new User();
    }

    /**
     * Get all users in the manager's company
     */
    public function index() {
        try {
            $payload = AuthMiddleware::handle(['manager', 'system_admin']);
            
            if ($payload->role === 'system_admin') {
                // System admin can see all users
                $users = $this->model->all();
            } else {
                // Manager can only see users in their company
                $users = $this->model->allByCompany($payload->company_id);
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $users
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
     * Get single user by ID (Multi-tenant safe)
     */
    public function show($id) {
        try {
            $payload = AuthMiddleware::handle(['manager', 'system_admin']);
            
            // Use company-scoped find for managers (MANDATORY for multi-tenant isolation)
            if ($payload->role === 'manager' && $payload->company_id) {
                $user = $this->model->find($id, $payload->company_id);
            } else {
                // System admin can see any user, but still validate
                $user = $this->model->findById($id);
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found or access denied'
                ]);
                return;
            }
            
            // Additional security check: Managers can only view users in their company
            if ($payload->role === 'manager' && $user['company_id'] != $payload->company_id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access to this user'
                ]);
                return;
            }
            
            // Remove password from response
            unset($user['password']);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $user
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
     * Create new user (salesperson or technician)
     */
    public function store() {
        try {
            $payload = AuthMiddleware::handle(['manager', 'system_admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['username']) || empty($data['email']) || empty($data['role'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Username, email, and role are required'
                ]);
                return;
            }
            
            // Validate role (managers can only create salesperson or technician)
            if ($payload->role === 'manager') {
                if (!in_array($data['role'], ['salesperson', 'technician'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Managers can only create salesperson or technician users'
                    ]);
                    return;
                }
                // Set company_id to manager's company
                $data['company_id'] = $payload->company_id;
            } else if ($payload->role === 'system_admin') {
                // System admin must specify company_id
                if (empty($data['company_id']) && $data['role'] !== 'system_admin') {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'company_id is required'
                    ]);
                    return;
                }
            }
            
            // Set default password if not provided
            $plainPassword = $data['password'] ?? '123456';
            $data['password'] = password_hash($plainPassword, PASSWORD_DEFAULT);
            $data['is_active'] = $data['is_active'] ?? 1;
            $data['unique_id'] = 'USR' . strtoupper(uniqid());
            $data['full_name'] = $data['full_name'] ?? $data['username'];
            
            $success = $this->model->create($data);
            
            if ($success) {
                // Send SMS notification to worker with account details (if phone_number is provided)
                if (!empty($data['phone_number'])) {
                    try {
                        $notificationService = new \App\Services\NotificationService();
                        $accountData = [
                            'phone_number' => $data['phone_number'],
                            'username' => $data['username'],
                            'password' => $plainPassword,
                            'company_id' => $data['company_id'] ?? null
                        ];
                        
                        $smsResult = $notificationService->sendWorkerAccountNotification($accountData);
                        if ($smsResult['success']) {
                            error_log("UserController store: SMS sent successfully to worker {$data['phone_number']}");
                        } else {
                            error_log("UserController store: SMS failed - " . ($smsResult['error'] ?? 'Unknown error'));
                            // Don't fail account creation if SMS fails
                        }
                    } catch (\Exception $smsException) {
                        error_log("UserController store: Error sending SMS notification: " . $smsException->getMessage());
                        // Don't fail account creation if SMS fails
                    }
                }
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => [
                        'username' => $data['username'],
                        'email' => $data['email'],
                        'role' => $data['role']
                    ]
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create user'
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
     * Update user information (Multi-tenant safe)
     */
    public function update($id) {
        try {
            $payload = AuthMiddleware::handle(['manager', 'system_admin']);
            
            // Use company-scoped find for managers (MANDATORY for multi-tenant isolation)
            if ($payload->role === 'manager' && $payload->company_id) {
                $user = $this->model->find($id, $payload->company_id);
            } else {
                // System admin can see any user, but still validate
                $user = $this->model->findById($id);
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found or access denied'
                ]);
                return;
            }
            
            // Additional security check: Managers can only update users in their company
            if ($payload->role === 'manager' && $user['company_id'] != $payload->company_id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access to this user'
                ]);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Hash password if provided
            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Prevent role changes to system_admin or manager by managers
            if ($payload->role === 'manager' && isset($data['role'])) {
                if (!in_array($data['role'], ['salesperson', 'technician'])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Cannot change role to system_admin or manager'
                    ]);
                    return;
                }
            }
            
            // Remove company_id from update data to prevent changing it
            unset($data['company_id']);
            
            // Update with company isolation (only update if belongs to company)
            $companyId = ($payload->role === 'manager') ? $payload->company_id : null;
            $success = $this->model->update($id, $data, $companyId);
            
            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update user'
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
     * Delete user (Multi-tenant safe)
     */
    public function delete($id) {
        try {
            $payload = AuthMiddleware::handle(['manager', 'system_admin']);
            
            // Use company-scoped find for managers (MANDATORY for multi-tenant isolation)
            if ($payload->role === 'manager' && $payload->company_id) {
                $user = $this->model->find($id, $payload->company_id);
            } else {
                // System admin can see any user, but still validate
                $user = $this->model->findById($id);
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found or access denied'
                ]);
                return;
            }
            
            // Additional security check: Managers can only delete users in their company
            if ($payload->role === 'manager' && $user['company_id'] != $payload->company_id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unauthorized access to this user'
                ]);
                return;
            }
            
            // Prevent deletion of system_admin or manager by managers
            if ($payload->role === 'manager' && in_array($user['role'], ['system_admin', 'manager'])) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Cannot delete system_admin or manager users'
                ]);
                return;
            }
            
            // Delete with company isolation (only delete if belongs to company)
            $companyId = ($payload->role === 'manager') ? $payload->company_id : null;
            $success = $this->model->delete($id, $companyId);
            
            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete user'
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
}



