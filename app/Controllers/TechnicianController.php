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
        
        // Ensure user ID and company ID are valid integers
        $userId = (int)$userId;
        $companyId = (int)$companyId;
        
        // Debug logging
        error_log("TechnicianController::dashboard() - User ID: {$userId} (type: " . gettype($userId) . "), Company ID: {$companyId}");
        
        // Get technician's repairs
        $pendingRepairs = $this->repair->findByTechnician($userId, $companyId, 'pending');
        $inProgressRepairs = $this->repair->findByTechnician($userId, $companyId, 'in_progress');
        $completedRepairs = $this->repair->findByTechnician($userId, $companyId, 'completed');
        
        // Get all repairs for stats calculation
        $allRepairs = $this->repair->findByTechnician($userId, $companyId, null);
        $totalRepairs = count($allRepairs);
        
        // Debug logging - check all statuses
        error_log("TechnicianController::dashboard() - Total repairs found: {$totalRepairs}");
        if ($totalRepairs > 0) {
            error_log("TechnicianController::dashboard() - First repair sample: " . json_encode($allRepairs[0]));
            // Log all unique statuses found
            $statuses = array_map(function($r) {
                return $r['status'] ?? 'NULL';
            }, $allRepairs);
            $uniqueStatuses = array_unique($statuses);
            error_log("TechnicianController::dashboard() - All statuses found: " . json_encode($uniqueStatuses));
            error_log("TechnicianController::dashboard() - Status counts: " . json_encode(array_count_values($statuses)));
        }
        
        // Calculate completed count
        $completedCount = count(array_filter($allRepairs, function($r) {
            $status = strtolower(trim($r['status'] ?? ''));
            return $status === 'completed';
        }));
        
        // Calculate pending count
        $pendingCount = count(array_filter($allRepairs, function($r) {
            $status = strtolower(trim($r['status'] ?? ''));
            return $status === 'pending';
        }));
        
        // Calculate delivered count
        // Note: Status should already be normalized by findByTechnician, but we'll ensure it's lowercase
        $deliveredCount = 0;
        foreach ($allRepairs as $repair) {
            $status = strtolower(trim($repair['status'] ?? ''));
            if ($status === 'delivered') {
                $deliveredCount++;
            }
        }
        
        // Calculate repair cost (workmanship/labour) and parts cost
        $totalRepairCost = 0; // Workmanship/labour cost
        $totalPartsCost = 0;  // Parts + accessories cost
        $totalRevenue = 0;
        
        foreach ($allRepairs as $repair) {
            $repairCost = floatval($repair['repair_cost'] ?? 0);
            $partsCost = floatval($repair['parts_cost'] ?? 0);
            $accessoryCost = floatval($repair['accessory_cost'] ?? 0);
            $totalCost = floatval($repair['total_cost'] ?? 0);
            
            // Sum repair costs (workmanship)
            $totalRepairCost += $repairCost;
            
            // Sum parts cost - use parts_cost if set, otherwise accessory_cost
            // When accessories are selected, parts_cost is set to equal accessory_cost in RepairController,
            // so we use parts_cost as the primary source to avoid double counting
            $partsCostToAdd = 0;
            if ($partsCost > 0) {
                $partsCostToAdd = $partsCost;
            } else {
                // Only use accessory_cost if parts_cost is not set
                $partsCostToAdd = $accessoryCost;
            }
            $totalPartsCost += $partsCostToAdd;
            
            // Debug logging for each repair
            error_log("TechnicianController::dashboard() - Repair ID: {$repair['id']}, parts_cost: {$partsCost}, accessory_cost: {$accessoryCost}, using: {$partsCostToAdd}");
            
            // Sum total revenue
            $totalRevenue += $totalCost;
        }
        
        // Debug logging
        error_log("TechnicianController::dashboard() - Stats calculated:");
        error_log("  - Total Repair Cost: {$totalRepairCost}");
        error_log("  - Total Parts Cost: {$totalPartsCost}");
        error_log("  - Completed Count: {$completedCount}");
        error_log("  - Pending Count: {$pendingCount}");
        error_log("  - Delivered Count: {$deliveredCount}");
        error_log("  - Total Revenue: {$totalRevenue}");
        
        $title = 'Technician Dashboard';
        
        // Pass variables to view
        $GLOBALS['totalRepairCost'] = $totalRepairCost;
        $GLOBALS['totalPartsCost'] = $totalPartsCost;
        $GLOBALS['totalRevenue'] = $totalRevenue;
        $GLOBALS['completedCount'] = $completedCount;
        $GLOBALS['pendingCount'] = $pendingCount;
        $GLOBALS['deliveredCount'] = $deliveredCount;
        $GLOBALS['totalRepairs'] = $totalRepairs;
        
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
        WebAuthMiddleware::handle(['technician', 'manager', 'admin', 'system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        // Get category IDs
        $categoryModel = new \App\Models\Category();
        $categories = $categoryModel->getAll();
        $accessoriesCategoryId = null;
        $phoneCategoryId = null;
        
        foreach ($categories as $cat) {
            $catName = strtolower($cat['name']);
            if ($catName === 'accessories' || $catName === 'accessory') {
                $accessoriesCategoryId = $cat['id'];
            }
            if ($catName === 'phone') {
                $phoneCategoryId = $cat['id'];
            }
        }
        
        // Get products from Phone category only for device selection
        $products = [];
        if ($phoneCategoryId) {
            $products = $this->product->findByCompany($companyId, 1000, $phoneCategoryId);
            error_log("TechnicianController: Found " . count($products) . " products in Phone category for device source");
        } else {
            error_log("Warning: Phone category not found. No devices available for device source.");
        }
        
        // Get products from Accessories category for repair parts
        $partsAndAccessories = [];
        if ($accessoriesCategoryId) {
            // Get products specifically from Accessories category
            $accessoriesProducts = $this->product->findByCompany($companyId, 1000, $accessoriesCategoryId);
            
            // Debug logging
            error_log("TechnicianController: Found " . count($accessoriesProducts) . " products in Accessories category");
            
            foreach ($accessoriesProducts as $product) {
                // Only include products with stock > 0
                $quantity = (int)($product['quantity'] ?? 0);
                if ($quantity > 0) {
                    $partsAndAccessories[] = $product;
                    error_log("TechnicianController: Added product: " . ($product['name'] ?? 'Unknown') . " (Qty: {$quantity})");
                }
            }
            
            error_log("TechnicianController: Total accessories with stock: " . count($partsAndAccessories));
        } else {
            // Fallback: if Accessories category doesn't exist, show nothing or log warning
            error_log("Warning: Accessories category not found. No repair parts available for technician.");
        }
        
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
        $GLOBALS['products'] = $products; // Phone products for device source
        
        // Debug logging
        error_log("TechnicianController::booking() - Passing " . count($partsAndAccessories) . " accessories for repair parts");
        error_log("TechnicianController::booking() - Passing " . count($products) . " phone products for device source");
        
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
        
        // Ensure user ID and company ID are valid integers
        if (empty($userId) || empty($companyId)) {
            error_log("TechnicianController::myRepairs() - Missing user ID or company ID. User ID: " . ($userId ?? 'NULL') . ", Company ID: " . ($companyId ?? 'NULL'));
            $repairs = [];
            $userId = null; // Set to null for logging below
        } else {
            // Ensure IDs are integers
            $userId = (int)$userId;
            $companyId = (int)$companyId;
            
            // Debug logging
            error_log("TechnicianController::myRepairs() - User ID: {$userId} (type: " . gettype($userId) . "), Company ID: {$companyId}");
            
            $status = $_GET['status'] ?? null;
            $repairs = $this->repair->findByTechnician($userId, $companyId, $status);
            
            // Debug logging
            error_log("TechnicianController::myRepairs() - Found " . count($repairs) . " repairs for technician {$userId}");
            if (count($repairs) > 0) {
                error_log("TechnicianController::myRepairs() - First repair: " . json_encode($repairs[0]));
            }
        }
        
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

