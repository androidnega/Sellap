<?php
namespace App\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Middleware\WebAuthMiddleware;

/**
 * Products Controller for Salespeople
 * Read-only access to products for sales purposes
 */
class ProductsController {
    private $product;
    private $category;
    private $brand;

    public function __construct() {
        $this->product = new Product();
        $this->category = new Category();
        $this->brand = new Brand();
    }

    /**
     * Display products for salespeople (read-only)
     */
    public function index() {
        // Handle web authentication - only salespeople and technicians
        WebAuthMiddleware::handle(['salesperson', 'technician']);
        
        // Get company_id from session
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            throw new \Exception('Company ID not found in session');
        }
        
        $title = 'Products';
        $page = 'products';
        
        // Get pagination parameters
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 20;
        $category_id = $_GET['category_id'] ?? null;
        $swappedItemsOnly = isset($_GET['swapped_items']) && $_GET['swapped_items'] == '1';
        
        // Get total count for pagination
        $totalItems = $this->product->getTotalCountByCompany($companyId, $category_id, $swappedItemsOnly);
        
        // Ensure current page doesn't exceed available pages
        $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
        }
        
        // Get paginated products for the company
        $products = $this->product->findByCompanyPaginated($companyId, $currentPage, $itemsPerPage, $category_id, $swappedItemsOnly);
        $categories = $this->category->getAll();
        $brands = $this->brand->getAll();
        
        // Calculate stats
        $allProducts = $this->product->findByCompany($companyId, 10000);
        $stats = [
            'total_products' => count($allProducts),
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'total_value' => 0,
            'total_quantity' => 0,
            'swapped_items' => 0
        ];
        
        foreach ($allProducts as $prod) {
            $qty = $prod['quantity'] ?? 0;
            $stats['total_quantity'] += $qty;
            
            if ($qty > 10) {
                $stats['in_stock']++;
            } elseif ($qty > 0) {
                $stats['low_stock']++;
            } else {
                $stats['out_of_stock']++;
            }
            
            // Count swapped items
            if (isset($prod['is_swapped_item']) && $prod['is_swapped_item'] == 1) {
                $stats['swapped_items']++;
            }
            
            $stats['total_value'] += ($prod['price'] ?? 0) * $qty;
        }
        
        // Build pagination URL with query parameters
        $paginationUrl = BASE_URL_PATH . '/dashboard/products';
        $queryParams = [];
        if ($swappedItemsOnly) {
            $queryParams[] = 'swapped_items=1';
        }
        if ($category_id) {
            $queryParams[] = 'category_id=' . urlencode($category_id);
        }
        if (!empty($queryParams)) {
            $paginationUrl .= '?' . implode('&', $queryParams);
        }
        
        // Generate pagination
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            $paginationUrl
        );
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/products_index.php';
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
     * View single product details (read-only)
     */
    public function show($id) {
        // Handle web authentication
        WebAuthMiddleware::handle(['salesperson', 'technician']);
        
        // Get company_id from session
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            throw new \Exception('Company ID not found in session');
        }
        
        // Use find() method which checks company_id
        $product = $this->product->find($id, $companyId);
        
        if (!$product) {
            http_response_code(404);
            echo "Product not found.";
            exit;
        }
        
        $title = 'Product Details - ' . $product['name'];
        $page = 'products';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/products_show.php';
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
     * Bulk delete products (API endpoint)
     */
    public function bulkDelete() {
        header('Content-Type: application/json');
        
        // Handle web authentication - only managers can delete
        try {
            \App\Middleware\WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        // Get company_id from session
        $companyId = $_SESSION['user']['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit;
        }
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        
        if (empty($ids) || !is_array($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No product IDs provided']);
            exit;
        }
        
        $deletedCount = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id <= 0) continue;
            
            // Verify the product exists and belongs to the company
            $product = $this->product->find($id, $companyId);
            if (!$product) {
                $errors[] = "Product ID {$id} not found or access denied";
                continue;
            }
            
            // Delete the product
            $deleted = $this->product->delete($id, $companyId);
            
            if ($deleted) {
                $deletedCount++;
            } else {
                $errors[] = "Failed to delete product '{$product['name']}' (ID: {$id})";
            }
        }
        
        if ($deletedCount > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} product(s)",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No products were deleted',
                'errors' => $errors
            ]);
        }
        exit;
    }
}
