<?php

namespace App\Controllers;

use App\Models\Brand;
use App\Models\Category;

class BrandManagementController {
    private $brand;
    private $category;

    public function __construct() {
        $this->brand = new Brand();
        $this->category = new Category();
    }

    /**
     * Display brands list
     */
    public function index() {
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 10;
        
        $brands = $this->brand->getWithProductCountPaginated($currentPage, $itemsPerPage);
        $totalItems = $this->brand->getTotalCount();
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/brands'
        );
        
        $page = 'brand_management';
        $title = 'Brand Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/brand_management_index.php';
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
     * Show create brand form
     */
    public function create() {
        $categories = $this->category->getAll();
        
        $page = 'brand_management';
        $title = 'Add New Brand';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/brand_management_form.php';
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
     * Store new brand
     */
    public function store() {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? null;

        if (!$name) {
            $_SESSION['flash_error'] = 'Brand name is required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands/create');
            exit;
        }

        // Check if brand already exists for this category
        if ($category_id) {
            $existing = $this->brand->findByNameAndCategory($name, $category_id);
            if ($existing) {
                $_SESSION['flash_error'] = 'Brand already exists for this category';
                header('Location: ' . BASE_URL_PATH . '/dashboard/brands/create');
                exit;
            }
        }

        $brandId = $this->brand->create([
            'name' => $name,
            'description' => $description,
            'category_id' => $category_id
        ]);

        if ($brandId) {
            $_SESSION['flash_success'] = 'Brand created successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
        } else {
            $_SESSION['flash_error'] = 'Failed to create brand';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands/create');
        }
        exit;
    }

    /**
     * Show edit brand form
     */
    public function edit($id) {
        $brand = $this->brand->find($id);
        $categories = $this->category->getAll();
        
        if (!$brand) {
            $_SESSION['flash_error'] = 'Brand not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
            exit;
        }

        $page = 'brand_management';
        $title = 'Edit Brand';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/brand_management_form.php';
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
     * Update brand
     */
    public function update($id) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? null;

        if (!$name) {
            $_SESSION['flash_error'] = 'Brand name is required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands/edit/' . $id);
            exit;
        }

        // Check if brand already exists for this category (excluding current one)
        if ($category_id) {
            $existing = $this->brand->findByNameAndCategory($name, $category_id);
            if ($existing && $existing['id'] != $id) {
                $_SESSION['flash_error'] = 'Brand name already exists for this category';
                header('Location: ' . BASE_URL_PATH . '/dashboard/brands/edit/' . $id);
                exit;
            }
        }

        $success = $this->brand->update($id, [
            'name' => $name,
            'description' => $description,
            'category_id' => $category_id
        ]);

        if ($success) {
            $_SESSION['flash_success'] = 'Brand updated successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
        } else {
            $_SESSION['flash_error'] = 'Failed to update brand';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete brand
     */
    public function delete($id) {
        $brand = $this->brand->find($id);
        
        if (!$brand) {
            $_SESSION['flash_error'] = 'Brand not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
            exit;
        }

        // Check if brand has linked products
        $productCount = $this->brand->getProductCount($id);
        if ($productCount > 0) {
            $_SESSION['flash_error'] = "Cannot delete brand. It has {$productCount} linked products.";
            header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
            exit;
        }

        $success = $this->brand->delete($id);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Brand deleted successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to delete brand';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/brands');
        exit;
    }

    /**
     * API endpoint: Get brands by category (for dynamic loading)
     */
    public function apiGetBrandsByCategory($categoryId) {
        header('Content-Type: application/json');
        
        try {
        $brands = $this->brand->getByCategory($categoryId);
            echo json_encode([
                'success' => true,
                'data' => $brands
            ]);
        } catch (\Exception $e) {
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
     * API endpoint: Get brand specifications (for dynamic form fields)
     */
    public function apiGetBrandSpecs($brandId) {
        header('Content-Type: application/json');
        
        try {
        $specs = $this->brand->getSpecifications($brandId);
            echo json_encode([
                'success' => true,
                'data' => $specs
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
        exit;
    }
}
