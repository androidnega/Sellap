<?php
namespace App\Controllers;

use Exception;
use App\Middleware\AuthMiddleware;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Subcategory;

class ProductController {
    private $product;
    private $category;
    private $brand;
    private $subcategory;

    public function __construct() {
        $this->product = new Product();
        $this->category = new Category();
        $this->brand = new Brand();
        $this->subcategory = new Subcategory();
    }

    /**
     * Display product list for managers
     */
    public function index() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $category_id = $_GET['category_id'] ?? null;
        $products = $this->product->findByCompany($companyId, 200, $category_id);
        $categories = $this->category->getAll();
        
        $title = 'Products';
        $viewFile = __DIR__ . '/../Views/products_index.php';
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
     * Show create product form
     */
    public function create() {
        $payload = AuthMiddleware::handle(['manager']);
        
        $categories = $this->category->getAll();
        
        $title = 'New Product';
        $viewFile = __DIR__ . '/../Views/products_create.php';
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
     * Show edit product form
     */
    public function edit($id) {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $product = $this->product->find($id, $companyId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        $categories = $this->category->getAll();
        
        $title = 'Edit Product';
        $viewFile = __DIR__ . '/../Views/products_edit.php';
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
     * Store new product
     */
    public function store() {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        try {
            $this->validateRequest();
            
            $productData = $this->prepareProductData($_POST, $companyId);
            $productId = $this->product->create($productData);
            
            if ($productId) {
                $_SESSION['flash_success'] = 'Product created successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/edit/' . $productId);
            } else {
                $_SESSION['flash_error'] = 'Failed to create product';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/create');
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/create');
        }
        exit;
    }

    /**
     * Update existing product
     */
    public function update($id) {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $product = $this->product->find($id, $companyId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        try {
            $this->validateRequest($id);
            
            $productData = $this->prepareProductData($_POST, $companyId);
            $success = $this->product->update($id, $productData, $companyId);
            
            if ($success) {
                $_SESSION['flash_success'] = 'Product updated successfully';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/edit/' . $id);
            } else {
                $_SESSION['flash_error'] = 'Failed to update product';
                header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/edit/' . $id);
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete product
     */
    public function delete($id) {
        $payload = AuthMiddleware::handle(['manager']);
        $companyId = $payload->company_id;
        
        $product = $this->product->find($id, $companyId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
            exit;
        }
        
        $success = $this->product->delete($id, $companyId);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Product deleted successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to delete product';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/inventory');
        exit;
    }

    /**
     * API endpoint: Get brands by category
     */
    public function apiGetBrandsByCategory($categoryId) {
        header('Content-Type: application/json');
        
        try {
            $brands = $this->brand->getByCategory($categoryId);
            echo json_encode([
                'success' => true,
                'data' => $brands
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Get subcategories by category
     */
    public function apiGetSubcategoriesByCategory($categoryId) {
        header('Content-Type: application/json');
        
        $subcategories = $this->subcategory->getByCategory($categoryId);
        echo json_encode([
            'success' => true,
            'data' => $subcategories
        ]);
        exit;
    }

    /**
     * API endpoint: Get brand specifications
     */
    public function apiGetBrandSpecs($brandId) {
        header('Content-Type: application/json');
        
        try {
            // Get category from query parameter or from brand's category_id
            $categoryId = $_GET['category_id'] ?? null;
            $categoryName = $_GET['category_name'] ?? null;
            
            // Check if brandId is numeric (ID) or string (name)
            if (is_numeric($brandId)) {
                $brand = $this->brand->find($brandId);
                $brandName = $brand ? $brand['name'] : $brandId;
                
                // If category not provided, try to get it from brand
                if (!$categoryId && !$categoryName && $brand && isset($brand['category_id'])) {
                    $categoryId = $brand['category_id'];
                }
            } else {
                // It's a brand name, use it directly
                $brandName = $brandId;
            }
            
            // Get category name if we have category_id
            if ($categoryId && !$categoryName) {
                $category = $this->category->find($categoryId);
                if ($category) {
                    $categoryName = strtolower($category['name'] ?? '');
                }
            } elseif ($categoryName) {
                $categoryName = strtolower($categoryName);
            }

            // Laptop-specific specs for different brands
            $laptopSpecs = [
                'Apple' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., MacBook Pro 16" M3 Pro', 'tooltip' => 'Enter the specific MacBook model'],
                    ['name' => 'screen_size', 'label' => 'Screen Size', 'type' => 'select', 'required' => true, 'options' => ['13"', '14"', '15"', '16"'], 'tooltip' => 'Select the screen size'],
                    ['name' => 'processor', 'label' => 'Processor', 'type' => 'select', 'required' => true, 'options' => ['M1', 'M1 Pro', 'M1 Max', 'M2', 'M2 Pro', 'M2 Max', 'M3', 'M3 Pro', 'M3 Max', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9'], 'tooltip' => 'Select the processor'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => true, 'options' => ['8GB', '16GB', '24GB', '32GB', '36GB', '48GB', '64GB', '96GB', '128GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'storage_type', 'label' => 'Storage Type', 'type' => 'select', 'required' => true, 'options' => ['SSD', 'HDD', 'SSD + HDD'], 'tooltip' => 'Select the storage type'],
                    ['name' => 'storage', 'label' => 'Storage Capacity', 'type' => 'select', 'required' => true, 'options' => ['256GB', '512GB', '1TB', '2TB', '4TB', '8TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'operating_system', 'label' => 'Operating System', 'type' => 'select', 'required' => true, 'options' => ['macOS Sonoma', 'macOS Ventura', 'macOS Monterey', 'macOS Big Sur', 'macOS Catalina', 'macOS Mojave', 'macOS High Sierra', 'Other macOS'], 'tooltip' => 'Select the macOS version'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Space Gray, Silver', 'tooltip' => 'Enter the device color'],
                    ['name' => 'year', 'label' => 'Year', 'type' => 'select', 'required' => false, 'options' => ['2020', '2021', '2022', '2023', '2024', '2025'], 'tooltip' => 'Select the model year'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => false, 'options' => ['New', 'Refurbished', 'Used - Excellent', 'Used - Good', 'Used - Fair'], 'tooltip' => 'Select the condition'],
                ],
                'HP' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., HP Pavilion 15', 'tooltip' => 'Enter the specific HP laptop model'],
                    ['name' => 'screen_size', 'label' => 'Screen Size', 'type' => 'select', 'required' => true, 'options' => ['13.3"', '14"', '15.6"', '17.3"'], 'tooltip' => 'Select the screen size'],
                    ['name' => 'processor', 'label' => 'Processor', 'type' => 'select', 'required' => true, 'options' => ['Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'AMD Ryzen 3', 'AMD Ryzen 5', 'AMD Ryzen 7', 'AMD Ryzen 9'], 'tooltip' => 'Select the processor'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => true, 'options' => ['4GB', '8GB', '16GB', '32GB', '64GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'storage_type', 'label' => 'Storage Type', 'type' => 'select', 'required' => true, 'options' => ['SSD', 'HDD', 'SSD + HDD', 'NVMe SSD'], 'tooltip' => 'Select the storage type (SSD or HDD)'],
                    ['name' => 'storage', 'label' => 'Storage Capacity', 'type' => 'select', 'required' => true, 'options' => ['128GB', '256GB', '512GB', '1TB', '2TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'operating_system', 'label' => 'Operating System', 'type' => 'select', 'required' => true, 'options' => ['Windows 11', 'Windows 10', 'Windows 8.1', 'Windows 7', 'Linux', 'No OS'], 'tooltip' => 'Select the Windows version or operating system'],
                    ['name' => 'graphics', 'label' => 'Graphics', 'type' => 'select', 'required' => false, 'options' => ['Integrated', 'NVIDIA GeForce GTX', 'NVIDIA GeForce RTX', 'AMD Radeon'], 'tooltip' => 'Select the graphics card'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Silver, Black, Blue', 'tooltip' => 'Enter the device color'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => false, 'options' => ['New', 'Refurbished', 'Used - Excellent', 'Used - Good', 'Used - Fair'], 'tooltip' => 'Select the condition'],
                ],
                'Dell' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Dell XPS 15', 'tooltip' => 'Enter the specific Dell laptop model'],
                    ['name' => 'screen_size', 'label' => 'Screen Size', 'type' => 'select', 'required' => true, 'options' => ['13.3"', '14"', '15.6"', '17.3"'], 'tooltip' => 'Select the screen size'],
                    ['name' => 'processor', 'label' => 'Processor', 'type' => 'select', 'required' => true, 'options' => ['Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'AMD Ryzen 3', 'AMD Ryzen 5', 'AMD Ryzen 7', 'AMD Ryzen 9'], 'tooltip' => 'Select the processor'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => true, 'options' => ['4GB', '8GB', '16GB', '32GB', '64GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'storage_type', 'label' => 'Storage Type', 'type' => 'select', 'required' => true, 'options' => ['SSD', 'HDD', 'SSD + HDD', 'NVMe SSD'], 'tooltip' => 'Select the storage type (SSD or HDD)'],
                    ['name' => 'storage', 'label' => 'Storage Capacity', 'type' => 'select', 'required' => true, 'options' => ['128GB', '256GB', '512GB', '1TB', '2TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'operating_system', 'label' => 'Operating System', 'type' => 'select', 'required' => true, 'options' => ['Windows 11', 'Windows 10', 'Windows 8.1', 'Windows 7', 'Linux', 'No OS'], 'tooltip' => 'Select the Windows version or operating system'],
                    ['name' => 'graphics', 'label' => 'Graphics', 'type' => 'select', 'required' => false, 'options' => ['Integrated', 'NVIDIA GeForce GTX', 'NVIDIA GeForce RTX', 'AMD Radeon'], 'tooltip' => 'Select the graphics card'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Silver, Black, Platinum', 'tooltip' => 'Enter the device color'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => false, 'options' => ['New', 'Refurbished', 'Used - Excellent', 'Used - Good', 'Used - Fair'], 'tooltip' => 'Select the condition'],
                ],
            ];
            
            // Repair Parts specs for different brands
            $repairPartsSpecs = [
                'Apple' => [
                    ['name' => 'part_type', 'label' => 'Part Type', 'type' => 'select', 'required' => true, 'options' => ['Screen/Display', 'Battery', 'Charging Port', 'Camera Module', 'Logic Board', 'Speaker', 'Microphone', 'Home Button', 'Face ID Module', 'Back Glass', 'Frame/Chassis', 'Other'], 'tooltip' => 'Select the type of repair part'],
                    ['name' => 'compatible_models', 'label' => 'Compatible Models', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., iPhone 12, iPhone 13, iPhone 14', 'tooltip' => 'Enter compatible iPhone/iPad models'],
                    ['name' => 'part_number', 'label' => 'Part Number', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., 661-12345', 'tooltip' => 'Enter the Apple part number if available'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => true, 'options' => ['New', 'Refurbished', 'Used - Working', 'Used - Needs Testing'], 'tooltip' => 'Select the condition of the part'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, White, Space Gray', 'tooltip' => 'Enter the color if applicable'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false, 'placeholder' => 'Additional notes for technicians', 'tooltip' => 'Enter any additional information'],
                ],
                'Samsung' => [
                    ['name' => 'part_type', 'label' => 'Part Type', 'type' => 'select', 'required' => true, 'options' => ['Screen/Display', 'Battery', 'Charging Port', 'Camera Module', 'Main Board', 'Speaker', 'Microphone', 'Fingerprint Sensor', 'Back Glass', 'Frame/Chassis', 'Other'], 'tooltip' => 'Select the type of repair part'],
                    ['name' => 'compatible_models', 'label' => 'Compatible Models', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Galaxy S21, Galaxy S22, Galaxy Note 20', 'tooltip' => 'Enter compatible Samsung models'],
                    ['name' => 'part_number', 'label' => 'Part Number', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., GH82-12345', 'tooltip' => 'Enter the Samsung part number if available'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => true, 'options' => ['New', 'Refurbished', 'Used - Working', 'Used - Needs Testing'], 'tooltip' => 'Select the condition of the part'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, White, Blue', 'tooltip' => 'Enter the color if applicable'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false, 'placeholder' => 'Additional notes for technicians', 'tooltip' => 'Enter any additional information'],
                ],
                'default_repair_parts' => [
                    ['name' => 'part_type', 'label' => 'Part Type', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Screen, Battery, Charging Port', 'tooltip' => 'Enter the type of repair part'],
                    ['name' => 'compatible_models', 'label' => 'Compatible Models', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., iPhone 12, Samsung Galaxy S21', 'tooltip' => 'Enter compatible device models'],
                    ['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'required' => true, 'options' => ['New', 'Refurbished', 'Used - Working', 'Used - Needs Testing'], 'tooltip' => 'Select the condition of the part'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, White', 'tooltip' => 'Enter the color if applicable'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false, 'placeholder' => 'Additional notes for technicians', 'tooltip' => 'Enter any additional information'],
                ],
            ];

            // Check if category is Laptops - return laptop specs
            if ($categoryName && (strpos($categoryName, 'laptop') !== false)) {
                $normalizedBrandName = strtolower(trim($brandName));
                $specs = null;
                
                // Try to find brand-specific laptop specs
                foreach ($laptopSpecs as $specBrand => $specData) {
                    if (strtolower($specBrand) === $normalizedBrandName) {
                        $specs = $specData;
                        break;
                    }
                }
                
                // If no brand-specific specs found, return generic laptop specs
                if (!$specs) {
                    $specs = [
                        ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter laptop model', 'tooltip' => 'Enter the specific laptop model'],
                        ['name' => 'screen_size', 'label' => 'Screen Size', 'type' => 'select', 'required' => false, 'options' => ['13"', '13.3"', '14"', '15.6"', '16"', '17.3"'], 'tooltip' => 'Select the screen size'],
                        ['name' => 'processor', 'label' => 'Processor', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Intel Core i5', 'tooltip' => 'Enter the processor'],
                        ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '8GB', '16GB', '32GB', '64GB'], 'tooltip' => 'Select the RAM size'],
                        ['name' => 'storage_type', 'label' => 'Storage Type', 'type' => 'select', 'required' => false, 'options' => ['SSD', 'HDD', 'SSD + HDD', 'NVMe SSD'], 'tooltip' => 'Select the storage type (SSD or HDD)'],
                        ['name' => 'storage', 'label' => 'Storage Capacity', 'type' => 'select', 'required' => false, 'options' => ['128GB', '256GB', '512GB', '1TB', '2TB'], 'tooltip' => 'Select the storage capacity'],
                        ['name' => 'operating_system', 'label' => 'Operating System', 'type' => 'select', 'required' => false, 'options' => ['Windows 11', 'Windows 10', 'Windows 8.1', 'Windows 7', 'macOS', 'Linux', 'No OS'], 'tooltip' => 'Select the operating system'],
                        ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'Enter device color', 'tooltip' => 'Enter the device color'],
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $specs
                ]);
                exit;
            }
            
            // Check if category is Repair Parts - return repair parts specs
            if ($categoryName && (strpos($categoryName, 'repair') !== false || strpos($categoryName, 'part') !== false)) {
                $normalizedBrandName = strtolower(trim($brandName));
                $specs = null;
                
                // Try to find brand-specific repair parts specs
                foreach ($repairPartsSpecs as $specBrand => $specData) {
                    if (strtolower($specBrand) === $normalizedBrandName) {
                        $specs = $specData;
                        break;
                    }
                }
                
                // If no brand-specific specs found, return default repair parts specs
                if (!$specs) {
                    $specs = $repairPartsSpecs['default_repair_parts'];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $specs
                ]);
                exit;
            }

            // Brand-specific spec field definitions (for phones and other categories)
            $specMap = [
                'Apple' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., A2896 (iPhone 14 Pro Max)', 'tooltip' => 'Enter the specific model number'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Space Black, Silver', 'tooltip' => 'Enter the device color'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'battery_health', 'label' => 'Battery Health', 'type' => 'select', 'required' => false, 'options' => ['100%', '95%', '90%', '85%', '80%', '75%', '70%', '65%', '60%', 'Below 60%'], 'tooltip' => 'Select the battery health percentage'],
                    ['name' => 'imei', 'label' => 'IMEI (optional)', 'type' => 'text', 'required' => false, 'placeholder' => '15-digit IMEI number', 'tooltip' => 'Enter the 15-digit IMEI number'],
                ],
                'Samsung' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., SM-G998B (Galaxy S21 Ultra)', 'tooltip' => 'Enter the specific model number'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB', '16GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'network', 'label' => 'Network Type', 'type' => 'select', 'required' => false, 'options' => ['4G LTE', '5G', '4G/5G'], 'tooltip' => 'Select the network type (optional)'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Phantom Black, Phantom Silver', 'tooltip' => 'Enter the device color'],
                ],
                'Tecno' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Camon 20 Pro', 'tooltip' => 'Enter the specific model name'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => false, 'options' => ['32GB', '64GB', '128GB', '256GB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['3GB', '4GB', '6GB', '8GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, Blue, Gold', 'tooltip' => 'Enter the device color'],
                ],
                'Infinix' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Note 12 Pro', 'tooltip' => 'Enter the specific model name'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => false, 'options' => ['32GB', '64GB', '128GB', '256GB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['3GB', '4GB', '6GB', '8GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, Blue, Gold', 'tooltip' => 'Enter the device color'],
                ],
                'Huawei' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., P50 Pro', 'tooltip' => 'Enter the specific model name'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => false, 'options' => ['64GB', '128GB', '256GB', '512GB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, White, Gold', 'tooltip' => 'Enter the device color'],
                ],
                'Xiaomi' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., Redmi Note 12', 'tooltip' => 'Enter the specific model name'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => false, 'options' => ['64GB', '128GB', '256GB', '512GB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Black, Blue, Green', 'tooltip' => 'Enter the device color'],
                ],
                // Default generic phone specs
                'default_phone' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'Enter model name', 'tooltip' => 'Enter the specific model name'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => false, 'options' => ['32GB', '64GB', '128GB', '256GB', '512GB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'Enter device color', 'tooltip' => 'Enter the device color'],
                ],
                
                // Common brand variations (case-insensitive matching)
                'apple' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., A2896 (iPhone 14 Pro Max)', 'tooltip' => 'Enter the specific model number'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Space Black, Silver', 'tooltip' => 'Enter the device color'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'battery_health', 'label' => 'Battery Health', 'type' => 'select', 'required' => false, 'options' => ['100%', '95%', '90%', '85%', '80%', '75%', '70%', '65%', '60%', 'Below 60%'], 'tooltip' => 'Select the battery health percentage'],
                    ['name' => 'imei', 'label' => 'IMEI (optional)', 'type' => 'text', 'required' => false, 'placeholder' => '15-digit IMEI number', 'tooltip' => 'Enter the 15-digit IMEI number'],
                ],
                'samsung' => [
                    ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., SM-G998B (Galaxy S21 Ultra)', 'tooltip' => 'Enter the specific model number'],
                    ['name' => 'storage', 'label' => 'Storage', 'type' => 'select', 'required' => true, 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'tooltip' => 'Select the storage capacity'],
                    ['name' => 'ram', 'label' => 'RAM', 'type' => 'select', 'required' => false, 'options' => ['4GB', '6GB', '8GB', '12GB', '16GB'], 'tooltip' => 'Select the RAM size'],
                    ['name' => 'network', 'label' => 'Network Type', 'type' => 'select', 'required' => false, 'options' => ['4G LTE', '5G', '4G/5G'], 'tooltip' => 'Select the network type (optional)'],
                    ['name' => 'color', 'label' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g., Phantom Black, Phantom Silver', 'tooltip' => 'Enter the device color'],
                ]
            ];

            // Flexible brand matching - normalize brand name for case-insensitive matching
            $normalizedBrandName = strtolower(trim($brandName));
            $specs = null;
            
            // Try exact match first
            if (isset($specMap[$brandName])) {
                $specs = $specMap[$brandName];
            } elseif (isset($specMap[$normalizedBrandName])) {
                $specs = $specMap[$normalizedBrandName];
            } else {
                // Try flexible matching for common variations
                foreach ($specMap as $specBrand => $specData) {
                    $normalizedSpecBrand = strtolower(trim($specBrand));
                    if ($normalizedBrandName === $normalizedSpecBrand) {
                        $specs = $specData;
                        break;
                    }
                }
            }
            
            // Fallback to default if no match found
            if (!$specs) {
                // Check if it's a phone category (by checking if category has brands)
                $categoryId = $brand ? ($brand['category_id'] ?? null) : null;
                if ($categoryId) {
                    // Try to get category name to determine if it's phone-related
                    $categoryModel = new \App\Models\Category();
                    $category = $categoryModel->find($categoryId);
                    if ($category) {
                        $categoryName = strtolower($category['name'] ?? '');
                        if (strpos($categoryName, 'phone') !== false || 
                            strpos($categoryName, 'smart') !== false || 
                            strpos($categoryName, 'mobile') !== false) {
                            $specs = $specMap['default_phone'];
                        } else {
                            $specs = [];
                        }
                    } else {
                        $specs = $specMap['default_phone'];
                    }
                } else {
                    $specs = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $specs
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
        exit;
    }

    /**
     * Enhanced product request validation with comprehensive checks
     */
    private function validateRequest($productId = null) {
        $errors = [];
        
        // Required fields validation
        $requiredFields = [
            'name' => 'Product name',
            'category_id' => 'Category',
            'sku' => 'SKU/Barcode',
            'model_name' => 'Model name',
            'cost_price' => 'Cost price',
            'selling_price' => 'Selling price',
            'quantity' => 'Quantity/Stock'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($_POST[$field]) && $_POST[$field] !== '0') {
                $errors[] = $label . ' is required';
            }
        }
        
        // Product name validation
        if (!empty($_POST['name'])) {
            $name = trim($_POST['name']);
            if (strlen($name) < 3) {
                $errors[] = 'Product name must be at least 3 characters long';
            }
            if (strlen($name) > 255) {
                $errors[] = 'Product name must not exceed 255 characters';
            }
        }
        
        // SKU validation and uniqueness check
        if (!empty($_POST['sku'])) {
            $sku = trim($_POST['sku']);
            if (strlen($sku) < 2) {
                $errors[] = 'SKU must be at least 2 characters long';
            }
            if (strlen($sku) > 100) {
                $errors[] = 'SKU must not exceed 100 characters';
            }
            
            $existingProduct = $this->product->findBySku($sku);
            if ($existingProduct && (!$productId || $existingProduct['id'] != $productId)) {
                $errors[] = 'SKU already exists. Please use a unique SKU';
            }
        }
        
        // Model name validation
        if (!empty($_POST['model_name'])) {
            $modelName = trim($_POST['model_name']);
            if (strlen($modelName) > 100) {
                $errors[] = 'Model name must not exceed 100 characters';
            }
        }
        
        // Price validation
        $costPrice = floatval($_POST['cost_price'] ?? 0);
        $sellingPrice = floatval($_POST['selling_price'] ?? $_POST['price'] ?? 0);
        
        if ($costPrice < 0) {
            $errors[] = 'Cost price cannot be negative';
        }
        if ($costPrice > 999999.99) {
            $errors[] = 'Cost price cannot exceed ₵999,999.99';
        }
        
        if ($sellingPrice < 0) {
            $errors[] = 'Selling price cannot be negative';
        }
        if ($sellingPrice > 999999.99) {
            $errors[] = 'Selling price cannot exceed ₵999,999.99';
        }
        
        if ($costPrice > $sellingPrice && $sellingPrice > 0) {
            $errors[] = 'Selling price should be higher than cost price for profitability';
        }
        
        // Quantity validation
        $quantity = intval($_POST['quantity'] ?? 0);
        if ($quantity < 0) {
            $errors[] = 'Quantity cannot be negative';
        }
        if ($quantity > 99999) {
            $errors[] = 'Quantity cannot exceed 99,999 units';
        }
        
        // Category validation
        if (!empty($_POST['category_id'])) {
            $category = $this->category->find($_POST['category_id']);
            if (!$category) {
                $errors[] = 'Invalid category selected';
            } else {
                // Brand requirement check for phones
                $categoryName = strtolower($category['name']);
                if (strpos($categoryName, 'phone') !== false && empty($_POST['brand_id'])) {
                    $errors[] = 'Brand is required for phone products';
                }
            }
        }
        
        // Brand validation
        if (!empty($_POST['brand_id'])) {
            $brand = $this->brand->find($_POST['brand_id']);
            if (!$brand) {
                $errors[] = 'Invalid brand selected';
            }
        }
        
        // Subcategory validation
        if (!empty($_POST['subcategory_id'])) {
            $subcategory = $this->subcategory->find($_POST['subcategory_id']);
            if (!$subcategory) {
                $errors[] = 'Invalid subcategory selected';
            }
        }
        
        // Description validation
        if (!empty($_POST['description'])) {
            $description = trim($_POST['description']);
            if (strlen($description) > 2000) {
                $errors[] = 'Description must not exceed 2000 characters';
            }
        }
        
        // Supplier validation
        if (!empty($_POST['supplier'])) {
            $supplier = trim($_POST['supplier']);
            if (strlen($supplier) > 255) {
                $errors[] = 'Supplier name must not exceed 255 characters';
            }
        }
        
        // Weight/Dimensions validation
        if (!empty($_POST['weight'])) {
            $weight = trim($_POST['weight']);
            if (strlen($weight) > 100) {
                $errors[] = 'Weight/Dimensions must not exceed 100 characters';
            }
        }
        
        // Image upload validation
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                $errors[] = 'Image must be a JPEG, PNG, or GIF file';
            }
            
            if ($_FILES['image']['size'] > $maxSize) {
                $errors[] = 'Image size must not exceed 10MB';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('. ', $errors));
        }
    }

    /**
     * Prepare product data for saving
     */
    private function prepareProductData($data, $companyId) {
        // Handle available_for_swap logic
        $allowSwap = false;
        if (!empty($data['category_id'])) {
            $category = $this->category->find($data['category_id']);
            if ($category) {
                $categoryName = strtolower($category['name']);
                if (strpos($categoryName, 'phone') !== false || strpos($categoryName, 'smart') !== false) {
                    $allowSwap = true;
                }
            }
        }
        
        // Check if swap is explicitly enabled by user
        $swapEnabled = isset($data['available_for_swap']) && $data['available_for_swap'] == '1';
        
        // Handle specs JSON
        $specs = [];
        if (!empty($data['specs']) && is_array($data['specs'])) {
            $specs = $data['specs'];
        }
        
        // Handle image upload
        $imageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/images/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                $imageUrl = $filePath;
            }
        }
        
        // Generate SKU if not provided
        $sku = $data['sku'] ?? $this->generateSku($data['name']);
        
        return [
            'name' => trim($data['name']),
            'sku' => $sku,
            'model_name' => trim($data['model_name'] ?? ''),
            'category_id' => $data['category_id'],
            'brand_id' => !empty($data['brand_id']) ? $data['brand_id'] : null,
            'subcategory_id' => !empty($data['subcategory_id']) ? $data['subcategory_id'] : null,
            'description' => trim($data['description'] ?? ''),
            'image_url' => $imageUrl,
            'supplier' => trim($data['supplier'] ?? ''),
            'weight' => trim($data['weight'] ?? ''),
            'dimensions' => trim($data['dimensions'] ?? ''),
            'cost' => floatval($data['cost_price'] ?? $data['cost'] ?? 0),
            'cost_price' => floatval($data['cost_price'] ?? $data['cost'] ?? 0),
            'price' => floatval($data['selling_price'] ?? $data['price'] ?? 0),
            'selling_price' => floatval($data['selling_price'] ?? $data['price'] ?? 0),
            'quantity' => intval($data['quantity']),
            'available_for_swap' => $allowSwap && $swapEnabled ? 1 : 0,
            'specs' => !empty($specs) ? $specs : null,
            'status' => 'available',
            'company_id' => $companyId
        ];
    }

    /**
     * Generate SKU from product name
     */
    private function generateSku($name) {
        $cleanName = preg_replace('/[^A-Z0-9]/i', '', $name);
        $prefix = strtoupper(substr($cleanName, 0, 6));
        $suffix = strtoupper(substr(md5(uniqid()), 0, 4));
        return $prefix . '-' . $suffix;
    }

    // ==================== API METHODS ====================

    /**
     * API endpoint: List all products
     */
    public function apiList() {
        header('Content-Type: application/json');
        
        try {
            // Try session-based authentication first for web requests
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if ($userData && in_array($userData['role'], ['manager', 'salesperson', 'technician', 'system_admin'])) {
                $companyId = $userData['company_id'] ?? null;
            } else {
                // Fall back to JWT authentication
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            }
            
            $category_id = $_GET['category_id'] ?? null;
            $limit = intval($_GET['limit'] ?? 100);
            $offset = intval($_GET['offset'] ?? 0);
            
            $products = $this->product->findByCompany($companyId, $limit, $category_id, $offset);
            
            echo json_encode([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Get single product
     */
    public function apiShow($id) {
        header('Content-Type: application/json');
        
        try {
            $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
            $companyId = $payload->company_id;
            
            $product = $this->product->find($id, $companyId);
            
            if (!$product) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Product not found'
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $product
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: List products by category
     */
    public function apiListByCategory($category) {
        header('Content-Type: application/json');
        
        try {
            // Try session-based authentication first for web requests
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userData = $_SESSION['user'] ?? null;
            if ($userData && in_array($userData['role'], ['manager', 'salesperson', 'technician', 'system_admin'])) {
                $companyId = $userData['company_id'] ?? null;
            } else {
                // Fall back to JWT authentication
                $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
                $companyId = $payload->company_id;
            }
            
            // Find category by name or ID
            $categoryId = null;
            if (is_numeric($category)) {
                $categoryId = $category;
            } else {
                $categoryObj = $this->category->findByName($category);
                if ($categoryObj) {
                    $categoryId = $categoryObj['id'];
                }
            }
            
            if (!$categoryId) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Category not found'
                ]);
                exit;
            }
            
            $products = $this->product->findByCompany($companyId, 100, $categoryId);
            
            echo json_encode([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Get products available for swap
     */
    public function apiSwapProducts() {
        header('Content-Type: application/json');
        
        try {
            $payload = AuthMiddleware::handle(['manager', 'salesperson', 'technician']);
            $companyId = $payload->company_id;
            
            $products = $this->product->findSwapProducts($companyId);
            
            echo json_encode([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Update product quantity
     */
    public function apiUpdateQuantity($id) {
        header('Content-Type: application/json');
        
        try {
            $payload = AuthMiddleware::handle(['manager', 'salesperson']);
            $companyId = $payload->company_id;
            
            $input = json_decode(file_get_contents('php://input'), true);
            $quantity = intval($input['quantity'] ?? 0);
            
            if ($quantity < 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Quantity must be non-negative'
                ]);
                exit;
            }
            
            $product = $this->product->find($id, $companyId);
            if (!$product) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Product not found'
                ]);
                exit;
            }
            
            $success = $this->product->updateQuantity($id, $quantity, $companyId);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Quantity updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update quantity'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * API endpoint: Get all categories
     */
    public function apiGetCategories() {
        header('Content-Type: application/json');
        
        try {
            // Handle web authentication
            \App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);
            
            $categories = $this->category->getAll();
            
            echo json_encode([
                'success' => true,
                'data' => $categories,
                'count' => count($categories)
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}