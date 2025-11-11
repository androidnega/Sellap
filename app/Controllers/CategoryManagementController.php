<?php

namespace App\Controllers;

use App\Models\Category;

class CategoryManagementController {
    private $category;

    public function __construct() {
        $this->category = new Category();
    }

    /**
     * Display categories list
     */
    public function index() {
        $categories = $this->category->getAllForManagement();
        
        $page = 'category_management';
        $title = 'Category Management';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/category_management_index.php';
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
     * Show create category form
     */
    public function create() {
        $page = 'category_management';
        $title = 'Add New Category';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/category_management_form.php';
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
     * Store new category
     */
    public function store() {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) {
            $_SESSION['flash_error'] = 'Category name is required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/create');
            exit;
        }

        // Check if category already exists
        $existing = $this->category->findByName($name);
        if ($existing) {
            $_SESSION['flash_error'] = 'Category already exists';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/create');
            exit;
        }

        $categoryId = $this->category->create([
            'name' => $name,
            'description' => $description,
            'is_active' => $is_active
        ]);

        if ($categoryId) {
            $_SESSION['flash_success'] = 'Category created successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
        } else {
            $_SESSION['flash_error'] = 'Failed to create category';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/create');
        }
        exit;
    }

    /**
     * Show edit category form
     */
    public function edit($id) {
        $category = $this->category->find($id);
        
        if ($category === false || empty($category)) {
            $_SESSION['flash_error'] = 'Category not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
            exit;
        }

        $page = 'category_management';
        $title = 'Edit Category';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/category_management_form.php';
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
     * Update category
     */
    public function update($id) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) {
            $_SESSION['flash_error'] = 'Category name is required';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/edit/' . $id);
            exit;
        }

        // Check if category already exists (excluding current one)
        $existing = $this->category->findByName($name);
        if ($existing && $existing['id'] != $id) {
            $_SESSION['flash_error'] = 'Category name already exists';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/edit/' . $id);
            exit;
        }

        $success = $this->category->update($id, [
            'name' => $name,
            'description' => $description,
            'is_active' => $is_active
        ]);

        if ($success) {
            $_SESSION['flash_success'] = 'Category updated successfully';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
        } else {
            $_SESSION['flash_error'] = 'Failed to update category';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories/edit/' . $id);
        }
        exit;
    }

    /**
     * Delete category (with safety check)
     */
    public function delete($id) {
        $category = $this->category->find($id);
        
        if ($category === false || empty($category)) {
            $_SESSION['flash_error'] = 'Category not found';
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
            exit;
        }

        // Check if category has linked products
        $productCount = $this->category->getProductCount($id);
        if ($productCount > 0) {
            $_SESSION['flash_error'] = "Cannot delete category. It has {$productCount} linked products. Please deactivate instead.";
            header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
            exit;
        }

        $success = $this->category->delete($id);
        
        if ($success) {
            $_SESSION['flash_success'] = 'Category deleted successfully';
        } else {
            $_SESSION['flash_error'] = 'Failed to delete category';
        }
        
        header('Location: ' . BASE_URL_PATH . '/dashboard/categories');
        exit;
    }
}
