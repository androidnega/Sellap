<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\WebAuthMiddleware;
use App\Models\Repair;
use App\Models\RepairAccessory;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\CompanyModule;
use App\Services\NotificationService;
use Exception;
use PDO;

class RepairController {
    private $repair;
    private $repairAccessory;
    private $product;
    private $customer;
    private $notification;
    private $notificationService;

    public function __construct() {
        $this->repair = new Repair();
        $this->repairAccessory = new RepairAccessory();
        $this->product = new Product();
        $this->customer = new Customer();
        $this->notification = new Notification();
        $this->notificationService = new NotificationService();
    }

    /**
     * Check if module is enabled (safeguard)
     */
    private function checkModuleEnabled($companyId, $moduleKey, $userRole) {
        // System admins bypass module checks
        if ($userRole === 'system_admin') {
            return true;
        }
        
        if (!$companyId) {
            return false;
        }
        
        return CompanyModule::isEnabled($companyId, $moduleKey);
    }
    
    /**
     * Display repair list for managers/technicians
     */
    public function index() {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'technician', 'system_admin']);
        
        // Get user data from session
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
        $userRole = $userData['role'] ?? 'salesperson';
        
        // Check if Repairs module is enabled (safeguard)
        // Note: We allow access even if module is disabled to prevent redirects
        // Module access should be controlled at the route level via ModuleAccessMiddleware if needed
        
        $status = $_GET['status'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $search = trim($_GET['search'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; // Items per page
        
        // Both technicians and managers see all company repairs for consistency
        // Technicians can still identify their assigned repairs via technician_id field
        $repairs = $this->repair->findByCompanyPaginated($companyId, $page, $limit, $status, $dateFrom, $dateTo, $search);
        $totalRepairs = $this->repair->getCountByCompany($companyId, $status, $dateFrom, $dateTo, $search);
        $totalPages = $limit > 0 ? max(1, ceil($totalRepairs / $limit)) : 1;
        
        // Ensure page doesn't exceed total pages
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
            $repairs = $this->repair->findByCompanyPaginated($companyId, $page, $limit, $status, $dateFrom, $dateTo, $search);
        }
        
        // Calculate stats for manager dashboard (only for managers)
        $totalWorkmanshipFee = 0;
        $totalTechnicianSales = 0;
        $technicianSalesCount = 0;
        
        if ($userRole === 'manager' || $userRole === 'system_admin') {
            try {
                $db = \Database::getInstance()->getConnection();
                
                // Get all repairs for the company (not filtered by status, but filtered by date if provided)
                $allRepairs = $this->repair->findByCompany($companyId, 1000, null, $dateFrom, $dateTo);
                
                // Calculate total workmanship fee (sum of repair_cost)
                foreach ($allRepairs as $repair) {
                    $totalWorkmanshipFee += floatval($repair['repair_cost'] ?? 0);
                    $totalTechnicianSales += floatval($repair['accessory_cost'] ?? 0);
                }
                
                // Get number of sales by technician (count of repairs with accessories)
                $dateCondition = '';
                $dateParams = [$companyId];
                
                if ($dateFrom && $dateTo) {
                    $dateCondition = " AND DATE(r.created_at) >= ? AND DATE(r.created_at) <= ?";
                    $dateParams[] = $dateFrom;
                    $dateParams[] = $dateTo;
                } elseif ($dateFrom) {
                    $dateCondition = " AND DATE(r.created_at) >= ?";
                    $dateParams[] = $dateFrom;
                } elseif ($dateTo) {
                    $dateCondition = " AND DATE(r.created_at) <= ?";
                    $dateParams[] = $dateTo;
                }
                
                $salesCountQuery = $db->prepare("
                    SELECT COUNT(DISTINCT r.id) as sales_count
                    FROM repairs_new r
                    WHERE r.company_id = ? 
                    AND (r.accessory_cost > 0 OR EXISTS (
                        SELECT 1 FROM repair_accessories ra 
                        WHERE ra.repair_id = r.id
                    ))
                    {$dateCondition}
                ");
                $salesCountQuery->execute($dateParams);
                $salesCountResult = $salesCountQuery->fetch(PDO::FETCH_ASSOC);
                $technicianSalesCount = (int)($salesCountResult['sales_count'] ?? 0);
                
            } catch (Exception $e) {
                error_log("Error calculating repair stats: " . $e->getMessage());
            }
        }
        
        // Pass filters and pagination to view
        $GLOBALS['date_from'] = $dateFrom;
        $GLOBALS['date_to'] = $dateTo;
        $GLOBALS['search'] = $search;
        $GLOBALS['current_page'] = $page;
        $GLOBALS['total_pages'] = $totalPages;
        $GLOBALS['total_repairs'] = $totalRepairs;
        $GLOBALS['limit'] = $limit;
        
        $title = 'Repairs';
        
        // Pass user role and stats to view for conditional display
        $GLOBALS['user_role'] = $userRole;
        $GLOBALS['total_workmanship_fee'] = $totalWorkmanshipFee;
        $GLOBALS['total_technician_sales'] = $totalTechnicianSales;
        $GLOBALS['technician_sales_count'] = $technicianSalesCount;
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/repairs_index.php';
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
     * Show create repair form - Uses booking form instead of repair form
     */
    public function create() {
        // Use WebAuthMiddleware for session-based authentication
        // Managers cannot create repairs, only technicians can
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userRole = $userData['role'] ?? 'salesperson';
        
        // For technicians, redirect to technician booking page
        if ($userRole === 'technician') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/booking');
            exit;
        }
        
        // For managers/admins, use the booking form (same as technician booking)
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
        }
        
        // Get products from Accessories category for repair parts
        $partsAndAccessories = [];
        if ($accessoriesCategoryId) {
            $accessoriesProducts = $this->product->findByCompany($companyId, 1000, $accessoriesCategoryId);
            foreach ($accessoriesProducts as $product) {
                // Only include products with stock > 0
                $quantity = (int)($product['quantity'] ?? 0);
                if ($quantity > 0) {
                    $partsAndAccessories[] = $product;
                }
            }
        }
        
        // Get brands for device details
        $brandModel = new \App\Models\Brand();
        $brands = [];
        if ($phoneCategoryId) {
            $brands = $brandModel->getByCategory($phoneCategoryId);
        }
        
        $title = 'New Repair Booking';
        
        // Pass data to view via globals (same as TechnicianController)
        $GLOBALS['partsAndAccessories'] = $partsAndAccessories;
        $GLOBALS['products'] = $products;
        $GLOBALS['brands'] = $brands;
        
        // Use the technician booking form (same form for all users)
        ob_start();
        include __DIR__ . '/../Views/technician_booking.php';
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
     * Show repair details
     */
    public function show($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        error_log("============================================");
        error_log("RepairController::show() - Displaying repair ID: {$id}");
        error_log("============================================");
        
        $repair = $this->repair->find($id, $companyId);
        if (!$repair) {
            error_log("RepairController::show() - ERROR: Repair not found!");
            $_SESSION['flash_error'] = 'Repair not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs');
            exit;
        }
        
        error_log("RepairController::show() - DATA PASSED TO VIEW:");
        error_log("  - issue_description: " . var_export($repair['issue_description'] ?? 'NOT SET', true) . " (length: " . strlen($repair['issue_description'] ?? '') . ")");
        error_log("  - repair_cost: " . var_export($repair['repair_cost'] ?? 'NOT SET', true));
        error_log("  - customer_name: " . var_export($repair['customer_name'] ?? 'NOT SET', true));
        error_log("  - customer_contact: " . var_export($repair['customer_contact'] ?? 'NOT SET', true));
        error_log("  - parts_cost: " . var_export($repair['parts_cost'] ?? 'NOT SET', true));
        error_log("  - total_cost: " . var_export($repair['total_cost'] ?? 'NOT SET', true));
        error_log("============================================");
        
        $accessories = $this->repairAccessory->getByRepair($id);
        
        // Get related in-progress repairs (same company, exclude current repair)
        $inProgressRepairs = $this->repair->findByCompany($companyId, 10, 'in_progress');
        $inProgressRepairs = array_filter($inProgressRepairs, function($r) use ($id) {
            return $r['id'] != $id;
        });
        $inProgressRepairs = array_values($inProgressRepairs); // Re-index array
        
        // Calculate stats for in-progress repairs
        $inProgressStats = [
            'total' => count($inProgressRepairs),
            'total_revenue' => array_sum(array_column($inProgressRepairs, 'total_cost'))
        ];
        
        $title = 'Repair Details';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/repairs_show.php';
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
     * Store new repair (POST) with enhanced accessory handling and stock deduction
     */
    public function store() {
        // Use WebAuthMiddleware for session-based authentication
        // Managers cannot create repairs, only technicians can
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        // Get user data from session
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
        $userRole = $userData['role'] ?? 'technician';
        
        // Check if Repairs module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'repairs', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'repairs',
                'message' => 'The Repairs module is not enabled for your company. Please contact your administrator.'
            ]);
            exit;
        }
        
        // ============================================
        // COMPREHENSIVE LOGGING - START
        // ============================================
        error_log("============================================");
        error_log("REPAIR STORE - START");
        error_log("============================================");
        error_log("RepairController::store() - REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("RepairController::store() - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
        error_log("RepairController::store() - Full POST data: " . json_encode($_POST, JSON_PRETTY_PRINT));
        error_log("RepairController::store() - POST keys: " . implode(', ', array_keys($_POST)));
        error_log("RepairController::store() - Raw input: " . file_get_contents('php://input'));
        
        // Validate required fields - CRITICAL: Check if data exists in $_POST
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_contact = trim($_POST['customer_contact'] ?? '');
        $issue_description = trim($_POST['issue_description'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $device_brand = trim($_POST['device_brand'] ?? '');
        $device_model = trim($_POST['device_model'] ?? '');
        
        // CRITICAL: Check if required fields are present in $_POST
        if (!isset($_POST['customer_name'])) {
            error_log("RepairController::store() - CRITICAL: customer_name key NOT in \$_POST!");
            $_SESSION['flash_error'] = 'Form submission error: Customer name field was not received. Please refresh and try again.';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        if (!isset($_POST['customer_contact'])) {
            error_log("RepairController::store() - CRITICAL: customer_contact key NOT in \$_POST!");
            $_SESSION['flash_error'] = 'Form submission error: Customer contact field was not received. Please refresh and try again.';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        if (!isset($_POST['issue_description'])) {
            error_log("RepairController::store() - CRITICAL: issue_description key NOT in \$_POST!");
            $_SESSION['flash_error'] = 'Form submission error: Issue description field was not received. Please refresh and try again.';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }

        // Debug logging - log ALL POST data
        error_log("RepairController::store() - EXTRACTED VALUES:");
        error_log("  - customer_name: " . var_export($customer_name, true) . " (length: " . strlen($customer_name) . ")");
        error_log("  - customer_contact: " . var_export($customer_contact, true) . " (length: " . strlen($customer_contact) . ")");
        error_log("  - issue_description: " . var_export($issue_description, true) . " (length: " . strlen($issue_description) . ")");
        error_log("  - product_id: " . var_export($product_id, true));
        error_log("  - customer_id: " . var_export($customer_id, true));
        error_log("  - device_brand: " . var_export($device_brand, true));
        error_log("  - device_model: " . var_export($device_model, true));
        error_log("  - repair_cost (raw POST): " . var_export($_POST['repair_cost'] ?? 'NOT SET', true));
        error_log("  - parts_cost (raw POST): " . var_export($_POST['parts_cost'] ?? 'NOT SET', true));
        error_log("  - total_cost (raw POST): " . var_export($_POST['total_cost'] ?? 'NOT SET', true));

        if (!$customer_name || !$customer_contact || !$issue_description) {
            $_SESSION['flash_error'] = 'Customer name, contact, and issue description are required';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }

        // Calculate base costs
        // IMPORTANT: Get repair_cost from POST before processing accessories
        // This ensures repair_cost is preserved even when parts are selected
        $repair_cost_raw = $_POST['repair_cost'] ?? '';
        $repair_cost = floatval($repair_cost_raw);
        $parts_cost_raw = $_POST['parts_cost'] ?? '';
        $parts_cost = floatval($parts_cost_raw);
        $accessory_cost = 0;
        
        // Debug logging - log raw POST values
        error_log("RepairController::store() - COST CALCULATION:");
        error_log("  - repair_cost (raw string): " . var_export($repair_cost_raw, true));
        error_log("  - repair_cost (floatval): " . var_export($repair_cost, true));
        error_log("  - parts_cost (raw string): " . var_export($parts_cost_raw, true));
        error_log("  - parts_cost (floatval): " . var_export($parts_cost, true));

        // Process accessories and calculate total accessory cost
        $accessories = [];
        if (isset($_POST['accessories']) && is_array($_POST['accessories'])) {
            foreach ($_POST['accessories'] as $accessory) {
                if (!empty($accessory['product_id']) && !empty($accessory['quantity']) && !empty($accessory['price'])) {
                    $productId = intval($accessory['product_id']);
                    $quantity = intval($accessory['quantity']);
                    $price = floatval($accessory['price']);
                    
                    // Verify product exists and has sufficient stock
                    $product = $this->product->find($productId, $companyId);
                    if (!$product || !is_array($product)) {
                        $_SESSION['flash_error'] = 'One or more selected accessories are not available';
                        $redirectUrl = $userRole === 'technician' 
                            ? BASE_URL_PATH . '/dashboard/booking'
                            : BASE_URL_PATH . '/dashboard/repairs/create';
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                    
                    if ($product['quantity'] < $quantity) {
                        $_SESSION['flash_error'] = "Insufficient stock for {$product['name']}. Available: {$product['quantity']}, Requested: {$quantity}";
                        $redirectUrl = $userRole === 'technician' 
                            ? BASE_URL_PATH . '/dashboard/booking'
                            : BASE_URL_PATH . '/dashboard/repairs/create';
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                    
                    $accessories[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'product_name' => $product['name']
                    ];
                    
                    $accessory_cost += $quantity * $price;
                }
            }
            
            // IMPORTANT: Set parts_cost to match accessory_cost when accessories are selected
            // This ensures the repair record correctly reflects the parts used in the booking
            $parts_cost = $accessory_cost;
        }

        // Calculate total: repair_cost + parts_cost (parts_cost already includes accessory_cost when accessories are selected)
        // Note: When accessories are selected, parts_cost = accessory_cost, so we only count once
        $total_cost = $repair_cost + $parts_cost;
        
        // IMPORTANT: If repair_cost is 0 but total_cost is greater than parts_cost, 
        // it means repair_cost was likely lost during form submission
        // Calculate it from total_cost - parts_cost to preserve the user's intended repair cost
        if ($repair_cost == 0 && $total_cost > $parts_cost) {
            $calculated_repair_cost = $total_cost - $parts_cost;
            if ($calculated_repair_cost > 0) {
                error_log("RepairController::store() - WARNING: repair_cost was 0 but total_cost suggests repair_cost should be {$calculated_repair_cost}. Recalculating...");
                $repair_cost = $calculated_repair_cost;
                // Recalculate total with corrected repair_cost
                $total_cost = $repair_cost + $parts_cost;
            }
        }
        
        // Final validation: Ensure repair_cost is not negative
        if ($repair_cost < 0) {
            $repair_cost = 0;
            error_log("RepairController::store() - WARNING: repair_cost was negative, setting to 0");
        }
        
        // Debug logging after all calculations
        error_log("RepairController::store() - Final costs before save: repair_cost=" . $repair_cost . ", parts_cost=" . $parts_cost . ", total_cost=" . $total_cost);

        // Validate technician_id exists in users table - REQUIRED (NOT NULL constraint)
        $db = \Database::getInstance()->getConnection();
        $technicianId = null;
        
        if ($userRole === 'technician') {
            // If user is a technician, validate their ID exists in users table
            // Cast to ensure proper type matching (users.id is BIGINT UNSIGNED)
            $userId = intval($userId);
            $checkUser = $db->prepare("SELECT CAST(id AS UNSIGNED) as id FROM users WHERE id = ? AND company_id = ?");
            $checkUser->execute([$userId, $companyId]);
            $userResult = $checkUser->fetch(PDO::FETCH_ASSOC);
            if ($userResult && isset($userResult['id'])) {
                $technicianId = intval($userResult['id']);
            } else {
                // User ID doesn't exist - find any technician in the company as fallback
                $fallbackTech = $db->prepare("SELECT CAST(id AS UNSIGNED) as id FROM users WHERE company_id = ? AND role = 'technician' LIMIT 1");
                $fallbackTech->execute([$companyId]);
                $techResult = $fallbackTech->fetch(PDO::FETCH_ASSOC);
                if ($techResult && isset($techResult['id'])) {
                    $technicianId = intval($techResult['id']);
                } else {
                    // No technicians found - this is a critical error
                    $_SESSION['flash_error'] = 'No valid technician found. Please ensure at least one technician exists in your company.';
                    $redirectUrl = $userRole === 'technician' 
                        ? BASE_URL_PATH . '/dashboard/booking'
                        : BASE_URL_PATH . '/dashboard/repairs/create';
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }
        } else {
            // For non-technicians, check if a technician was explicitly selected
            $selectedTechnicianId = intval($_POST['technician_id'] ?? 0);
            if ($selectedTechnicianId > 0) {
                $checkTech = $db->prepare("SELECT CAST(id AS UNSIGNED) as id FROM users WHERE id = ? AND company_id = ? AND role = 'technician'");
                $checkTech->execute([$selectedTechnicianId, $companyId]);
                $techResult = $checkTech->fetch(PDO::FETCH_ASSOC);
                if ($techResult && isset($techResult['id'])) {
                    $technicianId = intval($techResult['id']);
                } else {
                    // Invalid technician ID - find any technician as fallback
                    $fallbackTech = $db->prepare("SELECT CAST(id AS UNSIGNED) as id FROM users WHERE company_id = ? AND role = 'technician' LIMIT 1");
                    $fallbackTech->execute([$companyId]);
                    $techResult = $fallbackTech->fetch(PDO::FETCH_ASSOC);
                    if ($techResult && isset($techResult['id'])) {
                        $technicianId = intval($techResult['id']);
                    } else {
                        $_SESSION['flash_error'] = 'No valid technician found. Please select a technician or ensure at least one technician exists in your company.';
                        header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/create');
                        exit;
                    }
                }
            } else {
                // No technician selected - find any technician in the company
                $fallbackTech = $db->prepare("SELECT CAST(id AS UNSIGNED) as id FROM users WHERE company_id = ? AND role = 'technician' LIMIT 1");
                $fallbackTech->execute([$companyId]);
                $techResult = $fallbackTech->fetch(PDO::FETCH_ASSOC);
                if ($techResult && isset($techResult['id'])) {
                    $technicianId = intval($techResult['id']);
                } else {
                    $_SESSION['flash_error'] = 'No technician selected and no technicians found in your company. Please select a technician or create one first.';
                    header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/create');
                    exit;
                }
            }
        }
        
        // Final validation - ensure technician_id is set and verify it exists one more time
        if (!$technicianId || $technicianId <= 0) {
            $_SESSION['flash_error'] = 'Unable to assign a technician. Please contact your administrator.';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        // Double-check the technician exists before proceeding
        $finalCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
        $finalCheck->execute([$technicianId]);
        if ($finalCheck->rowCount() === 0) {
            error_log("RepairController: Technician ID {$technicianId} does not exist in users table");
            $_SESSION['flash_error'] = 'Selected technician does not exist. Please select a valid technician.';
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        // Log for debugging
        error_log("RepairController: Using technician_id: {$technicianId} for company_id: {$companyId}");

        // Start database transaction for atomicity (reuse existing $db connection)
        $db->beginTransaction();
        
        try {
            // Create repair record - automatically mark as paid when booked
            // Ensure technician_id is explicitly cast as integer
            $repairData = [
                'company_id' => $companyId,
                'technician_id' => (int)$technicianId, // Explicitly cast to int to ensure type matching
                'product_id' => $product_id ?: null,
                'device_brand' => $device_brand ?: null,
                'device_model' => $device_model ?: null,
                'customer_name' => $customer_name,
                'customer_contact' => $customer_contact,
                'customer_id' => $customer_id ?: null,
                'issue_description' => $issue_description,
                'repair_cost' => $repair_cost,
                'parts_cost' => $parts_cost,
                'accessory_cost' => $accessory_cost,
                'total_cost' => $total_cost,
                'status' => 'pending', // Start as pending
                'payment_status' => 'paid', // Automatically paid when booked
                'notes' => trim($_POST['notes'] ?? '')
            ];
            
            // Debug logging before create
            error_log("============================================");
            error_log("BEFORE CALLING Repair::create()");
            error_log("============================================");
            error_log("RepairController::store() - Data array being passed to Repair::create():");
            error_log(json_encode($repairData, JSON_PRETTY_PRINT));
            error_log("RepairController::store() - KEY VALUES CHECK:");
            error_log("  - customer_name: " . var_export($repairData['customer_name'] ?? 'NOT IN ARRAY', true) . " (length: " . strlen($repairData['customer_name'] ?? '') . ")");
            error_log("  - customer_contact: " . var_export($repairData['customer_contact'] ?? 'NOT IN ARRAY', true) . " (length: " . strlen($repairData['customer_contact'] ?? '') . ")");
            error_log("  - issue_description: " . var_export($repairData['issue_description'] ?? 'NOT IN ARRAY', true) . " (length: " . strlen($repairData['issue_description'] ?? '') . ")");
            error_log("  - technician_id: " . var_export($repairData['technician_id'] ?? 'NOT IN ARRAY', true));
            error_log("  - company_id: " . var_export($repairData['company_id'] ?? 'NOT IN ARRAY', true));
            
            // CRITICAL: Final validation before passing to Repair::create()
            if (empty($repairData['customer_name'])) {
                error_log("RepairController::store() - CRITICAL ERROR: customer_name is EMPTY in repairData array!");
                $_SESSION['flash_error'] = 'Customer name is required. Please ensure the form field is filled correctly.';
                $redirectUrl = $userRole === 'technician' 
                    ? BASE_URL_PATH . '/dashboard/booking'
                    : BASE_URL_PATH . '/dashboard/repairs/create';
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            if (empty($repairData['customer_contact'])) {
                error_log("RepairController::store() - CRITICAL ERROR: customer_contact is EMPTY in repairData array!");
                $_SESSION['flash_error'] = 'Customer contact is required. Please ensure the form field is filled correctly.';
                $redirectUrl = $userRole === 'technician' 
                    ? BASE_URL_PATH . '/dashboard/booking'
                    : BASE_URL_PATH . '/dashboard/repairs/create';
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            if (empty($repairData['issue_description'])) {
                error_log("RepairController::store() - CRITICAL ERROR: issue_description is EMPTY in repairData array!");
                $_SESSION['flash_error'] = 'Issue description is required. Please ensure the form field is filled correctly.';
                $redirectUrl = $userRole === 'technician' 
                    ? BASE_URL_PATH . '/dashboard/booking'
                    : BASE_URL_PATH . '/dashboard/repairs/create';
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            error_log("RepairController::store() - Additional values:");
            error_log("  - repair_cost: " . var_export($repairData['repair_cost'] ?? 'NOT IN ARRAY', true));
            error_log("  - parts_cost: " . var_export($repairData['parts_cost'] ?? 'NOT IN ARRAY', true));
            error_log("  - total_cost: " . var_export($repairData['total_cost'] ?? 'NOT IN ARRAY', true));
            
            $repairId = $this->repair->create($repairData);
            
            // Debug logging after create
            error_log("============================================");
            error_log("AFTER CALLING Repair::create()");
            error_log("============================================");
            error_log("RepairController::store() - Repair created with ID: " . $repairId . " (type: " . gettype($repairId) . ")");
            
            // Validate repair ID - must be a positive integer
            if (!$repairId || $repairId <= 0) {
                throw new Exception('Failed to create repair: Invalid repair ID returned. ID: ' . var_export($repairId, true));
            }
            
            // Ensure repair_id is an integer (for foreign key constraint)
            $repairId = (int)$repairId;
            
            // Verify the repair exists in the database before proceeding
            $savedRepair = $this->repair->find($repairId, $companyId);
            if (!$savedRepair) {
                throw new Exception('Failed to create repair: Repair was created but could not be retrieved from database. ID: ' . $repairId);
            }
            
            error_log("RepairController::store() - VERIFYING SAVED DATA:");
            error_log("RepairController::store() - Saved repair data from database:");
            error_log("  - id: " . var_export($savedRepair['id'] ?? 'NOT SET', true));
            error_log("  - issue_description: " . var_export($savedRepair['issue_description'] ?? 'NOT SET', true) . " (length: " . strlen($savedRepair['issue_description'] ?? '') . ")");
            error_log("  - repair_cost: " . var_export($savedRepair['repair_cost'] ?? 'NOT SET', true));
            error_log("  - customer_name: " . var_export($savedRepair['customer_name'] ?? 'NOT SET', true));
            error_log("  - customer_contact: " . var_export($savedRepair['customer_contact'] ?? 'NOT SET', true));
            error_log("  - parts_cost: " . var_export($savedRepair['parts_cost'] ?? 'NOT SET', true));
            error_log("  - total_cost: " . var_export($savedRepair['total_cost'] ?? 'NOT SET', true));
            error_log("  - product_id: " . var_export($savedRepair['product_id'] ?? 'NOT SET', true));
            error_log("  - device_brand: " . var_export($savedRepair['device_brand'] ?? 'NOT SET', true));
            error_log("  - device_model: " . var_export($savedRepair['device_model'] ?? 'NOT SET', true));
            error_log("============================================");
            error_log("REPAIR STORE - END");
            error_log("============================================");

            // Process accessories/parts and create POS sales
            $saleItems = [];
            $totalPartsProfit = 0; // Track total profit from repair parts only
            
            if (!empty($accessories)) {
                // Create a unified POS sale for all products used in repair
                $posSaleModel = new \App\Models\POSSale();
                $posSaleItemModel = new \App\Models\POSSaleItem();
                
                $saleTotal = $accessory_cost;
                
                // Check if partial payments module is enabled
                $partialPaymentsEnabled = CompanyModule::isEnabled($companyId, 'partial_payments');
                
                // Create POS sale record with repair indication
                $saleId = $posSaleModel->create([
                    'company_id' => $companyId,
                    'customer_id' => $customer_id ?: null,
                    'total_amount' => $saleTotal,
                    'discount' => 0,
                    'tax' => 0,
                    'final_amount' => $saleTotal,
                    'payment_method' => 'CASH',
                    'payment_status' => 'PAID',
                    'created_by_user_id' => $userId,
                    'notes' => "Repair #{$repairId} - Products sold by repairer"
                ]);
                
                // Create payment record for the sale (repairs are paid at booking time)
                // This ensures the sale shows as PAID in partial payments page
                if ($partialPaymentsEnabled) {
                    try {
                        $salePaymentModel = new \App\Models\SalePayment();
                        $salePaymentModel->create([
                            'pos_sale_id' => $saleId,
                            'company_id' => $companyId,
                            'amount' => $saleTotal,
                            'payment_method' => 'CASH',
                            'recorded_by_user_id' => $userId,
                            'notes' => "Repair #{$repairId} - Payment received at booking"
                        ]);
                        error_log("RepairController: Created payment record for POS sale #{$saleId} (repair #{$repairId})");
                    } catch (Exception $paymentError) {
                        error_log("RepairController: Error creating payment record for repair sale (non-fatal): " . $paymentError->getMessage());
                        // Don't fail the repair if payment record creation fails
                    }
                }
                
                // Create sale items and process accessories
                foreach ($accessories as $accessory) {
                    // Get product cost for profit calculation
                    $product = $this->product->find($accessory['product_id'], $companyId);
                    // Prioritize cost_price over cost (cost_price is the correct column)
                    $productCost = floatval($product['cost_price'] ?? $product['cost'] ?? 0);
                    $sellingPrice = floatval($accessory['price']);
                    $quantity = intval($accessory['quantity']);
                    
                    // Calculate profit for this part: (selling price - cost) * quantity
                    $partProfit = ($sellingPrice - $productCost) * $quantity;
                    $totalPartsProfit += $partProfit;
                    
                    // Create repair accessory record - ensure repair_id is integer
                    $accessoryRepairId = (int)$repairId;
                    if ($accessoryRepairId <= 0) {
                        throw new Exception('Invalid repair ID for accessory: ' . $repairId);
                    }
                    
                    $this->repairAccessory->create([
                        'repair_id' => $accessoryRepairId,
                        'product_id' => (int)$accessory['product_id'],
                        'quantity' => $quantity,
                        'price' => $sellingPrice
                    ]);
                    
                    // Create POS sale item
                    $saleItemId = $posSaleItemModel->create([
                        'pos_sale_id' => $saleId,
                        'item_type' => 'PART',
                        'item_id' => $accessory['product_id'],
                        'item_description' => $accessory['product_name'],
                        'quantity' => $quantity,
                        'unit_price' => $sellingPrice,
                        'total_price' => $quantity * $sellingPrice
                    ]);
                    
                    // Deduct stock quantity
                    $this->product->updateQuantity($accessory['product_id'], $companyId, -$quantity);
                    
                    // Log audit trail for part sold by repairer
                    try {
                        \App\Services\AuditService::log(
                            $companyId,
                            $userId,
                            'repair.part_sold',
                            'pos_sale_item',
                            $saleItemId,
                            [
                                'repair_id' => $repairId,
                                'product_id' => $accessory['product_id'],
                                'product_name' => $accessory['product_name'],
                                'quantity' => $quantity,
                                'unit_price' => $sellingPrice,
                                'total_price' => $quantity * $sellingPrice,
                                'cost_price' => $productCost,
                                'profit' => $partProfit,
                                'sold_by' => 'repairer'
                            ]
                        );
                    } catch (Exception $auditError) {
                        error_log("Audit logging error for part sale (non-fatal): " . $auditError->getMessage());
                    }
                }
                
                // Store parts profit in repair notes (for tracking)
                $existingNotes = trim($_POST['notes'] ?? '');
                $profitNote = "Parts Profit: ₵" . number_format($totalPartsProfit, 2);
                $newNotes = $existingNotes ? $existingNotes . " | " . $profitNote : $profitNote;
                
                // Update repair notes with profit info
                $this->repair->update($repairId, ['notes' => $newNotes], $companyId);
            }

            // Send notifications
            $this->sendRepairNotifications($repairId, $companyId, $userId, $accessories);

            // Commit transaction
            $db->commit();
            
            // Log audit event
            try {
                \App\Services\AuditService::log(
                    $companyId,
                    $userId,
                    'repair.created',
                    'repair',
                    $repairId,
                    [
                        'total_cost' => $total_cost,
                        'repair_cost' => $repair_cost,
                        'parts_cost' => $parts_cost,
                        'accessory_cost' => $accessory_cost,
                        'status' => 'completed',
                        'customer_id' => $customer_id,
                        'accessories_count' => count($accessories)
                    ]
                );
            } catch (Exception $auditError) {
                error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
            }
            
            $successMsg = 'Repair booking created successfully! Payment received at booking.';
            if (!empty($accessories)) {
                $successMsg .= ' Products used have been recorded as sales and stock updated.';
            }
            $_SESSION['flash_success'] = $successMsg;
            
            // Redirect based on user role
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/technician/repairs'
                : BASE_URL_PATH . '/dashboard/repairs';
            header('Location: ' . $redirectUrl);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            $_SESSION['flash_error'] = 'Failed to create repair: ' . $e->getMessage();
            
            // Redirect based on user role to prevent redirect loops
            $redirectUrl = $userRole === 'technician' 
                ? BASE_URL_PATH . '/dashboard/booking'
                : BASE_URL_PATH . '/dashboard/repairs/create';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Update repair status
     */
    public function updateStatus($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userRole = $userData['role'] ?? '';
        
        $status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'delivered', 'cancelled', 'failed'])) {
            $_SESSION['flash_error'] = 'Invalid status';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
            exit;
        }
        
        // Prevent managers from starting repairs (changing status to in_progress)
        if ($userRole === 'manager' && $status === 'in_progress') {
            $_SESSION['flash_error'] = 'Managers cannot start repairs. Only technicians can start repairs.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
            exit;
        }
        
        // Get repair details before updating (needed for refund calculation)
        $repairBeforeUpdate = $this->repair->find($id, $companyId);
        
        $success = $this->repair->updateStatus($id, $companyId, $status, $notes);
        
        if ($success) {
            // Handle refund for failed repairs
            if ($status === 'failed' && $repairBeforeUpdate) {
                $repairCost = floatval($repairBeforeUpdate['repair_cost'] ?? 0);
                $partsCost = floatval($repairBeforeUpdate['parts_cost'] ?? 0);
                $accessoryCost = floatval($repairBeforeUpdate['accessory_cost'] ?? 0);
                
                // Refund only workmanship (repair_cost), not parts or accessories which are already sold
                $refundAmount = $repairCost;
                
                if ($refundAmount > 0) {
                    // Add refund information to notes
                    $refundNote = "\n\n[REFUND PROCESSED] Workmanship refund: ₵" . number_format($refundAmount, 2) . 
                                  " (Parts: ₵" . number_format($partsCost, 2) . 
                                  ", Accessories: ₵" . number_format($accessoryCost, 2) . " - Not refundable as already sold)";
                    
                    $updatedNotes = ($notes ? $notes . $refundNote : $refundNote);
                    $this->repair->updateStatus($id, $companyId, $status, $updatedNotes);
                    
                    // Log refund in audit trail
                    try {
                        $userId = $userData['id'] ?? null;
                        \App\Services\AuditService::log(
                            $companyId,
                            $userId,
                            'repair.refund_processed',
                            'repair',
                            $id,
                            [
                                'refund_amount' => $refundAmount,
                                'repair_cost' => $repairCost,
                                'parts_cost' => $partsCost,
                                'accessory_cost' => $accessoryCost,
                                'reason' => 'Repair failed - workmanship refund'
                            ]
                        );
                    } catch (Exception $auditError) {
                        error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
                    }
                }
            }
            
            // Log audit event
            try {
                $userId = $userData['id'] ?? null;
                \App\Services\AuditService::log(
                    $companyId,
                    $userId,
                    'repair.status_changed',
                    'repair',
                    $id,
                    [
                        'new_status' => $status,
                        'notes' => $notes
                    ]
                );
            } catch (Exception $auditError) {
                error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
            }
            
            // Send automatic SMS notifications based on status
            $repair = $this->repair->find($id, $companyId);
            if ($repair && !empty($repair['customer_contact'])) {
                $phoneNumber = $repair['customer_contact'];
                $deviceName = $repair['product_name'] ?? 'Device';
                $trackingCode = $repair['tracking_code'] ?? 'N/A';
                
                // Different messages for different statuses
                if ($status === 'in_progress') {
                    // Repair started notification
                    $repairData = [
                        'repair_id' => $trackingCode,
                        'device' => $deviceName,
                        'status' => 'In Progress',
                        'completion_date' => 'TBD',
                        'phone_number' => $phoneNumber,
                        'company_id' => $companyId,
                        'message_type' => 'repair_started'
                    ];
                    $this->notificationService->sendRepairStatusUpdate($repairData);
                } elseif ($status === 'completed') {
                    // Ready for delivery notification
                    $repairData = [
                        'repair_id' => $trackingCode,
                        'device' => $deviceName,
                        'status' => 'Completed - Ready for Pickup',
                        'completion_date' => date('Y-m-d'),
                        'phone_number' => $phoneNumber,
                        'company_id' => $companyId,
                        'message_type' => 'repair_ready'
                    ];
                    $this->notificationService->sendRepairStatusUpdate($repairData);
                } elseif ($status === 'failed') {
                    // Repair failed notification with refund information
                    $repairCost = floatval($repair['repair_cost'] ?? 0);
                    $refundMessage = '';
                    if ($repairCost > 0) {
                        $refundMessage = " A workmanship refund of ₵" . number_format($repairCost, 2) . " will be processed.";
                    }
                    $repairData = [
                        'repair_id' => $trackingCode,
                        'device' => $deviceName,
                        'status' => 'Failed',
                        'completion_date' => 'N/A',
                        'phone_number' => $phoneNumber,
                        'company_id' => $companyId,
                        'message_type' => 'repair_failed',
                        'refund_amount' => $repairCost,
                        'refund_note' => $refundMessage
                    ];
                    $this->notificationService->sendRepairStatusUpdate($repairData);
                } else {
                    // Generic status update for other statuses
                    $repairData = [
                        'repair_id' => $trackingCode,
                        'device' => $deviceName,
                        'status' => ucfirst(str_replace('_', ' ', $status)),
                        'completion_date' => $status === 'delivered' ? date('Y-m-d') : 'TBD',
                        'phone_number' => $phoneNumber,
                        'company_id' => $companyId
                    ];
                    $this->notificationService->sendRepairStatusUpdate($repairData);
                }
            }
            
            // Send manager notifications for status changes
            $this->sendRepairStatusChangeNotification($id, $companyId, $status, $repair);
            
            $_SESSION['flash_success'] = 'Repair status updated successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to update repair status';
        }
        
        // Redirect based on user role
        $userRole = $userData['role'] ?? 'technician';
        if ($userRole === 'technician') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
        } else {
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
        }
        exit;
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'salesperson', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        $payment_status = $_POST['payment_status'] ?? '';
        
        if (!in_array($payment_status, ['unpaid', 'partial', 'paid'])) {
            $_SESSION['flash_error'] = 'Invalid payment status';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
            exit;
        }
        
        $success = $this->repair->updatePaymentStatus($id, $companyId, $payment_status);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Payment status updated successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to update payment status';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
        exit;
    }

    /**
     * API endpoint: Get repairs for technician
     */
    public function apiList() {
        $payload = AuthMiddleware::handle(['manager', 'technician']);
        $companyId = $payload->company_id;
        $userId = $payload->sub;
        $userRole = $payload->role;
        
        $status = $_GET['status'] ?? null;
        
        if ($userRole === 'technician') {
            $repairs = $this->repair->findByTechnician($userId, $companyId, $status);
        } else {
            $repairs = $this->repair->findByCompany($companyId, 100, $status);
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $repairs
        ]);
        exit;
    }

    /**
     * API endpoint: Get repair details
     */
    public function apiShow($id) {
        $payload = AuthMiddleware::handle(['manager', 'technician']);
        $companyId = $payload->company_id;
        
        $repair = $this->repair->find($id, $companyId);
        if (!$repair) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Repair not found'
            ]);
            exit;
        }
        
        $accessories = $this->repairAccessory->getByRepair($id);
        $repair['accessories'] = $accessories;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $repair
        ]);
        exit;
    }

    /**
     * API endpoint: Get repair statistics
     */
    public function apiStats() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        $userRole = $payload->role ?? 'manager';
        
        // Check if Repairs module is enabled (safeguard)
        if (!$this->checkModuleEnabled($companyId, 'repairs', $userRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module Disabled for this company',
                'module' => 'repairs'
            ]);
            exit;
        }
        
        $stats = $this->repair->getStats($companyId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }

    /**
     * API endpoint: Find repair by tracking code
     */
    public function apiFindByTrackingCode($tracking_code) {
        $payload = AuthMiddleware::handle(['manager', 'technician']);
        $companyId = $payload->company_id;
        
        $repair = $this->repair->findByTrackingCode($tracking_code, $companyId);
        if (!$repair) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Repair not found'
            ]);
            exit;
        }
        
        $accessories = $this->repairAccessory->getByRepair($repair['id']);
        $repair['accessories'] = $accessories;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $repair
        ]);
        exit;
    }

    /**
     * API endpoint: Search repairs with pagination (for live search)
     */
    public function apiSearch() {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized'
            ]);
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Company ID not found'
            ]);
            exit;
        }
        
        $status = $_GET['status'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $search = trim($_GET['search'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        
        try {
            $repairs = $this->repair->findByCompanyPaginated($companyId, $page, $limit, $status, $dateFrom, $dateTo, $search);
            $totalRepairs = $this->repair->getCountByCompany($companyId, $status, $dateFrom, $dateTo, $search);
            $totalPages = $limit > 0 ? max(1, ceil($totalRepairs / $limit)) : 1;
            
            // Clean output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'repairs' => $repairs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalRepairs,
                    'items_per_page' => $limit
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Exception $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Generate repair receipt (printable)
     */
    public function receipt($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userRole = $userData['role'] ?? '';
        
        $repair = $this->repair->find($id, $companyId);
        if (!$repair) {
            $_SESSION['flash_error'] = 'Repair not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs');
            exit;
        }
        
        $accessories = $this->repairAccessory->getByRepair($id);
        
        // Use POS receipt format for technicians
        if ($userRole === 'technician') {
            $this->generatePOSReceipt($repair, $accessories, $companyId);
            return;
        }
        
        // Use normal receipt format for managers/admins
        $title = 'Repair Receipt';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/repairs_receipt.php';
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
     * Generate POS-style receipt for repair
     */
    private function generatePOSReceipt($repair, $accessories, $companyId) {
        // Get company information
        $companyModel = new \App\Models\Company();
        $company = $companyModel->find($companyId);
        $companyName = $company['name'] ?? "Repair Service";
        $companyAddress = $company['address'] ?? "123 Business Street, City, Country";
        $companyPhone = $company['phone'] ?? "+233 XX XXX XXXX";
        
        // Calculate costs
        $repairCost = floatval($repair['repair_cost'] ?? 0);
        $partsCost = floatval($repair['parts_cost'] ?? 0);
        $accessoryCost = floatval($repair['accessory_cost'] ?? 0);
        $totalCost = floatval($repair['total_cost'] ?? 0);
        
        // If total_cost is 0, calculate it
        if ($totalCost == 0) {
            $totalCost = $repairCost + $partsCost + $accessoryCost;
        }
        
        // Build items list
        $items = [];
        
        // Add repair cost as an item
        if ($repairCost > 0) {
            $items[] = [
                'item_description' => 'Repair Service',
                'quantity' => 1,
                'unit_price' => $repairCost,
                'total_price' => $repairCost
            ];
        }
        
        // Add accessories/parts as items
        foreach ($accessories as $accessory) {
            $items[] = [
                'item_description' => $accessory['product_name'] ?? 'Repair Part',
                'quantity' => intval($accessory['quantity'] ?? 1),
                'unit_price' => floatval($accessory['price'] ?? 0),
                'total_price' => floatval($accessory['quantity'] ?? 1) * floatval($accessory['price'] ?? 0)
            ];
        }
        
        // If no items but we have parts_cost, add it as a single item
        if (empty($items) && ($partsCost > 0 || $accessoryCost > 0)) {
            $items[] = [
                'item_description' => 'Repair Parts',
                'quantity' => 1,
                'unit_price' => $partsCost + $accessoryCost,
                'total_price' => $partsCost + $accessoryCost
            ];
        }
        
        $receiptDate = date('Y-m-d H:i:s', strtotime($repair['created_at']));
        $customerName = $repair['customer_name'] ?? $repair['customer_name_from_table'] ?? 'Walk-in Customer';
        $technicianName = $repair['technician_name'] ?? $_SESSION['user']['username'] ?? 'Technician';
        
        // Set headers for printing
        header('Content-Type: text/html; charset=utf-8');
        
        // Generate receipt HTML
        $receipt = $this->generateRepairPOSReceiptHTML($repair, $items, $companyName, $companyAddress, $companyPhone, $receiptDate, $customerName, $technicianName, $totalCost);
        
        echo $receipt;
        exit;
    }
    
    /**
     * Generate POS receipt HTML for repair
     */
    private function generateRepairPOSReceiptHTML($repair, $items, $companyName, $companyAddress, $companyPhone, $receiptDate, $customerName, $technicianName, $totalCost) {
        $itemsHTML = '';
        foreach ($items as $item) {
            $itemsHTML .= "
                <div class='item'>
                    <div>
                        <div class='item-name'>{$item['item_description']}</div>
                        <div class='item-details'>{$item['quantity']} × ₵" . number_format($item['unit_price'], 2) . "</div>
                    </div>
                    <div>₵" . number_format($item['total_price'], 2) . "</div>
                </div>";
        }
        
        $paymentStatus = strtoupper($repair['payment_status'] ?? 'PAID');
        $trackingCode = !empty($repair['tracking_code']) ? "<div><span>Tracking Code:</span><span>{$repair['tracking_code']}</span></div>" : "";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Repair Receipt #{$repair['id']}</title>
            <style>
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    line-height: 1.4;
                    margin: 0;
                    padding: 20px;
                    background: white;
                }
                .receipt {
                    max-width: 300px;
                    margin: 0 auto;
                    border: 1px solid #ccc;
                    padding: 15px;
                }
                .header {
                    text-align: center;
                    border-bottom: 1px dashed #333;
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
                .company-name {
                    font-size: 16px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .company-details {
                    font-size: 10px;
                    color: #666;
                }
                .sale-info {
                    margin-bottom: 15px;
                }
                .sale-info div {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .items {
                    border-bottom: 1px dashed #333;
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
                .item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                }
                .item-name {
                    flex: 1;
                }
                .item-details {
                    font-size: 10px;
                    color: #666;
                    margin-left: 10px;
                }
                .totals {
                    margin-bottom: 15px;
                }
                .totals div {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .total {
                    font-weight: bold;
                    border-top: 1px solid #333;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .footer {
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px dashed #333;
                    padding-top: 10px;
                }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .receipt { border: none; max-width: none; }
                }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <div class='company-name'>{$companyName}</div>
                    <div class='company-details'>
                        {$companyAddress}<br>
                        Tel: {$companyPhone}
                    </div>
                </div>
                
                <div class='sale-info'>
                    <div><span>Repair #:</span><span>{$repair['id']}</span></div>
                    <div><span>Date:</span><span>{$receiptDate}</span></div>
                    <div><span>Technician:</span><span>{$technicianName}</span></div>
                    <div><span>Customer:</span><span>{$customerName}</span></div>
                    {$trackingCode}
                    <div><span>Status:</span><span>" . strtoupper(str_replace('_', ' ', $repair['status'])) . "</span></div>
                </div>
                
                <div class='items'>
                    <div style='font-weight: bold; margin-bottom: 8px;'>ITEMS:</div>
                    {$itemsHTML}
                </div>
                
                <div class='totals'>
                    <div class='total'><span>TOTAL:</span><span>₵" . number_format($totalCost, 2) . "</span></div>
                </div>
                
                <div class='footer'>
                    <div>Payment Status: {$paymentStatus}</div>
                    <div>Thank you for your business!</div>
                    <div>Visit us again soon</div>
                </div>
            </div>
            
            <script>
                // Auto print when page loads
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>";
    }

    /**
     * Get technician performance data
     */
    public function technicianPerformance() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $period = $_GET['period'] ?? 'today'; // today, week, month
        $stats = $this->repair->getTechnicianPerformance($companyId, $period);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }

    /**
     * Get repair summary reports
     */
    public function reports() {
        // Use WebAuthMiddleware for session-based authentication
        // Managers cannot access reports, only technicians can
        WebAuthMiddleware::handle(['technician', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
        $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
        
        $reports = $this->repair->getSummaryReports($companyId, $startDate, $endDate);
        
        $title = 'Repair Reports';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/repairs_reports.php';
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
     * Approve repair (Manager only)
     */
    public function approve($id) {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        $managerId = $payload->sub;
        
        $success = $this->repair->approve($id, $companyId, $managerId);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Repair approved successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to approve repair';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/repairs/' . $id);
        exit;
    }

    /**
     * Delete repair (Manager only)
     */
    public function delete($id) {
        // Use WebAuthMiddleware for session-based authentication
        WebAuthMiddleware::handle(['manager', 'system_admin']);
        
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            $_SESSION['flash_error'] = 'Unauthorized access';
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        $companyId = $userData['company_id'] ?? null;
        $userRole = $userData['role'] ?? '';
        
        // Only managers and system admins can delete repairs
        if ($userRole !== 'manager' && $userRole !== 'system_admin') {
            $_SESSION['flash_error'] = 'Only managers can delete repairs';
            header('Location: ' . BASE_URL_PATH . '/dashboard/repairs');
            exit;
        }
        
        try {
            $success = $this->repair->delete($id, $companyId);
            
            if ($success) {
                $_SESSION['flash_success'] = 'Repair deleted successfully';
            } else {
                $_SESSION['flash_error'] = 'Failed to delete repair or repair not found';
            }
        } catch (Exception $e) {
            error_log("RepairController::delete - Error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error deleting repair: ' . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/repairs');
        exit;
    }

    /**
     * Send repair notifications
     */
    private function sendRepairNotifications($repairId, $companyId, $technicianId, $accessories) {
        try {
            // Get manager IDs for this company
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE company_id = ? AND role = 'manager' AND is_active = 1
            ");
            $stmt->execute([$companyId]);
            $managers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Notify managers about new repair booking
            foreach ($managers as $managerId) {
                $this->notification->create([
                    'user_id' => $managerId,
                    'company_id' => $companyId,
                    'message' => "New repair #{$repairId} has been booked by technician",
                    'type' => 'repair',
                    'data' => ['repair_id' => $repairId, 'technician_id' => $technicianId, 'status' => 'booked']
                ]);
            }

            // Check for low stock notifications
            foreach ($accessories as $accessory) {
                $product = $this->product->find($accessory['product_id'], $companyId);
                if ($product && $product['quantity'] <= 5) {
                    foreach ($managers as $managerId) {
                        $this->notification->create([
                            'user_id' => $managerId,
                            'company_id' => $companyId,
                            'message' => "Low stock alert: {$product['name']} has only {$product['quantity']} units remaining",
                            'type' => 'stock',
                            'data' => ['product_id' => $accessory['product_id'], 'quantity' => $product['quantity']]
                        ]);
                    }
                }
            }

        } catch (Exception $e) {
            // Don't fail the repair if notifications fail
            error_log("Notification error: " . $e->getMessage());
        }
    }

    /**
     * Send repair status change notifications to managers
     */
    private function sendRepairStatusChangeNotification($repairId, $companyId, $status, $repair) {
        try {
            // Get manager IDs for this company
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE company_id = ? AND role = 'manager' AND is_active = 1
            ");
            $stmt->execute([$companyId]);
            $managers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get technician info if available
            $technicianId = $repair['technician_id'] ?? null;
            $technicianName = '';
            if ($technicianId) {
                $techStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $techStmt->execute([$technicianId]);
                $techData = $techStmt->fetch(PDO::FETCH_ASSOC);
                $technicianName = $techData ? $techData['username'] : '';
            }

            // Create status-specific messages
            $statusMessages = [
                'in_progress' => "Repair #{$repairId} is now in progress",
                'completed' => "Repair #{$repairId} has been completed by technician" . ($technicianName ? " ({$technicianName})" : ''),
                'failed' => "Repair #{$repairId} has failed",
                'delivered' => "Repair #{$repairId} has been delivered",
                'cancelled' => "Repair #{$repairId} has been cancelled",
                'pending' => "Repair #{$repairId} status changed to pending"
            ];

            $message = $statusMessages[$status] ?? "Repair #{$repairId} status changed to " . ucfirst(str_replace('_', ' ', $status));

            // Notify managers about status change
            foreach ($managers as $managerId) {
                $this->notification->create([
                    'user_id' => $managerId,
                    'company_id' => $companyId,
                    'message' => $message,
                    'type' => 'repair',
                    'data' => [
                        'repair_id' => $repairId,
                        'technician_id' => $technicianId,
                        'status' => $status
                    ]
                ]);
            }

        } catch (Exception $e) {
            // Don't fail the repair if notifications fail
            error_log("Repair status notification error: " . $e->getMessage());
        }
    }
}