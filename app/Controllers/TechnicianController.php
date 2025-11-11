<?php
namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Models\Repair;
use App\Models\Customer;
use App\Models\Product;

class TechnicianController {
    private $repair;
    private $customer;
    private $product;

    public function __construct() {
        $this->repair = new Repair();
        $this->customer = new Customer();
        $this->product = new Product();
    }

    /**
     * Technician Dashboard - Main page
     */
    public function dashboard() {
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userId = $userData['id'] ?? null;
        
        // Get technician's repairs
        $pendingRepairs = $this->repair->findByTechnician($userId, $companyId, 'pending');
        $inProgressRepairs = $this->repair->findByTechnician($userId, $companyId, 'in_progress');
        $completedRepairs = $this->repair->findByTechnician($userId, $companyId, 'completed');
        
        // Get stats
        $allRepairs = $this->repair->findByTechnician($userId, $companyId, null);
        $totalRepairs = count($allRepairs);
        $totalRevenue = array_sum(array_column($allRepairs, 'total_cost'));
        $completedCount = count(array_filter($allRepairs, fn($r) => $r['status'] === 'completed'));
        
        $title = 'Technician Dashboard';
        
        ob_start();
        include __DIR__ . '/../Views/technician_dashboard.php';
        $content = ob_get_clean();
        
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * Booking/Create Repair Page
     */
    public function booking() {
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        // Get all products for device selection (including phones)
        $allProducts = $this->product->findByCompany($companyId, 1000);
        
        // Get Repair Parts category ID
        $categoryModel = new \App\Models\Category();
        $categories = $categoryModel->getAll();
        $repairPartsCategoryId = null;
        $phoneCategoryId = null;
        
        foreach ($categories as $cat) {
            $catName = strtolower($cat['name']);
            if ($catName === 'repair parts' || $catName === 'repair part' || $catName === 'repairparts') {
                $repairPartsCategoryId = $cat['id'];
            }
            if ($catName === 'phone') {
                $phoneCategoryId = $cat['id'];
            }
        }
        
        // Filter to only show Repair Parts category products
        $partsAndAccessories = [];
        if ($repairPartsCategoryId) {
            // Get products specifically from Repair Parts category
            $repairPartsProducts = $this->product->findByCompany($companyId, 1000, $repairPartsCategoryId);
            
            // Debug logging
            error_log("TechnicianController: Found " . count($repairPartsProducts) . " products in Repair Parts category");
            
            foreach ($repairPartsProducts as $product) {
                // Only include products with stock > 0
                $quantity = (int)($product['quantity'] ?? 0);
                if ($quantity > 0) {
                    $partsAndAccessories[] = $product;
                    error_log("TechnicianController: Added product: " . ($product['name'] ?? 'Unknown') . " (Qty: {$quantity})");
                }
            }
            
            error_log("TechnicianController: Total repair parts with stock: " . count($partsAndAccessories));
        } else {
            // Fallback: if Repair Parts category doesn't exist, show nothing or log warning
            error_log("Warning: Repair Parts category not found. No repair parts available for technician.");
        }
        
        // Get all products for device selection (including phones)
        $products = $allProducts;
        
        // Get customers
        $customers = $this->customer->findByCompany($companyId, 1000);
        
        // Get brands for device details
        $brandModel = new \App\Models\Brand();
        $brands = [];
        if ($phoneCategoryId) {
            $brands = $brandModel->getByCategory($phoneCategoryId);
        }
        
        $title = 'New Repair Booking';
        
        // Pass additional data to view BEFORE including it
        $GLOBALS['partsAndAccessories'] = $partsAndAccessories;
        $GLOBALS['brands'] = $brands;
        
        // Debug logging
        error_log("TechnicianController::booking() - Passing " . count($partsAndAccessories) . " repair parts to view");
        
        ob_start();
        include __DIR__ . '/../Views/technician_booking.php';
        $content = ob_get_clean();
        
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    /**
     * My Repairs - List view
     */
    public function myRepairs() {
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userId = $userData['id'] ?? null;
        
        $status = $_GET['status'] ?? null;
        $repairs = $this->repair->findByTechnician($userId, $companyId, $status);
        
        $title = 'My Repairs';
        
        ob_start();
        include __DIR__ . '/../Views/technician_repairs.php';
        $content = ob_get_clean();
        
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }
}

