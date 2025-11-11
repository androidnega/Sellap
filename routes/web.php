<?php

// ========================================
// ROOT ROUTE
// ========================================

// Root/homepage route - shows login page or redirects to dashboard if authenticated
$router->get('', function() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is authenticated
    if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
        // Redirect authenticated users to dashboard
        $redirectUrl = $_GET['redirect'] ?? '/dashboard';
        // Ensure redirect URL is relative and starts with /
        if (!empty($redirectUrl) && $redirectUrl[0] !== '/') {
            $redirectUrl = '/' . $redirectUrl;
        }
        header('Location: ' . BASE_URL_PATH . $redirectUrl);
        exit;
    }
    
    // Include login view for unauthenticated users
    include __DIR__ . '/../resources/views/login.php';
});

// Redirect /login to root for backward compatibility
$router->get('login', function() {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $redirectUrl = BASE_URL_PATH . ($queryString ? '?' . $queryString : '');
    header('Location: ' . $redirectUrl);
    exit;
});

// ========================================
// AUTHENTICATION ROUTES (Required for WebAuthMiddleware)
// ========================================

// Login endpoint (POST)
$router->post('api/auth/login', function() {
    $controller = new \App\Controllers\AuthController();
    $controller->login();
});

// Validate localStorage token and set in session
$router->post('api/auth/validate-local-token', function() {
    $controller = new \App\Controllers\AuthController();
    $controller->validateLocalToken();
});

// Validate token endpoint
$router->get('api/auth/validate', function() {
    $controller = new \App\Controllers\AuthController();
    $controller->validate();
});

// Logout endpoint
$router->post('api/auth/logout', function() {
    $controller = new \App\Controllers\AuthController();
    $controller->logout();
});

// Logout endpoint (GET for compatibility)
$router->get('api/auth/logout', function() {
    $controller = new \App\Controllers\AuthController();
    $controller->logout();
});

// ========================================
// NOTIFICATIONS ROUTES
// ========================================

// Get notifications
$router->get('api/notifications', function() {
    // Clean any existing output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    // Set JSON header early
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        // Load database config if not already loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $controller = new \App\Controllers\NotificationController();
        $controller->getNotifications();
    } catch (\Throwable $e) {
        ob_end_clean();
        
        // Log the error with full details
        error_log("Notifications route error: " . $e->getMessage());
        error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        if (!headers_sent()) {
        http_response_code(500);
            header('Content-Type: application/json');
        }
        
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to load notifications',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
});

// Mark notification as read
$router->post('api/notifications/mark-read', function() {
    // Clean any existing output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    try {
        $controller = new \App\Controllers\NotificationController();
        $controller->markAsRead();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Delete/clear notification (for managers)
$router->post('api/notifications/delete/{id}', function($id) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    try {
        $controller = new \App\Controllers\NotificationController();
        $controller->deleteNotification($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// Get notification details
$router->get('api/notifications/{id}', function($id) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    try {
        $controller = new \App\Controllers\NotificationController();
        $controller->getNotificationDetails($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// Notifications page route - Allow all authenticated roles
$router->get('dashboard/notifications', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'repairer']);
    $GLOBALS['currentPage'] = 'notifications';
    require_once __DIR__ . '/../app/Views/notifications_page.php';
});

// ========================================
// DASHBOARD ROUTES
// ========================================

// Main Dashboard Page (role-based dashboard)
// Handles all roles: system_admin, manager, admin, salesperson, technician
$router->get('dashboard', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
    $GLOBALS['currentPage'] = 'dashboard';
    $controller = new \App\Controllers\DashboardController();
    $controller->index();
});

// ========================================
// INVENTORY ROUTES
// ========================================

// Products route (for salespersons - shows read-only view)
$router->get('dashboard/products', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'products';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userRole = $_SESSION['user']['role'] ?? 'salesperson';
    
    // For managers/admins, redirect to inventory
    if (in_array($userRole, ['manager', 'admin', 'system_admin'])) {
        header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
        exit;
    }
    
    // For salespersons, show read-only products view
    $controller = new \App\Controllers\InventoryController();
    $controller->productsIndex();
});

// Product detail view (for salespersons - read-only)
$router->get('dashboard/products/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'products';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userRole = $_SESSION['user']['role'] ?? 'salesperson';
    
    // For managers/admins, redirect to inventory view
    if (in_array($userRole, ['manager', 'admin', 'system_admin'])) {
        header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/view/' . $id);
        exit;
    }
    
    // For salespersons, show read-only product details
    $controller = new \App\Controllers\ProductsController();
    $controller->show($id);
});

// Inventory Index
$router->get('dashboard/inventory', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'inventory';
    $controller = new \App\Controllers\InventoryController();
    $controller->index();
});

// Inventory Create Form
$router->get('dashboard/inventory/create', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'inventory';
    $controller = new \App\Controllers\InventoryController();
    $controller->create();
});

// Inventory Store (POST)
$router->post('dashboard/inventory/store', function() {
    $controller = new \App\Controllers\InventoryController();
    $controller->store();
});

// Inventory View
$router->get('dashboard/inventory/view/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'inventory';
    $controller = new \App\Controllers\InventoryController();
    $controller->view($id);
});

// Inventory Edit Form
$router->get('dashboard/inventory/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'inventory';
    $controller = new \App\Controllers\InventoryController();
    $controller->edit($id);
});

// Inventory Update (POST)
$router->post('dashboard/inventory/update/{id}', function($id) {
    $controller = new \App\Controllers\InventoryController();
    $controller->update($id);
});

// Inventory Delete
$router->get('dashboard/inventory/delete/{id}', function($id) {
    $controller = new \App\Controllers\InventoryController();
    $controller->delete($id);
});

// Inventory Bulk Delete (POST)
$router->post('dashboard/inventory/bulk-delete', function() {
    $controller = new \App\Controllers\InventoryController();
    $controller->bulkDelete();
});

// ========================================
// RESTOCK ROUTES
// ========================================

// Restock Index
$router->get('dashboard/restock', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'restock';
    $controller = new \App\Controllers\RestockController();
    $controller->index();
});

// Restock Show (single product restock)
$router->get('dashboard/restock/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'restock';
    $controller = new \App\Controllers\RestockController();
    $controller->show($id);
});

// Restock Update (POST)
$router->post('dashboard/restock/update/{id}', function($id) {
    $controller = new \App\Controllers\RestockController();
    $controller->update($id);
});

// Restock Bulk (POST)
$router->post('dashboard/restock/bulk', function() {
    $controller = new \App\Controllers\RestockController();
    $controller->bulkRestock();
});

// Restock History
$router->get('dashboard/restock/history', function() {
    \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
    $GLOBALS['currentPage'] = 'restock';
    $controller = new \App\Controllers\RestockController();
    $controller->history();
});

// ========================================
// CATEGORIES ROUTES
// ========================================

// Categories Index
$router->get('dashboard/categories', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'categories';
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->index();
});

// Categories Create Form
$router->get('dashboard/categories/create', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'categories';
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->create();
});

// Categories Store (POST)
$router->post('dashboard/categories/store', function() {
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->store();
});

// Categories Edit Form
$router->get('dashboard/categories/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'categories';
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->edit($id);
});

// Categories Update (POST)
$router->post('dashboard/categories/update/{id}', function($id) {
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->update($id);
});

// Categories Delete
$router->get('dashboard/categories/delete/{id}', function($id) {
    $controller = new \App\Controllers\CategoryManagementController();
    $controller->delete($id);
});

// ========================================
// SUBCATEGORIES ROUTES
// ========================================

// Subcategories Index
$router->get('dashboard/subcategories', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'subcategories';
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->index();
});

// Subcategories Create Form
$router->get('dashboard/subcategories/create', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'subcategories';
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->create();
});

// Subcategories Store (POST)
$router->post('dashboard/subcategories/store', function() {
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->store();
});

// Subcategories Edit Form
$router->get('dashboard/subcategories/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'subcategories';
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->edit($id);
});

// Subcategories Update (POST)
$router->post('dashboard/subcategories/update/{id}', function($id) {
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->update($id);
});

// Subcategories Delete
$router->get('dashboard/subcategories/delete/{id}', function($id) {
    $controller = new \App\Controllers\SubcategoryManagementController();
    $controller->delete($id);
});

// ========================================
// BRANDS ROUTES
// ========================================

// Brands Index
$router->get('dashboard/brands', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'brands';
    $controller = new \App\Controllers\BrandManagementController();
    $controller->index();
});

// Brands Create Form
$router->get('dashboard/brands/create', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'brands';
    $controller = new \App\Controllers\BrandManagementController();
    $controller->create();
});

// Brands Store (POST)
$router->post('dashboard/brands/store', function() {
    $controller = new \App\Controllers\BrandManagementController();
    $controller->store();
});

// Brands Edit Form
$router->get('dashboard/brands/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'brands';
    $controller = new \App\Controllers\BrandManagementController();
    $controller->edit($id);
});

// Brands Update (POST)
$router->post('dashboard/brands/update/{id}', function($id) {
    $controller = new \App\Controllers\BrandManagementController();
    $controller->update($id);
});

// Brands Delete
$router->get('dashboard/brands/delete/{id}', function($id) {
    $controller = new \App\Controllers\BrandManagementController();
    $controller->delete($id);
});

// ========================================
// API ROUTES - STAFF
// ========================================

// Get staff list for dropdowns
$router->get('api/staff/list', function() {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
        $controller = new \App\Controllers\StaffController();
        $controller->apiList();
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'staff' => []
        ]);
    }
});

// ========================================
// API ROUTES - BRANDS AND SUBCATEGORIES
// ========================================

// Get brands by category ID
$router->get('api/products/brands/{categoryId}', function($categoryId) {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        $controller = new \App\Controllers\ProductController();
        $controller->apiGetBrandsByCategory($categoryId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ]);
    }
});

// Get brands by category ID (alternative endpoint)
$router->get('api/brands/by-category/{categoryId}', function($categoryId) {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        $controller = new \App\Controllers\BrandManagementController();
        $controller->apiGetBrandsByCategory($categoryId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ]);
    }
});

// Get subcategories by category ID
$router->get('api/products/subcategories/{categoryId}', function($categoryId) {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        $controller = new \App\Controllers\ProductController();
        $controller->apiGetSubcategoriesByCategory($categoryId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ]);
    }
});

// Get brand specifications
$router->get('api/products/brand-specs/{brandId}', function($brandId) {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        // Use ProductController which has better brand-specific specs
        $controller = new \App\Controllers\ProductController();
        $controller->apiGetBrandSpecs($brandId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ]);
    }
});

// Alternative endpoint for brand specs
$router->get('api/brands/specs/{brandId}', function($brandId) {
    header('Content-Type: application/json');
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
        // Use ProductController which has better brand-specific specs
        $controller = new \App\Controllers\ProductController();
        $controller->apiGetBrandSpecs($brandId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ]);
    }
});

// ========================================
// COMPANIES ROUTES
// ========================================

// Companies Index
$router->get('dashboard/companies', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'companies';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->index();
});

// Companies Create Form
$router->get('dashboard/companies/create', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'companies';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->create();
});

// Companies Store (POST)
$router->post('dashboard/companies/store', function() {
    $controller = new \App\Controllers\CompanyWebController();
    $controller->store();
});

// Companies View
$router->get('dashboard/companies/view/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'companies';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->view($id);
});

// Companies Edit Form
$router->get('dashboard/companies/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'companies';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->edit($id);
});

// Companies Update (POST)
$router->post('dashboard/companies/update/{id}', function($id) {
    $controller = new \App\Controllers\CompanyWebController();
    $controller->update($id);
});

// Companies Delete (GET - backward compatibility, redirects to companies list with delete modal)
$router->get('dashboard/companies/delete/{id}', function($id) {
    // Redirect to companies list - the delete functionality is now handled via API
    header('Location: ' . BASE_URL_PATH . '/dashboard/companies');
    exit;
});

// Companies SMS Config
$router->get('dashboard/companies/sms-config', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'sms-config';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->smsConfig();
});

// Companies Modules Index (list all companies)
$router->get('dashboard/companies/modules', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'company-modules';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->modulesIndex();
});

// Company Modules Management (specific company)
$router->get('dashboard/companies/{id}/modules', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'company-modules';
    $controller = new \App\Controllers\CompanyWebController();
    $controller->modules($id);
});

// ========================================
// SETTINGS ROUTES
// ========================================

// SMS Settings (Manager/Admin)
$router->get('dashboard/sms-settings', function() {
    \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
    $GLOBALS['currentPage'] = 'sms-settings';
    $controller = new \App\Controllers\ProfileController();
    $controller->smsSettings();
});

// SMS Purchase Page
$router->get('dashboard/sms/purchase', function() {
    $controller = new \App\Controllers\ProfileController();
    $controller->smsPurchase();
});

// SMS Payment Success Page
$router->get('dashboard/sms/payment-success', function() {
    \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
    $paymentId = $_GET['payment_id'] ?? null;
    require_once __DIR__ . '/../app/Views/payment-success.php';
    exit;
});

// Profile Page
$router->get('dashboard/profile', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
    $GLOBALS['currentPage'] = 'profile';
    $controller = new \App\Controllers\ProfileController();
    $controller->profile();
});

// System Settings Page (System Admin and Admin only - restrict manager, salesperson, technician)
$router->get('dashboard/system-settings', function() {
    try {
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin']);
        $GLOBALS['currentPage'] = 'settings';
        $controller = new \App\Controllers\SettingsController();
        $controller->index();
    } catch (\Exception $e) {
        error_log("System settings route error: " . $e->getMessage());
        error_log("System settings route trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h1>Error Loading Settings Page</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check the error logs for more details.</p>";
        echo "</body></html>";
        exit;
    } catch (\Error $e) {
        error_log("System settings route fatal error: " . $e->getMessage());
        error_log("System settings route fatal trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h1>Fatal Error Loading Settings Page</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check the error logs for more details.</p>";
        echo "</body></html>";
        exit;
    }
});

// Analytics Page (System Admin only)
$router->get('dashboard/analytics', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'analytics';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $page = 'analytics';
    $title = 'Platform Analytics';
    
    // Capture the view content
    ob_start();
    include __DIR__ . '/../app/Views/analytics.php';
    $content = ob_get_clean();
    
    // Set content variable for layout
    $GLOBALS['content'] = $content;
    $GLOBALS['title'] = $title;
    $GLOBALS['pageTitle'] = $title;
    
    // Pass user data to layout for sidebar role detection
    if (isset($_SESSION['user'])) {
        $GLOBALS['user_data'] = $_SESSION['user'];
    }
    
    // Include layout (same as other admin pages)
    include __DIR__ . '/../app/Views/simple_layout.php';
    exit;
});

// ========================================
// STAFF ROUTES
// ========================================

// Staff Index
$router->get('dashboard/staff', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'staff';
    $controller = new \App\Controllers\StaffController();
    $controller->index();
});

// Staff Create Form
$router->get('dashboard/staff/create', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'staff';
    $controller = new \App\Controllers\StaffController();
    $controller->create();
});

// Staff Store (POST)
$router->post('dashboard/staff/store', function() {
    $controller = new \App\Controllers\StaffController();
    $controller->store();
});

// Staff Edit Form
$router->get('dashboard/staff/edit/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'staff';
    $controller = new \App\Controllers\StaffController();
    $controller->edit($id);
});

// Staff Update (POST)
$router->post('dashboard/staff/update/{id}', function($id) {
    $controller = new \App\Controllers\StaffController();
    $controller->update($id);
});

// Staff Delete
$router->get('dashboard/staff/delete/{id}', function($id) {
    $controller = new \App\Controllers\StaffController();
    $controller->delete($id);
});

// Staff Reset Password
$router->get('dashboard/staff/reset-password/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $controller = new \App\Controllers\StaffController();
    $controller->resetPassword($id);
});

// ========================================
// SWAPS ROUTES
// ========================================

// Swaps Index
$router->get('dashboard/swaps', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'swaps';
    $controller = new \App\Controllers\SwapController();
    $controller->index();
});

// Swaps Create Form
$router->get('dashboard/swaps/create', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'swaps';
    $controller = new \App\Controllers\SwapController();
    $controller->create();
});

// Swaps Store (POST)
$router->post('dashboard/swaps/store', function() {
    $controller = new \App\Controllers\SwapController();
    $controller->store();
});

// Swaps Show (View)
$router->get('dashboard/swaps/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'swaps';
    $controller = new \App\Controllers\SwapController();
    $controller->show($id);
});

// Swaps Receipt
$router->get('dashboard/swaps/{id}/receipt', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $controller = new \App\Controllers\SwapController();
    $controller->generateReceipt($id);
});

// Swaps Resale
$router->get('dashboard/swaps/resale', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'swaps';
    $controller = new \App\Controllers\SwapController();
    $controller->resale();
});

// Swaps Update Status (POST)
$router->post('dashboard/swaps/update-status/{id}', function($id) {
    $controller = new \App\Controllers\SwapController();
    $controller->updateStatus($id);
});

// Swaps Delete
$router->get('dashboard/swaps/delete/{id}', function($id) {
    $controller = new \App\Controllers\SwapController();
    $controller->delete($id);
});

// ========================================
// REPAIRS ROUTES
// ========================================

// Repairs Index
$router->get('dashboard/repairs', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'repairs';
    $controller = new \App\Controllers\RepairController();
    $controller->index();
});

// Repairs Create Form
$router->get('dashboard/repairs/create', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'repairs';
    $controller = new \App\Controllers\RepairController();
    $controller->create();
});

// Repairs Store (POST)
$router->post('dashboard/repairs/store', function() {
    $controller = new \App\Controllers\RepairController();
    $controller->store();
});

// Repairs Show (View)
$router->get('dashboard/repairs/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'repairs';
    $controller = new \App\Controllers\RepairController();
    $controller->show($id);
});

// Repairs Receipt
$router->get('dashboard/repairs/{id}/receipt', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $controller = new \App\Controllers\RepairController();
    $controller->receipt($id);
});

// Repairs Update Status (POST)
$router->post('dashboard/repairs/update-status/{id}', function($id) {
    $controller = new \App\Controllers\RepairController();
    $controller->updateStatus($id);
});

// Repairs Update Payment Status (POST)
$router->post('dashboard/repairs/update-payment-status/{id}', function($id) {
    $controller = new \App\Controllers\RepairController();
    $controller->updatePaymentStatus($id);
});

// ========================================
// TECHNICIAN DASHBOARD ROUTES
// ========================================

// Note: Main technician dashboard is handled by DashboardController at /dashboard
// This ensures technicians use /dashboard like all other roles

// Technician Booking
$router->get('dashboard/technician/booking', function() {
    \App\Middleware\WebAuthMiddleware::handle(['technician', 'system_admin']);
    $GLOBALS['currentPage'] = 'booking';
    $controller = new \App\Controllers\TechnicianController();
    $controller->booking();
});

// Technician My Repairs
$router->get('dashboard/technician/repairs', function() {
    \App\Middleware\WebAuthMiddleware::handle(['technician', 'system_admin']);
    $GLOBALS['currentPage'] = 'repairs';
    $controller = new \App\Controllers\TechnicianController();
    $controller->myRepairs();
});

// ========================================
// CUSTOMERS ROUTES
// ========================================

// Reports route (for salespersons)
$router->get('dashboard/reports', function() {
    \App\Middleware\WebAuthMiddleware::handle(['salesperson']);
    $GLOBALS['currentPage'] = 'reports';
    $controller = new \App\Controllers\ReportsController();
    $controller->index();
});

// Reports export route
$router->get('dashboard/reports/export', function() {
    $controller = new \App\Controllers\ReportsController();
    $controller->export();
});

// Reports preview API
$router->get('api/reports/preview', function() {
    $controller = new \App\Controllers\ReportsController();
    $controller->preview();
});

// Settings route
$router->get('dashboard/settings', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'settings';
    $controller = new \App\Controllers\SettingsController();
    $controller->index();
});

// Customers Index
$router->get('dashboard/customers', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'customers';
    $controller = new \App\Controllers\CustomerController();
    $controller->webIndex();
});

// Customers Store (POST)
$router->post('dashboard/customers/store', function() {
    $controller = new \App\Controllers\CustomerController();
    $controller->store();
});

// Customers Show (View)
$router->get('dashboard/customers/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'customers';
    $controller = new \App\Controllers\CustomerController();
    $controller->show($id);
});

// Customers Update (POST)
$router->post('dashboard/customers/update/{id}', function($id) {
    $controller = new \App\Controllers\CustomerController();
    $controller->update($id);
});

// ========================================
// POS ROUTES
// ========================================

// POS Main Page
$router->get('dashboard/pos', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    
    // Check if user is a manager and should be redirected to audit trail
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user = $_SESSION['user'] ?? null;
    if ($user && ($user['role'] === 'manager' || $user['role'] === 'admin')) {
        $companyId = $user['company_id'] ?? null;
        if ($companyId) {
            // Check if manager_can_sell module is enabled
            $canSell = \App\Models\CompanyModule::isEnabled($companyId, 'manager_can_sell');
            if (!$canSell) {
                // Redirect to audit trail
                header('Location: ' . BASE_URL_PATH . '/dashboard/audit-trail');
                exit;
            }
        }
    }
    
    $GLOBALS['currentPage'] = 'pos';
    $controller = new \App\Controllers\POSController();
    $controller->index();
});

// POS Sales History
$router->get('dashboard/pos/sales-history', function() {
    \App\Middleware\WebAuthMiddleware::handle();
    $GLOBALS['currentPage'] = 'sales-history';
    $controller = new \App\Controllers\POSController();
    $controller->salesHistory();
});

// Partial Payments Management
$router->get('dashboard/pos/partial-payments', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson']);
    $controller = new \App\Controllers\POSController();
    $controller->partialPayments();
});

// POS Receipt
$router->get('pos/receipt/{id}', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->generateReceipt($id);
});

// ========================================
// AUDIT TRAIL / MANAGER ANALYTICS ROUTES
// ========================================

// Audit Trail Main Page
$router->get('dashboard/audit-trail', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager']);
    $GLOBALS['currentPage'] = 'audit-trail';
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->index();
});

// Analytics Overview API
$router->get('api/analytics/overview', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->overview();
});

// Analytics Trace API
$router->get('api/analytics/trace', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->trace();
});

// Analytics Metrics API
$router->get('api/analytics/metrics', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->metrics();
});

// Analytics Charts API
$router->get('api/analytics/charts', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->charts();
});

// Analytics Export API
$router->get('api/analytics/export/{type}', function($type) {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->export($type);
});

// Analytics Audit Logs API
$router->get('api/analytics/audit-logs', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->auditLogs();
});

// Analytics Alerts API
$router->get('api/analytics/alerts', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->alerts();
});

// Acknowledge Alert API
$router->post('api/analytics/alerts/{id}/acknowledge', function($id) {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->acknowledgeAlert($id);
});

// Test Alert API
$router->post('api/analytics/alerts/test', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->testAlert();
});

// Anomalies API
$router->get('api/analytics/anomalies', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->anomalies();
});

// Forecast APIs
$router->get('api/analytics/forecast/sales', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->forecastSales();
});

$router->get('api/analytics/forecast/restock', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->forecastRestock();
});

$router->get('api/analytics/forecast/profit', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->forecastProfit();
});

// Recommendations API
$router->get('api/analytics/recommendations', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->recommendations();
});

$router->post('api/analytics/recommendations/{id}/read', function($id) {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->markRecommendationRead($id);
});

$router->post('api/analytics/recommendations/generate', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->generateRecommendations();
});

// Profit Optimization API
$router->get('api/analytics/profit-optimization', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->profitOptimization();
});

// Backup & Restore API (Legacy - kept for compatibility)
$router->get('api/analytics/backups', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->getBackups();
});

$router->post('api/analytics/backup/export', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->exportBackup();
});

// Backup Manager Routes
$router->get('dashboard/backup', function() {
    $controller = new \App\Controllers\BackupController();
    $controller->index();
});

$router->post('dashboard/backup/export', function() {
    $controller = new \App\Controllers\BackupController();
    $controller->export();
});

$router->post('dashboard/backup/import', function() {
    $controller = new \App\Controllers\BackupController();
    $controller->import();
});

$router->get('api/company/{id}/backups', function($id) {
    $controller = new \App\Controllers\BackupController();
    $controller->getBackups($id);
});

$router->get('dashboard/backup/download/{id}', function($id) {
    $controller = new \App\Controllers\BackupController();
    $controller->download($id);
});

// Integrity Dashboard API
$router->get('api/analytics/integrity', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->integrity();
});

// Admin Benchmarks (System Admin Only)
$router->get('dashboard/admin/benchmarks', function() {
    $controller = new \App\Controllers\AdminBenchmarkController();
    $controller->index();
});

$router->get('api/admin/benchmarks', function() {
    $controller = new \App\Controllers\AdminBenchmarkController();
    $controller->getBenchmarks();
});

// Unified Live Data Fetch Endpoint
$router->get('api/audit-trail/data', function() {
    $controller = new \App\Controllers\ManagerAnalyticsController();
    $controller->fetchLiveData();
});

// ========================================
// POS API ROUTES
// ========================================

// POS Products API
$router->get('api/pos/products', function() {
    $controller = new \App\Controllers\POSController();
    $controller->apiProducts();
});

// POS Customers API
$router->get('api/customers', function() {
    $controller = new \App\Controllers\POSController();
    $controller->apiCustomers();
});

// Create Customer API (for POS)
$router->post('api/customers', function() {
    $controller = new \App\Controllers\CustomerController();
    $controller->store();
});

// Get Customer by ID API
$router->get('api/customers/{id}', function($id) {
    $controller = new \App\Controllers\CustomerController();
    $controller->show($id);
});

// Get Customer Purchase History API
$router->get('api/customers/{id}/history', function($id) {
    $controller = new \App\Controllers\CustomerController();
    $controller->getPurchaseHistory($id);
});

// Get Total Customers Count API
$router->get('api/customers/count', function() {
    $controller = new \App\Controllers\CustomerController();
    $controller->getTotalCount();
});

// Update Customer API
$router->put('api/customers/{id}', function($id) {
    $controller = new \App\Controllers\CustomerController();
    $controller->update($id);
});

// Delete Customer API
$router->delete('api/customers/{id}', function($id) {
    $controller = new \App\Controllers\CustomerController();
    $controller->destroy($id);
});

// POS Quick Stats API (for salespersons and managers)
$router->get('api/pos/quick-stats', function() {
    $controller = new \App\Controllers\POSController();
    $controller->apiQuickStats();
});

// POS Cart Routes
$router->post('pos/cart/add', function() {
    $controller = new \App\Controllers\POSController();
    $controller->addToCart();
});

$router->post('pos/cart/update', function() {
    $controller = new \App\Controllers\POSController();
    $controller->updateCartQuantity();
});

$router->post('pos/cart/remove', function() {
    $controller = new \App\Controllers\POSController();
    $controller->removeFromCart();
});

$router->post('pos/cart/clear', function() {
    $controller = new \App\Controllers\POSController();
    $controller->clearCart();
});

// POS Sales API - Process Sale (Create)
$router->post('api/pos', function() {
    $controller = new \App\Controllers\POSController();
    $controller->processSale();
});

// POS Sales API
$router->get('api/pos/sales', function() {
    $controller = new \App\Controllers\POSController();
    $controller->apiSales();
});

// Get single sale details API
$router->get('api/pos/sale/{id}', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->apiSaleDetails($id);
});

// Delete sale route - support both DELETE and POST (for servers that don't support DELETE)
$router->delete('api/pos/sale/{id}', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->deleteSale($id);
});

// POST alternative for delete (method override)
$router->post('api/pos/sale/{id}/delete', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->deleteSale($id);
});

// Bulk delete route - support both DELETE and POST
$router->delete('api/pos/sales/bulk', function() {
    $controller = new \App\Controllers\POSController();
    $controller->bulkDeleteSales();
});

// POST alternative for bulk delete
$router->post('api/pos/sales/bulk-delete', function() {
    $controller = new \App\Controllers\POSController();
    $controller->bulkDeleteSales();
});

// Add partial payment to existing sale
$router->post('api/pos/sale/{id}/payment', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->addPayment($id);
});

// Get payment history for a sale
$router->get('api/pos/sale/{id}/payments', function($id) {
    $controller = new \App\Controllers\POSController();
    $controller->getPayments($id);
});

// Get sales with partial payment information
$router->get('api/pos/partial-payments', function() {
    $controller = new \App\Controllers\POSController();
    $controller->apiPartialPayments();
});

// ========================================
// DASHBOARD API ROUTES
// ========================================

// Helper function to ensure clean JSON output
$ensureJsonOutput = function() {
    // Clean any existing output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Start output buffering
    ob_start();
    // Suppress PHP errors from breaking JSON
    error_reporting(0);
    ini_set('display_errors', 0);
    // Set JSON header
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
};

// ========================================
// COMPANY API ROUTES
// ========================================

// Companies Delete (POST for password confirmation)
$router->post('api/companies/{id}/delete', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\CompanyController();
        $controller->delete($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Check company data before deletion
$router->get('api/companies/{id}/check-data', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\CompanyController();
        $controller->checkData($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Get users by company
$router->get('api/companies/{id}/users', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\CompanyController();
        $controller->getUsers($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Manager Overview API (comprehensive stats)
$router->get('api/dashboard/manager-overview', function() {
    // Clean output and set headers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    ini_set('display_errors', 0);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->managerOverview();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        error_log("Route error for manager-overview: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        error_log("Route fatal error for manager-overview: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
});

// Dashboard Export API
$router->get('api/dashboard/export', function() {
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->exportDashboard();
    } catch (\Exception $e) {
        error_log("Dashboard export route error: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Company Metrics API
$router->get('api/dashboard/company-metrics', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->companyMetrics();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Recent Sales API
$router->get('api/dashboard/recent-sales', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->recentSales();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Inventory Alerts API
$router->get('api/dashboard/inventory-alerts', function() use ($ensureJsonOutput) {
    // Ensure Database class is loaded
    if (!class_exists('Database')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->inventoryAlerts();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Staff Performance API
$router->get('api/dashboard/staff-performance', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->staffPerformance();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Sales Metrics API
$router->get('api/dashboard/sales-metrics', function() use ($ensureJsonOutput) {
    // Ensure Database class is loaded
    if (!class_exists('Database')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->salesMetrics();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Top Products API
$router->get('api/dashboard/top-products', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->topProducts();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Stats API (general dashboard stats)
$router->get('api/dashboard/stats', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->stats();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Check Module Status API
$router->get('api/dashboard/check-module', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->checkModule();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Toggle Module API
$router->post('api/dashboard/toggle-module', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->toggleModule();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Charts Data API
$router->get('api/dashboard/charts-data', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\DashboardController();
        $controller->chartsData();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// ========================================
// ADMIN API ROUTES (System Admin Only)
// ========================================

// Admin Dashboard API
$router->get('api/admin/dashboard', function() {
    // Ensure Database class is loaded
    if (!class_exists('Database')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    // Clean output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->dashboard();
    } catch (\Exception $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to load dashboard',
            'message' => $e->getMessage()
        ]);
        error_log("Admin Dashboard Route Error: " . $e->getMessage() . " - Stack: " . $e->getTraceAsString());
    } catch (\Error $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Internal server error',
            'message' => 'A fatal error occurred: ' . $e->getMessage()
        ]);
        error_log("Admin Dashboard Route Fatal Error: " . $e->getMessage() . " - Stack: " . $e->getTraceAsString());
    }
});

// Admin Stats API
$router->get('api/admin/stats', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->stats();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Admin Companies API
$router->get('api/admin/companies', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->companies();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Admin Managers API
$router->get('api/admin/managers', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->managers();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Admin Users API
$router->get('api/admin/users', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->users();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Admin Health API
$router->get('api/admin/health', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->health();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Admin Analytics API
$router->get('api/admin/analytics', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminController();
        $controller->analytics();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// ========================================
// SYSTEM SETTINGS API ROUTES
// ========================================

// Get system settings
$router->get('api/system-settings', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->getSettings();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Update system settings
$router->post('api/system-settings/update', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->updateSettings();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Test Cloudinary configuration
$router->post('api/system-settings/test-cloudinary', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->testCloudinary();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Test SMS configuration
$router->post('api/system-settings/test-sms', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->testSMS();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Send test SMS
$router->post('api/system-settings/send-test-sms', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->sendTestSMS();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Test Paystack configuration
$router->post('api/system-settings/test-paystack', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->testPaystack();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Upload image
$router->post('api/system-settings/upload-image', function() use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\SettingsController();
        $controller->uploadImage();
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// ========================================
// ADMIN COMPANY MODULES API ROUTES
// ========================================

// Get company modules
$router->get('api/admin/company/{id}/modules', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanyModulesController();
        $controller->getModules($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Toggle company module
$router->post('api/admin/company/{id}/modules/toggle', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanyModulesController();
        $controller->toggleModule($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// ========================================
// ADMIN COMPANY SMS API ROUTES
// ========================================

// Get company SMS details
$router->get('api/admin/company/{id}/sms/details', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->getDetails($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Toggle company SMS
$router->post('api/admin/company/{id}/sms/toggle', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->toggleCustomSMS($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Top up company SMS
$router->post('api/admin/company/{id}/sms/topup', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->topUpSMS($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Set company SMS total
$router->post('api/admin/company/{id}/sms/set-total', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->setTotalSMS($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Set company SMS sender name
$router->post('api/admin/company/{id}/sms/sender-name', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->setSenderName($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// Get company SMS logs
$router->get('api/admin/company/{id}/sms/logs', function($id) use ($ensureJsonOutput) {
    $ensureJsonOutput();
    try {
        $controller = new \App\Controllers\AdminCompanySMSController();
        $controller->getLogs($id);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

// ========================================
// RESET SYSTEM ROUTES (PHASE E)
// ========================================

// Company Manager Password Reset
$router->get('dashboard/companies/{id}/reset-password', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $controller = new \App\Controllers\CompanyWebController();
    $controller->resetManagerPassword($id);
});

// Company Reset Page
$router->get('dashboard/companies/{id}/reset', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'company-reset';
    include __DIR__ . '/../app/Views/admin_reset_company.php';
});

// System Reset Page
$router->get('dashboard/reset', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'system-reset';
    include __DIR__ . '/../app/Views/admin_reset_system.php';
});

// Reset History Page
$router->get('dashboard/reset/history', function() {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['currentPage'] = 'reset-history';
    include __DIR__ . '/../app/Views/admin_reset_history.php';
});

// Reset Status Page
$router->get('dashboard/admin/reset/{id}', function($id) {
    \App\Middleware\WebAuthMiddleware::handle(['system_admin']);
    $GLOBALS['reset_action_id'] = $id;
    $GLOBALS['currentPage'] = 'admin';
    include __DIR__ . '/../app/Views/admin_reset_status.php';
});

// ========================================
// RESET API ROUTES (PHASE B)
// ========================================

// Company Reset API (combined preview + execute)
$router->post('api/admin/companies/{id}/reset', function($id) {
    $controller = new \App\Controllers\ResetController();
    $controller->resetCompany($id);
});

// System Reset API (combined preview + execute)
$router->post('api/admin/system/reset', function() {
    $controller = new \App\Controllers\ResetController();
    $controller->resetSystem();
});

// List Reset Actions (must come before dynamic route)
$router->get('api/admin/reset/actions', function() {
    $controller = new \App\Controllers\ResetController();
    $controller->listActions();
});

// Get Reset Action Details
$router->get('api/admin/reset/{admin_action_id}', function($admin_action_id) {
    $controller = new \App\Controllers\ResetController();
    $controller->getActionDetails($admin_action_id);
});

// ========================================
// BACKUP ROUTES
// ========================================

// Backup Download Route
$router->get('api/admin/backup/download/{backupId}', function($backupId) {
    // Set JSON header first to prevent HTML output
    header('Content-Type: application/json');
    ob_start();
    
    try {
        $payload = \App\Middleware\AuthMiddleware::handle(['system_admin', 'manager']);
        
        $backupService = new \App\Services\BackupService();
        $backupPath = $backupService->getBackupPath($backupId);
        
        if (!$backupPath || !is_dir($backupPath)) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Backup not found']);
            return;
        }
        
        // Create zip archive of backup
        $zipFile = $backupService->createBackupZip($backupId, $backupPath);
        
        if (!$zipFile || !file_exists($zipFile)) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create backup archive']);
            return;
        }
        
        ob_end_clean();
        
        // Send file for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($zipFile);
        
        // Optionally delete zip after download (uncomment if needed)
        // unlink($zipFile);
        
        exit;
        
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Error $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
    }
});

// Create Company Backup
$router->post('api/admin/backup/company', function() {
    header('Content-Type: application/json');
    ob_start();
    
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Try session-based authentication first (for web requests)
        $authenticated = false;
        $userData = $_SESSION['user'] ?? null;
        $allowedRoles = ['system_admin', 'manager'];
        
        if ($userData && is_array($userData) && isset($userData['role']) && in_array($userData['role'], $allowedRoles)) {
            $authenticated = true;
        } else {
            // Fall back to JWT authentication (for API requests)
            try {
                $payload = \App\Middleware\AuthMiddleware::handle($allowedRoles);
                $authenticated = true;
            } catch (\Exception $e) {
                // JWT auth failed, check if session exists as fallback
                if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', $allowedRoles)) {
                    $authenticated = true;
                } else {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized',
                        'message' => 'Authentication required. Please login as system administrator or manager.'
                    ]);
                    return;
                }
            }
        }
        
        if (!$authenticated) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Authentication required. Please login as system administrator or manager.'
            ]);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $companyId = $input['company_id'] ?? null;
        
        if (!$companyId) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'company_id is required']);
            return;
        }
        
        $backupService = new \App\Services\BackupService();
        $backupId = $backupService->createCompanyBackup($companyId);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'backup_id' => $backupId,
            'message' => 'Backup created successfully'
        ]);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// Create System Backup
$router->post('api/admin/backup/system', function() {
    header('Content-Type: application/json');
    ob_start();
    
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Try session-based authentication first (for web requests)
        $authenticated = false;
        $userData = $_SESSION['user'] ?? null;
        
        if ($userData && is_array($userData) && isset($userData['role']) && $userData['role'] === 'system_admin') {
            $authenticated = true;
        } else {
            // Fall back to JWT authentication (for API requests)
            try {
                $payload = \App\Middleware\AuthMiddleware::handle(['system_admin']);
                $authenticated = true;
            } catch (\Exception $e) {
                // JWT auth failed, check if session exists as fallback
                if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'system_admin') {
                    $authenticated = true;
                } else {
                    ob_end_clean();
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unauthorized',
                        'message' => 'Authentication required. Please login as system administrator.'
                    ]);
                    return;
                }
            }
        }
        
        if (!$authenticated) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Authentication required. Please login as system administrator.'
            ]);
            return;
        }
        
        $backupService = new \App\Services\BackupService();
        $backupId = $backupService->createSystemBackup();
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'backup_id' => $backupId,
            'message' => 'System backup created successfully'
        ]);
    } catch (\Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ========================================
// SMS PAYMENT ROUTES
// ========================================

// Get SMS rate per message
$router->get('api/sms/pricing/rate', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->getSMSRate();
});

// Get SMS pricing/bundles (legacy - for backward compatibility)
$router->get('api/sms/pricing', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->getPricing();
});

// Get specific bundle pricing
$router->get('api/sms/pricing/bundle', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->getBundlePricing();
});

// PayPal SMS purchase (legacy - kept for backward compatibility)
$router->post('api/payments/sms/initiate', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->initiatePurchase();
});

// PayPal success callback
$router->get('api/payments/sms/success', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->handleSuccess();
});

// PayPal cancel callback
$router->get('api/payments/sms/cancel', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->handleCancel();
});

// Payment history
$router->get('api/payments/sms/history', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->getPaymentHistory();
});

// Paystack SMS purchase initiation
$router->post('api/sms/paystack/initiate', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->initiatePaystackPurchase();
});

// Paystack payment verification/callback
$router->get('api/sms/paystack/verify', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->verifyPaystackPayment();
});

// Paystack callback (same as verify but different route for clarity)
$router->get('api/sms/paystack/callback', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->verifyPaystackPayment();
});

// Paystack webhook (POST for actual webhook events)
$router->post('api/sms/paystack/webhook', function() {
    $controller = new \App\Controllers\PaymentController();
    $controller->handlePaystackWebhook();
});

// Paystack webhook verification (GET for Paystack to verify endpoint exists)
$router->get('api/sms/paystack/webhook', function() {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Paystack webhook endpoint is active'
    ]);
});

// ========================================
// NOTE: Other application routes should be added here
// This file was restored with reset, backup, auth, and notification routes
// ========================================
