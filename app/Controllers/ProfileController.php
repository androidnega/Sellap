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
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    throw new \Exception("Field {$field} is required");
                }
            }
            
            // Validate email format
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Invalid email format");
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Update user profile
            $updateQuery = $db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $updateQuery->execute([
                $input['first_name'],
                $input['last_name'],
                $input['email'],
                $input['phone'] ?? null,
                $userId
            ]);
            
            if (!$result) {
                throw new \Exception("Failed to update profile");
            }
            
            // Update session data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user']['first_name'] = $input['first_name'];
            $_SESSION['user']['last_name'] = $input['last_name'];
            $_SESSION['user']['email'] = $input['email'];
            $_SESSION['user']['phone'] = $input['phone'] ?? null;
            
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
        } else {
            // Return default user structure if not found
            $user = [
                'id' => $userId,
                'first_name' => 'Unknown',
                'last_name' => 'User',
                'email' => 'unknown@example.com',
                'phone' => '',
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
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
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
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Account Information</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Role</label>
                                <p class="mt-1 text-sm text-gray-900 capitalize"><?= htmlspecialchars($userRole) ?></p>
                            </div>
                            
                            <?php if ($user['company_name']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Company</label>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($user['company_name']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Member Since</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?= date('F j, Y', strtotime($user['created_at'] ?? 'now')) ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?= date('F j, Y g:i A', strtotime($user['updated_at'] ?? 'now')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Profile form submission
        document.getElementById('profile-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('/sellapp/api/profile/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
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
                const response = await fetch('/sellapp/api/profile/change-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
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
                const response = await fetch('/sellapp/api/settings/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
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
        
        // Only managers and admins can access
        if (!in_array($userRole, ['manager', 'admin', 'system_admin'])) {
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
        
        // Only managers and admins can access
        if (!in_array($userRole, ['manager', 'admin', 'system_admin'])) {
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
}
