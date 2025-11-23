<?php
/**
 * Automatic runner for the Laptop category + brands migration
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Models/Brand.php';
require_once __DIR__ . '/../../app/Models/Category.php';

use App\Models\Brand;
use App\Models\Category;

$db = \Database::getInstance()->getConnection();
$brandModel = new Brand();
$categoryModel = new Category();

function logMessage($message, $prefix = '•') {
    echo "{$prefix} {$message}\n";
}

echo "🚀 Running Migration: Add Laptop Category & Brands\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    logMessage("Database: {$dbName}", '📊');
    echo "\n";

    // 1. Ensure Laptops category exists
    $laptopCategory = $categoryModel->findByName('Laptops');
    if (!$laptopCategory) {
        logMessage("Creating 'Laptops' category...");
        $categoryId = $categoryModel->create([
            'name' => 'Laptops',
            'description' => 'Notebooks and portable computers',
            'is_active' => 1
        ]);
        $laptopCategory = $categoryModel->find($categoryId);
        logMessage("✓ Laptops category created with ID {$categoryId}");
    } else {
        logMessage("✓ 'Laptops' category already exists (ID {$laptopCategory['id']})");
    }

    $laptopCategoryId = (int)$laptopCategory['id'];

    // 2. Seed core laptop brands
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

    foreach ($defaultBrands as $brandData) {
        $existing = $brandModel->findByNameAndCategory($brandData['name'], $laptopCategoryId);
        if ($existing) {
            logMessage("Brand '{$brandData['name']}' already linked to Laptops (ID {$existing['id']})", 'ℹ️');
            $brandModel->syncCategories($existing['id'], array_unique(array_merge($existing['category_ids'] ?? [], [$laptopCategoryId])));
            continue;
        }

        logMessage("Creating brand '{$brandData['name']}'...");
        $brandId = $brandModel->create([
            'name' => $brandData['name'],
            'description' => $brandData['description'],
            'category_id' => $laptopCategoryId
        ]);
        $brandModel->syncCategories($brandId, [$laptopCategoryId]);
        logMessage("✓ Brand '{$brandData['name']}' created (ID {$brandId})");
    }

    echo "\n✅ Migration completed successfully!\n";
    echo "   • Laptops category is available in Inventory\n";
    echo "   • Core laptop brands (Dell, HP, Lenovo, Apple) are ready to use\n";
    echo "   • Sales staff can now add laptop products with the new specs form\n";
    echo "\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

