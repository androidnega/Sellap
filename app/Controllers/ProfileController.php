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
            <p>Welcome to the Salesperson User Guide! This guide will help you understand how to use the system effectively for managing sales, customers, and inventory.</p>
            <div class="info-box">
                <strong>Your Role:</strong> As a salesperson, you have access to the dashboard, product management, sales operations, and customer management features.
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard Overview</div>
        <div class="content">
            <p>The dashboard is your central hub where you can view:</p>
            <ul>
                <li>Today\'s sales summary</li>
                <li>Recent transactions</li>
                <li>Product inventory status</li>
                <li>Customer information</li>
            </ul>
            <div class="highlight-box">
                <strong>Tip:</strong> Check your dashboard daily to stay updated on sales performance and inventory levels.
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Product Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Products</div>
            <div class="content">
                <p>To view available products:</p>
                <ol>
                    <li>Navigate to <strong>Product Management</strong> from the sidebar</li>
                    <li>Browse through the product list</li>
                    <li>Use the search bar to find specific products</li>
                    <li>Click on a product to view detailed information</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Product Information</div>
            <div class="content">
                <p>Each product displays:</p>
                <ul>
                    <li>Product name and description</li>
                    <li>Current stock quantity</li>
                    <li>Price information</li>
                    <li>Brand and category</li>
                    <li>Product specifications</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Sales Operations</div>
        <div class="subsection">
            <div class="subsection-title">Creating a Sale</div>
            <div class="content">
                <p>To process a sale:</p>
                <ol>
                    <li>Go to <strong>Sales</strong> from the sidebar</li>
                    <li>Click <strong>New Sale</strong> or use the POS system</li>
                    <li>Add products to the cart</li>
                    <li>Enter customer information (if applicable)</li>
                    <li>Apply discounts if needed</li>
                    <li>Process payment</li>
                    <li>Generate receipt</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">POS System</div>
            <div class="content">
                <p>The Point of Sale (POS) system allows you to:</p>
                <ul>
                    <li>Quickly scan or search for products</li>
                    <li>Add multiple items to a transaction</li>
                    <li>Calculate totals automatically</li>
                    <li>Process various payment methods</li>
                    <li>Print receipts</li>
                </ul>
                <div class="warning-box">
                    <strong>Important:</strong> Always verify product quantities and prices before completing a sale.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Customer Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Customers</div>
            <div class="content">
                <p>To view customer information:</p>
                <ol>
                    <li>Navigate to <strong>Customers</strong> from the sidebar</li>
                    <li>Browse the customer list</li>
                    <li>Search for specific customers</li>
                    <li>View customer purchase history</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Customer Information</div>
            <div class="content">
                <p>Customer profiles include:</p>
                <ul>
                    <li>Contact information</li>
                    <li>Purchase history</li>
                    <li>Transaction records</li>
                    <li>Payment information</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Best Practices</div>
        <div class="content">
            <ul>
                <li><strong>Always verify stock:</strong> Check product availability before promising delivery</li>
                <li><strong>Accurate data entry:</strong> Double-check all information when creating sales</li>
                <li><strong>Customer service:</strong> Maintain professional communication with customers</li>
                <li><strong>Receipt management:</strong> Always provide receipts for transactions</li>
                <li><strong>Report issues:</strong> Notify your manager if you encounter any problems</li>
            </ul>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. Quick Reference</div>
        <div class="content">
            <table>
                <tr>
                    <th>Action</th>
                    <th>Location</th>
                </tr>
                <tr>
                    <td>View Dashboard</td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td>View Products</td>
                    <td>Sidebar → Product Management</td>
                </tr>
                <tr>
                    <td>Create Sale</td>
                    <td>Sidebar → Sales → New Sale</td>
                </tr>
                <tr>
                    <td>View Customers</td>
                    <td>Sidebar → Customers</td>
                </tr>
                <tr>
                    <td>Access Profile</td>
                    <td>Top Right → Profile Icon</td>
                </tr>
            </table>
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
            <p>Welcome to the Technician User Guide! This guide will help you understand how to use the system for managing repairs, inventory, and product maintenance.</p>
            <div class="info-box">
                <strong>Your Role:</strong> As a technician, you have access to the dashboard, product management, and repair management features.
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard Overview</div>
        <div class="content">
            <p>The dashboard provides you with:</p>
            <ul>
                <li>Active repair jobs</li>
                <li>Repair statistics</li>
                <li>Product inventory status</li>
                <li>Pending tasks</li>
            </ul>
            <div class="highlight-box">
                <strong>Tip:</strong> Regularly check your dashboard to stay on top of repair assignments and deadlines.
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Product Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Products</div>
            <div class="content">
                <p>To access product information:</p>
                <ol>
                    <li>Navigate to <strong>Product Management</strong> from the sidebar</li>
                    <li>Search for specific products</li>
                    <li>View product details and specifications</li>
                    <li>Check product availability and condition</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Product Specifications</div>
            <div class="content">
                <p>Important product information includes:</p>
                <ul>
                    <li>Model numbers and serial numbers</li>
                    <li>Technical specifications</li>
                    <li>Compatibility information</li>
                    <li>Warranty status</li>
                    <li>Repair history</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Repair Management</div>
        <div class="subsection">
            <div class="subsection-title">Creating a Repair Job</div>
            <div class="content">
                <p>To create a new repair job:</p>
                <ol>
                    <li>Go to <strong>Repairs</strong> from the sidebar</li>
                    <li>Click <strong>New Repair</strong></li>
                    <li>Enter customer information</li>
                    <li>Select the product/device to repair</li>
                    <li>Describe the issue or problem</li>
                    <li>Set repair status and priority</li>
                    <li>Add estimated completion date</li>
                    <li>Save the repair job</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Managing Repair Jobs</div>
            <div class="content">
                <p>You can manage repair jobs by:</p>
                <ul>
                    <li>Updating repair status (In Progress, Completed, Waiting for Parts, etc.)</li>
                    <li>Adding repair notes and updates</li>
                    <li>Recording parts and accessories used</li>
                    <li>Updating repair costs</li>
                    <li>Marking repairs as completed</li>
                </ul>
                <div class="warning-box">
                    <strong>Important:</strong> Always update repair status regularly to keep customers informed.
                </div>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Repair Status Types</div>
            <div class="content">
                <table>
                    <tr>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                    <tr>
                        <td>Pending</td>
                        <td>Repair job created but not started</td>
                    </tr>
                    <tr>
                        <td>In Progress</td>
                        <td>Currently being worked on</td>
                    </tr>
                    <tr>
                        <td>Waiting for Parts</td>
                        <td>Awaiting replacement parts</td>
                    </tr>
                    <tr>
                        <td>Completed</td>
                        <td>Repair finished and ready for pickup</td>
                    </tr>
                    <tr>
                        <td>Cancelled</td>
                        <td>Repair job cancelled</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Parts and Accessories</div>
        <div class="subsection">
            <div class="subsection-title">Recording Parts Used</div>
            <div class="content">
                <p>When completing a repair:</p>
                <ol>
                    <li>Open the repair job</li>
                    <li>Navigate to the Parts/Accessories section</li>
                    <li>Add parts used in the repair</li>
                    <li>Record quantities and costs</li>
                    <li>Update inventory automatically</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Inventory Updates</div>
            <div class="content">
                <p>The system automatically updates inventory when you record parts usage. Make sure to:</p>
                <ul>
                    <li>Accurately record all parts used</li>
                    <li>Verify part numbers and quantities</li>
                    <li>Report low stock levels to your manager</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Best Practices</div>
        <div class="content">
            <ul>
                <li><strong>Document everything:</strong> Keep detailed notes on all repairs</li>
                <li><strong>Update status regularly:</strong> Keep repair status current</li>
                <li><strong>Accurate parts tracking:</strong> Record all parts and accessories used</li>
                <li><strong>Quality control:</strong> Test all repairs before marking as completed</li>
                <li><strong>Communication:</strong> Update customers on repair progress</li>
                <li><strong>Time management:</strong> Set realistic completion dates</li>
            </ul>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. Quick Reference</div>
        <div class="content">
            <table>
                <tr>
                    <th>Action</th>
                    <th>Location</th>
                </tr>
                <tr>
                    <td>View Dashboard</td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td>View Products</td>
                    <td>Sidebar → Product Management</td>
                </tr>
                <tr>
                    <td>Create Repair</td>
                    <td>Sidebar → Repairs → New Repair</td>
                </tr>
                <tr>
                    <td>View Repairs</td>
                    <td>Sidebar → Repairs</td>
                </tr>
                <tr>
                    <td>Update Repair Status</td>
                    <td>Repairs → Select Repair → Update Status</td>
                </tr>
                <tr>
                    <td>Access Profile</td>
                    <td>Top Right → Profile Icon</td>
                </tr>
            </table>
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
            <p>Welcome to the Manager User Guide! This comprehensive guide covers all system features available to managers, including staff management, inventory control, reporting, and system configuration.</p>
            <div class="info-box">
                <strong>Your Role:</strong> As a manager, you have access to dashboard analytics, staff management, inventory management, categories, brands, subcategories, reports, and system settings.
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">1. Dashboard and Analytics</div>
        <div class="subsection">
            <div class="subsection-title">Dashboard Overview</div>
            <div class="content">
                <p>Your manager dashboard provides:</p>
                <ul>
                    <li>Sales analytics and trends</li>
                    <li>Revenue summaries</li>
                    <li>Inventory status and alerts</li>
                    <li>Staff performance metrics</li>
                    <li>Recent transactions</li>
                    <li>System notifications</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Analytics Features</div>
            <div class="content">
                <p>Use analytics to:</p>
                <ul>
                    <li>Track sales performance over time</li>
                    <li>Identify top-selling products</li>
                    <li>Monitor inventory turnover</li>
                    <li>Analyze staff productivity</li>
                    <li>Generate business insights</li>
                </ul>
                <div class="highlight-box">
                    <strong>Tip:</strong> Review analytics regularly to make informed business decisions.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">2. Staff Management</div>
        <div class="subsection">
            <div class="subsection-title">Viewing Staff</div>
            <div class="content">
                <p>To manage staff members:</p>
                <ol>
                    <li>Navigate to <strong>Staff Management</strong> from the sidebar</li>
                    <li>View all staff members and their roles</li>
                    <li>Search and filter staff by role or status</li>
                    <li>View individual staff profiles</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Adding Staff Members</div>
            <div class="content">
                <p>To add a new staff member:</p>
                <ol>
                    <li>Go to Staff Management</li>
                    <li>Click <strong>Add New Staff</strong></li>
                    <li>Enter personal information (name, email, phone)</li>
                    <li>Assign a role (Salesperson, Technician, etc.)</li>
                    <li>Set login credentials</li>
                    <li>Configure permissions</li>
                    <li>Save the staff member</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Managing Staff</div>
            <div class="content">
                <p>You can:</p>
                <ul>
                    <li>Edit staff information</li>
                    <li>Update roles and permissions</li>
                    <li>Deactivate/reactivate staff accounts</li>
                    <li>View staff activity and performance</li>
                    <li>Reset staff passwords</li>
                </ul>
                <div class="warning-box">
                    <strong>Important:</strong> Only assign appropriate roles and permissions to staff members based on their responsibilities.
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">3. Inventory Management</div>
        <div class="subsection">
            <div class="subsection-title">Product Management</div>
            <div class="content">
                <p>As a manager, you can:</p>
                <ul>
                    <li>View all products in inventory</li>
                    <li>Add new products</li>
                    <li>Edit product information</li>
                    <li>Update product prices</li>
                    <li>Manage stock quantities</li>
                    <li>Set low stock alerts</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Adding Products</div>
            <div class="content">
                <p>To add a new product:</p>
                <ol>
                    <li>Go to Product Management</li>
                    <li>Click <strong>Add New Product</strong></li>
                    <li>Enter product details (name, description, SKU)</li>
                    <li>Select category, subcategory, and brand</li>
                    <li>Set pricing information</li>
                    <li>Enter initial stock quantity</li>
                    <li>Add product specifications</li>
                    <li>Upload product images (if applicable)</li>
                    <li>Save the product</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Stock Management</div>
            <div class="content">
                <p>Monitor and manage inventory:</p>
                <ul>
                    <li>Track stock levels in real-time</li>
                    <li>Receive low stock notifications</li>
                    <li>Update quantities manually</li>
                    <li>Process restock orders</li>
                    <li>View stock movement history</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">4. Categories, Subcategories, and Brands</div>
        <div class="subsection">
            <div class="subsection-title">Category Management</div>
            <div class="content">
                <p>To manage categories:</p>
                <ol>
                    <li>Navigate to <strong>Categories</strong> from the sidebar</li>
                    <li>View all product categories</li>
                    <li>Add new categories</li>
                    <li>Edit existing categories</li>
                    <li>Organize category hierarchy</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Subcategory Management</div>
            <div class="content">
                <p>To manage subcategories:</p>
                <ol>
                    <li>Go to <strong>Subcategories</strong></li>
                    <li>Create subcategories under main categories</li>
                    <li>Edit subcategory details</li>
                    <li>Assign products to subcategories</li>
                </ol>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Brand Management</div>
            <div class="content">
                <p>To manage brands:</p>
                <ol>
                    <li>Navigate to <strong>Brands</strong></li>
                    <li>Add new brands</li>
                    <li>Edit brand information</li>
                    <li>Associate products with brands</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">5. Reports and Analytics</div>
        <div class="subsection">
            <div class="subsection-title">Generating Reports</div>
            <div class="content">
                <p>Access comprehensive reports:</p>
                <ul>
                    <li>Sales reports (daily, weekly, monthly, custom range)</li>
                    <li>Inventory reports</li>
                    <li>Staff performance reports</li>
                    <li>Product performance reports</li>
                    <li>Financial summaries</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">Exporting Reports</div>
            <div class="content">
                <p>You can export reports in various formats:</p>
                <ul>
                    <li>PDF format for printing</li>
                    <li>Excel format for analysis</li>
                    <li>CSV format for data processing</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">6. System Settings</div>
        <div class="subsection">
            <div class="subsection-title">Company Settings</div>
            <div class="content">
                <p>Manage company-wide settings:</p>
                <ul>
                    <li>Company information and details</li>
                    <li>Business hours and contact information</li>
                    <li>Tax settings and rates</li>
                    <li>Currency and payment settings</li>
                    <li>Notification preferences</li>
                </ul>
            </div>
        </div>
        <div class="subsection">
            <div class="subsection-title">SMS Settings</div>
            <div class="content">
                <p>Configure SMS functionality:</p>
                <ul>
                    <li>Set up SMS account</li>
                    <li>Purchase SMS credits</li>
                    <li>Configure SMS templates</li>
                    <li>View SMS logs and usage</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">7. Best Practices</div>
        <div class="content">
            <ul>
                <li><strong>Regular monitoring:</strong> Check dashboard and reports regularly</li>
                <li><strong>Staff oversight:</strong> Review staff performance and provide feedback</li>
                <li><strong>Inventory control:</strong> Maintain optimal stock levels</li>
                <li><strong>Data accuracy:</strong> Ensure all information is accurate and up-to-date</li>
                <li><strong>Security:</strong> Protect system access and user credentials</li>
                <li><strong>Backup:</strong> Ensure regular data backups are performed</li>
                <li><strong>Training:</strong> Keep staff trained on system usage</li>
            </ul>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">8. Quick Reference</div>
        <div class="content">
            <table>
                <tr>
                    <th>Action</th>
                    <th>Location</th>
                </tr>
                <tr>
                    <td>View Dashboard</td>
                    <td>Sidebar → Dashboard</td>
                </tr>
                <tr>
                    <td>Manage Staff</td>
                    <td>Sidebar → Staff Management</td>
                </tr>
                <tr>
                    <td>Manage Products</td>
                    <td>Sidebar → Product Management</td>
                </tr>
                <tr>
                    <td>Manage Categories</td>
                    <td>Sidebar → Categories</td>
                </tr>
                <tr>
                    <td>Manage Brands</td>
                    <td>Sidebar → Brands</td>
                </tr>
                <tr>
                    <td>View Reports</td>
                    <td>Sidebar → Reports</td>
                </tr>
                <tr>
                    <td>System Settings</td>
                    <td>Sidebar → Settings</td>
                </tr>
                <tr>
                    <td>SMS Settings</td>
                    <td>Sidebar → Settings → SMS Settings</td>
                </tr>
                <tr>
                    <td>Access Profile</td>
                    <td>Top Right → Profile Icon</td>
                </tr>
            </table>
        </div>
    </div>
';
    }
}
