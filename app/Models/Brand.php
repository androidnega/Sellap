<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Brand {
    private $db;
    private $table = 'brands';
    private static $brandCategoryTableExists = null;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Check if brand_category_links table exists (cached)
     */
    private function brandCategoryTableExists(): bool {
        if (self::$brandCategoryTableExists !== null) {
            return self::$brandCategoryTableExists;
        }

        self::$brandCategoryTableExists = $this->checkBrandCategoryTable();
        if (!self::$brandCategoryTableExists) {
            self::$brandCategoryTableExists = $this->createBrandCategoryLinksTable();
        }

        return self::$brandCategoryTableExists;
    }

    private function checkBrandCategoryTable(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'brand_category_links'");
            return $stmt && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function createBrandCategoryLinksTable(): bool {
        try {
            $createSql = "
                CREATE TABLE IF NOT EXISTS brand_category_links (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    brand_id INT NOT NULL,
                    category_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_brand_category (brand_id, category_id),
                    INDEX idx_brand_category_brand (brand_id),
                    INDEX idx_brand_category_category (category_id),
                    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
                )
            ";
            $this->db->exec($createSql);

            $backfillSql = "
                INSERT IGNORE INTO brand_category_links (brand_id, category_id)
                SELECT id, category_id FROM {$this->table}
                WHERE category_id IS NOT NULL
            ";
            $this->db->exec($backfillSql);

            return true;
        } catch (\Exception $e) {
            error_log('Brand::createBrandCategoryLinksTable() error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all category IDs linked to a brand (includes primary category_id)
     */
    public function getCategoryIds(int $brandId): array {
        $categoryIds = [];

        // Include primary category from brands table
        $stmt = $this->db->prepare("SELECT category_id FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$brandId]);
        $primaryId = $stmt->fetchColumn();
        if ($primaryId) {
            $categoryIds[] = (int) $primaryId;
        }

        if ($this->brandCategoryTableExists()) {
            $stmt = $this->db->prepare("
                SELECT category_id 
                FROM brand_category_links 
                WHERE brand_id = ?
            ");
            $stmt->execute([$brandId]);
            $linked = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($linked as $id) {
                if ($id) {
                    $categoryIds[] = (int) $id;
                }
            }
        }

        // Deduplicate and filter invalid entries
        $categoryIds = array_values(array_unique(array_filter($categoryIds, function($id) {
            return (int)$id > 0;
        })));

        return $categoryIds;
    }

    /**
     * Synchronize category links for a brand
     */
    public function syncCategories(int $brandId, array $categoryIds): void {
        // Normalize IDs
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), function($id) {
            return $id > 0;
        })));

        // Update primary category for backward compatibility
        $primaryCategoryId = $categoryIds[0] ?? null;
        $stmt = $this->db->prepare("UPDATE {$this->table} SET category_id = ? WHERE id = ?");
        $stmt->execute([$primaryCategoryId, $brandId]);

        if (!$this->brandCategoryTableExists()) {
            return;
        }

        // Remove all links if no categories selected
        if (empty($categoryIds)) {
            $deleteStmt = $this->db->prepare("DELETE FROM brand_category_links WHERE brand_id = ?");
            $deleteStmt->execute([$brandId]);
            return;
        }

        // Insert or keep existing links
        $insertStmt = $this->db->prepare("
            INSERT IGNORE INTO brand_category_links (brand_id, category_id)
            VALUES (?, ?)
        ");
        foreach ($categoryIds as $categoryId) {
            $insertStmt->execute([$brandId, $categoryId]);
        }

        // Remove links that are no longer selected
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $deleteSql = "
            DELETE FROM brand_category_links 
            WHERE brand_id = ? AND category_id NOT IN ({$placeholders})
        ";
        $deleteStmt = $this->db->prepare($deleteSql);
        $deleteStmt->execute(array_merge([$brandId], $categoryIds));
    }

    /**
     * Build map of brand_id => [category names]
     */
    private function getCategoryNameMap(array $brandIds): array {
        $map = [];
        if (empty($brandIds)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($brandIds), '?'));

        // Include primary category names
        $stmt = $this->db->prepare("
            SELECT b.id as brand_id, c.name 
            FROM {$this->table} b
            JOIN categories c ON b.category_id = c.id
            WHERE b.category_id IS NOT NULL AND b.id IN ({$placeholders})
        ");
        $stmt->execute($brandIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['brand_id']][] = $row['name'];
        }

        // Include linked categories if pivot table exists
        if ($this->brandCategoryTableExists()) {
            $stmt = $this->db->prepare("
                SELECT bcl.brand_id, c.name
                FROM brand_category_links bcl
                JOIN categories c ON bcl.category_id = c.id
                WHERE bcl.brand_id IN ({$placeholders})
            ");
            $stmt->execute($brandIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[$row['brand_id']][] = $row['name'];
            }
        }

        // Deduplicate names per brand
        foreach ($map as $brandId => $names) {
            $map[$brandId] = array_values(array_unique(array_filter($names)));
        }

        return $map;
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
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $map = $this->getCategoryNameMap(array_column($results, 'id'));
            foreach ($results as &$row) {
                $row['category_names'] = $map[$row['id']] ?? [];
                if (empty($row['category_names']) && !empty($row['category_name'])) {
                    $row['category_names'] = [$row['category_name']];
                }
            }
            unset($row);
        }
        return $results;
    }

    /**
     * Get brands by category ID
     * @param int $categoryId
     * @param int|null $companyId Optional company ID to filter by company-specific brands
     */
    public function getByCategory($categoryId, $companyId = null) {
        if ($companyId) {
            // Filter by company_id through products
            if ($this->brandCategoryTableExists()) {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT b.id, b.name, b.category_id, b.created_at
                    FROM brands b 
                    INNER JOIN products_new p ON b.id = p.brand_id
                    LEFT JOIN brand_category_links bcl ON b.id = bcl.brand_id
                    WHERE p.company_id = ?
                    AND (b.category_id = ? OR bcl.category_id = ?)
                    ORDER BY b.name ASC
                ");
                $stmt->execute([$companyId, $categoryId, $categoryId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT b.id, b.name, b.category_id, b.created_at
                    FROM brands b 
                    INNER JOIN products_new p ON b.id = p.brand_id
                    WHERE p.company_id = ?
                    AND b.category_id = ? 
                    ORDER BY b.name ASC
                ");
                $stmt->execute([$companyId, $categoryId]);
            }
        } else {
            if ($this->brandCategoryTableExists()) {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT b.id, b.name, b.category_id, b.created_at
                    FROM brands b 
                    LEFT JOIN brand_category_links bcl ON b.id = bcl.brand_id
                    WHERE b.category_id = ? OR bcl.category_id = ?
                    ORDER BY b.name ASC
                ");
                $stmt->execute([$categoryId, $categoryId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT b.id, b.name, b.category_id, b.created_at
                    FROM brands b 
                    WHERE b.category_id = ? 
                    ORDER BY b.name ASC
                ");
                $stmt->execute([$categoryId]);
            }
        }
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
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($brand) {
            $brand['category_ids'] = $this->getCategoryIds($id);
        }
        return $brand;
    }

    /**
     * Find brand by name and category
     */
    public function findByNameAndCategory($name, $categoryId) {
        if (!$categoryId) {
            return false;
        }

        if ($this->brandCategoryTableExists()) {
            $stmt = $this->db->prepare("
                SELECT * FROM brands 
                WHERE LOWER(name) = LOWER(?) 
                AND (
                    category_id = ?
                    OR EXISTS (
                        SELECT 1 FROM brand_category_links bcl 
                        WHERE bcl.brand_id = brands.id AND bcl.category_id = ?
                    )
                )
                LIMIT 1
            ");
            $stmt->execute([$name, $categoryId, $categoryId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM brands 
                WHERE LOWER(name) = LOWER(?) AND category_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$name, $categoryId]);
        }
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
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $map = $this->getCategoryNameMap(array_column($results, 'id'));
            foreach ($results as &$row) {
                $row['category_names'] = $map[$row['id']] ?? [];
                if (empty($row['category_names']) && !empty($row['category_name'])) {
                    $row['category_names'] = [$row['category_name']];
                }
            }
            unset($row);
        }
        return $results;
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
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            $map = $this->getCategoryNameMap(array_column($results, 'id'));
            foreach ($results as &$row) {
                $row['category_names'] = $map[$row['id']] ?? [];
                if (empty($row['category_names']) && !empty($row['category_name'])) {
                    $row['category_names'] = [$row['category_name']];
                }
            }
            unset($row);
        }
        return $results;
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