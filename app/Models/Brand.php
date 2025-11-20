<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Brand {
    private $db;
    private $table = 'brands';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all brands
     */
    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as category_name
            FROM brands b
            LEFT JOIN categories c ON b.category_id = c.id
            ORDER BY c.name ASC, b.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get brands by category ID
     */
    public function getByCategory($categoryId) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT b.id, b.name, b.category_id, b.is_active, b.created_at
            FROM brands b 
            WHERE b.category_id = ? 
            ORDER BY b.name ASC
        ");
        $stmt->execute([$categoryId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Deduplicate by name (case-insensitive) to avoid duplicates like "Samsung, Samsung"
        $unique = [];
        $seenNames = [];
        foreach ($results as $row) {
            $normalizedName = strtolower(trim($row['name']));
            // Only add if we haven't seen this brand name before (case-insensitive)
            if (!isset($seenNames[$normalizedName])) {
                $seenNames[$normalizedName] = true;
                $unique[] = $row;
            }
        }
        return $unique;
    }

    /**
     * Find brand by ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as category_name
            FROM brands b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find brand by name and category
     */
    public function findByNameAndCategory($name, $categoryId) {
        $stmt = $this->db->prepare("
            SELECT * FROM brands 
            WHERE name = ? AND category_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$name, $categoryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new brand
     */
    public function create(array $data) {
        // Check if description column exists
        $hasDescription = $this->checkColumnExists('description');
        
        if ($hasDescription) {
            $stmt = $this->db->prepare("
                INSERT INTO brands (name, description, category_id) 
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['category_id'] ?? null
            ]);
        } else {
            // Description column doesn't exist, don't include it
            $stmt = $this->db->prepare("
                INSERT INTO brands (name, category_id) 
                VALUES (?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['category_id'] ?? null
            ]);
        }
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a brand
     */
    public function update($id, array $data) {
        // Check if description column exists
        $hasDescription = $this->checkColumnExists('description');
        
        if ($hasDescription) {
            $stmt = $this->db->prepare("
                UPDATE brands SET 
                    name = ?, description = ?, category_id = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['category_id'] ?? null,
                $id
            ]);
        } else {
            // Description column doesn't exist, don't include it
            $stmt = $this->db->prepare("
                UPDATE brands SET 
                    name = ?, category_id = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                $data['category_id'] ?? null,
                $id
            ]);
        }
    }

    /**
     * Delete a brand
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM brands WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Get brand with product count
     */
    public function getWithProductCount() {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as category_name, COUNT(p.id) as product_count
            FROM brands b
            LEFT JOIN categories c ON b.category_id = c.id
            LEFT JOIN {$productsTable} p ON b.id = p.brand_id
            GROUP BY b.id
            ORDER BY c.name ASC, b.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get brands with pagination
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
            SELECT b.*, c.name as category_name, COUNT(p.id) as product_count
            FROM brands b
            LEFT JOIN categories c ON b.category_id = c.id
            LEFT JOIN {$productsTable} p ON b.id = p.brand_id
            GROUP BY b.id
            ORDER BY c.name ASC, b.name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of brands
     */
    public function getTotalCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM brands");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Get product count for a specific brand
     */
    public function getProductCount($brandId) {
        // Determine which products table exists
        $productsTable = $this->getProductsTableName();
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM {$productsTable} 
            WHERE brand_id = ?
        ");
        $stmt->execute([$brandId]);
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

    /**
     * Check if a column exists in the brands table
     */
    private function checkColumnExists($columnName) {
        try {
            // Get all columns from brands table
            $stmt = $this->db->query("SHOW COLUMNS FROM brands");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            // Check if the column name exists (case-insensitive)
            return in_array(strtolower($columnName), array_map('strtolower', $columns));
        } catch (\Exception $e) {
            error_log("Brand::checkColumnExists() - Error checking column: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get brand specifications (for dynamic form fields)
     */
    public function getSpecifications($brandId) {
        // This would typically come from a brand_specifications table
        // For now, we'll return common phone specs based on brand
        $commonSpecs = [
            'storage' => ['label' => 'Storage (GB)', 'type' => 'text', 'required' => true],
            'ram' => ['label' => 'RAM (GB)', 'type' => 'text', 'required' => true],
            'color' => ['label' => 'Color', 'type' => 'text', 'required' => false],
            'battery' => ['label' => 'Battery (mAh)', 'type' => 'text', 'required' => false]
        ];
        
        return $commonSpecs;
    }
}