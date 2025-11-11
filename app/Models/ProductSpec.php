<?php
namespace App\Models;

use PDO;
use Exception;

require_once __DIR__ . '/../../config/database.php';

class ProductSpec {
    private $db;
    private $table = 'product_specs';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Set specifications for a product (replaces existing specs)
     */
    public function setSpecs($product_id, array $specs) {
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Delete existing specs for this product
            $del = $this->db->prepare("DELETE FROM product_specs WHERE product_id = ?");
            $del->execute([$product_id]);
            
            // Insert new specs
            if (!empty($specs)) {
                $ins = $this->db->prepare("
                    INSERT INTO product_specs (product_id, spec_key, spec_value) 
                    VALUES (?, ?, ?)
                ");
                
                foreach ($specs as $key => $value) {
                    if (!empty($key) && $value !== null && $value !== '') {
                        $ins->execute([$product_id, $key, (string)$value]);
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get all specifications for a product
     */
    public function getByProduct($product_id) {
        $stmt = $this->db->prepare("
            SELECT spec_key, spec_value 
            FROM product_specs 
            WHERE product_id = ? 
            ORDER BY spec_key ASC
        ");
        $stmt->execute([$product_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $specs = [];
        foreach ($rows as $row) {
            $specs[$row['spec_key']] = $row['spec_value'];
        }
        
        return $specs;
    }

    /**
     * Get a specific specification value
     */
    public function getSpec($product_id, $spec_key) {
        $stmt = $this->db->prepare("
            SELECT spec_value 
            FROM product_specs 
            WHERE product_id = ? AND spec_key = ? 
            LIMIT 1
        ");
        $stmt->execute([$product_id, $spec_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['spec_value'] : null;
    }

    /**
     * Add or update a single specification
     */
    public function setSpec($product_id, $spec_key, $spec_value) {
        // Check if spec already exists
        $stmt = $this->db->prepare("
            SELECT id FROM product_specs 
            WHERE product_id = ? AND spec_key = ? 
            LIMIT 1
        ");
        $stmt->execute([$product_id, $spec_key]);
        
        if ($stmt->fetch()) {
            // Update existing
            $stmt = $this->db->prepare("
                UPDATE product_specs 
                SET spec_value = ? 
                WHERE product_id = ? AND spec_key = ?
            ");
            return $stmt->execute([$spec_value, $product_id, $spec_key]);
        } else {
            // Insert new
            $stmt = $this->db->prepare("
                INSERT INTO product_specs (product_id, spec_key, spec_value) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$product_id, $spec_key, $spec_value]);
        }
    }

    /**
     * Delete a specific specification
     */
    public function deleteSpec($product_id, $spec_key) {
        $stmt = $this->db->prepare("
            DELETE FROM product_specs 
            WHERE product_id = ? AND spec_key = ?
        ");
        return $stmt->execute([$product_id, $spec_key]);
    }

    /**
     * Get products by specification value
     */
    public function findBySpec($company_id, $spec_key, $spec_value) {
        $stmt = $this->db->prepare("
            SELECT p.* 
            FROM products p
            INNER JOIN product_specs ps ON p.id = ps.product_id
            WHERE p.company_id = ? 
            AND ps.spec_key = ? 
            AND ps.spec_value = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$company_id, $spec_key, $spec_value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all unique values for a specific spec key within a company
     */
    public function getUniqueSpecValues($company_id, $spec_key) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT ps.spec_value 
            FROM product_specs ps
            INNER JOIN products p ON ps.product_id = p.id
            WHERE p.company_id = ? 
            AND ps.spec_key = ? 
            AND ps.spec_value IS NOT NULL 
            AND ps.spec_value != ''
            ORDER BY ps.spec_value ASC
        ");
        $stmt->execute([$company_id, $spec_key]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Delete all specifications for a product
     */
    public function deleteByProductId($product_id) {
        $stmt = $this->db->prepare("DELETE FROM product_specs WHERE product_id = ?");
        return $stmt->execute([$product_id]);
    }
}
