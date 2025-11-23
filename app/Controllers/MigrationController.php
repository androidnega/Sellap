<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Models\Brand;
use App\Models\Category;

class MigrationController
{
    /**
     * Run laptop category & brand seeding via web
     */
    public function runLaptopCategoryMigration()
    {
        WebAuthMiddleware::handle(['system_admin']);

        $categoryModel = new Category();
        $brandModel = new Brand();

        $logs = [];
        $status = 'success';
        $errorMessage = null;

        $defaultBrands = [
            [
                'name' => 'Dell',
                'description' => 'Dell Inspiron, XPS and Latitude laptop lines',
            ],
            [
                'name' => 'HP',
                'description' => 'HP Pavilion, Envy and EliteBook laptops',
            ],
            [
                'name' => 'Lenovo',
                'description' => 'Lenovo ThinkPad, IdeaPad and Legion series',
            ],
            [
                'name' => 'Apple',
                'description' => 'MacBook Air and MacBook Pro laptops',
            ],
        ];

        try {
            $logs[] = ['type' => 'info', 'message' => 'Starting laptop category migration...'];

            $laptopCategory = $categoryModel->findByName('Laptops');

            if (!$laptopCategory) {
                $categoryId = $categoryModel->create([
                    'name' => 'Laptops',
                    'description' => 'Notebooks and portable computers',
                    'is_active' => 1,
                ]);
                $laptopCategory = $categoryModel->find($categoryId);
                $logs[] = ['type' => 'success', 'message' => "Created 'Laptops' category (ID {$categoryId})"];
            } else {
                $logs[] = ['type' => 'success', 'message' => "'Laptops' category already exists (ID {$laptopCategory['id']})"];
            }

            $laptopCategoryId = (int) $laptopCategory['id'];

            foreach ($defaultBrands as $brandData) {
                $existing = $brandModel->findByNameAndCategory($brandData['name'], $laptopCategoryId);

                if ($existing) {
                    $logs[] = ['type' => 'info', 'message' => "Brand '{$brandData['name']}' already linked to Laptops (ID {$existing['id']})"];
                    $currentCategoryIds = $brandModel->getCategoryIds($existing['id']);
                    if (!in_array($laptopCategoryId, $currentCategoryIds, true)) {
                        $currentCategoryIds[] = $laptopCategoryId;
                        $brandModel->syncCategories($existing['id'], $currentCategoryIds);
                        $logs[] = ['type' => 'success', 'message' => "Updated '{$brandData['name']}' category links"];
                    }
                    continue;
                }

                $logs[] = ['type' => 'info', 'message' => "Creating brand '{$brandData['name']}'..."];
                $brandId = $brandModel->create([
                    'name' => $brandData['name'],
                    'description' => $brandData['description'],
                    'category_id' => $laptopCategoryId,
                ]);
                $brandModel->syncCategories($brandId, [$laptopCategoryId]);
                $logs[] = ['type' => 'success', 'message' => "Brand '{$brandData['name']}' created (ID {$brandId})"];
            }

            $logs[] = ['type' => 'success', 'message' => 'Laptop migration completed successfully.'];
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            $logs[] = ['type' => 'error', 'message' => 'Migration failed: ' . $e->getMessage()];
        }

        $title = 'Laptop Category Migration';

        $GLOBALS['migration_logs'] = $logs;
        $GLOBALS['migration_status'] = $status;
        $GLOBALS['migration_error'] = $errorMessage;
        $GLOBALS['title'] = $title;

        ob_start();
        include __DIR__ . '/../Views/migration_laptop_result.php';
        $content = ob_get_clean();

        $GLOBALS['content'] = $content;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }

        require __DIR__ . '/../Views/simple_layout.php';
    }
}

