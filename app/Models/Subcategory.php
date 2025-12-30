<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Subcategory {
    private $db;
    private $table = 'subcategories';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all subcategories
     * @param int|null $companyId Optional company ID to filter by company-specific subcategories
     */
    public function getAll($companyId = null) {
        if ($companyId) {
            $stmt = $this->db->prepare("
                SELECT DISTINCT s.*, c.name as category_name
                FROM subcategories s
                INNER JOIN categories cat ON s.category_id = cat.id
                INNER JOIN products_new p ON s.category_id = p.category_id AND (s.id = p.subcategory_id OR p.subcategory_id IS NULL)
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE p.company_id = ?
                ORDER BY c.name ASC, s.name ASC
            ");
            $stmt->execute([$companyId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT s.*, c.name as category_name
                FROM subcategories s
                LEFT JOIN categories c ON s.category_id = c.id
                ORDER BY c.name ASC, s.name ASC
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get subcategories by category ID
     * @param int $categoryId
     * @param int|null $companyId Optional company ID to filter by company-specific subcategories
     */
    public function getByCategory($categoryId, $companyId = null) {
        if ($companyId) {
            $stmt = $this->db->prepare("
                SELECT DISTINCT s.* 
                FROM subcategories s
                INNER JOIN products_new p ON s.category_id = p.category_id AND (s.id = p.subcategory_id OR p.subcategory_id IS NULL)
                WHERE s.category_id = ? 
                AND p.company_id = ?
                ORDER BY s.name ASC
            ");
            $stmt->execute([$categoryId, $companyId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM subcategories 
                WHERE category_id = ? 
                ORDER BY name ASC
            ");
            $stmt->execute([$categoryId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find subcategory by ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as category_name
            FROM subcategories s
            LEFT JOIN categories c ON s.category_id = c.id
            WHERE s.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find subcategory by name and category
     */
    public function findByNameAndCategory($name, $categoryId) {
        $stmt = $this->db->prepare("
            SELECT * FROM subcategories 
            WHERE name = ? AND category_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$name, $categoryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new subcategory
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO subcategories (category_id, name, description) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['description'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a subcategory
     */
    public function update($id, array $data) {
        $stmt = $this->db->prepare("
            UPDATE subcategories SET 
                category_id = ?, name = ?, description = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['description'] ?? null,
            $id
        ]);
    }

    /**
     * Delete a subcategory
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM subcategories WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Get subcategory with product count
     */
    public function getWithProductCount() {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as category_name, COUNT(p.id) as product_count
            FROM subcategories s
            LEFT JOIN categories c ON s.category_id = c.id
            LEFT JOIN {$productsTable} p ON s.id = p.subcategory_id
            GROUP BY s.id
            ORDER BY c.name ASC, s.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get subcategories with pagination
     */
    public function getWithProductCountPaginated($page = 1, $limit = 10) {
        // Ensure values are integers
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        // Use direct integer values in SQL to avoid prepared statement issues with LIMIT/OFFSET
        $sql = "
            SELECT s.*, c.name as category_name, COUNT(p.id) as product_count
            FROM subcategories s
            LEFT JOIN categories c ON s.category_id = c.id
            LEFT JOIN {$productsTable} p ON s.id = p.subcategory_id
            GROUP BY s.id
            ORDER BY c.name ASC, s.name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of subcategories
     */
    public function getTotalCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM subcategories");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Get product count for a specific subcategory
     */
    public function getProductCount($subcategoryId) {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM {$productsTable} 
            WHERE subcategory_id = ?
        ");
        $stmt->execute([$subcategoryId]);
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