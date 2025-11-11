<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Company;

class UserWebController {
    private $userModel;
    private $companyModel;

    public function __construct() {
        $this->userModel = new User();
        $this->companyModel = new Company();
    }

    /**
     * Display all users (System Admin only)
     */
    public function index() {
        // Start session to check user role
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in and has system_admin role
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'system_admin') {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $page = 'users';
        $title = 'User Management';
        
        // Get all users with company information
        $users = $this->getAllUsersWithCompany();
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/users_index.php';
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
     * Show create user form
     */
    public function create() {
        $page = 'users';
        $title = 'Add New User';
        $companies = $this->companyModel->all();
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/users_form.php';
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
     * Store new user
     */
    public function store() {
        $data = [
            'company_id' => $_POST['company_id'] ?? null,
            'unique_id' => 'USR' . strtoupper(uniqid()),
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'phone_number' => $_POST['phone_number'] ?? null,
            'full_name' => $_POST['full_name'] ?? $_POST['username'],
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
            'role' => $_POST['role'] ?? 'salesperson',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $this->userModel->create($data);
        header("Location: " . BASE_URL_PATH . "/dashboard/users");
        exit;
    }

    /**
     * Show user details (view only)
     */
    public function view($id) {
        $user = $this->userModel->findById($id);
        
        if (!$user) {
            header("Location: " . BASE_URL_PATH . "/dashboard/users");
            exit;
        }
        
        // Get company information if user belongs to a company
        $company = null;
        if ($user['company_id']) {
            $company = $this->companyModel->findById($user['company_id']);
        }
        
        $page = 'users';
        $title = 'View User';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/users_view.php';
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
     * Show edit user form
     */
    public function edit($id) {
        $user = $this->userModel->findById($id);
        
        if (!$user) {
            header("Location: " . BASE_URL_PATH . "/dashboard/users");
            exit;
        }
        
        $companies = $this->companyModel->all();
        $page = 'users';
        $title = 'Edit User';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/users_form.php';
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
     * Update user
     */
    public function update($id) {
        $data = [
            'company_id' => $_POST['company_id'] ?? null,
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'phone_number' => $_POST['phone_number'] ?? null,
            'full_name' => $_POST['full_name'] ?? $_POST['username'],
            'role' => $_POST['role'] ?? 'salesperson',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Only update password if provided
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }
        
        $this->userModel->update($id, $data);
        header("Location: " . BASE_URL_PATH . "/dashboard/users");
        exit;
    }

    /**
     * Delete user
     */
    public function destroy($id) {
        $this->userModel->delete($id);
        header("Location: " . BASE_URL_PATH . "/dashboard/users");
        exit;
    }

    /**
     * Reset user password
     */
    public function resetPassword($id) {
        $user = $this->userModel->findById($id);
        
        if (!$user) {
            header("Location: " . BASE_URL_PATH . "/dashboard/users");
            exit;
        }
        
        // Generate new password
        $newPassword = 'temp' . rand(1000, 9999);
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $this->userModel->update($id, ['password' => $hashedPassword]);
        
        // In a real application, you might want to email this password
        // For now, we'll redirect with a success message
        header("Location: " . BASE_URL_PATH . "/dashboard/users?password_reset=1&new_password=" . urlencode($newPassword));
        exit;
    }

    /**
     * Get all users with company information
     */
    private function getAllUsersWithCompany() {
        $db = \Database::getInstance()->getConnection();
        $sql = "SELECT u.*, c.name as company_name 
                FROM users u 
                LEFT JOIN companies c ON u.company_id = c.id 
                ORDER BY u.created_at DESC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
