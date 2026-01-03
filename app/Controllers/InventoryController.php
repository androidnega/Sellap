<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductSpec;

class InventoryController {
    private $productModel;
    private $productSpecModel;

    public function __construct() {
        $this->productModel = new Product();
        $this->productSpecModel = new ProductSpec();
        // Auto-run migration if needed
        $this->checkAndRunMigration();
    }
    
    /**
     * Check if supplier tracking table exists and run migration if needed
     */
    private function checkAndRunMigration() {
        try {
            $db = \Database::getInstance()->getConnection();
            $tableExists = $db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            
            if (!$tableExists) {
                // Run migration
                $migrationFile = __DIR__ . '/../../database/migrations/create_supplier_tracking.sql';
                if (file_exists($migrationFile)) {
                    $sql = file_get_contents($migrationFile);
                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        function($stmt) {
                            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                        }
                    );
                    
                    foreach ($statements as $statement) {
                        if (empty(trim($statement))) continue;
                        try {
                            // Handle prepared statements
                            if (preg_match('/^SET\s+@|PREPARE|EXECUTE|DEALLOCATE/', $statement)) {
                                $db->exec($statement);
                            } else {
                                $db->exec($statement);
                            }
                        } catch (\PDOException $e) {
                            // Ignore "already exists" errors
                            if (strpos($e->getMessage(), 'already exists') === false && 
                                strpos($e->getMessage(), 'Duplicate column') === false) {
                                error_log("Migration error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error checking/running migration: " . $e->getMessage());
        }
    }

    public function index() {
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'salesperson', 'technician', 'system_admin'],
            "You do not have permission to access inventory pages.",
            BASE_URL_PATH . '/dashboard'
        );
        
        // Start session and check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/inventory';
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 10;
        $category_id = $_GET['category_id'] ?? null;
        $companyId = $_SESSION['user']['company_id'] ?? 1;

        // Get total count first to validate pagination
        $totalItems = $this->productModel->getTotalCountByCompany($companyId, $category_id);
        
        // Ensure current page doesn't exceed available pages
        $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
        }
        
        // Fetch paginated products and totals for the authenticated company
        $products = $this->productModel->findByCompanyPaginated($companyId, $currentPage, $itemsPerPage, $category_id);
        $inventoryStats = $this->productModel->getStats($companyId);
        $categories = (new \App\Models\Category())->getAll();
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/inventory'
        );
        
        $page = 'inventory';
        $title = 'Product Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/inventory_index.php';
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
     * Products index for salespersons (read-only view)
     */
    public function productsIndex() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        $userRole = $_SESSION['user']['role'] ?? 'salesperson';
        
        // Only allow salespersons and technicians
        if (!in_array($userRole, ['salesperson', 'technician'])) {
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 20;
        $category_id = $_GET['category_id'] ?? null;
        $swappedItemsOnly = isset($_GET['swapped_items']) && $_GET['swapped_items'] == '1';
        
        // Get total count first to validate pagination (same as managers use)
        $totalItems = $this->productModel->getTotalCountByCompany($companyId, $category_id, $swappedItemsOnly);
        
        // Ensure current page doesn't exceed available pages
        $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
        }
        
        // Use findByCompanyPaginated like managers do - this ensures salespersons see ALL products
        $products = $this->productModel->findByCompanyPaginated($companyId, $currentPage, $itemsPerPage, $category_id, $swappedItemsOnly);
        
        // Calculate stats from all products (not just paginated ones)
        // Get all products for stats calculation
        $allProducts = $this->productModel->findByCompanyPaginated($companyId, 1, 10000, $category_id, $swappedItemsOnly);
        $stats = [
            'total_products' => $totalItems,
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'swapped_items' => 0,
            'total_value' => 0
        ];
        
        foreach ($allProducts as $product) {
            $qty = intval($product['quantity'] ?? $product['qty'] ?? 0);
            $price = floatval($product['price'] ?? 0);
            
            if ($swappedItemsOnly || (isset($product['is_swapped_item']) && $product['is_swapped_item'])) {
                $stats['swapped_items']++;
            }
            
            if ($qty > 0) {
                $stats['in_stock']++;
                $minQty = intval($product['min_quantity'] ?? 5);
                if ($qty <= $minQty) {
                    $stats['low_stock']++;
                }
                $stats['total_value'] += $qty * $price;
            } else {
                $stats['out_of_stock']++;
            }
        }
        
        // Use paginated products for display (view expects $products variable)
        $products = $paginatedProducts;
        
        // Generate pagination (same as managers use)
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/products' . ($category_id ? '?category_id=' . $category_id : '')
        );
        
        $categories = (new \App\Models\Category())->getAll();
        
        $page = 'products';
        $title = 'Products';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/products_index.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }

    public function create() {
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'system_admin'],
            "You do not have permission to create products. Only Managers and System Administrators can add products to inventory.",
            BASE_URL_PATH . '/dashboard/inventory'
        );
        
        // Start session and check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/inventory/create';
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        
        $categoryModel = new \App\Models\Category();
        $categories = $categoryModel->getAll($companyId);
        $suppliers = (new \App\Models\Supplier())->getActiveForDropdown($companyId);
        
        // Debug: Log category count
        error_log("InventoryController::create() - Loaded " . count($categories) . " categories for company ID: " . $companyId);
        
        // Ensure we have categories - if not, log error
        if (empty($categories)) {
            error_log("WARNING: No categories found for company ID: " . $companyId);
        }
        
        // Check if coming from purchase order
        $productData = null;
        if (isset($_GET['from_po']) && isset($_GET['item_id'])) {
            $purchaseOrderId = (int)$_GET['from_po'];
            $itemId = (int)$_GET['item_id'];
            
            // Get purchase order item data
            $purchaseOrderModel = new \App\Models\PurchaseOrder();
            $order = $purchaseOrderModel->find($purchaseOrderId, $companyId);
            
            if ($order) {
                $items = $purchaseOrderModel->getItems($purchaseOrderId);
                foreach ($items as $item) {
                    if ($item['id'] == $itemId) {
                        // Pre-fill product data from purchase order item
                        $productData = [
                            'name' => $item['product_name'],
                            'sku' => $item['product_sku'] ?? '',
                            'cost_price' => $item['unit_cost'],
                            'quantity' => $item['quantity'],
                            'supplier' => $order['supplier_name'] ?? '',
                            'description' => 'Received from Purchase Order #' . $order['order_number']
                        ];
                        break;
                    }
                }
            }
        }
        
        $page = 'inventory';
        $title = 'Add New Product';
        
        // Capture the view content
        ob_start();
        // Pass variables to view
        $GLOBALS['categories'] = $categories;
        $GLOBALS['suppliers'] = $suppliers;
        if ($productData) {
            $GLOBALS['prefill_product_data'] = $productData;
        }
        include __DIR__ . '/../Views/inventory_form.php';
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

    public function store() {
        // Handle web form submission with session-based authentication
        // Only manager and system_admin can create products - admin role excluded
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'manager']);
        
        try {
            // Get company_id from session (for web users)
            $companyId = $_SESSION['user']['company_id'] ?? 1; // Default to 1 for now
            
            // Validate required fields
            if (empty($_POST['name']) || empty($_POST['category_id']) || empty($_POST['selling_price'])) {
                throw new \Exception('Product name, category, and selling price are required');
            }
            
            // Clean and validate subcategory_id if provided
            $subcategoryId = null;
            if (!empty($_POST['subcategory_id']) && $_POST['subcategory_id'] !== '') {
                $subcategoryId = (int)$_POST['subcategory_id'];
                if ($subcategoryId > 0) {
                    $subcategoryModel = new \App\Models\Subcategory();
                    $subcategory = $subcategoryModel->find($subcategoryId);
                    if (!$subcategory) {
                        throw new \Exception('Invalid subcategory selected');
                    }
                } else {
                    $subcategoryId = null;
                }
            }
            
            // Clean and validate brand_id if provided
            $brandId = null;
            if (!empty($_POST['brand_id']) && $_POST['brand_id'] !== '') {
                $brandId = (int)$_POST['brand_id'];
                if ($brandId > 0) {
                    $brandModel = new \App\Models\Brand();
                    $brand = $brandModel->find($brandId);
                    if (!$brand) {
                        throw new \Exception('Invalid brand selected');
                    }
                } else {
                    $brandId = null;
                }
            }
            
        // Handle SKU - generate unique SKU if empty
        $sku = trim($_POST['sku'] ?? '');
        if (empty($sku)) {
            // Generate unique SKU and ensure it doesn't already exist
            do {
                $sku = 'SKU-' . date('Ymd') . '-' . rand(1000, 9999);
                $existingProduct = $this->productModel->findBySku($sku);
            } while ($existingProduct);
        } else {
            // Check if provided SKU already exists
            $existingProduct = $this->productModel->findBySku($sku);
            if ($existingProduct) {
                throw new \Exception('SKU already exists. Please choose a different SKU.');
            }
        }

        // Generate unique product ID
        $productId = $this->productModel->generateProductId($_POST['name'], $companyId);

        // Check for duplicate products
        $duplicates = $this->productModel->findDuplicates(
            $_POST['name'], 
            $_POST['category_id'], 
            $brandId
        );
        
        if (!empty($duplicates)) {
            $duplicateInfo = [];
            foreach ($duplicates as $dup) {
                $duplicateInfo[] = "Product ID: {$dup['product_id']} (SKU: {$dup['sku']})";
            }
            throw new \Exception('Duplicate product found: ' . implode(', ', $duplicateInfo) . '. Please check existing products.');
        }

        // Check for similar products (fuzzy matching)
        $similarProducts = $this->productModel->findSimilarProducts(
            $_POST['name'], 
            $_POST['category_id'], 
            $brandId
        );
        
        if (!empty($similarProducts)) {
            $similarInfo = [];
            foreach ($similarProducts as $sim) {
                $similarInfo[] = "Product ID: {$sim['product_id']} (Similarity: {$sim['similarity_score']}%)";
            }
            // Log similar products but don't block creation
            error_log("Similar products found for '{$_POST['name']}': " . implode(', ', $similarInfo));
        }
            
            // Extract model_name from specs - prioritize 'model' field from specifications
            $modelName = null;
            if (isset($_POST['specs']['model']) && !empty(trim($_POST['specs']['model']))) {
                $modelName = trim($_POST['specs']['model']);
            }
            // Fallback to 'model_name' in specs if 'model' is not available
            if (empty($modelName) && isset($_POST['specs']['model_name']) && !empty(trim($_POST['specs']['model_name']))) {
                $modelName = trim($_POST['specs']['model_name']);
            }
            // Last fallback: check if model_name was provided directly (for backward compatibility)
            if (empty($modelName) && isset($_POST['model_name']) && !empty(trim($_POST['model_name']))) {
                $modelName = trim($_POST['model_name']);
            }
            
            // Handle supplier_id
            $supplierId = null;
            $supplier = null;
            if (!empty($_POST['supplier_id'])) {
                $supplierId = (int)$_POST['supplier_id'];
                if ($supplierId > 0) {
                    $supplierModel = new \App\Models\Supplier();
                    $supplier = $supplierModel->find($supplierId, $companyId);
                    if (!$supplier) {
                        throw new \Exception('Invalid supplier selected');
                    }
                } else {
                    $supplierId = null;
                }
            }
            
            // Prepare product data
            $productData = [
                'company_id' => $companyId,
                'name' => $_POST['name'],
                'category_id' => $_POST['category_id'],
                'subcategory_id' => $subcategoryId,
                'brand_id' => $brandId,
                'sku' => $sku,
                'model_name' => $modelName,
                'description' => $_POST['description'] ?? null,
                'cost' => $_POST['cost_price'] ?? 0,
                'price' => $_POST['selling_price'],
                'quantity' => $_POST['quantity'] ?? 0,
                'item_location' => $_POST['item_location'] ?? null, // New field for item location
                'supplier_id' => $supplierId,
                'supplier' => $supplier ? $supplier['name'] : null, // Keep supplier name for backward compatibility
                'weight' => $_POST['weight'] ?? null,
                'dimensions' => $_POST['dimensions'] ?? null,
                'available_for_swap' => isset($_POST['available_for_swap']) ? 1 : 0,
                'created_by' => $_SESSION['user']['id'] ?? 1,
                'product_id' => $productId // New unique product ID
            ];
            
            // Handle file upload - Try Cloudinary first, fallback to local storage
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUploaded = false;
                
                // Try Cloudinary upload
                try {
                    $db = \Database::getInstance()->getConnection();
                    $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
                    $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
                    
                    $cloudinaryService = new \App\Services\CloudinaryService();
                    $cloudinaryService->loadFromSettings($settings);
                    
                    if ($cloudinaryService->isConfigured()) {
                        // Upload to Cloudinary
                        $folder = 'sellapp/products';
                        $result = $cloudinaryService->uploadImage($_FILES['image']['tmp_name'], $folder);
                        
                        if ($result['success'] && !empty($result['secure_url'])) {
                            $productData['image_url'] = $result['secure_url'];
                            $imageUploaded = true;
                            error_log("Product image uploaded to Cloudinary: " . $result['secure_url']);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Cloudinary upload failed: " . $e->getMessage());
                }
                
                // Fallback to local storage if Cloudinary failed or not configured
                if (!$imageUploaded) {
                $uploadDir = 'assets/images/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . $_FILES['image']['name'];
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $productData['image_url'] = $uploadPath;
                        error_log("Product image saved locally: " . $uploadPath);
                    }
                }
            }
            
            // Create product
            $productId = $this->productModel->create($productData);
            
            if ($productId) {
                // Handle specifications if provided
                if (isset($_POST['specs']) && is_array($_POST['specs'])) {
                    $specs = [];
                    foreach ($_POST['specs'] as $key => $value) {
                        if (!empty($value)) {
                            $specs[$key] = $value;
                        }
                    }
                    if (!empty($specs)) {
                        $productSpecModel = new ProductSpec();
                        $productSpecModel->setSpecs($productId, $specs);
                    }
                }
                
                // Link product to supplier and create tracking record if supplier is selected
                if ($supplierId) {
                    $supplierModel = new \App\Models\Supplier();
                    $initialQuantity = (int)($_POST['quantity'] ?? 0);
                    $initialCost = floatval($_POST['cost_price'] ?? 0);
                    $initialAmount = $initialQuantity * $initialCost;
                    
                    // Link product to supplier
                    $supplierModel->linkProduct($supplierId, $productId, $companyId, [
                        'supplier_product_code' => $sku ?? null,
                        'unit_cost' => $initialCost
                    ]);
                    
                    // Create or update supplier tracking record
                    $this->updateSupplierTracking($companyId, $supplierId, $productId, $initialQuantity, $initialAmount);
                }
                
                $_SESSION['flash_success'] = 'Product created successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
                exit;
            } else {
                throw new \Exception('Failed to create product');
            }
            
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/create');
            exit;
        }
    }

    public function edit($id) {
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'system_admin'],
            "You do not have permission to edit products. Only Managers and System Administrators can modify inventory.",
            BASE_URL_PATH . '/dashboard/inventory'
        );
        
        // Start session and check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/inventory/edit/' . $id;
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        
        // Clear any existing flash messages when opening edit page
        unset($_SESSION['flash_success']);
        unset($_SESSION['flash_error']);
        
        // Use findInNew since we're working with products_new table
        $product = $this->productModel->findInNew($id, $companyId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        // Load product specifications from product_specs table
        $productSpecModel = new ProductSpec();
        $specs = $productSpecModel->getByProduct($id);
        // Keep specs as array for the view (JavaScript will JSON encode it)
        $product['specs'] = !empty($specs) ? $specs : null;
        
        $categories = (new \App\Models\Category())->getAll();
        $categoryId = $product['category_id'] ?? null;
        $brands = $categoryId ? (new \App\Models\Brand())->getByCategory($categoryId) : [];
        $suppliers = (new \App\Models\Supplier())->getActiveForDropdown($companyId);
        
        $page = 'inventory';
        $title = 'Edit Product';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/inventory_form.php';
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

    public function update($id) {
        // Handle web form submission with session-based authentication
        // Only manager and system_admin can update products - admin role excluded
        \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'manager']);
        
        try {
            // Get company_id from session (for web users)
            $companyId = $_SESSION['user']['company_id'] ?? 1; // Default to 1 for now
            
            // Fetch existing product to get old image URL
            $product = $this->productModel->findInNew($id, $companyId);
            if (!$product) {
                throw new \Exception('Product not found');
            }
            
            // Validate required fields
            if (empty($_POST['name']) || empty($_POST['category_id']) || empty($_POST['selling_price'])) {
                throw new \Exception('Product name, category, and selling price are required');
            }
            
            // Clean and validate subcategory_id if provided
            $subcategoryId = null;
            if (!empty($_POST['subcategory_id']) && $_POST['subcategory_id'] !== '') {
                $subcategoryId = (int)$_POST['subcategory_id'];
                if ($subcategoryId > 0) {
                    $subcategoryModel = new \App\Models\Subcategory();
                    $subcategory = $subcategoryModel->find($subcategoryId);
                    if (!$subcategory) {
                        throw new \Exception('Invalid subcategory selected');
                    }
                } else {
                    $subcategoryId = null;
                }
            }
            
            // Clean and validate brand_id if provided
            $brandId = null;
            if (!empty($_POST['brand_id']) && $_POST['brand_id'] !== '') {
                $brandId = (int)$_POST['brand_id'];
                if ($brandId > 0) {
                    $brandModel = new \App\Models\Brand();
                    $brand = $brandModel->find($brandId);
                    if (!$brand) {
                        throw new \Exception('Invalid brand selected');
                    }
                } else {
                    $brandId = null;
                }
            }
            
            // Extract model_name from specs - prioritize 'model' field from specifications
            $modelName = null;
            if (isset($_POST['specs']['model']) && !empty(trim($_POST['specs']['model']))) {
                $modelName = trim($_POST['specs']['model']);
            }
            // Fallback to 'model_name' in specs if 'model' is not available
            if (empty($modelName) && isset($_POST['specs']['model_name']) && !empty(trim($_POST['specs']['model_name']))) {
                $modelName = trim($_POST['specs']['model_name']);
            }
            // Last fallback: check if model_name was provided directly (for backward compatibility)
            if (empty($modelName) && isset($_POST['model_name']) && !empty(trim($_POST['model_name']))) {
                $modelName = trim($_POST['model_name']);
            }
            
            // Prepare product data
            $productData = [
                'name' => $_POST['name'],
                'category_id' => $_POST['category_id'],
                'subcategory_id' => $subcategoryId,
                'brand_id' => $brandId,
                'model_name' => $modelName,
                'description' => $_POST['description'] ?? null,
                'cost' => $_POST['cost_price'] ?? 0,
                'price' => $_POST['selling_price'],
                'quantity' => $_POST['quantity'] ?? 0,
                'item_location' => $_POST['item_location'] ?? null,
                'supplier' => $_POST['supplier'] ?? null,
                'weight' => $_POST['weight'] ?? null,
                'dimensions' => $_POST['dimensions'] ?? null,
                'available_for_swap' => isset($_POST['available_for_swap']) ? 1 : 0,
                'specs' => !empty($_POST['specs']) ? $_POST['specs'] : null
            ];
            
            // Handle file upload - Try Cloudinary first, fallback to local storage
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUploaded = false;
                
                // Try Cloudinary upload
                try {
                    $db = \Database::getInstance()->getConnection();
                    $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
                    $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
                    
                    $cloudinaryService = new \App\Services\CloudinaryService();
                    $cloudinaryService->loadFromSettings($settings);
                    
                    if ($cloudinaryService->isConfigured()) {
                        // Upload to Cloudinary
                        $folder = 'sellapp/products';
                        $result = $cloudinaryService->uploadImage($_FILES['image']['tmp_name'], $folder);
                        
                        if ($result['success'] && !empty($result['secure_url'])) {
                            // Delete old image from Cloudinary if it exists
                            if (!empty($product['image_url']) && strpos($product['image_url'], 'res.cloudinary.com') !== false) {
                                // Extract public_id from URL
                                $urlParts = parse_url($product['image_url']);
                                if (isset($urlParts['path'])) {
                                    $pathParts = explode('/', trim($urlParts['path'], '/'));
                                    // Cloudinary URLs are typically: /v{version}/{public_id}.{format}
                                    // or: /{folder}/{public_id}.{format}
                                    if (count($pathParts) >= 2) {
                                        $publicId = implode('/', array_slice($pathParts, -2)); // Get folder/product_id
                                        $publicId = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $publicId);
                                        try {
                                            $cloudinaryService->deleteImage($publicId);
                                        } catch (\Exception $e) {
                                            error_log("Failed to delete old Cloudinary image: " . $e->getMessage());
                                        }
                                    }
                                }
                            }
                            
                            $productData['image_url'] = $result['secure_url'];
                            $imageUploaded = true;
                            error_log("Product image uploaded to Cloudinary: " . $result['secure_url']);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Cloudinary upload failed: " . $e->getMessage());
                }
                
                // Fallback to local storage if Cloudinary failed or not configured
                if (!$imageUploaded) {
                $uploadDir = 'assets/images/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                    
                    // Delete old local image if it exists
                    if (!empty($product['image_url']) && strpos($product['image_url'], 'res.cloudinary.com') === false) {
                        $oldImagePath = $product['image_url'];
                        if (file_exists($oldImagePath)) {
                            @unlink($oldImagePath);
                        }
                    }
                
                $fileName = uniqid() . '_' . $_FILES['image']['name'];
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $productData['image_url'] = $uploadPath;
                        error_log("Product image saved locally: " . $uploadPath);
                    }
                }
            }
            
            // Update product
            $success = $this->productModel->update($id, $productData, $companyId);
            
            if ($success) {
                // Handle specifications if provided
                if (isset($_POST['specs']) && is_array($_POST['specs'])) {
                    $specs = [];
                    foreach ($_POST['specs'] as $key => $value) {
                        if (!empty($value)) {
                            $specs[$key] = $value;
                        }
                    }
                    if (!empty($specs)) {
                        $productSpecModel = new ProductSpec();
                        $productSpecModel->setSpecs($id, $specs);
                    }
                }
                
                $_SESSION['flash_success'] = 'Product updated successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
                exit;
            } else {
                throw new \Exception('Failed to update product');
            }
            
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/edit/' . $id);
            exit;
        }
    }

    public function destroy($id) {
        // For form submissions, we need to validate the token
        // But we'll handle this in the ProductController
        $productController = new \App\Controllers\ProductController();
        $productController->delete($id);
    }

    public function view($id) {
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'salesperson', 'technician', 'system_admin'],
            "You do not have permission to view inventory.",
            BASE_URL_PATH . '/dashboard'
        );
        
        // Start session and check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/inventory/view/' . $id;
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        $companyId = $_SESSION['user']['company_id'] ?? 1;
        
        // Use findInNew since we're working with products_new table
        $product = $this->productModel->findInNew($id, $companyId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        $page = 'inventory';
        $title = 'View Product';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/inventory_view.php';
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
     * Helper method to get category ID by name (dynamic lookup)
     */
    private function getCategoryId($categoryName) {
        $category = new \App\Models\Category();
        $categoryData = $category->findByName($categoryName);
        return $categoryData ? $categoryData['id'] : 1; // Default to Phone category
    }

    /**
     * Helper method to get brand ID by name and category (dynamic lookup)
     */
    private function getBrandId($brandName, $categoryId = null) {
        if (!$brandName || !$categoryId) {
            return null;
        }
        
        $brand = new \App\Models\Brand();
        $brandData = $brand->findByNameAndCategory($brandName, $categoryId);
        return $brandData ? $brandData['id'] : null;
    }

    /**
     * Delete a product
     */
    public function delete($id) {
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'system_admin'],
            "You do not have permission to delete products. Only Managers and System Administrators can delete inventory items.",
            BASE_URL_PATH . '/dashboard/inventory'
        );
        
        try {
            // Start session and check authentication
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check if user is logged in
            $userData = $_SESSION['user'] ?? null;
            if (!$userData) {
                $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
                $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/inventory';
                $redirectParam = 'redirect=' . urlencode($currentPath);
                header('Location: ' . $basePath . '/?' . $redirectParam);
                exit;
            }
            
            // Handle web authentication
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'manager']);
            
            $companyId = $_SESSION['user']['company_id'] ?? null;
            if (!$companyId) {
                throw new \Exception('Company ID not found in session');
            }
            
            // Verify the product exists and belongs to the company
            $product = $this->productModel->findInNew($id, $companyId);
            if (!$product) {
                throw new \Exception('Product not found or access denied');
            }
            
            // Delete the product
            $deleted = $this->productModel->delete($id, $companyId);
            
            if ($deleted) {
                // Also delete associated product specifications
                $productSpecModel = new ProductSpec();
                $productSpecModel->deleteByProductId($id);
                
                $_SESSION['success_message'] = "Product '{$product['name']}' has been deleted successfully.";
            } else {
                throw new \Exception('Failed to delete product');
            }
            
        } catch (\Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
        
        // Redirect back to inventory list
        header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
        exit;
    }

    /**
     * Bulk delete multiple products
     */
    public function bulkDelete() {
        header('Content-Type: application/json');
        
        // BLOCK ADMIN IMMEDIATELY - Must be first thing in method
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (\App\Helpers\AdminBlockHelper::shouldBlockAdmin(['manager', 'system_admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to delete products']);
            exit;
        }
        
        try {
            // Start session and check authentication
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check if user is logged in
            $userData = $_SESSION['user'] ?? null;
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            
            // Handle web authentication
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'manager']);
            
            $companyId = $_SESSION['user']['company_id'] ?? null;
            if (!$companyId) {
                throw new \Exception('Company ID not found in session');
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                throw new \Exception('No product IDs provided');
            }
            
            $deletedCount = 0;
            $errors = [];
            $productSpecModel = new ProductSpec();
            
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id <= 0) continue;
                
                // Verify the product exists and belongs to the company
                $product = $this->productModel->findInNew($id, $companyId);
                if (!$product) {
                    $errors[] = "Product ID {$id} not found or access denied";
                    continue;
                }
                
                // Delete the product
                $deleted = $this->productModel->delete($id, $companyId);
                
                if ($deleted) {
                    // Also delete associated product specifications
                    $productSpecModel->deleteByProductId($id);
                    $deletedCount++;
                } else {
                    $errors[] = "Failed to delete product '{$product['name']}' (ID: {$id})";
                }
            }
            
            if ($deletedCount > 0) {
                $message = "Successfully deleted {$deletedCount} product(s).";
                if (!empty($errors)) {
                    $message .= " " . count($errors) . " error(s) occurred.";
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ]);
            } else {
                throw new \Exception('No products were deleted. ' . implode(' ', $errors));
            }
            
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }

    /**
     * API: Live search inventory (returns JSON)
     */
    public function apiSearch() {
        // Ensure JSON only output
        ob_start();
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            $companyId = $_SESSION['user']['company_id'] ?? 1;
            $query = trim($_GET['q'] ?? '');
            $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;

            if ($query === '') {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }

            $results = $this->productModel->search($companyId, $query, $categoryId);

            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Search failed',
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }
    
    /**
     * Update supplier product tracking
     * Creates or updates tracking record for supplier-product relationship
     */
    private function updateSupplierTracking($companyId, $supplierId, $productId, $quantity, $amount) {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if tracking table exists
            $tableExists = $db->query("SHOW TABLES LIKE 'supplier_product_tracking'")->rowCount() > 0;
            if (!$tableExists) {
                error_log("supplier_product_tracking table does not exist. Please run the migration.");
                return;
            }
            
            // Check if record exists
            $checkStmt = $db->prepare("
                SELECT id, total_quantity_received, total_amount_spent 
                FROM supplier_product_tracking 
                WHERE supplier_id = ? AND product_id = ? AND company_id = ?
            ");
            $checkStmt->execute([$supplierId, $productId, $companyId]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing record
                $newTotalQuantity = $existing['total_quantity_received'] + $quantity;
                $newTotalAmount = $existing['total_amount_spent'] + $amount;
                
                $updateStmt = $db->prepare("
                    UPDATE supplier_product_tracking 
                    SET total_quantity_received = ?,
                        total_amount_spent = ?,
                        last_restock_quantity = ?,
                        last_restock_amount = ?,
                        last_restock_date = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $newTotalQuantity,
                    $newTotalAmount,
                    $quantity,
                    $amount,
                    $existing['id']
                ]);
            } else {
                // Create new record
                $insertStmt = $db->prepare("
                    INSERT INTO supplier_product_tracking 
                    (company_id, supplier_id, product_id, total_quantity_received, total_amount_spent,
                     last_restock_quantity, last_restock_amount, last_restock_date, first_received_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                ");
                $insertStmt->execute([
                    $companyId,
                    $supplierId,
                    $productId,
                    $quantity,
                    $amount,
                    $quantity,
                    $amount
                ]);
            }
        } catch (\Exception $e) {
            error_log("Error updating supplier tracking: " . $e->getMessage());
        }
    }
}
