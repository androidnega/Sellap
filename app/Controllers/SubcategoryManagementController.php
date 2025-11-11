<?php

namespace App\Controllers;

use App\Models\Subcategory;
use App\Models\Category;

class SubcategoryManagementController {
    private $subcategory;
    private $category;

    public function __construct() {
        $this->subcategory = new Subcategory();
        $this->category = new Category();
    }

    /**
     * Display subcategories list
     */
    public function index() {
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $itemsPerPage = 10;
        
        $subcategories = $this->subcategory->getWithProductCountPaginated($currentPage, $itemsPerPage);
        $totalItems = $this->subcategory->getTotalCount();
        
        $pagination = \App\Helpers\PaginationHelper::generate(
            $currentPage, 
            $totalItems, 
            $itemsPerPage, 
            BASE_URL_PATH . '/dashboard/subcategories'
        );
        
        $page = 'subcategory_management';
        $title = 'Subcategory Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/subcategory_management_index.php';
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
     * Show create subcategory form
     */
    public function create() {
        /** @var array $categories */
        $categories = $this->category->getAll();
        
        $page = 'subcategory_management';
        $title = 'Add New Subcategory';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/subcategory_management_form.php';
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
     * Store new subcategory
     */
    public function store() {
        $category_id = $_POST['category_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$category_id || !$name) {
            $_SESSION['flash_error'] = 'Category and subcategory name are required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/create');
            exit;
        }

        // Check if subcategory already exists for this category
        $existing = $this->subcategory->findByNameAndCategory($name, $category_id);
        if ($existing) {
            $_SESSION['flash_error'] = 'Subcategory already exists for this category';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/create');
            exit;
        }

        $subcategoryId = $this->subcategory->create([
            'category_id' => $category_id,
            'name' => $name,
            'description' => $description
        ]);

        if ($subcategoryId) {
            $_SESSION['flash_success'] = 'Subcategory created successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
        } else {
            $_SESSION['flash_error'] = 'Failed to create subcategory';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/create');
        }
        exit;
    }

    /**
     * Show edit subcategory form
     */
    public function edit($id) {
        $subcategory = $this->subcategory->find($id);
        /** @var array $categories */
        $categories = $this->category->getAll();
        
        if (!$subcategory) {
            $_SESSION['flash_error'] = 'Subcategory not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
            exit;
        }

        $page = 'subcategory_management';
        $title = 'Edit Subcategory';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/subcategory_management_form.php';
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
     * Update subcategory
     */
    public function update($id) {
        $category_id = $_POST['category_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$category_id || !$name) {
            $_SESSION['flash_error'] = 'Category and subcategory name are required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/edit/' . $id);
            exit;
        }

        // Check if subcategory already exists for this category (excluding current one)
        $existing = $this->subcategory->findByNameAndCategory($name, $category_id);
        if ($existing && $existing['id'] != $id) {
            $_SESSION['flash_error'] = 'Subcategory name already exists for this category';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/edit/' . $id);
            exit;
        }

        $success = $this->subcategory->update($id, [
            'category_id' => $category_id,
            'name' => $name,
            'description' => $description
        ]);

        if ($success) {
            $_SESSION['flash_success'] = 'Subcategory updated successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
        } else {
            $_SESSION['flash_error'] = 'Failed to update subcategory';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete subcategory
     */
    public function delete($id) {
        $subcategory = $this->subcategory->find($id);
        
        if (!$subcategory) {
            $_SESSION['flash_error'] = 'Subcategory not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
            exit;
        }

        // Check if subcategory has linked products
        $productCount = $this->subcategory->getProductCount($id);
        if ($productCount > 0) {
            $_SESSION['flash_error'] = "Cannot delete subcategory. It has {$productCount} linked products.";
            header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
            exit;
        }

        $success = $this->subcategory->delete($id);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Subcategory deleted successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to delete subcategory';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/subcategories');
        exit;
    }
}
