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
        // Start session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        
        if (!$userData) {
            // Not logged in - redirect to login
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/tools';
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        // Check if user has system_admin role
        $userRole = $userData['role'] ?? '';
        if ($userRole !== 'system_admin') {
            // Show access denied page instead of redirecting
            $title = 'Access Denied';
            $errorMessage = "You need to be a System Administrator to access migration tools. Your current role is: " . htmlspecialchars($userRole);
            
            ob_start();
            ?>
            <div class="max-w-2xl mx-auto">
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <svg class="w-8 h-8 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <h1 class="text-2xl font-bold text-red-900">Access Denied</h1>
                    </div>
                    <p class="text-red-800 mb-4"><?= htmlspecialchars($errorMessage) ?></p>
                    <a href="<?= BASE_URL_PATH ?>/dashboard" class="inline-block px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Return to Dashboard
                    </a>
                </div>
            </div>
            <?php
            $content = ob_get_clean();
            
            $GLOBALS['content'] = $content;
            $GLOBALS['title'] = $title;
            $GLOBALS['user_data'] = $userData;
            
            require __DIR__ . '/../Views/simple_layout.php';
            exit;
        }
        
        // User is system_admin, proceed with migration

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
            [
                'name' => 'Acer',
                'description' => 'Acer Aspire, Predator and Swift laptop series',
            ],
            [
                'name' => 'ASUS',
                'description' => 'ASUS VivoBook, ZenBook, ROG and TUF gaming laptops',
            ],
            [
                'name' => 'MSI',
                'description' => 'MSI gaming and professional laptops',
            ],
            [
                'name' => 'Samsung',
                'description' => 'Samsung Galaxy Book and Notebook series',
            ],
            [
                'name' => 'Microsoft',
                'description' => 'Microsoft Surface laptops and 2-in-1 devices',
            ],
            [
                'name' => 'Razer',
                'description' => 'Razer Blade gaming and professional laptops',
            ],
            [
                'name' => 'Toshiba',
                'description' => 'Toshiba Satellite and Portégé laptops',
            ],
            [
                'name' => 'LG',
                'description' => 'LG Gram ultra-lightweight laptops',
            ],
            [
                'name' => 'Alienware',
                'description' => 'Alienware gaming laptops by Dell',
            ],
            [
                'name' => 'Acer Predator',
                'description' => 'Acer Predator gaming laptop series',
            ],
            [
                'name' => 'HP Omen',
                'description' => 'HP Omen gaming laptops',
            ],
            [
                'name' => 'Dell Alienware',
                'description' => 'Dell Alienware gaming laptops',
            ],
            [
                'name' => 'Chromebook',
                'description' => 'Chromebook laptops running Chrome OS',
            ],
            [
                'name' => 'Huawei',
                'description' => 'Huawei MateBook laptop series',
            ],
            [
                'name' => 'Xiaomi',
                'description' => 'Xiaomi Mi Notebook and RedmiBook laptops',
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

    /**
     * Run backup columns migration via web
     */
    public function runBackupColumnsMigration()
    {
        // Start session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        $userData = $_SESSION['user'] ?? null;
        
        if (!$userData) {
            // Not logged in - redirect to login
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard/tools';
            $redirectParam = 'redirect=' . urlencode($currentPath);
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        // Check if user has system_admin role
        $userRole = $userData['role'] ?? '';
        if ($userRole !== 'system_admin') {
            // Show access denied page
            $title = 'Access Denied';
            $errorMessage = "You need to be a System Administrator to access migration tools. Your current role is: " . htmlspecialchars($userRole);
            
            ob_start();
            ?>
            <div class="max-w-2xl mx-auto">
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <svg class="w-8 h-8 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <h1 class="text-2xl font-bold text-red-900">Access Denied</h1>
                    </div>
                    <p class="text-red-800 mb-4"><?= htmlspecialchars($errorMessage) ?></p>
                    <a href="<?= BASE_URL_PATH ?>/dashboard" class="inline-block px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Return to Dashboard
                    </a>
                </div>
            </div>
            <?php
            $content = ob_get_clean();
            
            $GLOBALS['content'] = $content;
            $GLOBALS['title'] = $title;
            $GLOBALS['user_data'] = $userData;
            
            require __DIR__ . '/../Views/simple_layout.php';
            exit;
        }
        
        // User is system_admin, proceed with migration
        $db = \Database::getInstance()->getConnection();
        $logs = [];
        $status = 'success';
        $errorMessage = null;

        try {
            $logs[] = ['type' => 'info', 'message' => 'Starting backup columns migration...'];
            
            $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
            $logs[] = ['type' => 'info', 'message' => "Database: {$dbName}"];

            // Check if columns already exist
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = 'backups' 
                AND COLUMN_NAME IN ('backup_type', 'description')
            ");
            $stmt->execute([$dbName]);
            $existingColumns = $stmt->fetchColumn();

            if ($existingColumns >= 2) {
                $logs[] = ['type' => 'success', 'message' => "Columns 'backup_type' and 'description' already exist in backups table"];
            } else {
                // Add backup_type column if it doesn't exist
                $stmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'backups' 
                    AND COLUMN_NAME = 'backup_type'
                ");
                $stmt->execute([$dbName]);
                if ($stmt->fetchColumn() == 0) {
                    $db->exec("
                        ALTER TABLE backups 
                        ADD COLUMN backup_type VARCHAR(20) DEFAULT 'manual' 
                        COMMENT 'Type of backup: manual or automatic'
                        AFTER format
                    ");
                    $logs[] = ['type' => 'success', 'message' => "Added 'backup_type' column"];
                } else {
                    $logs[] = ['type' => 'info', 'message' => "Column 'backup_type' already exists"];
                }

                // Add description column if it doesn't exist
                $stmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'backups' 
                    AND COLUMN_NAME = 'description'
                ");
                $stmt->execute([$dbName]);
                if ($stmt->fetchColumn() == 0) {
                    $db->exec("
                        ALTER TABLE backups 
                        ADD COLUMN description TEXT NULL 
                        COMMENT 'Optional description or notes about the backup'
                        AFTER backup_type
                    ");
                    $logs[] = ['type' => 'success', 'message' => "Added 'description' column"];
                } else {
                    $logs[] = ['type' => 'info', 'message' => "Column 'description' already exists"];
                }

                // Add index for backup_type
                try {
                    $db->exec("
                        ALTER TABLE backups 
                        ADD INDEX idx_backup_type (backup_type)
                    ");
                    $logs[] = ['type' => 'success', 'message' => "Added index on 'backup_type' column"];
                } catch (\PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                        $logs[] = ['type' => 'info', 'message' => "Index 'idx_backup_type' already exists"];
                    } else {
                        throw $e;
                    }
                }
            }

            // Update existing backups with automatic marker
            $stmt = $db->query("
                UPDATE backups 
                SET backup_type = 'automatic' 
                WHERE description LIKE '%[AUTOMATIC DAILY BACKUP]%'
                AND backup_type = 'manual'
            ");
            $updatedRows = $stmt->rowCount();
            if ($updatedRows > 0) {
                $logs[] = ['type' => 'success', 'message' => "Updated {$updatedRows} existing automatic backups"];
            }

            $logs[] = ['type' => 'success', 'message' => 'Backup columns migration completed successfully!'];
            $logs[] = ['type' => 'info', 'message' => 'Backup statistics will now display correctly on the backup page.'];
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            $logs[] = ['type' => 'error', 'message' => 'Migration failed: ' . $e->getMessage()];
        }

        $title = 'Backup Columns Migration';

        $GLOBALS['migration_logs'] = $logs;
        $GLOBALS['migration_status'] = $status;
        $GLOBALS['migration_error'] = $errorMessage;
        $GLOBALS['title'] = $title;

        ob_start();
        include __DIR__ . '/../Views/migration_result.php';
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

