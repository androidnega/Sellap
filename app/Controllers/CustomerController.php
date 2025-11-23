<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Middleware\AuthMiddleware;

class CustomerController {
    private $model;

    public function __construct() {
        $this->model = new Customer();
    }

    /**
     * Web interface for customer management
     */
    public function webIndex() {
        // Get company_id from session for multi-tenant isolation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $companyId = $_SESSION['user']['company_id'] ?? null;
        
        // If no company_id, user cannot access customers (multi-tenant isolation)
        if (!$companyId) {
            $_SESSION['error_message'] = 'Access denied: Company ID not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 10; // Ensure this is always 10, not 1
        $search = trim($_GET['search'] ?? '');
        $dateFilter = $_GET['date_filter'] ?? null;
        
        // Ensure empty strings are treated as null for proper filtering
        if ($search === '') $search = null;
        if ($dateFilter === '') $dateFilter = null;
        
        // Verify itemsPerPage is correct (safety check)
        if ($itemsPerPage < 1 || $itemsPerPage > 100) {
            $itemsPerPage = 10;
        }
        
        // Get customers with pagination, search and filters (FILTERED BY COMPANY)
        // Ensure companyId is always provided and is an integer
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $_SESSION['error_message'] = 'Access denied: Invalid Company ID';
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        
        $customers = $this->model->getPaginated($currentPage, $itemsPerPage, $search, $dateFilter, $companyId);
        $totalItems = $this->model->getTotalCount($search, $dateFilter, $companyId);
        
        // Add cache control headers to prevent stale data
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        
        // Calculate total pages and adjust current page if needed
        $totalPages = $itemsPerPage > 0 ? max(1, ceil($totalItems / $itemsPerPage)) : 1;
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
            // Re-fetch with corrected page
            $customers = $this->model->getPaginated($currentPage, $itemsPerPage, $search, $dateFilter, $companyId);
        }
        
        // CRITICAL FIX: Remove actual duplicate rows (same customer ID appearing twice)
        $seenIds = [];
        $uniqueCustomers = [];
        foreach ($customers as $customer) {
            $customerId = $customer['id'] ?? null;
            if ($customerId && !isset($seenIds[$customerId])) {
                $seenIds[$customerId] = true;
                $uniqueCustomers[] = $customer;
            }
        }
        $customers = $uniqueCustomers;
        
        // Detect duplicate customers by phone number (check ONLY within same company)
        $allDuplicatePhones = $this->detectDuplicatePhonesFromDatabase();
        foreach ($customers as &$customer) {
            $phone = $customer['phone_number'] ?? '';
            if (empty($phone)) {
                $customer['is_duplicate'] = false;
                $customer['duplicate_count'] = 1;
                $customer['duplicate_ids'] = [];
                continue;
            }
            
            // Normalize phone number (remove spaces, dashes, etc.) for comparison
            $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
            
            if (isset($allDuplicatePhones[$normalizedPhone])) {
                $customer['is_duplicate'] = $allDuplicatePhones[$normalizedPhone]['count'] > 1;
                $customer['duplicate_count'] = $allDuplicatePhones[$normalizedPhone]['count'];
                $customer['duplicate_ids'] = $allDuplicatePhones[$normalizedPhone]['ids'];
            } else {
                $customer['is_duplicate'] = false;
                $customer['duplicate_count'] = 1;
                $customer['duplicate_ids'] = [];
            }
        }
        
        // Build pagination URL with search and filter params
        $paginationBaseUrl = BASE_URL_PATH . '/dashboard/customers';
        $queryParams = [];
        if (!empty($search)) {
            $queryParams[] = 'search=' . urlencode($search);
        }
        if (!empty($dateFilter)) {
            $queryParams[] = 'date_filter=' . urlencode($dateFilter);
        }
        $paginationUrl = $paginationBaseUrl . (!empty($queryParams) ? '?' . implode('&', $queryParams) . '&' : '?') . 'page=';
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            $paginationUrl
        );
        
        $page = 'customers';
        $title = 'Customer Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/customers_index.php';
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
     * Detect duplicate customers by phone number from database
     * Checks across ALL customers in the database (not just current page)
     * Returns array of normalized phone numbers with duplicate counts
     */
    private function detectDuplicatePhonesFromDatabase() {
        try {
            // Get company_id from session for multi-tenant filtering
            $companyId = null;
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['user']['company_id'])) {
                $companyId = $_SESSION['user']['company_id'];
            }
            
            $db = \Database::getInstance()->getConnection();
            
            // Query all customers with phone numbers (filtered by company if not system_admin)
            $query = "SELECT id, phone_number FROM customers WHERE phone_number IS NOT NULL AND phone_number != ''";
            if ($companyId !== null) {
                $query .= " AND company_id = :company_id";
            }
            
            $stmt = $db->prepare($query);
            if ($companyId !== null) {
                $stmt->execute(['company_id' => $companyId]);
            } else {
                $stmt->execute();
            }
            
            $allCustomers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $phoneCounts = [];
            $phoneToIds = [];
            
            foreach ($allCustomers as $customer) {
                $phone = $customer['phone_number'] ?? '';
                if (empty($phone)) {
                    continue;
                }
                
                // Normalize phone number (remove spaces, dashes, parentheses, etc.)
                $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
                
                if (!isset($phoneCounts[$normalizedPhone])) {
                    $phoneCounts[$normalizedPhone] = 0;
                    $phoneToIds[$normalizedPhone] = [];
                }
                
                $phoneCounts[$normalizedPhone]++;
                $phoneToIds[$normalizedPhone][] = $customer['id'];
            }
            
            // Return only phones with duplicates (count > 1)
            $duplicates = [];
            foreach ($phoneCounts as $phone => $count) {
                if ($count > 1) {
                    $duplicates[$phone] = [
                        'count' => $count,
                        'ids' => $phoneToIds[$phone]
                    ];
                }
            }
            
            return $duplicates;
        } catch (\Exception $e) {
            error_log("Error detecting duplicate phones: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * API endpoint for quick search (autocomplete)
     */
    public function quickSearch() {
        header('Content-Type: application/json');
        
        $searchTerm = trim($_GET['q'] ?? '');
        
        if (empty($searchTerm)) {
            echo json_encode([
                'success' => true,
                'data' => []
            ]);
            return;
        }
        
        try {
            // Get company_id from session for multi-tenant isolation
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $companyId = $_SESSION['user']['company_id'] ?? null;
            
            // If no company_id, return empty results (multi-tenant isolation)
            if (!$companyId) {
                echo json_encode([
                    'success' => true,
                    'data' => []
                ]);
                return;
            }
            
            $customers = $this->model->quickSearch($searchTerm, 50, $companyId);
            echo json_encode([
                'success' => true,
                'data' => $customers
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
     * GET /api/customers - Get all customers
     */
    public function index() {
        $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
        $companyId = $payload->company_id;
        
        header('Content-Type: application/json');
        
        try {
            $customers = $this->model->findByCompany($companyId);
            echo json_encode([
                'success' => true,
                'data' => $customers,
                'count' => count($customers)
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
     * POST /api/customers - Create new customer
     * Salesperson and technician (repairer) can always create customers
     * Managers can create if manager_create_contact permission is enabled
     */
    public function store() {
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Try session-based authentication first (for POS page)
        $user = $_SESSION['user'] ?? null;
        $companyId = null;
        $userRole = '';
        $userId = null;
        
        if ($user) {
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? '';
            $userId = $user['id'] ?? null;
        } else {
            // Fallback to token-based authentication
            try {
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
                $userRole = $payload->role ?? '';
                $userId = $payload->user_id ?? $payload->sub ?? null;
            } catch (\Exception $e) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                return;
            }
        }
        
        if (!$companyId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Company ID not found'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if this is a walk-in customer (only phone number required)
        $isWalkIn = isset($data['is_walk_in']) && $data['is_walk_in'] === true;
        
        if ($isWalkIn) {
            // For walk-in customers, only phone number is required
            if (empty($data['phone_number'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Phone number is required for walk-in customers'
                ]);
                return;
            }
            // Set default name for walk-in customers
            $data['full_name'] = $data['full_name'] ?? 'Walk-in Customer';
        } else {
            // For regular customers, both name and phone are required
            if (empty($data['full_name']) || empty($data['phone_number'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Full name and phone number are required'
                ]);
                return;
            }
        }
        
        // Check if manager has permission to create contacts
        if ($userRole === 'manager') {
            if (!\App\Models\CompanyModule::isEnabled($companyId, 'manager_create_contact')) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Manager create contact permission not enabled'
                ]);
                return;
            }
        }
        
        // Check for duplicate phone number (REQUIRED - phone is a key)
        $phoneNumber = trim($data['phone_number'] ?? '');
        if (!empty($phoneNumber)) {
            // Normalize phone number for comparison (remove spaces, dashes, parentheses)
            $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);
            
            // Query all customers in company and check normalized phones
            $allCustomers = $this->model->allByCompany($companyId);
            foreach ($allCustomers as $customer) {
                $existingNormalized = preg_replace('/[\s\-\(\)]/', '', $customer['phone_number'] ?? '');
                if ($existingNormalized === $normalizedPhone && !empty($existingNormalized)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'A customer with this phone number already exists',
                        'existing_customer' => [
                            'id' => $customer['id'],
                            'unique_id' => $customer['unique_id'],
                            'full_name' => $customer['full_name'],
                            'phone_number' => $customer['phone_number']
                        ]
                    ]);
                    return;
                }
            }
        }
        
        // Check for duplicate email (OPTIONAL - only if email is provided)
        $email = trim($data['email'] ?? '');
        if (!empty($email)) {
            // Normalize email (lowercase, trim)
            $normalizedEmail = strtolower(trim($email));
            
            // Query all customers in company and check emails
            $allCustomers = $this->model->allByCompany($companyId);
            foreach ($allCustomers as $customer) {
                $existingEmail = strtolower(trim($customer['email'] ?? ''));
                if (!empty($existingEmail) && $existingEmail === $normalizedEmail) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'A customer with this email address already exists',
                        'existing_customer' => [
                            'id' => $customer['id'],
                            'unique_id' => $customer['unique_id'],
                            'full_name' => $customer['full_name'],
                            'email' => $customer['email']
                        ]
                    ]);
                    return;
                }
            }
        }

        // Ensure phone_number is properly trimmed and not modified
        $data['phone_number'] = trim($data['phone_number'] ?? '');
        $data['full_name'] = trim($data['full_name'] ?? '');
        $data['email'] = !empty($data['email']) ? trim($data['email']) : null;
        $data['address'] = !empty($data['address']) ? trim($data['address']) : null;
        
        $data['company_id'] = $companyId;
        $data['unique_id'] = 'CUS' . strtoupper(uniqid());
        
        // Validate and set created_by_user_id - ensure user exists
        if ($userId) {
            // Verify user exists
            $userModel = new \App\Models\User();
            $user = $userModel->findById($userId);
            if (!$user) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid user ID: User does not exist'
                ]);
                return;
            }
            $data['created_by_user_id'] = $userId;
        } else {
            // If no userId, try to get a valid user from the company
            $userModel = new \App\Models\User();
            $companyUsers = $userModel->allByCompany($companyId);
            if (!empty($companyUsers)) {
                $data['created_by_user_id'] = $companyUsers[0]['id'];
            } else {
                // Last resort: set to NULL (now allowed since column is nullable)
                $data['created_by_user_id'] = null;
            }
        }
        
        try {
            $result = $this->model->create($data);
            
            if ($result) {
                // Fetch the created customer to get full data including ID (with company isolation)
                $customer = $this->model->findByUniqueId($data['unique_id'], $companyId);
                
                if (!$customer) {
                    // If findByUniqueId failed, try to find by phone in company
                    $customer = $this->model->findByPhoneInCompany($data['phone_number'], $companyId);
                }
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer created successfully',
                    'data' => $customer ?: [
                        'unique_id' => $data['unique_id'],
                        'full_name' => $data['full_name'],
                        'phone_number' => $data['phone_number'],
                        'email' => $data['email'],
                        'company_id' => $companyId
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create customer'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
* GET /api/customers/{id} - Get single customer
     */
    public function show($id) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first
        $user = $_SESSION['user'] ?? null;
        $companyId = null;
        
        if ($user) {
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? '';
            
            // Check if user has required role
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied'
                ]);
                return;
            }
        } else {
            // Try token-based authentication as fallback
            try {
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            } catch (\Exception $e) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                return;
            }
        }
        
        header('Content-Type: application/json');
        
        try {
            // Always use company-scoped find method for multi-tenant isolation
            // Even system_admin should use company_id when available for better isolation
            if ($companyId) {
                $customer = $this->model->find($id, $companyId);
            } else {
                // Only allow findById for system_admin without company_id
                if ($userRole === 'system_admin') {
                    $customer = $this->model->findById($id);
                } else {
                    $customer = null; // Force not found for non-admin without company_id
                }
            }
            
            if (!$customer) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
                return;
            }
            
            // Additional security check: Ensure customer belongs to user's company (multi-tenant isolation)
            if ($customer['company_id'] != $companyId && $userRole !== 'system_admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied: Customer does not belong to your company'
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/customers/{id}/history - Get customer purchase history
     * Returns sales, repairs, and swaps for the customer
     */
    public function getPurchaseHistory($id) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first
        $user = $_SESSION['user'] ?? null;
        $companyId = null;
        
        if ($user) {
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? '';
            
            // Check if user has required role
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied'
                ]);
                return;
            }
        } else {
            // Try token-based authentication as fallback
            try {
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            } catch (\Exception $e) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                return;
            }
        }
        
        header('Content-Type: application/json');
        
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Verify customer exists and belongs to company (use company-scoped lookup)
            $customer = $this->model->find($id, $companyId);
            if (!$customer) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
                return;
            }
            
            // Get customer phone for matching swaps by contact
            $customerPhone = $customer['phone_number'] ?? '';
            $customerName = $customer['full_name'] ?? '';
            
            $history = [];
            
            // Get Sales History with item details
            try {
                $salesStmt = $db->prepare("
                    SELECT 
                        ps.id,
                        ps.unique_id,
                        ps.final_amount as amount,
                        ps.payment_method,
                        ps.created_at,
                        'sale' as type,
                        (SELECT COUNT(*) FROM pos_sale_items WHERE pos_sale_id = ps.id) as item_count,
                        GROUP_CONCAT(DISTINCT psi.item_description ORDER BY psi.id SEPARATOR ', ') as items
                    FROM pos_sales ps
                    LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
                    WHERE ps.customer_id = ? AND ps.company_id = ?
                    GROUP BY ps.id, ps.unique_id, ps.final_amount, ps.payment_method, ps.created_at
                    ORDER BY ps.created_at DESC
                    LIMIT 100
                ");
                $salesStmt->execute([$id, $companyId]);
                $sales = $salesStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($sales as $sale) {
                    $items = $sale['items'] ? explode(', ', $sale['items']) : [];
                    $itemsPreview = !empty($items) ? (count($items) > 2 ? implode(', ', array_slice($items, 0, 2)) . '...' : implode(', ', $items)) : 'Various items';
                    
                    $history[] = [
                        'id' => $sale['id'],
                        'type' => 'sale',
                        'type_label' => 'Direct Purchase',
                        'reference' => $sale['unique_id'],
                        'amount' => floatval($sale['amount']),
                        'payment_method' => $sale['payment_method'],
                        'item_count' => intval($sale['item_count']),
                        'items' => $items,
                        'items_preview' => $itemsPreview,
                        'timestamp' => $sale['created_at'],
                        'description' => $itemsPreview
                    ];
                }
            } catch (\Exception $e) {
                error_log("Error fetching sales history: " . $e->getMessage());
            }
            
            // Get Repairs History
            try {
                // Check which repairs table exists
                $repairsTable = 'repairs';
                $repairsCheckStmt = $db->query("SHOW TABLES LIKE 'repairs_new'");
                if ($repairsCheckStmt->rowCount() > 0) {
                    $repairsTable = 'repairs_new';
                }
                
                // Check for column variations
                $colStmt = $db->query("SHOW COLUMNS FROM {$repairsTable}");
                $columns = array_column($colStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $deviceCol = in_array('device_info', $columns) ? 'device_info' : 
                            (in_array('device_brand', $columns) ? 'device_brand' : 
                            (in_array('phone_description', $columns) ? 'phone_description' : null));
                $costCol = in_array('cost', $columns) ? 'cost' : 
                          (in_array('total_cost', $columns) ? 'total_cost' : 
                          (in_array('repair_cost', $columns) ? 'repair_cost' : '0'));
                $issueCol = in_array('issue_description', $columns) ? 'issue_description' : 
                           (in_array('issue', $columns) ? 'issue' : null);
                $hasCustomerName = in_array('customer_name', $columns);
                $hasCustomerContact = in_array('customer_contact', $columns);
                
                $deviceSelect = $deviceCol ? "COALESCE({$deviceCol}, 'Device')" : "'Device'";
                $costSelect = $costCol !== '0' ? "COALESCE({$costCol}, 0)" : "0";
                $issueSelect = $issueCol ? "COALESCE({$issueCol}, 'Repair service')" : "'Repair service'";
                
                // Build WHERE clause to match by customer_id OR customer_contact/phone
                $whereParams = [$companyId];
                $customerMatchConditions = [];
                
                // Match by customer_id if column exists
                if (in_array('customer_id', $columns)) {
                    $customerMatchConditions[] = "r.customer_id = ?";
                    $whereParams[] = $id;
                }
                
                // Also match by customer_contact/phone for repairs where customer_id might be NULL
                if ($customerPhone) {
                    if ($hasCustomerContact) {
                        $customerMatchConditions[] = "r.customer_contact = ?";
                        $whereParams[] = $customerPhone;
                    }
                }
                
                // Build WHERE clause: company_id must match AND (customer_id OR customer_contact matches)
                if (!empty($customerMatchConditions)) {
                    $whereClause = "r.company_id = ? AND (" . implode(" OR ", $customerMatchConditions) . ")";
                } else {
                    // Fallback if no customer matching columns exist
                    $whereClause = "r.company_id = ?";
                }
                
                // Build SELECT with customer_name and customer_contact
                $selectFields = [
                    "r.id",
                    "{$deviceSelect} as device_info",
                    "{$costSelect} as cost",
                    "r.status",
                    "{$issueSelect} as issue",
                    "r.created_at"
                ];
                
                if ($hasCustomerName) {
                    $selectFields[] = "r.customer_name";
                }
                if ($hasCustomerContact) {
                    $selectFields[] = "r.customer_contact";
                }
                
                $selectClause = implode(", ", $selectFields);
                
                $repairsStmt = $db->prepare("
                    SELECT 
                        {$selectClause}
                    FROM {$repairsTable} r
                    WHERE {$whereClause}
                    ORDER BY r.created_at DESC
                    LIMIT 100
                ");
                $repairsStmt->execute($whereParams);
                $repairs = $repairsStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($repairs as $repair) {
                    $deviceInfo = $repair['device_info'] ?? 'Device';
                    $issue = $repair['issue'] ?? 'Repair service';
                    
                    // Get customer name and contact from repair record if available
                    $repairCustomerName = $repair['customer_name'] ?? $customerName ?? 'Unknown Customer';
                    $repairCustomerContact = $repair['customer_contact'] ?? $customerPhone ?? 'No contact';
                    
                    $history[] = [
                        'id' => $repair['id'],
                        'type' => 'repair',
                        'type_label' => 'Repair Service',
                        'reference' => 'REP#' . $repair['id'],
                        'amount' => floatval($repair['cost'] ?? 0),
                        'device_info' => $deviceInfo,
                        'item_name' => $deviceInfo,
                        'issue' => $issue,
                        'status' => $repair['status'] ?? 'pending',
                        'timestamp' => $repair['created_at'],
                        'description' => $deviceInfo . ' - ' . $issue,
                        'customer_name' => $repairCustomerName,
                        'customer_contact' => $repairCustomerContact
                    ];
                }
            } catch (\Exception $e) {
                error_log("Error fetching repairs history: " . $e->getMessage());
            }
            
            // Get Swaps History with product details - match by customer_id OR phone/contact
            try {
                $swapColStmt = $db->query("SHOW COLUMNS FROM swaps");
                $swapColumns = array_column($swapColStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                
                $totalValueCol = in_array('total_value', $swapColumns) ? 'total_value' : 
                               (in_array('final_price', $swapColumns) ? 'final_price' : '0');
                $hasCompanyProductId = in_array('company_product_id', $swapColumns);
                $hasCustomerPhone = in_array('customer_phone', $swapColumns);
                $hasCustomerContact = in_array('customer_contact', $swapColumns);
                $hasCustomerName = in_array('customer_name', $swapColumns);
                $hasUniqueId = in_array('unique_id', $swapColumns);
                $hasTransactionCode = in_array('transaction_code', $swapColumns);
                
                $totalValueSelect = $totalValueCol !== '0' ? "COALESCE(s.{$totalValueCol}, 0)" : "0";
                
                // Build WHERE clause to match customer by ID or phone/contact
                $whereConditions = [];
                $whereParams = [];
                
                // Always filter by company
                $whereConditions[] = "s.company_id = ?";
                $whereParams[] = $companyId;
                
                // Build customer matching conditions
                $customerMatchConditions = [];
                
                // Match by customer_id if column exists
                if (in_array('customer_id', $swapColumns)) {
                    $customerMatchConditions[] = "s.customer_id = ?";
                    $whereParams[] = $id;
                }
                
                // Also match by phone number/contact for swaps where customer_id might be NULL
                if ($customerPhone) {
                    if ($hasCustomerPhone) {
                        $customerMatchConditions[] = "s.customer_phone = ?";
                        $whereParams[] = $customerPhone;
                    }
                    if ($hasCustomerContact) {
                        $customerMatchConditions[] = "s.customer_contact = ?";
                        $whereParams[] = $customerPhone;
                    }
                }
                
                // Combine customer matching conditions
                if (!empty($customerMatchConditions)) {
                    $whereConditions[] = "(" . implode(" OR ", $customerMatchConditions) . ")";
                }
                
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                
                // Try to get product name
                $productJoin = '';
                $productSelect = "NULL as product_name";
                if ($hasCompanyProductId) {
                    // Check which products table exists
                    $productsTableCheck = $db->query("SHOW TABLES LIKE 'products_new'");
                    if ($productsTableCheck->rowCount() > 0) {
                        $productJoin = "LEFT JOIN products_new p ON s.company_product_id = p.id";
                        $productSelect = "p.name as product_name";
                    } else {
                        $productsTableCheck2 = $db->query("SHOW TABLES LIKE 'products'");
                        if ($productsTableCheck2->rowCount() > 0) {
                            $productJoin = "LEFT JOIN products p ON s.company_product_id = p.id";
                            $productSelect = "p.name as product_name";
                        }
                    }
                }
                
                // Build reference/unique_id selection
                $referenceSelect = "NULL as reference_id";
                if ($hasUniqueId) {
                    $referenceSelect = "s.unique_id as reference_id";
                } elseif ($hasTransactionCode) {
                    $referenceSelect = "s.transaction_code as reference_id";
                }
                
                $swapsStmt = $db->prepare("
                    SELECT 
                        s.id,
                        {$totalValueSelect} as total_value,
                        s.status,
                        s.swap_status,
                        s.created_at,
                        {$referenceSelect},
                        {$productSelect}
                    FROM swaps s
                    {$productJoin}
                    {$whereClause}
                    ORDER BY s.created_at DESC
                    LIMIT 100
                ");
                $swapsStmt->execute($whereParams);
                $swaps = $swapsStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($swaps as $swap) {
                    $status = $swap['status'] ?? $swap['swap_status'] ?? 'pending';
                    $productName = $swap['product_name'] ?? 'Product';
                    $reference = $swap['reference_id'] ?? ($hasTransactionCode ? 'SWP#' . $swap['id'] : ($hasUniqueId ? 'SWP#' . $swap['id'] : 'SWP#' . $swap['id']));
                    
                    $history[] = [
                        'id' => $swap['id'],
                        'type' => 'swap',
                        'type_label' => 'Product Swap',
                        'reference' => $reference,
                        'amount' => floatval($swap['total_value'] ?? 0),
                        'item_name' => $productName,
                        'status' => $status,
                        'timestamp' => $swap['created_at'],
                        'description' => $productName . ' swap'
                    ];
                }
            } catch (\Exception $e) {
                error_log("Error fetching swaps history: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            
            // Sort all history by timestamp (newest first)
            usort($history, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            echo json_encode([
                'success' => true,
                'data' => $history,
                'count' => count($history)
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/customers/count - Get total customers count for company
     */
    public function getTotalCount() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first
        $user = $_SESSION['user'] ?? null;
        $companyId = null;
        
        if ($user) {
            $companyId = $user['company_id'] ?? null;
            $userRole = $user['role'] ?? '';
            
            // Check if user has required role
            $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
            if (!in_array($userRole, $allowedRoles)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Access denied'
                ]);
                return;
            }
        } else {
            // Try token-based authentication as fallback
            try {
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            } catch (\Exception $e) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                return;
            }
        }
        
        header('Content-Type: application/json');
        
        try {
            $count = $this->model->getTotalCount(null, null, $companyId);
            
            echo json_encode([
                'success' => true,
                'count' => (int)$count
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /api/customers/{id} - Update customer
     */
    public function update($id) {
        $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
        $companyId = $payload->company_id;
        
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['full_name']) || empty($data['phone_number'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Full name and phone number are required'
            ]);
            return;
        }
        
        try {
            // First check if customer exists and belongs to the company (use company-scoped lookup)
            $customer = $this->model->find($id, $companyId);
            if (!$customer) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found or access denied'
                ]);
                return;
            }
            
            // Check for duplicate phone number (REQUIRED - phone is a key)
            $phoneNumber = trim($data['phone_number'] ?? '');
            if (!empty($phoneNumber)) {
                // Normalize phone number for comparison (remove spaces, dashes, parentheses)
                $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);
                
                // Query all customers in company and check normalized phones (excluding current customer)
                $allCustomers = $this->model->allByCompany($companyId);
                foreach ($allCustomers as $existingCustomer) {
                    // Skip the current customer being updated
                    if ($existingCustomer['id'] == $id) {
                        continue;
                    }
                    
                    $existingNormalized = preg_replace('/[\s\-\(\)]/', '', $existingCustomer['phone_number'] ?? '');
                    if ($existingNormalized === $normalizedPhone && !empty($existingNormalized)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'A customer with this phone number already exists',
                            'existing_customer' => [
                                'id' => $existingCustomer['id'],
                                'unique_id' => $existingCustomer['unique_id'],
                                'full_name' => $existingCustomer['full_name'],
                                'phone_number' => $existingCustomer['phone_number']
                            ]
                        ]);
                        return;
                    }
                }
            }
            
            // Check for duplicate email (OPTIONAL - only if email is provided)
            $email = trim($data['email'] ?? '');
            if (!empty($email)) {
                // Normalize email (lowercase, trim)
                $normalizedEmail = strtolower(trim($email));
                
                // Query all customers in company and check emails (excluding current customer)
                $allCustomers = $this->model->allByCompany($companyId);
                foreach ($allCustomers as $existingCustomer) {
                    // Skip the current customer being updated
                    if ($existingCustomer['id'] == $id) {
                        continue;
                    }
                    
                    $existingEmail = strtolower(trim($existingCustomer['email'] ?? ''));
                    if (!empty($existingEmail) && $existingEmail === $normalizedEmail) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'A customer with this email address already exists',
                            'existing_customer' => [
                                'id' => $existingCustomer['id'],
                                'unique_id' => $existingCustomer['unique_id'],
                                'full_name' => $existingCustomer['full_name'],
                                'email' => $existingCustomer['email']
                            ]
                        ]);
                        return;
                    }
                }
            }
            
            // Remove company_id from update data to prevent changing it
            unset($data['company_id']);
            unset($data['unique_id']);
            unset($data['created_by_user_id']);
            
            // Update customer with company isolation (only update if belongs to company)
            $result = $this->model->update($id, $data, $companyId);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer updated successfully',
                    'data' => array_merge($customer, $data)
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update customer'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /api/customers/{id} - Delete customer
     * Managers can delete if manager_delete_contact permission is enabled
     * Salespersons and technicians can always delete customers in their company
     */
    public function destroy($id) {
        $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician', 'system_admin', 'admin']);
        $companyId = $payload->company_id ?? null;
        $userRole = $payload->role ?? '';
        
        header('Content-Type: application/json');
        
        // For salespersons and technicians, company_id is required
        if (in_array($userRole, ['salesperson', 'technician'])) {
            if (!$companyId) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Company ID is required for deletion'
                ]);
                return;
            }
        }
        
        // Check if manager has permission to delete contacts
        if ($userRole === 'manager') {
            if (!$companyId || !\App\Models\CompanyModule::isEnabled($companyId, 'manager_delete_contact')) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'You do not have permission to delete customers. Please contact your system administrator.'
                ]);
                return;
            }
        }
        
        // For all roles, verify customer belongs to company (multi-tenant isolation)
        if ($companyId) {
            $customer = $this->model->find($id, $companyId);
            if (!$customer) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found or access denied'
                ]);
                return;
            }
        } else {
            // System admin can delete, but still use company-scoped method if company_id available
            if ($companyId) {
                $customer = $this->model->find($id, $companyId);
            } else {
                // Only allow findById for system_admin without company_id
                $customer = $this->model->findById($id);
            }
            if (!$customer) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
                return;
            }
        }
        
        // Delete customer with company isolation (only delete if belongs to company)
        $result = $this->model->delete($id, $companyId);
        
        if ($result) {
            // Verify deletion was successful
            $verifyCustomer = $companyId ? $this->model->find($id, $companyId) : $this->model->findById($id);
            if ($verifyCustomer) {
                // Customer still exists, deletion failed
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete customer. Customer still exists in database.'
                ]);
                return;
            }
        }
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Customer deleted successfully' : 'Failed to delete customer'
        ]);
    }
}

