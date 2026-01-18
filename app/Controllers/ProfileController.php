<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\WebAuthMiddleware;

// Ensure database class is loaded
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
}

/**
 * Profile Controller
 * Handles user profile and settings for all roles
 */
class ProfileController {
    
    /**
     * Display profile page
     */
    public function profile() {
        // Suppress error reporting to prevent HTML errors in output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Start session to get user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $userRole = $userData['role'] ?? 'salesperson';
        
        // Get additional user data from database
        $userId = $userData['id'] ?? $userData['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $user = $this->getUserDetails($userId, $userRole);
        
        // Set current page for sidebar highlighting
        $GLOBALS['currentPage'] = 'profile';
        
        // Render profile page
        $this->renderProfilePage($user, $userRole);
    }
    
    /**
     * Display settings page
     */
    public function settings() {
        // Suppress error reporting to prevent HTML errors in output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Start session to get user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $userRole = $userData['role'] ?? 'salesperson';
        
        // For system admin, redirect to system settings
        if ($userRole === 'system_admin') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/system-settings');
            exit;
        }
        
        // For other roles, show user settings
        $userId = $userData['id'] ?? $userData['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        $user = $this->getUserDetails($userId, $userRole);
        
        // Set current page for sidebar highlighting
        $GLOBALS['currentPage'] = 'settings';
        
        // Render settings page
        $this->renderSettingsPage($user, $userRole);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile() {
        try {
            // Use session-based authentication for web pages
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if (!$userData) {
                throw new \Exception("User not authenticated");
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;
            $userRole = $userData['role'] ?? 'salesperson';
            
            if (!$userId) {
                throw new \Exception("User ID not found");
            }
            
            // Handle full_name - can come as full_name or first_name + last_name
            $fullName = null;
            if (isset($input['full_name']) && !empty($input['full_name'])) {
                $fullName = trim($input['full_name']);
            } elseif (isset($input['first_name']) || isset($input['last_name'])) {
                $firstName = trim($input['first_name'] ?? '');
                $lastName = trim($input['last_name'] ?? '');
                $fullName = trim($firstName . ' ' . $lastName);
            }
            
            // Validate required fields
            if (empty($fullName)) {
                throw new \Exception("Full name is required (provide full_name or both first_name and last_name)");
            }
            
            if (empty($input['email'])) {
                throw new \Exception("Email is required");
            }
            
            // Validate email format
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Invalid email format");
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Build update query dynamically based on provided fields
            $updateFields = [];
            $updateParams = [];
            
            // Always update full_name
            $updateFields[] = "full_name = ?";
            $updateParams[] = $fullName;
            
            if (isset($input['email'])) {
                $updateFields[] = "email = ?";
                $updateParams[] = $input['email'];
            }
            
            if (isset($input['phone_number']) || isset($input['phone'])) {
                $updateFields[] = "phone_number = ?";
                $updateParams[] = $input['phone_number'] ?? $input['phone'] ?? null;
            }
            
            if (isset($input['username'])) {
                $updateFields[] = "username = ?";
                $updateParams[] = $input['username'];
            }
            
            // Add updated_at
            $updateFields[] = "updated_at = NOW()";
            
            if (empty($updateFields)) {
                throw new \Exception("No fields to update");
            }
            
            // Add user id to params for WHERE clause
            $updateParams[] = $userId;
            
            // Update user profile
            $updateQuery = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            
            $result = $updateQuery->execute($updateParams);
            
            if (!$result) {
                throw new \Exception("Failed to update profile");
            }
            
            // Update session data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user']['full_name'] = $fullName;
            if (isset($input['email'])) {
                $_SESSION['user']['email'] = $input['email'];
            }
            if (isset($input['phone_number']) || isset($input['phone'])) {
                $_SESSION['user']['phone_number'] = $input['phone_number'] ?? $input['phone'] ?? null;
            }
            if (isset($input['username'])) {
                $_SESSION['user']['username'] = $input['username'];
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update user settings
     */
    public function updateSettings() {
        try {
            // Use session-based authentication for web pages
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if (!$userData) {
                throw new \Exception("User not authenticated");
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;
            $userRole = $userData['role'] ?? 'salesperson';
            
            if (!$userId) {
                throw new \Exception("User ID not found");
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Update user settings based on role
            $settings = [];
            
            if ($userRole === 'system_admin') {
                // System admin settings
                $settings = [
                    'notifications_email' => $input['notifications_email'] ?? 1,
                    'notifications_sms' => $input['notifications_sms'] ?? 0,
                    'theme' => $input['theme'] ?? 'light',
                    'timezone' => $input['timezone'] ?? 'UTC'
                ];
            } elseif (in_array($userRole, ['manager', 'admin'])) {
                // Manager/Admin settings
                $settings = [
                    'notifications_email' => $input['notifications_email'] ?? 1,
                    'notifications_sms' => $input['notifications_sms'] ?? 0,
                    'theme' => $input['theme'] ?? 'light',
                    'timezone' => $input['timezone'] ?? 'UTC',
                    'dashboard_refresh' => $input['dashboard_refresh'] ?? 30
                ];
            } else {
                // Salesperson/Technician settings
                $settings = [
                    'notifications_email' => $input['notifications_email'] ?? 1,
                    'notifications_sms' => $input['notifications_sms'] ?? 0,
                    'theme' => $input['theme'] ?? 'light',
                    'timezone' => $input['timezone'] ?? 'UTC'
                ];
            }
            
            // Update settings in database
            $updateQuery = $db->prepare("
                UPDATE users 
                SET settings = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $updateQuery->execute([
                json_encode($settings),
                $userId
            ]);
            
            if (!$result) {
                throw new \Exception("Failed to update settings");
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword() {
        try {
            // Use session-based authentication for web pages
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if (!$userData) {
                throw new \Exception("User not authenticated");
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;
            
            if (!$userId) {
                throw new \Exception("User ID not found");
            }
            
            // Validate required fields
            if (empty($input['current_password']) || empty($input['new_password']) || empty($input['confirm_password'])) {
                throw new \Exception("All password fields are required");
            }
            
            if ($input['new_password'] !== $input['confirm_password']) {
                throw new \Exception("New passwords do not match");
            }
            
            if (strlen($input['new_password']) < 6) {
                throw new \Exception("New password must be at least 6 characters long");
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Verify current password
            $userQuery = $db->prepare("SELECT password FROM users WHERE id = ?");
            $userQuery->execute([$userId]);
            $user = $userQuery->fetch();
            
            if (!$user || !password_verify($input['current_password'], $user['password'])) {
                throw new \Exception("Current password is incorrect");
            }
            
            // Update password
            $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);
            $updateQuery = $db->prepare("
                UPDATE users 
                SET password = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $updateQuery->execute([$hashedPassword, $userId]);
            
            if (!$result) {
                throw new \Exception("Failed to update password");
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get user details from database
     */
    private function getUserDetails($userId, $userRole) {
        // Ensure Database class is loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        $db = \Database::getInstance()->getConnection();
        
        $query = $db->prepare("
            SELECT u.*, c.name as company_name, c.id as company_id
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.id = ?
        ");
        $query->execute([$userId]);
        $user = $query->fetch();
        
        if ($user) {
            // Parse settings JSON
            $user['settings'] = json_decode($user['settings'] ?? '{}', true);
            
            // Split full_name into first_name and last_name for form display
            $fullName = $user['full_name'] ?? '';
            $nameParts = explode(' ', $fullName, 2);
            $user['first_name'] = $nameParts[0] ?? '';
            $user['last_name'] = $nameParts[1] ?? '';
            
            // Map phone_number to phone for form compatibility
            $user['phone'] = $user['phone_number'] ?? '';
        } else {
            // Return default user structure if not found
            $user = [
                'id' => $userId,
                'full_name' => 'Unknown User',
                'first_name' => 'Unknown',
                'last_name' => 'User',
                'email' => 'unknown@example.com',
                'phone' => '',
                'phone_number' => '',
                'company_name' => '',
                'company_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'settings' => []
            ];
        }
        
        return $user;
    }
    
    /**
     * Render profile page
     */
    private function renderProfilePage($user, $userRole) {
        // Set page title
        $pageTitle = 'Profile - ' . ucfirst($userRole) . ' Dashboard';
        
        // Ensure user data is properly set
        if (!is_array($user)) {
            $user = [];
        }
        
        // Start output buffering
        ob_start();
        ?>
        <div class="max-w-4xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Profile</h1>
                <p class="text-gray-600 mt-2">Manage your personal information and account details</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Personal Information</h2>
                        </div>
                        <div class="p-6">
                            <form id="profile-form" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                            <input type="text" id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                            <input type="text" id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($user['phone'] ?? $user['phone_number'] ?? '') ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <?php if ($userRole === 'manager'): ?>
                                        <p class="mt-1 text-sm text-gray-500">This number will appear in SMS receipts sent to customers (purchases, repairs, swaps)</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" 
                                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                            Update Profile
                                        </button>
                                    </div>
                                </form>
                        </div>
                    </div>
                    
                    <!-- Change Password Section -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
                        </div>
                        <div class="p-6">
                            <form id="password-form" class="space-y-6">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" id="new_password" name="new_password" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" 
                                            class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 <?= $userRole === 'system_admin' ? 'border-purple-300 shadow-md' : '' ?>">
                        <div class="px-6 py-4 border-b border-gray-200 <?= $userRole === 'system_admin' ? 'bg-gradient-to-r from-purple-50 to-indigo-50' : '' ?>">
                            <h2 class="text-lg font-semibold text-gray-900">
                                <?= $userRole === 'system_admin' ? '<i class="fas fa-crown text-purple-600 mr-2"></i>' : '' ?>
                                Account Information
                            </h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Personal Information Display -->
                            <div class="pb-4 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Personal Information</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">First Name</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium"><?= htmlspecialchars($user['first_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Last Name</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium"><?= htmlspecialchars($user['last_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Email Address</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Phone Number</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium"><?= htmlspecialchars($user['phone'] ?? $user['phone_number'] ?? 'N/A') ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Details -->
                            <div class="pt-2">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Account Details</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Role</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium capitalize">
                                            <?php if ($userRole === 'system_admin'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    <i class="fas fa-crown mr-1"></i>System Administrator
                                                </span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($userRole) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($user['company_name']): ?>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Company</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium"><?= htmlspecialchars($user['company_name']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Member Since</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium">
                                            <?= date('F j, Y', strtotime($user['created_at'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Last Updated</label>
                                        <p class="mt-1 text-sm text-gray-900 font-medium">
                                            <?= date('F j, Y g:i A', strtotime($user['updated_at'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        // Profile form submission
        document.getElementById('profile-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(basePath + '/api/profile/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Profile updated successfully', 'success');
                } else {
                    showNotification(result.error || 'Failed to update profile', 'error');
                }
            } catch (error) {
                showNotification('An error occurred while updating profile', 'error');
            }
        });
        
        // Password form submission
        document.getElementById('password-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const response = await fetch(basePath + '/api/profile/change-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Password changed successfully', 'success');
                    this.reset();
                } else {
                    showNotification(result.error || 'Failed to change password', 'error');
                }
            } catch (error) {
                showNotification('An error occurred while changing password', 'error');
            }
        });
        
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-md shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        </script>
        <?php
        
        $content = ob_get_clean();
        
        // Include the appropriate layout
        include APP_PATH . '/Views/layouts/dashboard.php';
    }
    
    /**
     * Render settings page
     */
    private function renderSettingsPage($user, $userRole) {
        // Set page title
        $pageTitle = 'Settings - ' . ucfirst($userRole) . ' Dashboard';
        
        // Ensure user data is properly set
        if (!is_array($user)) {
            $user = [];
        }
        
        // Start output buffering
        ob_start();
        ?>
        <div class="max-w-4xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
                <p class="text-gray-600 mt-2">Customize your application preferences and notifications</p>
            </div>
            
            <div class="space-y-8">
                <!-- Notification Settings -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Notification Preferences</h2>
                    </div>
                    <div class="p-6">
                        <form id="settings-form" class="space-y-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">Email Notifications</h3>
                                        <p class="text-sm text-gray-500">Receive notifications via email</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="notifications_email" value="1" 
                                               <?= ($user['settings']['notifications_email'] ?? 1) ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">SMS Notifications</h3>
                                        <p class="text-sm text-gray-500">Receive notifications via SMS</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="notifications_sms" value="1" 
                                               <?= ($user['settings']['notifications_sms'] ?? 0) ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Appearance Settings -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Appearance</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <label for="theme" class="block text-sm font-medium text-gray-700 mb-2">Theme</label>
                                <select id="theme" name="theme" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="light" <?= ($user['settings']['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                    <option value="dark" <?= ($user['settings']['theme'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                    <option value="auto" <?= ($user['settings']['theme'] ?? 'light') === 'auto' ? 'selected' : '' ?>>Auto</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                <select id="timezone" name="timezone" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="UTC" <?= ($user['settings']['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= ($user['settings']['timezone'] ?? 'UTC') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?= ($user['settings']['timezone'] ?? 'UTC') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                    <option value="America/Denver" <?= ($user['settings']['timezone'] ?? 'UTC') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?= ($user['settings']['timezone'] ?? 'UTC') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                    <option value="Europe/London" <?= ($user['settings']['timezone'] ?? 'UTC') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                    <option value="Europe/Paris" <?= ($user['settings']['timezone'] ?? 'UTC') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                    <option value="Asia/Tokyo" <?= ($user['settings']['timezone'] ?? 'UTC') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                <!-- Manager/Admin Specific Settings -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Dashboard Settings</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <label for="dashboard_refresh" class="block text-sm font-medium text-gray-700 mb-2">Dashboard Refresh Rate (seconds)</label>
                                <select id="dashboard_refresh" name="dashboard_refresh" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="15" <?= ($user['settings']['dashboard_refresh'] ?? 30) == 15 ? 'selected' : '' ?>>15 seconds</option>
                                    <option value="30" <?= ($user['settings']['dashboard_refresh'] ?? 30) == 30 ? 'selected' : '' ?>>30 seconds</option>
                                    <option value="60" <?= ($user['settings']['dashboard_refresh'] ?? 30) == 60 ? 'selected' : '' ?>>1 minute</option>
                                    <option value="300" <?= ($user['settings']['dashboard_refresh'] ?? 30) == 300 ? 'selected' : '' ?>>5 minutes</option>
                                    <option value="0" <?= ($user['settings']['dashboard_refresh'] ?? 30) == 0 ? 'selected' : '' ?>>Manual refresh only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Save Settings -->
                <div class="flex justify-end">
                    <button type="button" id="save-settings" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Save Settings
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        // Settings form submission
        document.getElementById('save-settings').addEventListener('click', async function() {
            const form = document.getElementById('settings-form');
            const formData = new FormData(form);
            
            // Add other form fields
            formData.append('theme', document.getElementById('theme').value);
            formData.append('timezone', document.getElementById('timezone').value);
            
            <?php if (in_array($userRole, ['manager', 'admin'])): ?>
            formData.append('dashboard_refresh', document.getElementById('dashboard_refresh').value);
            <?php endif; ?>
            
            const data = Object.fromEntries(formData.entries());
            
            try {
                const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const response = await fetch(basePath + '/api/settings/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Settings saved successfully', 'success');
                } else {
                    showNotification(result.error || 'Failed to save settings', 'error');
                }
            } catch (error) {
                showNotification('An error occurred while saving settings', 'error');
            }
        });
        
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-md shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        </script>
        <?php
        
        $content = ob_get_clean();
        
        // Include the appropriate layout
        include APP_PATH . '/Views/layouts/dashboard.php';
    }
    
    /**
     * SMS Settings page for managers
     */
    public function smsSettings() {
        // Start session to get user data
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
            // Set error message in session
            $_SESSION['flash_error'] = 'Access Denied: You do not have permission to access SMS settings. Only managers and system administrators can access this page.';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        // Set page title and current page
        $pageTitle = 'SMS Settings - Manager Dashboard';
        $GLOBALS['currentPage'] = 'sms-settings';
        
        // Get user info for sidebar
        $userId = $userData['id'] ?? $userData['user_id'] ?? null;
        $user = $this->getUserDetails($userId, $userRole);
        
        // Add email to session for view access
        if (!isset($_SESSION['user']['email']) && isset($user['email'])) {
            $_SESSION['user']['email'] = $user['email'];
        }
        
        // Start output buffering
        ob_start();
        
        // Include SMS settings view
        include APP_PATH . '/Views/sms_settings.php';
        
        $content = ob_get_clean();
        
        // Include the dashboard layout
        include APP_PATH . '/Views/layouts/dashboard.php';
    }
    
    /**
     * SMS Purchase page for managers
     */
    public function smsPurchase() {
        // Start session to get user data
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
        
        // Allow manager and system_admin to access purchase page
        if (!in_array($userRole, ['manager', 'system_admin'], true)) {
            // Set error message in session
            $_SESSION['flash_error'] = 'Access Denied: You do not have permission to access SMS purchase. Only managers and system administrators can access this page.';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        // Set page title and current page
        $pageTitle = 'Purchase SMS Credits - Manager Dashboard';
        $GLOBALS['currentPage'] = 'sms-settings';
        
        // Get user info for sidebar
        $userId = $userData['id'] ?? $userData['user_id'] ?? null;
        $user = $this->getUserDetails($userId, $userRole);
        
        // Add email to session for view access
        if (!isset($_SESSION['user']['email']) && isset($user['email'])) {
            $_SESSION['user']['email'] = $user['email'];
        }
        
        // Start output buffering
        ob_start();
        
        // Include SMS purchase view
        include APP_PATH . '/Views/sms_purchase.php';
        
        $content = ob_get_clean();
        
        // Include the dashboard layout
        include APP_PATH . '/Views/layouts/dashboard.php';
    }
    
    /**
     * Get SMS logs for manager's company with pagination
     * GET /api/sms/logs
     */
    public function getSMSLogs() {
        header('Content-Type: application/json');
        
        try {
            $userData = null;
            
            // Try session-based auth first (for web dashboard)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $sessionUser = $_SESSION['user'] ?? null;
            if ($sessionUser && is_array($sessionUser)) {
                $userData = $sessionUser;
            } else {
                // If no session, try JWT auth (for API calls)
                $headers = function_exists('getallheaders') ? getallheaders() : [];
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? 
                              $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
                
                if (strpos($authHeader, 'Bearer ') === 0) {
                    try {
                        $token = substr($authHeader, 7);
                        $auth = new \App\Services\AuthService();
                        $payload = $auth->validateToken($token);
                        $userData = [
                            'id' => $payload->sub ?? null,
                            'role' => $payload->role ?? 'salesperson',
                            'company_id' => $payload->company_id ?? null
                        ];
                    } catch (\Exception $e) {
                        // Token validation failed
                        error_log("ProfileController::getSMSLogs - Token validation failed: " . $e->getMessage());
                    }
                }
            }
            
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                return;
            }
            
            $userRole = $userData['role'] ?? 'salesperson';
            $companyId = $userData['company_id'] ?? null;
            
            // Allow manager and system_admin to access logs
            if (!in_array($userRole, ['manager', 'system_admin'], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                return;
            }
            
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company ID required']);
                return;
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Check if sms_logs table exists
            $tableCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
            $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
            
            if (!$tableExists) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'logs' => [],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 50,
                        'total' => 0,
                        'pages' => 0
                    ]
                ]);
                return;
            }
            
            // Get query parameters
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
            $offset = ($page - 1) * $limit;
            
            // Get total count
            try {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM sms_logs WHERE company_id = ?");
                $countStmt->execute([$companyId]);
                $total = (int)$countStmt->fetchColumn();
            } catch (\Exception $e) {
                error_log("Error counting SMS logs: " . $e->getMessage());
                $total = 0;
            }
            
            // Get logs
            try {
                $limitInt = (int)$limit;
                $offsetInt = (int)$offset;
                $stmt = $db->prepare("
                    SELECT 
                        message_type,
                        recipient,
                        message,
                        status,
                        sender_id,
                        sent_at
                    FROM sms_logs 
                    WHERE company_id = ? 
                    ORDER BY sent_at DESC 
                    LIMIT {$limitInt} OFFSET {$offsetInt}
                ");
                $stmt->execute([$companyId]);
                $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log("Error fetching SMS logs: " . $e->getMessage());
                $logs = [];
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => $total > 0 ? ceil($total / $limit) : 0
                ]
            ]);
        } catch (\Exception $e) {
            error_log("ProfileController::getSMSLogs error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve SMS logs: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Download user guide PDF based on user role
     */
    public function downloadUserGuide() {
        // Start session to get user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        $userRole = $userData['role'] ?? 'salesperson';
        
        // Map roles to guide content
        $allowedRoles = ['salesperson', 'technician', 'manager'];
        if (!in_array($userRole, $allowedRoles)) {
            // Default to salesperson guide for other roles
            $userRole = 'salesperson';
        }
        
        // Generate PDF content based on role
        $html = $this->generateUserGuideHTML($userRole);
        
        // Try to use Dompdf if available
        if (class_exists('Dompdf\Dompdf')) {
            try {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->set_option('isRemoteEnabled', true);
                $dompdf->set_option('isHtml5ParserEnabled', true);
                $dompdf->render();
                
                $filename = ucfirst($userRole) . '_User_Guide_' . date('Y-m-d') . '.pdf';
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                header('Pragma: public');
                
                echo $dompdf->output();
                exit;
            } catch (\Exception $e) {
                error_log("Dompdf error: " . $e->getMessage());
                // Fall through to HTML fallback
            }
        }
        
        // Fallback: Generate HTML that can be printed as PDF
        $filename = ucfirst($userRole) . '_User_Guide_' . date('Y-m-d') . '.html';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Add print CSS
        $html = str_replace('</head>', '
    <style media="print">
        @page { margin: 1cm; size: A4; }
        body { margin: 0; padding: 20px; }
        .no-print { display: none; }
    </style>
    <style>
        .download-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #818CF8 0%, #6366F1 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(129, 140, 248, 0.4);
            z-index: 1000;
        }
        .download-btn:hover {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
        }
        @media print {
            .download-btn { display: none; }
        }
    </style>
    <script>
        function downloadPDF() {
            window.print();
        }
    </script>
</head>', $html);
        
        $html = str_replace('</body>', '<button class="download-btn no-print" onclick="downloadPDF()"><i class="fas fa-download"></i> Print / Save as PDF</button></body>', $html);
        
        echo $html;
        exit;
    }
    
    /**
     * Generate HTML content for user guide based on role
     */
    private function generateUserGuideHTML($role) {
        $roleName = ucfirst(str_replace('_', ' ', $role));
        $baseUrl = BASE_URL_PATH ?? '';
        
        $content = '';
        
        switch ($role) {
            case 'salesperson':
                $content = $this->getSalespersonGuideContent();
                break;
            case 'technician':
                $content = $this->getTechnicianGuideContent();
                break;
            case 'manager':
                $content = $this->getManagerGuideContent();
                break;
            default:
                $content = $this->getSalespersonGuideContent();
        }
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $roleName . ' User Guide</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 11pt; 
            color: #1F2937;
            background: #FFFFFF;
            padding: 40px;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 32pt;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .header p {
            font-size: 14pt;
            opacity: 0.95;
        }
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 20pt;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #E0E7FF;
        }
        .subsection {
            margin-bottom: 20px;
            margin-left: 20px;
        }
        .subsection-title {
            font-size: 16pt;
            font-weight: 600;
            color: #6366F1;
            margin-bottom: 10px;
            margin-top: 15px;
        }
        .content {
            margin-bottom: 15px;
        }
        .content p {
            margin-bottom: 10px;
        }
        .content ul, .content ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }
        .content li {
            margin-bottom: 8px;
        }
        .highlight-box {
            background: #EEF2FF;
            border-left: 4px solid #6366F1;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .warning-box {
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info-box {
            background: #DBEAFE;
            border-left: 4px solid #3B82F6;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            color: #6B7280;
            font-size: 10pt;
        }
        .step-number {
            display: inline-block;
            background: #6366F1;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: 700;
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            border: 1px solid #D1D5DB;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #F3F4F6;
            font-weight: 600;
        }
        @media print {
            body { padding: 20px; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $roleName . ' User Guide</h1>
        <p>Complete System Usage Manual</p>
    </div>
    
    ' . $content . '
    
    <div class="footer">
        <p>Generated on ' . date('F j, Y') . ' | ' . $roleName . ' User Guide</p>
        <p>For support, please contact your system administrator</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Get salesperson guide content
     */
    private function getSalespersonGuideContent() {
        return '
    <div class="section">
        <div class="section-title">Introduction</div>
        <div class="content">
            <p>Welcome to the Salesperson User Guide! This comprehensive guide will walk you through every feature available to you in the system. As a salesperson, you are the front line of the business, handling customer interactions, processing sales, and managing customer relationships.</p>
            <div class="info-box">
                <strong>Your Role Permissions:</strong> As a salesperson, you have access to:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Dashboard with sales overview</li>
                    <li>Product Management (view only)</li>
                    <li>Sales Operations (create and manage sales)</li>
                    <li>Customer Management (view and create customers)</li>
                    <li>POS System for quick transactions</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard Overview</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Your Dashboard</div>
            <div class="content">
                <p><span class="step-number">1</span>After logging in, you will automatically be taken to your dashboard.</p>
                <p><span class="step-number">2</span>You can also access it anytime by clicking <strong>"Dashboard"</strong> in the left sidebar.</p>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Dashboard Features</div>
            <div class="content">
                <p>Your dashboard displays several key metrics and information:</p>
                <ul>
                    <li><strong>Today\'s Sales Summary:</strong> Total sales count and revenue for today</li>
                    <li><strong>Recent Transactions:</strong> List of your most recent sales</li>
                    <li><strong>Product Inventory Status:</strong> Quick view of low stock items</li>
                    <li><strong>Customer Information:</strong> Recent customer interactions</li>
                    <li><strong>Performance Metrics:</strong> Your sales statistics</li>
                </ul>
                <div class="highlight-box">
                    <strong>Pro Tip:</strong> Check your dashboard at the start of each day to understand your targets and review yesterday\'s performance.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Product Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Products</div>
            <div class="content">
                <p><strong>Step-by-Step Guide:</strong></p>
                <ol>
                    <li>Click on <strong>"Product Management"</strong> in the left sidebar menu</li>
                    <li>You will see a list of all available products in the system</li>
                    <li>Use the search bar at the top to find specific products by name, SKU, or brand</li>
                    <li>Use filters to narrow down products by category, brand, or stock status</li>
                    <li>Click on any product name to view detailed information</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Understanding Product Information</div>
            <div class="content">
                <p>When viewing a product, you will see:</p>
                <ul>
                    <li><strong>Product Name:</strong> The full name of the product</li>
                    <li><strong>SKU:</strong> Stock Keeping Unit - unique identifier</li>
                    <li><strong>Description:</strong> Detailed product description</li>
                    <li><strong>Current Stock Quantity:</strong> How many units are available</li>
                    <li><strong>Price Information:</strong> Selling price and cost price</li>
                    <li><strong>Brand:</strong> Manufacturer or brand name</li>
                    <li><strong>Category & Subcategory:</strong> Product classification</li>
                    <li><strong>Product Specifications:</strong> Technical details and features</li>
                    <li><strong>Product Images:</strong> Visual representation of the product</li>
                </ul>
                <div class="warning-box">
                    <strong>Important:</strong> Always check the stock quantity before promising a product to a customer. If stock is low or out of stock, inform your manager immediately.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Searching for Products</div>
            <div class="content">
                <p>To quickly find products:</p>
                <ol>
                    <li>Locate the search bar in the Product Management page</li>
                    <li>Type the product name, SKU, or brand</li>
                    <li>Results will filter automatically as you type</li>
                    <li>You can also use advanced filters for category, brand, or price range</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Sales Operations</div>
        <div class="subsection">
            <div class="subsection-title">Creating a New Sale</div>
            <div class="content">
                <p><strong>Complete Step-by-Step Process:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Sales"</strong> from the left sidebar</li>
                    <li>Click the <strong>"New Sale"</strong> button (usually at the top right)</li>
                    <li>You will see the sales form with the following sections:</li>
                </ol>
                <p><strong>Adding Products to Sale:</strong></p>
                <ol>
                    <li>Click <strong>"Add Product"</strong> or search for products</li>
                    <li>Select the product from the dropdown or search results</li>
                    <li>Enter the quantity you want to sell</li>
                    <li>The system will automatically calculate the subtotal</li>
                    <li>Repeat for all products in the sale</li>
                    <li>Review the items in your cart</li>
                </ol>
                <p><strong>Adding Customer Information:</strong></p>
                <ol>
                    <li>If the customer is new, click <strong>"Add New Customer"</strong></li>
                    <li>Fill in customer details (name, phone, email, address)</li>
                    <li>If customer exists, search and select from the customer list</li>
                    <li>Customer information helps track purchase history</li>
                </ol>
                <p><strong>Applying Discounts:</strong></p>
                <ol>
                    <li>If authorized, you can apply discounts</li>
                    <li>Enter discount amount or percentage</li>
                    <li>Add discount reason if required</li>
                    <li>System will recalculate the total</li>
                </ol>
                <p><strong>Processing Payment:</strong></p>
                <ol>
                    <li>Review the final total amount</li>
                    <li>Select payment method (Cash, Card, Mobile Money, etc.)</li>
                    <li>Enter the amount received</li>
                    <li>If partial payment, mark as partial and note remaining balance</li>
                    <li>Click <strong>"Complete Sale"</strong> or <strong>"Process Payment"</strong></li>
                </ol>
                <p><strong>Generating Receipt:</strong></p>
                <ol>
                    <li>After payment is processed, a receipt will be generated</li>
                    <li>You can print the receipt immediately</li>
                    <li>Receipt includes transaction number, items, prices, and payment details</li>
                    <li>Save or email receipt to customer if needed</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Using the POS (Point of Sale) System</div>
            <div class="content">
                <p>The POS system is designed for quick, efficient transactions at the counter:</p>
                <p><strong>Accessing POS:</strong></p>
                <ol>
                    <li>Click <strong>"POS"</strong> or <strong>"Point of Sale"</strong> from the sidebar</li>
                    <li>The POS interface will open with a clean, user-friendly layout</li>
                </ol>
                <p><strong>POS Features:</strong></p>
                <ul>
                    <li><strong>Product Search:</strong> Quickly search products by name, SKU, or barcode</li>
                    <li><strong>Barcode Scanner:</strong> If available, scan product barcodes directly</li>
                    <li><strong>Quick Add:</strong> Click products to instantly add to cart</li>
                    <li><strong>Quantity Adjustment:</strong> Use +/- buttons or type quantity</li>
                    <li><strong>Remove Items:</strong> Click remove button to delete items from cart</li>
                    <li><strong>Real-time Calculation:</strong> Totals update automatically</li>
                </ul>
                <p><strong>POS Workflow:</strong></p>
                <ol>
                    <li>Scan or search for first product</li>
                    <li>Add to cart (quantity defaults to 1)</li>
                    <li>Repeat for all items</li>
                    <li>Review cart on the right side</li>
                    <li>Apply discounts if needed</li>
                    <li>Select payment method</li>
                    <li>Process payment</li>
                    <li>Print or email receipt</li>
                </ol>
                <div class="warning-box">
                    <strong>Critical:</strong> Always verify:
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>Correct products are added</li>
                        <li>Quantities are accurate</li>
                        <li>Prices are correct</li>
                        <li>Payment amount matches total</li>
                        <li>Receipt is given to customer</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Viewing Sales History</div>
            <div class="content">
                <p>To view your past sales:</p>
                <ol>
                    <li>Go to <strong>"Sales"</strong> from the sidebar</li>
                    <li>You will see a list of all sales transactions</li>
                    <li>Use filters to view sales by date range</li>
                    <li>Click on any sale to view full details</li>
                    <li>You can reprint receipts from the sales list</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Handling Returns and Refunds</div>
            <div class="content">
                <p>If a customer wants to return a product:</p>
                <ol>
                    <li>Locate the original sale in the Sales list</li>
                    <li>Click on the sale to view details</li>
                    <li>If return functionality is available, click <strong>"Process Return"</strong></li>
                    <li>Select items to return</li>
                    <li>Enter return reason</li>
                    <li>Process refund through the same payment method</li>
                    <li>Generate return receipt</li>
                </ol>
                <div class="warning-box">
                    <strong>Note:</strong> Returns and refunds may require manager approval. Always check with your manager before processing large returns.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Customer Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Customers</div>
            <div class="content">
                <p><strong>Accessing Customer List:</strong></p>
                <ol>
                    <li>Click <strong>"Customers"</strong> from the left sidebar</li>
                    <li>You will see a table/list of all customers</li>
                    <li>Use the search bar to find specific customers by name, phone, or email</li>
                    <li>Click on any customer name to view their full profile</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Creating a New Customer</div>
            <div class="content">
                <p><strong>Step-by-Step Process:</strong></p>
                <ol>
                    <li>Go to <strong>"Customers"</strong> from the sidebar</li>
                    <li>Click the <strong>"Add New Customer"</strong> button (usually top right)</li>
                    <li>Fill in the customer form with the following information:</li>
                </ol>
                <ul>
                    <li><strong>Full Name:</strong> Customer\'s complete name (required)</li>
                    <li><strong>Phone Number:</strong> Primary contact number (required)</li>
                    <li><strong>Email Address:</strong> Email for receipts and communications (optional)</li>
                    <li><strong>Address:</strong> Physical address (optional but recommended)</li>
                    <li><strong>City/State:</strong> Location details</li>
                    <li><strong>Notes:</strong> Any special information about the customer</li>
                </ul>
                <ol start="4">
                    <li>Click <strong>"Save Customer"</strong> or <strong>"Create Customer"</strong></li>
                    <li>The customer will be added to the system</li>
                    <li>You can now select this customer when creating sales</li>
                </ol>
                <div class="info-box">
                    <strong>Tip:</strong> Creating customer profiles helps track purchase history, send receipts via email/SMS, and build customer relationships.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Viewing Customer Details</div>
            <div class="content">
                <p>When you click on a customer, you can see:</p>
                <ul>
                    <li><strong>Contact Information:</strong> Phone, email, address</li>
                    <li><strong>Purchase History:</strong> Complete list of all transactions</li>
                    <li><strong>Transaction Records:</strong> Detailed view of each sale</li>
                    <li><strong>Total Spent:</strong> Lifetime value of the customer</li>
                    <li><strong>Last Purchase:</strong> Date and amount of most recent transaction</li>
                    <li><strong>Payment Information:</strong> Payment methods used</li>
                    <li><strong>Notes:</strong> Any special notes or preferences</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Editing Customer Information</div>
            <div class="content">
                <p>To update customer details:</p>
                <ol>
                    <li>Find and click on the customer from the customer list</li>
                    <li>Click the <strong>"Edit"</strong> button</li>
                    <li>Update any information that has changed</li>
                    <li>Click <strong>"Save Changes"</strong></li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Profile and Settings</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Your Profile</div>
            <div class="content">
                <p>To view or edit your profile:</p>
                <ol>
                    <li>Click on your profile icon/name in the top right corner</li>
                    <li>Select <strong>"Your Profile"</strong> from the dropdown menu</li>
                    <li>You can view and edit:</li>
                </ol>
                <ul>
                    <li>Your name and contact information</li>
                    <li>Email address</li>
                    <li>Phone number</li>
                    <li>Account settings</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Changing Your Password</div>
            <div class="content">
                <p>To change your password:</p>
                <ol>
                    <li>Go to your Profile page</li>
                    <li>Scroll to the <strong>"Change Password"</strong> section</li>
                    <li>Enter your current password</li>
                    <li>Enter your new password (minimum 6 characters)</li>
                    <li>Confirm the new password</li>
                    <li>Click <strong>"Change Password"</strong></li>
                </ol>
                <div class="warning-box">
                    <strong>Security Tip:</strong> Use a strong password with a mix of letters, numbers, and special characters. Never share your password with anyone.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Downloading User Guide</div>
            <div class="content">
                <p>You can download this guide anytime:</p>
                <ol>
                    <li>Click on your profile icon in the top right</li>
                    <li>Select <strong>"Download User Guide"</strong> from the menu</li>
                    <li>The PDF will download automatically</li>
                    <li>Save it for future reference</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. Best Practices and Tips</div>
        <div class="subsection">
            <div class="subsection-title">Daily Workflow</div>
            <div class="content">
                <ol>
                    <li><strong>Start of Day:</strong> Check dashboard for daily targets and review yesterday\'s performance</li>
                    <li><strong>Before Sales:</strong> Verify product availability and stock levels</li>
                    <li><strong>During Sales:</strong> Always create customer profiles for new customers</li>
                    <li><strong>After Sales:</strong> Ensure receipts are provided and transactions are complete</li>
                    <li><strong>End of Day:</strong> Review your sales summary and prepare for next day</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Customer Service Excellence</div>
            <div class="content">
                <ul>
                    <li><strong>Always verify stock:</strong> Check product availability before promising delivery to avoid disappointment</li>
                    <li><strong>Accurate data entry:</strong> Double-check all information when creating sales to prevent errors</li>
                    <li><strong>Professional communication:</strong> Maintain friendly and professional interaction with all customers</li>
                    <li><strong>Receipt management:</strong> Always provide receipts for transactions - customers may need them for warranty or returns</li>
                    <li><strong>Follow up:</strong> If a product is out of stock, take customer contact and notify when available</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Common Issues and Solutions</div>
            <div class="content">
                <p><strong>Product Out of Stock:</strong></p>
                <ul>
                    <li>Inform customer immediately</li>
                    <li>Check if similar products are available</li>
                    <li>Take customer contact for restock notification</li>
                    <li>Notify your manager about low stock</li>
                </ul>
                <p><strong>Payment Issues:</strong></p>
                <ul>
                    <li>If payment fails, verify amount entered</li>
                    <li>Check payment method is correct</li>
                    <li>For partial payments, ensure remaining balance is noted</li>
                    <li>Always get manager approval for large discounts</li>
                </ul>
                <p><strong>System Errors:</strong></p>
                <ul>
                    <li>If you encounter errors, note the error message</li>
                    <li>Try refreshing the page</li>
                    <li>Report persistent issues to your manager</li>
                    <li>Don\'t attempt to fix system issues yourself</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">7. Quick Reference Guide</div>
        <div class="content">
            <table>
                <tr>
                    <th>Task</th>
                    <th>Steps</th>
                </tr>
                <tr>
                    <td><strong>View Dashboard</strong></td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td><strong>Search Products</strong></td>
                    <td>Sidebar → Product Management → Use search bar</td>
                </tr>
                <tr>
                    <td><strong>Create New Sale</strong></td>
                    <td>Sidebar → Sales → New Sale → Add products → Add customer → Process payment</td>
                </tr>
                <tr>
                    <td><strong>Use POS System</strong></td>
                    <td>Sidebar → POS → Scan/search products → Add to cart → Process payment</td>
                </tr>
                <tr>
                    <td><strong>View Sales History</strong></td>
                    <td>Sidebar → Sales → Browse list or use date filters</td>
                </tr>
                <tr>
                    <td><strong>Add New Customer</strong></td>
                    <td>Sidebar → Customers → Add New Customer → Fill form → Save</td>
                </tr>
                <tr>
                    <td><strong>View Customer Details</strong></td>
                    <td>Sidebar → Customers → Click on customer name</td>
                </tr>
                <tr>
                    <td><strong>Access Profile</strong></td>
                    <td>Top Right → Profile Icon → Your Profile</td>
                </tr>
                <tr>
                    <td><strong>Download Guide</strong></td>
                    <td>Top Right → Profile Icon → Download User Guide</td>
                </tr>
                <tr>
                    <td><strong>Change Password</strong></td>
                    <td>Profile → Change Password section → Enter old & new password</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">8. Getting Help</div>
        <div class="content">
            <p>If you need assistance:</p>
            <ul>
                <li><strong>Technical Issues:</strong> Contact your manager or system administrator</li>
                <li><strong>Sales Questions:</strong> Refer to this guide or ask your manager</li>
                <li><strong>Product Information:</strong> Check product details in Product Management</li>
                <li><strong>Customer Issues:</strong> Escalate to manager for complex situations</li>
            </ul>
            <div class="info-box">
                <strong>Remember:</strong> This guide is always available in your profile menu. Download it and keep it handy for quick reference!
            </div>
        </div>
    </div>
';
    }
    
    /**
     * Get technician guide content
     */
    private function getTechnicianGuideContent() {
        return '
    <div class="section">
        <div class="section-title">Introduction</div>
        <div class="content">
            <p>Welcome to the Technician User Guide! This comprehensive guide will help you master the repair management system. As a technician, you are responsible for handling device repairs, managing parts inventory, and ensuring quality service delivery.</p>
            <div class="info-box">
                <strong>Your Role Permissions:</strong> As a technician, you have access to:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Dashboard with repair statistics and active jobs</li>
                    <li>Product Management (view product details and specifications)</li>
                    <li>Repair Management (create, update, and complete repairs)</li>
                    <li>Parts and Accessories tracking</li>
                    <li>Inventory updates for parts used</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard Overview</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Your Dashboard</div>
            <div class="content">
                <p><span class="step-number">1</span>After logging in, you\'ll see your technician dashboard.</p>
                <p><span class="step-number">2</span>Access it anytime by clicking <strong>"Dashboard"</strong> in the left sidebar.</p>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Dashboard Information</div>
            <div class="content">
                <p>Your dashboard displays critical repair information:</p>
                <ul>
                    <li><strong>Active Repair Jobs:</strong> Repairs currently assigned to you or in progress</li>
                    <li><strong>Pending Repairs:</strong> New repairs waiting to be started</li>
                    <li><strong>Completed Today:</strong> Number of repairs finished today</li>
                    <li><strong>Waiting for Parts:</strong> Repairs on hold due to missing parts</li>
                    <li><strong>Repair Statistics:</strong> Your performance metrics and completion rates</li>
                    <li><strong>Product Inventory Alerts:</strong> Low stock warnings for commonly used parts</li>
                    <li><strong>Upcoming Deadlines:</strong> Repairs with approaching completion dates</li>
                </ul>
                <div class="highlight-box">
                    <strong>Pro Tip:</strong> Start each day by reviewing your dashboard to prioritize repairs and identify any parts you need to order.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Product Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Product Information</div>
            <div class="content">
                <p><strong>Step-by-Step Guide:</strong></p>
                <ol>
                    <li>Click <strong>"Product Management"</strong> in the left sidebar</li>
                    <li>Browse the product list or use the search bar to find specific products</li>
                    <li>Click on any product name to view detailed information</li>
                    <li>Review product specifications relevant to repairs</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Important Product Details for Technicians</div>
            <div class="content">
                <p>When viewing a product, pay attention to:</p>
                <ul>
                    <li><strong>Model Numbers:</strong> Exact model identification for compatibility</li>
                    <li><strong>Serial Numbers:</strong> Unique device identifiers</li>
                    <li><strong>Technical Specifications:</strong> Hardware details, screen size, processor, RAM, storage</li>
                    <li><strong>Compatibility Information:</strong> Which parts are compatible with this model</li>
                    <li><strong>Warranty Status:</strong> Whether device is under warranty</li>
                    <li><strong>Repair History:</strong> Previous repairs performed on this device</li>
                    <li><strong>Common Issues:</strong> Known problems with this model</li>
                    <li><strong>Parts List:</strong> Available replacement parts for this product</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Searching for Products</div>
            <div class="content">
                <p>To find products quickly:</p>
                <ol>
                    <li>Use the search bar in Product Management</li>
                    <li>Search by product name, model number, or SKU</li>
                    <li>Filter by brand or category if needed</li>
                    <li>View product details to access repair-relevant information</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Repair Management - Complete Guide</div>
        <div class="subsection">
            <div class="subsection-title">Creating a New Repair Job</div>
            <div class="content">
                <p><strong>Detailed Step-by-Step Process:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Repairs"</strong> from the left sidebar</li>
                    <li>Click the <strong>"New Repair"</strong> or <strong>"Create Repair"</strong> button</li>
                    <li>Fill in the repair form with the following information:</li>
                </ol>
                <p><strong>Customer Information:</strong></p>
                <ol>
                    <li>If customer is new, click <strong>"Add New Customer"</strong></li>
                    <li>Enter customer name, phone number, and email</li>
                    <li>If customer exists, search and select from the customer list</li>
                </ol>
                <p><strong>Device/Product Information:</strong></p>
                <ol>
                    <li>Select the product/device being repaired</li>
                    <li>Enter device serial number or IMEI if available</li>
                    <li>Select device condition (Good, Fair, Poor)</li>
                    <li>Note any physical damage or existing issues</li>
                </ol>
                <p><strong>Problem Description:</strong></p>
                <ol>
                    <li>Enter a detailed description of the issue</li>
                    <li>Include symptoms (won\'t turn on, screen cracked, battery drains fast, etc.)</li>
                    <li>Note when the problem started</li>
                    <li>Mention any previous repair attempts</li>
                </ol>
                <p><strong>Repair Details:</strong></p>
                <ol>
                    <li>Set initial repair status (usually "Pending" for new repairs)</li>
                    <li>Set priority level (Low, Medium, High, Urgent)</li>
                    <li>Enter estimated repair cost (if known)</li>
                    <li>Set estimated completion date</li>
                    <li>Add any special notes or instructions</li>
                </ol>
                <p><strong>Saving the Repair:</strong></p>
                <ol>
                    <li>Review all information for accuracy</li>
                    <li>Click <strong>"Save Repair"</strong> or <strong>"Create Repair"</strong></li>
                    <li>The repair will be added to your repair list</li>
                    <li>A repair ticket number will be generated</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Updating Repair Status</div>
            <div class="content">
                <p><strong>How to Update Repair Status:</strong></p>
                <ol>
                    <li>Go to <strong>"Repairs"</strong> from the sidebar</li>
                    <li>Find the repair job you want to update</li>
                    <li>Click on the repair to open details</li>
                    <li>Click <strong>"Update Status"</strong> button</li>
                    <li>Select the new status from the dropdown</li>
                    <li>Add a status update note explaining the change</li>
                    <li>Click <strong>"Save"</strong></li>
                </ol>
                <p><strong>Understanding Repair Statuses:</strong></p>
                <table>
                    <tr>
                        <th>Status</th>
                        <th>When to Use</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td><strong>Pending</strong></td>
                        <td>New repair just created</td>
                        <td>Repair job is in queue, waiting to be started</td>
                    </tr>
                    <tr>
                        <td><strong>In Progress</strong></td>
                        <td>You\'ve started working on it</td>
                        <td>Repair is currently being worked on</td>
                    </tr>
                    <tr>
                        <td><strong>Waiting for Parts</strong></td>
                        <td>Need parts to continue</td>
                        <td>Repair is on hold, waiting for replacement parts to arrive</td>
                    </tr>
                    <tr>
                        <td><strong>Diagnosis Complete</strong></td>
                        <td>After identifying the issue</td>
                        <td>Problem identified, waiting for customer approval or parts</td>
                    </tr>
                    <tr>
                        <td><strong>Testing</strong></td>
                        <td>After repair is done</td>
                        <td>Repair completed, currently testing functionality</td>
                    </tr>
                    <tr>
                        <td><strong>Completed</strong></td>
                        <td>Repair finished and tested</td>
                        <td>Repair is complete, tested, and ready for customer pickup</td>
                    </tr>
                    <tr>
                        <td><strong>Cancelled</strong></td>
                        <td>Customer cancelled or not repairable</td>
                        <td>Repair job was cancelled or device cannot be repaired</td>
                    </tr>
                </table>
                <div class="warning-box">
                    <strong>Critical:</strong> Always update repair status immediately when it changes. This keeps customers informed and helps management track progress.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Adding Repair Notes</div>
            <div class="content">
                <p>To add notes or updates to a repair:</p>
                <ol>
                    <li>Open the repair job</li>
                    <li>Scroll to the <strong>"Notes"</strong> or <strong>"Updates"</strong> section</li>
                    <li>Click <strong>"Add Note"</strong> or <strong>"Add Update"</strong></li>
                    <li>Enter your note (what you found, what you did, what\'s needed, etc.)</li>
                    <li>Click <strong>"Save Note"</strong></li>
                </ol>
                <p><strong>What to Include in Notes:</strong></p>
                <ul>
                    <li>Diagnosis findings</li>
                    <li>Repair steps taken</li>
                    <li>Parts replaced</li>
                    <li>Issues encountered</li>
                    <li>Test results</li>
                    <li>Customer communications</li>
                    <li>Any special instructions</li>
                </ul>
                <div class="info-box">
                    <strong>Best Practice:</strong> Add notes regularly throughout the repair process. Detailed notes help if the repair needs to be reviewed later or if another technician takes over.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Viewing All Repairs</div>
            <div class="content">
                <p>To view your repair list:</p>
                <ol>
                    <li>Go to <strong>"Repairs"</strong> from the sidebar</li>
                    <li>You\'ll see a table/list of all repairs</li>
                    <li>Use filters to view:
                        <ul>
                            <li>Repairs by status (Pending, In Progress, Completed, etc.)</li>
                            <li>Repairs by date range</li>
                            <li>Repairs by customer</li>
                            <li>Your assigned repairs</li>
                        </ul>
                    </li>
                    <li>Click on any repair to view full details</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Parts and Accessories Management</div>
        <div class="subsection">
            <div class="subsection-title">Recording Parts Used in Repairs</div>
            <div class="content">
                <p><strong>Complete Process:</strong></p>
                <ol>
                    <li>Open the repair job you\'re working on</li>
                    <li>Navigate to the <strong>"Parts"</strong> or <strong>"Accessories"</strong> section</li>
                    <li>Click <strong>"Add Part"</strong> or <strong>"Record Parts Used"</strong></li>
                    <li>Search for the part in the system or select from list</li>
                    <li>Enter the quantity used</li>
                    <li>Enter the unit cost (if different from default)</li>
                    <li>Add any notes about the part (condition, compatibility, etc.)</li>
                    <li>Click <strong>"Save"</strong> or <strong>"Add Part"</strong></li>
                </ol>
                <p><strong>Important Notes:</strong></p>
                <ul>
                    <li>The system automatically deducts parts from inventory when recorded</li>
                    <li>Always verify part numbers and quantities before saving</li>
                    <li>If a part is not in the system, notify your manager to add it</li>
                    <li>Record all parts used, even small components</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Checking Parts Availability</div>
            <div class="content">
                <p>Before starting a repair, check if parts are available:</p>
                <ol>
                    <li>Open the repair job</li>
                    <li>Review the parts needed for the repair</li>
                    <li>Check parts availability in the Parts section</li>
                    <li>If parts are low or unavailable:
                        <ul>
                            <li>Update repair status to "Waiting for Parts"</li>
                            <li>Notify your manager about needed parts</li>
                            <li>Add a note about which parts are needed</li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Inventory Impact</div>
            <div class="content">
                <p>When you record parts usage:</p>
                <ul>
                    <li>Inventory is automatically updated</li>
                    <li>Stock levels decrease by the quantity used</li>
                    <li>Cost is tracked for the repair</li>
                    <li>Low stock alerts are triggered if needed</li>
                </ul>
                <div class="warning-box">
                    <strong>Critical:</strong> Always record parts accurately. Incorrect recording affects inventory levels and repair costs. If you make a mistake, notify your manager immediately.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Completing a Repair</div>
        <div class="subsection">
            <div class="subsection-title">Final Steps Before Completion</div>
            <div class="content">
                <p><strong>Checklist Before Marking Repair as Complete:</strong></p>
                <ol>
                    <li><strong>Repair Work:</strong> All repair work is finished</li>
                    <li><strong>Parts Recorded:</strong> All parts used have been recorded in the system</li>
                    <li><strong>Testing:</strong> Device has been thoroughly tested</li>
                    <li><strong>Functionality:</strong> All functions work as expected</li>
                    <li><strong>Physical Condition:</strong> Device is clean and in good condition</li>
                    <li><strong>Notes Updated:</strong> Final notes added about the repair</li>
                    <li><strong>Cost Calculated:</strong> Total repair cost is accurate</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Testing the Repair</div>
            <div class="content">
                <p>Always test thoroughly before completion:</p>
                <ul>
                    <li>Test all basic functions (power on/off, charging, buttons)</li>
                    <li>Test the specific issue that was reported</li>
                    <li>Test related functions that might be affected</li>
                    <li>Run device for a period to ensure stability</li>
                    <li>Document test results in repair notes</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Marking Repair as Complete</div>
            <div class="content">
                <p>To complete a repair:</p>
                <ol>
                    <li>Open the repair job</li>
                    <li>Ensure all work is done and tested</li>
                    <li>Update status to <strong>"Testing"</strong> first</li>
                    <li>After successful testing, update status to <strong>"Completed"</strong></li>
                    <li>Add final completion notes</li>
                    <li>Enter final repair cost if different from estimate</li>
                    <li>Save the changes</li>
                </ol>
                <div class="info-box">
                    <strong>Note:</strong> Once marked as completed, the customer can be notified and the device is ready for pickup.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. Best Practices for Technicians</div>
        <div class="subsection">
            <div class="subsection-title">Daily Workflow</div>
            <div class="content">
                <ol>
                    <li><strong>Start of Day:</strong> Check dashboard for new repairs and prioritize</li>
                    <li><strong>Review Pending:</strong> Look at pending repairs and plan your day</li>
                    <li><strong>Check Parts:</strong> Verify you have necessary parts before starting</li>
                    <li><strong>Update Status:</strong> Change status to "In Progress" when you start</li>
                    <li><strong>Document Work:</strong> Add notes as you work through the repair</li>
                    <li><strong>Record Parts:</strong> Record parts immediately after use</li>
                    <li><strong>Test Thoroughly:</strong> Always test before marking complete</li>
                    <li><strong>End of Day:</strong> Update all repair statuses and add end-of-day notes</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Quality Standards</div>
            <div class="content">
                <ul>
                    <li><strong>Document Everything:</strong> Keep detailed notes on diagnosis, repair steps, and results</li>
                    <li><strong>Update Status Regularly:</strong> Keep repair status current - customers and managers rely on this</li>
                    <li><strong>Accurate Parts Tracking:</strong> Record all parts and accessories used, no matter how small</li>
                    <li><strong>Quality Control:</strong> Test all repairs thoroughly before marking as completed</li>
                    <li><strong>Communication:</strong> Add notes that help communicate progress to customers and managers</li>
                    <li><strong>Time Management:</strong> Set realistic completion dates and update if needed</li>
                    <li><strong>Professional Notes:</strong> Write clear, professional notes that others can understand</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Common Scenarios</div>
            <div class="content">
                <p><strong>When Parts Are Not Available:</strong></p>
                <ol>
                    <li>Update repair status to "Waiting for Parts"</li>
                    <li>Add a detailed note about which parts are needed</li>
                    <li>Notify your manager</li>
                    <li>Update estimated completion date</li>
                </ol>
                <p><strong>When Repair Cannot Be Completed:</strong></p>
                <ol>
                    <li>Add detailed notes explaining why</li>
                    <li>Update status appropriately</li>
                    <li>Notify manager and customer</li>
                    <li>Document all attempts made</li>
                </ol>
                <p><strong>When Additional Work Is Needed:</strong></p>
                <ol>
                    <li>Add notes about additional issues found</li>
                    <li>Update estimated cost if significant</li>
                    <li>Get customer approval if needed</li>
                    <li>Continue with additional repairs</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">7. Profile and Settings</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Your Profile</div>
            <div class="content">
                <p>To view or edit your profile:</p>
                <ol>
                    <li>Click on your profile icon/name in the top right corner</li>
                    <li>Select <strong>"Your Profile"</strong> from the dropdown</li>
                    <li>Update your information as needed</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Changing Your Password</div>
            <div class="content">
                <p>To change your password:</p>
                <ol>
                    <li>Go to your Profile page</li>
                    <li>Find the <strong>"Change Password"</strong> section</li>
                    <li>Enter current password</li>
                    <li>Enter new password (minimum 6 characters)</li>
                    <li>Confirm new password</li>
                    <li>Click <strong>"Change Password"</strong></li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">8. Quick Reference Guide</div>
        <div class="content">
            <table>
                <tr>
                    <th>Task</th>
                    <th>Steps</th>
                </tr>
                <tr>
                    <td><strong>View Dashboard</strong></td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td><strong>View Products</strong></td>
                    <td>Sidebar → Product Management</td>
                </tr>
                <tr>
                    <td><strong>Create New Repair</strong></td>
                    <td>Sidebar → Repairs → New Repair → Fill form → Save</td>
                </tr>
                <tr>
                    <td><strong>View All Repairs</strong></td>
                    <td>Sidebar → Repairs → Browse list or use filters</td>
                </tr>
                <tr>
                    <td><strong>Update Repair Status</strong></td>
                    <td>Repairs → Select Repair → Update Status → Select status → Add note → Save</td>
                </tr>
                <tr>
                    <td><strong>Add Repair Notes</strong></td>
                    <td>Repairs → Select Repair → Notes section → Add Note → Save</td>
                </tr>
                <tr>
                    <td><strong>Record Parts Used</strong></td>
                    <td>Repairs → Select Repair → Parts section → Add Part → Select part → Enter quantity → Save</td>
                </tr>
                <tr>
                    <td><strong>Complete Repair</strong></td>
                    <td>Repairs → Select Repair → Update Status → "Completed" → Add final notes → Save</td>
                </tr>
                <tr>
                    <td><strong>Access Profile</strong></td>
                    <td>Top Right → Profile Icon → Your Profile</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">9. Getting Help</div>
        <div class="content">
            <p>If you need assistance:</p>
            <ul>
                <li><strong>Technical Issues:</strong> Contact your manager or system administrator</li>
                <li><strong>Parts Availability:</strong> Check with your manager about ordering parts</li>
                <li><strong>Repair Questions:</strong> Consult this guide or ask your manager</li>
                <li><strong>System Errors:</strong> Report to manager immediately, don\'t attempt to fix</li>
            </ul>
            <div class="info-box">
                <strong>Remember:</strong> This guide is always available in your profile menu. Download it and keep it handy for quick reference!
            </div>
        </div>
    </div>
';
    }
    
    /**
     * Get manager guide content
     */
    private function getManagerGuideContent() {
        return '
    <div class="section">
        <div class="section-title">Introduction</div>
        <div class="content">
            <p>Welcome to the Manager User Guide! This comprehensive guide covers every feature available to managers in the system. As a manager, you have full control over staff, inventory, products, categories, brands, reports, and system settings including SMS functionality.</p>
            <div class="info-box">
                <strong>Your Complete Access:</strong> As a manager, you can:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Dashboard with comprehensive analytics and insights</li>
                    <li>Staff Management (add, edit, manage all staff members)</li>
                    <li>Product Management (add, edit, manage all products)</li>
                    <li>Category, Subcategory, and Brand Management</li>
                    <li>Inventory Control and Stock Management</li>
                    <li>Reports and Analytics (sales, inventory, staff performance)</li>
                    <li>System Settings and Configuration</li>
                    <li>SMS Account Setup and Credit Purchasing</li>
                    <li>View all sales, customers, and transactions</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard and Analytics</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Your Dashboard</div>
            <div class="content">
                <p><span class="step-number">1</span>After logging in, you\'ll see your manager dashboard.</p>
                <p><span class="step-number">2</span>Access it anytime by clicking <strong>"Dashboard"</strong> in the left sidebar.</p>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Dashboard Features</div>
            <div class="content">
                <p>Your manager dashboard provides comprehensive business insights:</p>
                <ul>
                    <li><strong>Sales Analytics:</strong> Daily, weekly, monthly sales trends and comparisons</li>
                    <li><strong>Revenue Summaries:</strong> Total revenue, profit margins, and financial overview</li>
                    <li><strong>Inventory Status:</strong> Current stock levels, low stock alerts, and inventory value</li>
                    <li><strong>Staff Performance:</strong> Individual and team performance metrics</li>
                    <li><strong>Recent Transactions:</strong> Latest sales, purchases, and activities</li>
                    <li><strong>System Notifications:</strong> Important alerts and updates</li>
                    <li><strong>Top Products:</strong> Best-selling items and revenue generators</li>
                    <li><strong>Customer Insights:</strong> New customers, repeat customers, and customer trends</li>
                </ul>
                <div class="highlight-box">
                    <strong>Pro Tip:</strong> Review your dashboard daily to stay informed about business performance and identify areas that need attention.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Using Analytics for Decision Making</div>
            <div class="content">
                <p>Use dashboard analytics to:</p>
                <ul>
                    <li>Track sales performance over time and identify trends</li>
                    <li>Identify top-selling products to optimize inventory</li>
                    <li>Monitor inventory turnover rates</li>
                    <li>Analyze staff productivity and performance</li>
                    <li>Generate business insights for strategic planning</li>
                    <li>Identify slow-moving products</li>
                    <li>Track customer acquisition and retention</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Staff Management - Complete Guide</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Staff Members</div>
            <div class="content">
                <p><strong>Step-by-Step:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Staff Management"</strong> from the left sidebar</li>
                    <li>You will see a table/list of all staff members</li>
                    <li>Each staff member shows:
                        <ul>
                            <li>Name and contact information</li>
                            <li>Role (Salesperson, Technician, etc.)</li>
                            <li>Status (Active, Inactive)</li>
                            <li>Last login date</li>
                            <li>Performance metrics (if available)</li>
                        </ul>
                    </li>
                    <li>Use the search bar to find specific staff members</li>
                    <li>Filter by role or status using the filter options</li>
                    <li>Click on any staff member\'s name to view their full profile</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Adding a New Staff Member</div>
            <div class="content">
                <p><strong>Complete Process:</strong></p>
                <ol>
                    <li>Go to <strong>"Staff Management"</strong> from the sidebar</li>
                    <li>Click the <strong>"Add New Staff"</strong> or <strong>"Create Staff"</strong> button (usually top right)</li>
                    <li>Fill in the staff form with the following information:</li>
                </ol>
                <p><strong>Personal Information:</strong></p>
                <ul>
                    <li><strong>Full Name:</strong> Staff member\'s complete name (required)</li>
                    <li><strong>Email Address:</strong> Valid email for login and notifications (required)</li>
                    <li><strong>Phone Number:</strong> Contact number (required)</li>
                    <li><strong>Address:</strong> Physical address (optional)</li>
                </ul>
                <p><strong>Account Information:</strong></p>
                <ul>
                    <li><strong>Username:</strong> Unique username for login (required)</li>
                    <li><strong>Password:</strong> Initial password (staff should change on first login)</li>
                    <li><strong>Confirm Password:</strong> Re-enter password for verification</li>
                </ul>
                <p><strong>Role Assignment:</strong></p>
                <ul>
                    <li><strong>Role:</strong> Select from dropdown (Salesperson, Technician, Manager, etc.)</li>
                    <li>Each role has specific permissions and access levels</li>
                    <li>Choose the role that matches the staff member\'s responsibilities</li>
                </ul>
                <p><strong>Additional Settings:</strong></p>
                <ul>
                    <li><strong>Status:</strong> Set to "Active" to enable login, "Inactive" to disable</li>
                    <li><strong>Permissions:</strong> Configure specific permissions if available</li>
                    <li><strong>Notes:</strong> Any additional information about the staff member</li>
                </ul>
                <ol start="4">
                    <li>Review all information for accuracy</li>
                    <li>Click <strong>"Save Staff"</strong> or <strong>"Create Staff"</strong></li>
                    <li>The staff member will be added to the system</li>
                    <li>They will receive login credentials (share securely)</li>
                </ol>
                <div class="warning-box">
                    <strong>Security Important:</strong> Only assign roles and permissions appropriate to each staff member\'s responsibilities. Never share admin-level access unnecessarily.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Editing Staff Information</div>
            <div class="content">
                <p>To update staff member details:</p>
                <ol>
                    <li>Go to Staff Management</li>
                    <li>Find and click on the staff member\'s name</li>
                    <li>Click the <strong>"Edit"</strong> button</li>
                    <li>Update any information that has changed:
                        <ul>
                            <li>Personal information (name, email, phone)</li>
                            <li>Role assignment</li>
                            <li>Status (Active/Inactive)</li>
                            <li>Permissions</li>
                        </ul>
                    </li>
                    <li>Click <strong>"Save Changes"</strong></li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Resetting Staff Passwords</div>
            <div class="content">
                <p>If a staff member forgets their password:</p>
                <ol>
                    <li>Open the staff member\'s profile</li>
                    <li>Click <strong>"Reset Password"</strong> or <strong>"Change Password"</strong></li>
                    <li>Enter a new temporary password</li>
                    <li>Confirm the new password</li>
                    <li>Click <strong>"Reset"</strong> or <strong>"Save"</strong></li>
                    <li>Share the new password securely with the staff member</li>
                    <li>Advise them to change it on their first login</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Deactivating/Activating Staff</div>
            <div class="content">
                <p>To temporarily disable a staff account:</p>
                <ol>
                    <li>Open the staff member\'s profile</li>
                    <li>Change status from "Active" to "Inactive"</li>
                    <li>Save changes</li>
                    <li>The staff member will no longer be able to log in</li>
                </ol>
                <p>To reactivate:</p>
                <ol>
                    <li>Open the staff member\'s profile</li>
                    <li>Change status from "Inactive" to "Active"</li>
                    <li>Save changes</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Viewing Staff Performance</div>
            <div class="content">
                <p>To monitor staff performance:</p>
                <ol>
                    <li>Open a staff member\'s profile</li>
                    <li>View performance metrics (if available):
                        <ul>
                            <li>Sales statistics (for salespersons)</li>
                            <li>Repairs completed (for technicians)</li>
                            <li>Activity logs</li>
                            <li>Login history</li>
                        </ul>
                    </li>
                    <li>Use this information for performance reviews and feedback</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Product Management - Complete Guide</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Products</div>
            <div class="content">
                <p><strong>Accessing Product List:</strong></p>
                <ol>
                    <li>Click <strong>"Product Management"</strong> or <strong>"Inventory"</strong> from the sidebar</li>
                    <li>You\'ll see a comprehensive list of all products</li>
                    <li>Use search to find products by name, SKU, or brand</li>
                    <li>Use filters for category, brand, stock status, etc.</li>
                    <li>Click on any product to view or edit details</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Adding a New Product - Detailed Steps</div>
            <div class="content">
                <p><strong>Complete Step-by-Step Process:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Product Management"</strong> from the sidebar</li>
                    <li>Click the <strong>"Add New Product"</strong> or <strong>"Create Product"</strong> button</li>
                    <li>Fill in the product form systematically:</li>
                </ol>
                <p><strong>Basic Information:</strong></p>
                <ol>
                    <li><strong>Product Name:</strong> Enter the full product name (required)</li>
                    <li><strong>SKU (Stock Keeping Unit):</strong> Unique identifier for the product (required, must be unique)</li>
                    <li><strong>Description:</strong> Detailed product description (optional but recommended)</li>
                    <li><strong>Short Description:</strong> Brief summary for quick reference</li>
                </ol>
                <p><strong>Classification:</strong></p>
                <ol>
                    <li><strong>Category:</strong> Select main category from dropdown (create category first if needed)</li>
                    <li><strong>Subcategory:</strong> Select subcategory (optional, but helps organization)</li>
                    <li><strong>Brand:</strong> Select brand from dropdown (create brand first if needed)</li>
                </ol>
                <p><strong>Pricing Information:</strong></p>
                <ol>
                    <li><strong>Cost Price:</strong> Price you paid to acquire the product</li>
                    <li><strong>Selling Price:</strong> Price customers will pay (required)</li>
                    <li><strong>Wholesale Price:</strong> Price for bulk purchases (optional)</li>
                    <li><strong>Tax Rate:</strong> Applicable tax percentage (if any)</li>
                </ol>
                <p><strong>Inventory Information:</strong></p>
                <ol>
                    <li><strong>Initial Stock Quantity:</strong> Starting inventory amount</li>
                    <li><strong>Low Stock Alert:</strong> Set minimum quantity that triggers alerts</li>
                    <li><strong>Unit:</strong> Unit of measurement (pieces, boxes, etc.)</li>
                </ol>
                <p><strong>Product Specifications:</strong></p>
                <ol>
                    <li>Click <strong>"Add Specification"</strong> to add technical details</li>
                    <li>Common specifications include:
                        <ul>
                            <li>Model number</li>
                            <li>Color, size, weight</li>
                            <li>Technical specifications</li>
                            <li>Compatibility information</li>
                            <li>Warranty information</li>
                        </ul>
                    </li>
                    <li>Add as many specifications as needed</li>
                </ol>
                <p><strong>Product Images:</strong></p>
                <ol>
                    <li>Click <strong>"Upload Image"</strong> or <strong>"Add Image"</strong></li>
                    <li>Select image file(s) from your computer</li>
                    <li>Wait for upload to complete</li>
                    <li>You can add multiple images</li>
                    <li>Set a primary/main image</li>
                </ol>
                <p><strong>Saving the Product:</strong></p>
                <ol>
                    <li>Review all information for accuracy</li>
                    <li>Verify prices and stock quantity</li>
                    <li>Click <strong>"Save Product"</strong> or <strong>"Create Product"</strong></li>
                    <li>The product will be added to inventory</li>
                    <li>You\'ll be redirected to the product list or product details page</li>
                </ol>
                <div class="info-box">
                    <strong>Tip:</strong> Create categories, subcategories, and brands before adding products to ensure proper organization.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Editing Product Information</div>
            <div class="content">
                <p>To update product details:</p>
                <ol>
                    <li>Go to Product Management</li>
                    <li>Find and click on the product you want to edit</li>
                    <li>Click the <strong>"Edit"</strong> button</li>
                    <li>Update any information:
                        <ul>
                            <li>Product name, description, SKU</li>
                            <li>Category, subcategory, brand</li>
                            <li>Prices (cost, selling, wholesale)</li>
                            <li>Stock quantity</li>
                            <li>Specifications</li>
                            <li>Images</li>
                        </ul>
                    </li>
                    <li>Click <strong>"Save Changes"</strong></li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Managing Stock Quantities</div>
            <div class="content">
                <p><strong>Updating Stock Manually:</strong></p>
                <ol>
                    <li>Open the product you want to update</li>
                    <li>Find the <strong>"Stock Quantity"</strong> or <strong>"Inventory"</strong> section</li>
                    <li>Click <strong>"Update Stock"</strong> or <strong>"Adjust Quantity"</strong></li>
                    <li>Enter the new quantity or adjustment amount</li>
                    <li>Add a reason/note for the adjustment (e.g., "Restocked", "Damaged items removed")</li>
                    <li>Click <strong>"Update"</strong> or <strong>"Save"</strong></li>
                </ol>
                <p><strong>Low Stock Alerts:</strong></p>
                <ul>
                    <li>Set low stock threshold when creating/editing products</li>
                    <li>You\'ll receive alerts when stock falls below threshold</li>
                    <li>Check dashboard for low stock notifications</li>
                    <li>Use alerts to plan restocking</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Deleting Products</div>
            <div class="content">
                <p>To remove a product from the system:</p>
                <ol>
                    <li>Open the product</li>
                    <li>Click <strong>"Delete"</strong> button (usually at bottom or in actions menu)</li>
                    <li>Confirm deletion (this action may be irreversible)</li>
                    <li>Product will be removed from inventory</li>
                </ol>
                <div class="warning-box">
                    <strong>Warning:</strong> Deleting a product removes it permanently. Consider deactivating or archiving instead if you might need the data later.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Categories, Subcategories, and Brands Management</div>
        <div class="subsection">
            <div class="subsection-title">Managing Categories</div>
            <div class="content">
                <p><strong>Viewing Categories:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Categories"</strong> from the sidebar</li>
                    <li>View all existing product categories</li>
                    <li>See how many products are in each category</li>
                </ol>
                <p><strong>Adding a New Category:</strong></p>
                <ol>
                    <li>Click <strong>"Add New Category"</strong> button</li>
                    <li>Enter category name (e.g., "Phones", "Accessories", "Repair Parts")</li>
                    <li>Add description (optional)</li>
                    <li>Set display order if needed</li>
                    <li>Click <strong>"Save Category"</strong></li>
                </ol>
                <p><strong>Editing Categories:</strong></p>
                <ol>
                    <li>Click on a category name</li>
                    <li>Click <strong>"Edit"</strong></li>
                    <li>Update name or description</li>
                    <li>Save changes</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Managing Subcategories</div>
            <div class="content">
                <p><strong>Viewing Subcategories:</strong></p>
                <ol>
                    <li>Go to <strong>"Subcategories"</strong> from the sidebar</li>
                    <li>View all subcategories organized by parent category</li>
                </ol>
                <p><strong>Adding a New Subcategory:</strong></p>
                <ol>
                    <li>Click <strong>"Add New Subcategory"</strong></li>
                    <li>Select the parent <strong>Category</strong> from dropdown</li>
                    <li>Enter subcategory name (e.g., "Smartphones", "Feature Phones" under "Phones")</li>
                    <li>Add description (optional)</li>
                    <li>Click <strong>"Save Subcategory"</strong></li>
                </ol>
                <p><strong>Editing Subcategories:</strong></p>
                <ol>
                    <li>Click on subcategory name</li>
                    <li>Click <strong>"Edit"</strong></li>
                    <li>Update information</li>
                    <li>Save changes</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Managing Brands</div>
            <div class="content">
                <p><strong>Viewing Brands:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Brands"</strong> from the sidebar</li>
                    <li>View all product brands in the system</li>
                    <li>See product count per brand</li>
                </ol>
                <p><strong>Adding a New Brand:</strong></p>
                <ol>
                    <li>Click <strong>"Add New Brand"</strong> button</li>
                    <li>Enter brand name (e.g., "Samsung", "Apple", "Huawei")</li>
                    <li>Add brand description (optional)</li>
                    <li>Upload brand logo (optional)</li>
                    <li>Click <strong>"Save Brand"</strong></li>
                </ol>
                <p><strong>Editing Brands:</strong></p>
                <ol>
                    <li>Click on brand name</li>
                    <li>Click <strong>"Edit"</strong></li>
                    <li>Update brand information</li>
                    <li>Save changes</li>
                </ol>
                <div class="info-box">
                    <strong>Organization Tip:</strong> Well-organized categories, subcategories, and brands make it easier to find products and manage inventory efficiently.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Reports and Analytics - Complete Guide</div>
        <div class="subsection">
            <div class="subsection-title">Accessing Reports</div>
            <div class="content">
                <p><strong>Step-by-Step:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Reports"</strong> from the left sidebar</li>
                    <li>You\'ll see different report types available</li>
                    <li>Select the type of report you want to generate</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Sales Reports</div>
            <div class="content">
                <p><strong>Generating Sales Reports:</strong></p>
                <ol>
                    <li>Go to Reports → <strong>"Sales Reports"</strong></li>
                    <li>Select date range:
                        <ul>
                            <li>Today</li>
                            <li>This Week</li>
                            <li>This Month</li>
                            <li>This Year</li>
                            <li>Custom Range (select start and end dates)</li>
                        </ul>
                    </li>
                    <li>Select additional filters (optional):
                        <ul>
                            <li>By staff member</li>
                            <li>By product</li>
                            <li>By payment method</li>
                        </ul>
                    </li>
                    <li>Click <strong>"Generate Report"</strong> or <strong>"View Report"</strong></li>
                    <li>Review the report data</li>
                </ol>
                <p><strong>Sales Report Information Includes:</strong></p>
                <ul>
                    <li>Total sales count</li>
                    <li>Total revenue</li>
                    <li>Total discounts given</li>
                    <li>Total taxes collected</li>
                    <li>Net profit</li>
                    <li>Breakdown by day/product/staff</li>
                    <li>Top-selling products</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Inventory Reports</div>
            <div class="content">
                <p><strong>Generating Inventory Reports:</strong></p>
                <ol>
                    <li>Go to Reports → <strong>"Inventory Reports"</strong></li>
                    <li>Select report type:
                        <ul>
                            <li>Current Stock Levels</li>
                            <li>Low Stock Items</li>
                            <li>Stock Movement History</li>
                            <li>Inventory Valuation</li>
                        </ul>
                    </li>
                    <li>Apply filters (category, brand, stock status)</li>
                    <li>Generate the report</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Staff Performance Reports</div>
            <div class="content">
                <p><strong>Generating Staff Reports:</strong></p>
                <ol>
                    <li>Go to Reports → <strong>"Staff Performance"</strong></li>
                    <li>Select date range</li>
                    <li>Select staff member(s) or view all</li>
                    <li>Generate report</li>
                </ol>
                <p><strong>Report Shows:</strong></p>
                <ul>
                    <li>Sales made by each salesperson</li>
                    <li>Repairs completed by each technician</li>
                    <li>Revenue generated per staff member</li>
                    <li>Activity statistics</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Exporting Reports</div>
            <div class="content">
                <p><strong>Export Options:</strong></p>
                <ol>
                    <li>After generating a report, you\'ll see export options</li>
                    <li>Click on your preferred format:</li>
                </ol>
                <p><strong>PDF Export:</strong></p>
                <ul>
                    <li>Click <strong>"Export as PDF"</strong></li>
                    <li>PDF will be generated and downloaded</li>
                    <li>Perfect for printing or sharing</li>
                </ul>
                <p><strong>Excel Export:</strong></p>
                <ul>
                    <li>Click <strong>"Export as Excel"</strong></li>
                    <li>Excel file (.xlsx) will be downloaded</li>
                    <li>Open in Microsoft Excel or Google Sheets</li>
                    <li>Great for further analysis and calculations</li>
                </ul>
                <p><strong>CSV Export:</strong></p>
                <ul>
                    <li>Click <strong>"Export as CSV"</strong></li>
                    <li>CSV file will be downloaded</li>
                    <li>Compatible with most spreadsheet applications</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. SMS Settings and Credit Purchasing - Complete Guide</div>
        <div class="subsection">
            <div class="subsection-title">Accessing SMS Settings</div>
            <div class="content">
                <p><strong>Step-by-Step:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Settings"</strong> from the left sidebar</li>
                    <li>Click on <strong>"SMS Settings"</strong> or look for SMS-related options</li>
                    <li>Alternatively, go directly to <strong>"SMS Settings"</strong> if available in sidebar</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Setting Up SMS Account</div>
            <div class="content">
                <p><strong>Initial Setup (First Time):</strong></p>
                <ol>
                    <li>Go to SMS Settings page</li>
                    <li>If no account exists, you\'ll see <strong>"Set Up SMS Account"</strong> option</li>
                    <li>Click <strong>"Create Account"</strong> or <strong>"Set Up"</strong></li>
                    <li>Fill in account information:
                        <ul>
                            <li>Account name/identifier</li>
                            <li>Contact information</li>
                            <li>Any required configuration</li>
                        </ul>
                    </li>
                    <li>Save the account settings</li>
                    <li>Your SMS account will be created</li>
                </ol>
                <div class="info-box">
                    <strong>Note:</strong> SMS account setup may require approval or verification. Follow any additional steps if prompted.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Purchasing SMS Credits - Detailed Process</div>
            <div class="content">
                <p><strong>Complete Step-by-Step Guide:</strong></p>
                <ol>
                    <li>Navigate to <strong>"SMS Settings"</strong> or <strong>"Purchase SMS"</strong> from Settings</li>
                    <li>You\'ll see your current SMS credit balance</li>
                    <li>Click <strong>"Purchase Credits"</strong> or <strong>"Buy SMS"</strong> button</li>
                    <li>You\'ll be taken to the SMS purchase page</li>
                </ol>
                <p><strong>Selecting a Plan:</strong></p>
                <ol>
                    <li>View available SMS packages/plans</li>
                    <li>Each plan shows:
                        <ul>
                            <li>Number of SMS credits included</li>
                            <li>Price per package</li>
                            <li>Price per SMS (unit cost)</li>
                            <li>Validity period (if applicable)</li>
                        </ul>
                    </li>
                    <li>Compare different packages</li>
                    <li>Select the package that best fits your needs</li>
                </ol>
                <p><strong>Purchase Options:</strong></p>
                <ul>
                    <li><strong>Small Package:</strong> For light usage (e.g., 100-500 SMS)</li>
                    <li><strong>Medium Package:</strong> For regular usage (e.g., 500-2000 SMS)</li>
                    <li><strong>Large Package:</strong> For heavy usage (e.g., 2000+ SMS)</li>
                    <li><strong>Custom Amount:</strong> Enter specific number of credits if available</li>
                </ul>
                <p><strong>Completing the Purchase:</strong></p>
                <ol>
                    <li>Click on your selected package</li>
                    <li>Review the purchase details:
                        <ul>
                            <li>Number of credits</li>
                            <li>Total cost</li>
                            <li>Payment method</li>
                        </ul>
                    </li>
                    <li>Select payment method (if multiple options available):
                        <ul>
                            <li>Mobile Money</li>
                            <li>Bank Transfer</li>
                            <li>Credit/Debit Card</li>
                            <li>Other available methods</li>
                        </ul>
                    </li>
                    <li>Enter payment details as required</li>
                    <li>Review terms and conditions</li>
                    <li>Click <strong>"Confirm Purchase"</strong> or <strong>"Proceed to Payment"</strong></li>
                    <li>Complete payment through the selected method</li>
                    <li>Wait for payment confirmation</li>
                    <li>Credits will be added to your account automatically</li>
                </ol>
                <p><strong>After Purchase:</strong></p>
                <ol>
                    <li>You\'ll receive a confirmation message</li>
                    <li>Your SMS credit balance will update</li>
                    <li>You can start using SMS features immediately</li>
                    <li>Check your purchase history in SMS Settings</li>
                </ol>
                <div class="highlight-box">
                    <strong>Pro Tip:</strong> Purchase larger packages for better value. Monitor your usage to determine the right package size for future purchases.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Viewing SMS Balance and Usage</div>
            <div class="content">
                <p><strong>Checking Your Balance:</strong></p>
                <ol>
                    <li>Go to SMS Settings</li>
                    <li>View your current credit balance (displayed prominently)</li>
                    <li>Check remaining credits before they run out</li>
                </ol>
                <p><strong>Viewing Usage History:</strong></p>
                <ol>
                    <li>In SMS Settings, find <strong>"Usage History"</strong> or <strong>"SMS Logs"</strong></li>
                    <li>View sent SMS records:
                        <ul>
                            <li>Date and time sent</li>
                            <li>Recipient phone number</li>
                            <li>Message content</li>
                            <li>Status (sent, failed, pending)</li>
                            <li>Credits used</li>
                        </ul>
                    </li>
                    <li>Filter by date range if needed</li>
                    <li>Export logs if required</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Configuring SMS Templates</div>
            <div class="content">
                <p><strong>Creating SMS Templates:</strong></p>
                <ol>
                    <li>Go to SMS Settings</li>
                    <li>Navigate to <strong>"Templates"</strong> section</li>
                    <li>Click <strong>"Create Template"</strong> or <strong>"Add Template"</strong></li>
                    <li>Enter template details:
                        <ul>
                            <li>Template name</li>
                            <li>Message content (use placeholders like {customer_name}, {amount}, etc.)</li>
                            <li>Template type (receipt, notification, reminder, etc.)</li>
                        </ul>
                    </li>
                    <li>Save the template</li>
                    <li>Templates can be used when sending SMS</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Low Credit Alerts</div>
            <div class="content">
                <p>The system may send alerts when:</p>
                <ul>
                    <li>SMS credits are running low (below threshold)</li>
                    <li>Credits are depleted</li>
                    <li>Purchase is needed</li>
                </ul>
                <p>Set up alerts in SMS Settings to be notified before running out.</p>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">7. System Settings and Configuration</div>
        <div class="subsection">
            <div class="subsection-title">Accessing System Settings</div>
            <div class="content">
                <p><strong>Step-by-Step:</strong></p>
                <ol>
                    <li>Navigate to <strong>"Settings"</strong> from the left sidebar</li>
                    <li>You\'ll see various setting categories</li>
                    <li>Click on the section you want to configure</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Company Information Settings</div>
            <div class="content">
                <p><strong>Updating Company Details:</strong></p>
                <ol>
                    <li>Go to Settings → <strong>"Company Information"</strong></li>
                    <li>Update company details:
                        <ul>
                            <li>Company name</li>
                            <li>Business registration number</li>
                            <li>Contact information (phone, email, address)</li>
                            <li>Business hours</li>
                            <li>Logo (upload company logo)</li>
                        </ul>
                    </li>
                    <li>Save changes</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Tax and Pricing Settings</div>
            <div class="content">
                <p><strong>Configuring Tax Settings:</strong></p>
                <ol>
                    <li>Go to Settings → <strong>"Tax Settings"</strong></li>
                    <li>Set default tax rate (percentage)</li>
                    <li>Configure tax rules if applicable</li>
                    <li>Save settings</li>
                </ol>
                <p><strong>Currency Settings:</strong></p>
                <ol>
                    <li>Go to Settings → <strong>"Currency"</strong></li>
                    <li>Select default currency</li>
                    <li>Set currency symbol</li>
                    <li>Save settings</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Notification Settings</div>
            <div class="content">
                <p><strong>Configuring Notifications:</strong></p>
                <ol>
                    <li>Go to Settings → <strong>"Notifications"</strong></li>
                    <li>Enable/disable notification types:
                        <ul>
                            <li>Email notifications</li>
                            <li>SMS notifications</li>
                            <li>System alerts</li>
                            <li>Low stock alerts</li>
                            <li>Sales notifications</li>
                        </ul>
                    </li>
                    <li>Configure notification preferences</li>
                    <li>Save settings</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">8. Best Practices for Managers</div>
        <div class="subsection">
            <div class="subsection-title">Daily Management Routine</div>
            <div class="content">
                <ol>
                    <li><strong>Morning:</strong> Check dashboard for overnight activity and alerts</li>
                    <li><strong>Review Reports:</strong> Check sales, inventory, and staff performance</li>
                    <li><strong>Monitor Inventory:</strong> Check low stock alerts and plan restocking</li>
                    <li><strong>Staff Oversight:</strong> Review staff activities and provide guidance</li>
                    <li><strong>End of Day:</strong> Review daily performance and plan for next day</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Key Management Principles</div>
            <div class="content">
                <ul>
                    <li><strong>Regular Monitoring:</strong> Check dashboard and reports daily to stay informed</li>
                    <li><strong>Staff Oversight:</strong> Regularly review staff performance and provide constructive feedback</li>
                    <li><strong>Inventory Control:</strong> Maintain optimal stock levels - not too high (tying up capital) or too low (missing sales)</li>
                    <li><strong>Data Accuracy:</strong> Ensure all product information, prices, and stock levels are accurate</li>
                    <li><strong>Security:</strong> Protect system access, regularly update passwords, and monitor user activities</li>
                    <li><strong>Backup:</strong> Ensure regular data backups are performed (check with system admin)</li>
                    <li><strong>Training:</strong> Keep staff trained on system usage and best practices</li>
                    <li><strong>SMS Management:</strong> Monitor SMS usage and maintain adequate credits</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">9. Quick Reference Guide</div>
        <div class="content">
            <table>
                <tr>
                    <th>Task</th>
                    <th>Steps</th>
                </tr>
                <tr>
                    <td><strong>View Dashboard</strong></td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td><strong>Add Staff Member</strong></td>
                    <td>Sidebar → Staff Management → Add New Staff → Fill form → Save</td>
                </tr>
                <tr>
                    <td><strong>Edit Staff</strong></td>
                    <td>Staff Management → Click staff name → Edit → Update → Save</td>
                </tr>
                <tr>
                    <td><strong>Add Product</strong></td>
                    <td>Sidebar → Product Management → Add New Product → Fill all fields → Save</td>
                </tr>
                <tr>
                    <td><strong>Edit Product</strong></td>
                    <td>Product Management → Click product → Edit → Update → Save</td>
                </tr>
                <tr>
                    <td><strong>Update Stock</strong></td>
                    <td>Product Management → Select product → Update Stock → Enter quantity → Save</td>
                </tr>
                <tr>
                    <td><strong>Add Category</strong></td>
                    <td>Sidebar → Categories → Add New Category → Enter name → Save</td>
                </tr>
                <tr>
                    <td><strong>Add Subcategory</strong></td>
                    <td>Sidebar → Subcategories → Add New → Select category → Enter name → Save</td>
                </tr>
                <tr>
                    <td><strong>Add Brand</strong></td>
                    <td>Sidebar → Brands → Add New Brand → Enter name → Save</td>
                </tr>
                <tr>
                    <td><strong>Generate Sales Report</strong></td>
                    <td>Sidebar → Reports → Sales Reports → Select date range → Generate</td>
                </tr>
                <tr>
                    <td><strong>Export Report</strong></td>
                    <td>Reports → Generate report → Click Export → Select format (PDF/Excel/CSV)</td>
                </tr>
                <tr>
                    <td><strong>Purchase SMS Credits</strong></td>
                    <td>Settings → SMS Settings → Purchase Credits → Select package → Complete payment</td>
                </tr>
                <tr>
                    <td><strong>View SMS Balance</strong></td>
                    <td>Settings → SMS Settings → View balance at top of page</td>
                </tr>
                <tr>
                    <td><strong>View SMS Logs</strong></td>
                    <td>Settings → SMS Settings → SMS Logs/Usage History</td>
                </tr>
                <tr>
                    <td><strong>System Settings</strong></td>
                    <td>Sidebar → Settings → Select category → Update → Save</td>
                </tr>
                <tr>
                    <td><strong>Access Profile</strong></td>
                    <td>Top Right → Profile Icon → Your Profile</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">10. Getting Help</div>
        <div class="content">
            <p>If you need assistance:</p>
            <ul>
                <li><strong>System Issues:</strong> Contact system administrator</li>
                <li><strong>Feature Questions:</strong> Refer to this guide</li>
                <li><strong>SMS Issues:</strong> Check SMS Settings or contact SMS provider support</li>
                <li><strong>Staff Training:</strong> Share relevant sections of this guide with staff</li>
            </ul>
            <div class="info-box">
                <strong>Remember:</strong> This guide is always available in your profile menu. Download it and keep it handy for quick reference. You can also share specific sections with your staff members for training purposes.
            </div>
        </div>
    </div>
';
    }
}
