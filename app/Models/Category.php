<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Category {
    private $db;
    private $table = 'categories';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all active categories
     * @param int|null $companyId Optional company ID to filter by company-specific categories
     * @return array
     */
    public function getAll($companyId = null): array {
        try {
            // Always return all active categories, regardless of whether they have products
            // This ensures the dropdown is always populated for product creation
            $stmt = $this->db->prepare("
                SELECT * FROM categories 
                WHERE is_active = 1 
                ORDER BY name ASC
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug logging
            error_log("Category::getAll() - Found " . count($result) . " active categories");
            if (empty($result)) {
                error_log("WARNING: No active categories found in database. Check if categories table has data and is_active = 1");
            }
            
            return $result ?: [];
        } catch (\Exception $e) {
            error_log("Category::getAll() error: " . $e->getMessage());
            error_log("Category::getAll() error trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Get all categories (including inactive) for management
     * @return array
     */
    public function getAllForManagement(): array {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT c.*, COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN {$productsTable} p ON c.id = p.category_id
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find category by ID
     * @param int $id
     * @return array|false
     */
    public function find($id): array|false {
        $stmt = $this->db->prepare("
            SELECT * FROM categories 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * Find category by name
     * @param string $name
     * @return array|false
     */
    public function findByName($name): array|false {
        $stmt = $this->db->prepare("
            SELECT * FROM categories 
            WHERE name = ? 
            LIMIT 1
        ");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new category
     * @return int|false
     */
    public function create(array $data): int|false {
        $stmt = $this->db->prepare("
            INSERT INTO categories (name, description, is_active) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['is_active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a category
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE categories SET 
                name = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
        
        return $result;
    }

    /**
     * Delete a category (hard delete)
     * @return bool
     */
    public function delete($id): bool {
        $stmt = $this->db->prepare("
            DELETE FROM categories WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Get category with product count
     * @return array
     */
    public function getWithProductCount(): array {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT c.*, COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN {$productsTable} p ON c.id = p.category_id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product count for a specific category
     * @return int
     */
    public function getProductCount($categoryId): int {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM {$productsTable} 
            WHERE category_id = ?
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Determine which products table exists (products or products_new)
     */
    private function getProductsTableName() {
        // Check if products_new exists
        $checkTable = $this->db->query("SHOW TABLES LIKE 'products_new'");
        if ($checkTable && $checkTable->rowCount() > 0) {
            return 'products_new';
        }
        // Default to products table
        return 'products';
    }
}
