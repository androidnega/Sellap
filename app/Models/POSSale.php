<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class POSSale {
    private $conn;
    private $table = 'pos_sales';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Generate custom sale code (e.g., SEL-SALE-001)
     */
    private function generateSaleCode($companyId = null) {
        try {
            // Get the highest existing sale code number
            // If company_id is provided, we could scope it per company, but for simplicity, we'll use global numbering
            $stmt = $this->conn->query("SELECT unique_id FROM {$this->table} WHERE unique_id IS NOT NULL AND unique_id LIKE 'SEL-SALE-%' ORDER BY unique_id DESC LIMIT 1");
            $lastCode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $nextNumber = 1;
            if ($lastCode && !empty($lastCode['unique_id'])) {
                // Extract number from last code (e.g., "SEL-SALE-001" -> 1)
                if (preg_match('/SEL-SALE-(\d+)/', $lastCode['unique_id'], $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
            }
            
            // Generate new code with zero-padding (001, 002, etc.)
            return 'SEL-SALE-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            error_log("POSSale::generateSaleCode error: " . $e->getMessage());
            // Fallback to timestamp-based ID if generation fails
            return 'SEL-SALE-' . date('YmdHis') . '-' . rand(100, 999);
        }
    }

    /**
     * Create a new POS sale (Multi-tenant)
     */
    public function create($data) {
        // Generate custom sale code
        $saleCode = $this->generateSaleCode($data['company_id'] ?? null);
        
        // Check if new columns exist, fallback to old schema if not
        $columnsExist = $this->checkSwapColumnsExist();
        
        if ($columnsExist) {
            $sql = "INSERT INTO {$this->table} (company_id, unique_id, customer_id, total_amount, discount, tax, final_amount, payment_method, payment_status, created_by_user_id, notes, swap_id, is_swap_mode)
                    VALUES (:company_id, :unique_id, :customer_id, :total_amount, :discount, :tax, :final_amount, :payment_method, :payment_status, :created_by_user_id, :notes, :swap_id, :is_swap_mode)";
        } else {
            $sql = "INSERT INTO {$this->table} (company_id, unique_id, customer_id, total_amount, discount, tax, final_amount, payment_method, payment_status, created_by_user_id, notes)
                    VALUES (:company_id, :unique_id, :customer_id, :total_amount, :discount, :tax, :final_amount, :payment_method, :payment_status, :created_by_user_id, :notes)";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        $discount = $data['discount'] ?? 0;
        $tax = $data['tax'] ?? 0;
        $totalAmount = $data['total_amount'] ?? $data['total'] ?? 0;
        // Use provided final_amount if available, otherwise calculate it
        $finalAmount = $data['final_amount'] ?? ($totalAmount - $discount + $tax);
        
        // Handle payment method - convert 'swap' to 'CASH' since swap is tracked via is_swap_mode
        $paymentMethod = strtoupper($data['payment_method'] ?? 'CASH');
        if ($paymentMethod === 'SWAP' || ($data['is_swap_mode'] ?? false)) {
            $paymentMethod = 'CASH'; // Swap transactions use CASH as payment method since swap is tracked separately
        }
        
        // Validate payment method is in allowed enum values
        $allowedMethods = ['CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            $paymentMethod = 'CASH'; // Fallback to CASH if invalid
        }
        
        $params = [
            'company_id' => $data['company_id'],
            'unique_id' => $data['unique_id'] ?? $saleCode,
            'customer_id' => $data['customer_id'] ?? null,
            'total_amount' => $totalAmount,
            'discount' => $discount,
            'tax' => $tax,
            'final_amount' => $finalAmount,
            'payment_method' => $paymentMethod,
            'payment_status' => strtoupper($data['payment_status'] ?? 'PAID'),
            'created_by_user_id' => $data['created_by_user_id'] ?? $data['cashier_id'] ?? 1,
            'notes' => $data['notes'] ?? null
        ];
        
        // Only add swap columns if they exist
        if ($columnsExist) {
            $params['swap_id'] = $data['swap_id'] ?? null;
            $params['is_swap_mode'] = $data['is_swap_mode'] ?? false;
        }
        
        try {
            $stmt->execute($params);
            return $this->conn->lastInsertId();
        } catch (\PDOException $e) {
            error_log("POSSale create error: " . $e->getMessage());
            error_log("POSSale create SQL: " . $sql);
            error_log("POSSale create params: " . json_encode($params));
            throw new \Exception('Failed to create sale: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if swap columns exist in the table
     */
    private function checkSwapColumnsExist() {
        try {
            $sql = "SHOW COLUMNS FROM {$this->table} LIKE 'swap_id'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Find sale by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all sales
     */
    public function all() {
        return $this->conn->query("SELECT p.*, c.full_name as customer_name, u.username as cashier 
                                   FROM {$this->table} p 
                                   LEFT JOIN customers c ON p.customer_id = c.id 
                                   LEFT JOIN users u ON p.created_by_user_id = u.id 
                                   ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all sales by company (Multi-tenant filtering)
     */
    public function allByCompany($company_id) {
        $stmt = $this->conn->prepare("SELECT p.*, c.full_name as customer_name, u.username as cashier 
                                       FROM {$this->table} p 
                                       LEFT JOIN customers c ON p.customer_id = c.id 
                                       LEFT JOIN users u ON p.created_by_user_id = u.id 
                                       WHERE p.company_id = :company_id 
                                       ORDER BY p.id DESC");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's sales
     */
    public function today() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE DATE(created_at) = CURDATE() ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sales summary
     */
    public function summary($startDate = null, $endDate = null) {
        $sql = "SELECT 
                    COUNT(*) as total_sales,
                    SUM(final_amount) as total_revenue,
                    AVG(final_amount) as average_sale
                FROM {$this->table}";
        
        if ($startDate && $endDate) {
            $sql .= " WHERE DATE(created_at) BETWEEN :start AND :end";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get sales summary by company (Multi-tenant filtering)
     */
    public function summaryByCompany($company_id, $startDate = null, $endDate = null) {
        $sql = "SELECT 
                    COUNT(*) as total_sales,
                    SUM(final_amount) as total_revenue,
                    AVG(final_amount) as average_sale
                FROM {$this->table}
                WHERE company_id = :company_id";
        
        if ($startDate && $endDate) {
            $sql .= " AND DATE(created_at) BETWEEN :start AND :end";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['company_id' => $company_id, 'start' => $startDate, 'end' => $endDate]);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['company_id' => $company_id]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Find sale by ID and company
     */
    public function find($id, $company_id) {
        $stmt = $this->conn->prepare("
            SELECT p.*, c.full_name as customer_name, u.username as cashier 
            FROM {$this->table} p 
            LEFT JOIN customers c ON p.customer_id = c.id 
            LEFT JOIN users u ON p.created_by_user_id = u.id 
            WHERE p.id = :id AND p.company_id = :company_id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'company_id' => $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update sale
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete sale
     */
    public function delete($id, $company_id = null) {
        if ($company_id !== null) {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id AND company_id = :company_id");
            return $stmt->execute(['id' => $id, 'company_id' => $company_id]);
        } else {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        }
    }


    /**
     * Find sales by company
     */
    public function findByCompany($company_id, $limit = 100, $sale_type = null, $date_from = null, $date_to = null) {
        // Determine which products table exists
        $productsTable = 'products';
        try {
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'products_new'");
            if ($checkTable->rowCount() > 0) {
                $productsTable = 'products_new';
            } else {
                $checkTable2 = $this->conn->query("SHOW TABLES LIKE 'products'");
                if ($checkTable2->rowCount() === 0) {
                    $productsTable = null;
                }
            }
        } catch (\Exception $e) {
            $productsTable = null;
        }
        
        // Build product-related subqueries only if products table exists
        $firstItemProductName = "NULL as first_item_product_name";
        $firstItemCategory = "'' as first_item_category";
        
        if ($productsTable) {
            // Check if categories table exists and if products has category_id
            try {
                $checkCategories = $this->conn->query("SHOW TABLES LIKE 'categories'");
                $hasCategories = $checkCategories->rowCount() > 0;
                
                $checkCategoryId = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'category_id'");
                $hasCategoryId = $checkCategoryId->rowCount() > 0;
                
                $checkCategory = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'category'");
                $hasCategory = $checkCategory->rowCount() > 0;
                
                $firstItemProductName = "(SELECT p.name FROM pos_sale_items psi 
                    LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                    WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                    ORDER BY psi.id LIMIT 1) as first_item_product_name";
                
                if ($hasCategories && $hasCategoryId) {
                    $firstItemCategory = "(SELECT COALESCE(cat.name, '') FROM pos_sale_items psi 
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                        ORDER BY psi.id LIMIT 1) as first_item_category";
                } elseif ($hasCategory) {
                    $firstItemCategory = "(SELECT COALESCE(p.category, '') FROM pos_sale_items psi 
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                        WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                        ORDER BY psi.id LIMIT 1) as first_item_category";
                }
            } catch (\Exception $e) {
                // If there's an error checking columns, use simpler queries
                error_log("POSSale::findByCompany: Error checking table structure: " . $e->getMessage());
            }
        }
        
        // Check if swapped item columns exist in products table
        $hasIsSwappedItem = false;
        $hasInventoryProductId = false;
        $isSwappedItemSelect = "0 as has_swapped_items";
        
        if ($productsTable) {
            try {
                $checkIsSwapped = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'is_swapped_item'");
                $hasIsSwappedItem = $checkIsSwapped->rowCount() > 0;
                
                $checkInventoryProductId = $this->conn->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
                $hasInventoryProductId = $checkInventoryProductId->rowCount() > 0;
                
                if ($hasIsSwappedItem || $hasInventoryProductId) {
                    $conditions = [];
                    if ($hasIsSwappedItem) {
                        $conditions[] = "p.is_swapped_item = 1";
                    }
                    if ($hasInventoryProductId) {
                        $conditions[] = "si.inventory_product_id IS NOT NULL";
                    }
                    $isSwappedItemSelect = "(SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END 
                        FROM pos_sale_items psi2 
                        LEFT JOIN {$productsTable} p ON psi2.item_id = p.id 
                        " . ($hasInventoryProductId ? "LEFT JOIN swapped_items si ON p.id = si.inventory_product_id" : "") . "
                        WHERE psi2.pos_sale_id = s.id 
                        AND psi2.item_id IS NOT NULL 
                        AND (" . implode(" OR ", $conditions) . ")) as has_swapped_items";
                }
            } catch (\Exception $e) {
                error_log("POSSale::findByCompany: Error checking swapped item columns: " . $e->getMessage());
            }
        }
        
        $sql = "
            SELECT 
                s.*, 
                u.full_name as cashier_name, 
                c.full_name as customer_name_from_table,
                (SELECT COUNT(*) FROM pos_sale_items WHERE pos_sale_id = s.id) as item_count,
                (SELECT item_description FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_name,
                {$firstItemProductName},
                {$firstItemCategory},
                {$isSwappedItemSelect}
            FROM {$this->table} s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.company_id = ?
        ";
        
        $params = [$company_id];
        
        if ($sale_type) {
            $sql .= " AND s.sale_type = ?";
            $params[] = $sale_type;
        }
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT " . (int)$limit;
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("POSSale::findByCompany SQL Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Find sales by cashier
     */
    public function findByCashier($cashier_id, $company_id, $date_from = null, $date_to = null) {
        // Determine which products table exists
        $productsTable = 'products';
        try {
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'products_new'");
            if ($checkTable->rowCount() > 0) {
                $productsTable = 'products_new';
            } else {
                $checkTable2 = $this->conn->query("SHOW TABLES LIKE 'products'");
                if ($checkTable2->rowCount() === 0) {
                    $productsTable = null;
                }
            }
        } catch (\Exception $e) {
            $productsTable = null;
        }
        
        // Build product-related subqueries only if products table exists
        $firstItemProductName = "NULL as first_item_product_name";
        $firstItemCategory = "'' as first_item_category";
        
        if ($productsTable) {
            // Check if categories table exists and if products has category_id
            try {
                $checkCategories = $this->conn->query("SHOW TABLES LIKE 'categories'");
                $hasCategories = $checkCategories->rowCount() > 0;
                
                $checkCategoryId = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'category_id'");
                $hasCategoryId = $checkCategoryId->rowCount() > 0;
                
                $checkCategory = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'category'");
                $hasCategory = $checkCategory->rowCount() > 0;
                
                $firstItemProductName = "(SELECT p.name FROM pos_sale_items psi 
                    LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                    WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                    ORDER BY psi.id LIMIT 1) as first_item_product_name";
                
                if ($hasCategories && $hasCategoryId) {
                    $firstItemCategory = "(SELECT COALESCE(cat.name, '') FROM pos_sale_items psi 
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                        ORDER BY psi.id LIMIT 1) as first_item_category";
                } elseif ($hasCategory) {
                    $firstItemCategory = "(SELECT COALESCE(p.category, '') FROM pos_sale_items psi 
                        LEFT JOIN {$productsTable} p ON psi.item_id = p.id 
                        WHERE psi.pos_sale_id = s.id AND psi.item_id IS NOT NULL
                        ORDER BY psi.id LIMIT 1) as first_item_category";
                }
            } catch (\Exception $e) {
                // If there's an error checking columns, use simpler queries
                error_log("POSSale::findByCashier: Error checking table structure: " . $e->getMessage());
            }
        }
        
        // Check if swapped item columns exist in products table
        $hasIsSwappedItem = false;
        $hasInventoryProductId = false;
        $isSwappedItemSelect = "0 as has_swapped_items";
        
        if ($productsTable) {
            try {
                $checkIsSwapped = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'is_swapped_item'");
                $hasIsSwappedItem = $checkIsSwapped->rowCount() > 0;
                
                $checkInventoryProductId = $this->conn->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
                $hasInventoryProductId = $checkInventoryProductId->rowCount() > 0;
                
                if ($hasIsSwappedItem || $hasInventoryProductId) {
                    $conditions = [];
                    if ($hasIsSwappedItem) {
                        $conditions[] = "p.is_swapped_item = 1";
                    }
                    if ($hasInventoryProductId) {
                        $conditions[] = "si.inventory_product_id IS NOT NULL";
                    }
                    $isSwappedItemSelect = "(SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END 
                        FROM pos_sale_items psi2 
                        LEFT JOIN {$productsTable} p ON psi2.item_id = p.id 
                        " . ($hasInventoryProductId ? "LEFT JOIN swapped_items si ON p.id = si.inventory_product_id" : "") . "
                        WHERE psi2.pos_sale_id = s.id 
                        AND psi2.item_id IS NOT NULL 
                        AND (" . implode(" OR ", $conditions) . ")) as has_swapped_items";
                }
            } catch (\Exception $e) {
                error_log("POSSale::findByCashier: Error checking swapped item columns: " . $e->getMessage());
            }
        }
        
        // Check if is_swap_mode column exists
        $hasIsSwapMode = false;
        try {
            $checkIsSwapMode = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("POSSale::findByCashier: Error checking is_swap_mode column: " . $e->getMessage());
        }
        
        $sql = "
            SELECT 
                s.*, 
                u.full_name as cashier_name, 
                c.full_name as customer_name_from_table,
                (SELECT COUNT(*) FROM pos_sale_items WHERE pos_sale_id = s.id) as item_count,
                (SELECT item_description FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_name,
                {$firstItemProductName},
                {$firstItemCategory},
                {$isSwappedItemSelect}
            FROM {$this->table} s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.created_by_user_id = ? AND s.company_id = ?
        ";
        
        $params = [$cashier_id, $company_id];
        
        // Exclude swap sales from sales history
        if ($hasIsSwapMode) {
            $sql .= " AND (s.is_swap_mode = 0 OR s.is_swap_mode IS NULL)";
        }
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("POSSale::findByCashier SQL Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Find sales by cashier excluding specific role
     */
    public function findByCashierExcludingRole($cashier_id, $company_id, $excludeRole, $date_from = null, $date_to = null) {
        // Check which table exists
        $checkTable = $this->conn->query("SHOW TABLES LIKE 'pos_sales'");
        $hasPosSales = $checkTable && $checkTable->rowCount() > 0;
        $this->table = $hasPosSales ? 'pos_sales' : 'pos_sale';
        
        // Check for product_name column in pos_sale_items
        $checkProductName = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'product_name'");
        $hasProductName = $checkProductName && $checkProductName->rowCount() > 0;
        $firstItemProductName = $hasProductName 
            ? "(SELECT product_name FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_product_name"
            : "NULL as first_item_product_name";
        
        // Check for category_name column
        $checkCategory = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'category_name'");
        $hasCategory = $checkCategory && $checkCategory->rowCount() > 0;
        $firstItemCategory = $hasCategory 
            ? "(SELECT category_name FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_category"
            : "NULL as first_item_category";
        
        // Check for swapped items - check both pos_sale_items and products table
        $checkSwapped = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'is_swapped_item'");
        $hasSwapped = $checkSwapped && $checkSwapped->rowCount() > 0;
        
        // Also check if products table has is_swapped_item column
        $hasProductsSwapped = false;
        $productsTable = 'products';
        try {
            $checkProductsSwapped = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'is_swapped_item'");
            $hasProductsSwapped = $checkProductsSwapped && $checkProductsSwapped->rowCount() > 0;
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        // Build query to check for swapped items - check both pos_sale_items and products table
        if ($hasSwapped || $hasProductsSwapped) {
            $conditions = [];
            if ($hasSwapped) {
                $conditions[] = "psi.is_swapped_item = 1";
            }
            if ($hasProductsSwapped) {
                $conditions[] = "p.is_swapped_item = 1";
            }
            $isSwappedItemSelect = "(SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END 
                FROM pos_sale_items psi 
                " . ($hasProductsSwapped ? "LEFT JOIN {$productsTable} p ON psi.item_id = p.id" : "") . "
                WHERE psi.pos_sale_id = s.id 
                AND (" . implode(" OR ", $conditions) . ")) as has_swapped_items";
        } else {
            $isSwappedItemSelect = "0 as has_swapped_items";
        }
        
        // Check if is_swap_mode column exists
        $hasIsSwapMode = false;
        try {
            $checkIsSwapMode = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("POSSale::findByCashierExcludingRole: Error checking is_swap_mode column: " . $e->getMessage());
        }
        
        $sql = "
            SELECT 
                s.*, 
                u.full_name as cashier_name,
                u.role as cashier_role,
                c.full_name as customer_name_from_table,
                (SELECT COUNT(*) FROM pos_sale_items WHERE pos_sale_id = s.id) as item_count,
                (SELECT item_description FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_name,
                {$firstItemProductName},
                {$firstItemCategory},
                {$isSwappedItemSelect}
            FROM {$this->table} s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.created_by_user_id = ? 
              AND s.company_id = ?
              AND (u.role IS NULL OR u.role != ?)
        ";
        
        $params = [$cashier_id, $company_id, $excludeRole];
        
        // Exclude swap sales from sales history
        if ($hasIsSwapMode) {
            $sql .= " AND (s.is_swap_mode = 0 OR s.is_swap_mode IS NULL)";
        }
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("POSSale::findByCashierExcludingRole SQL Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Find sales by company with role information
     */
    public function findByCompanyWithRoles($company_id, $limit = 100, $sale_type = null, $date_from = null, $date_to = null) {
        // Check which table exists
        $checkTable = $this->conn->query("SHOW TABLES LIKE 'pos_sales'");
        $hasPosSales = $checkTable && $checkTable->rowCount() > 0;
        $this->table = $hasPosSales ? 'pos_sales' : 'pos_sale';
        
        // Check for product_name column in pos_sale_items
        $checkProductName = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'product_name'");
        $hasProductName = $checkProductName && $checkProductName->rowCount() > 0;
        $firstItemProductName = $hasProductName 
            ? "(SELECT product_name FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_product_name"
            : "NULL as first_item_product_name";
        
        // Check for category_name column
        $checkCategory = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'category_name'");
        $hasCategory = $checkCategory && $checkCategory->rowCount() > 0;
        $firstItemCategory = $hasCategory 
            ? "(SELECT category_name FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_category"
            : "NULL as first_item_category";
        
        // Check for swapped items - check pos_sale_items, products table, and swapped_items table
        $checkSwapped = $this->conn->query("SHOW COLUMNS FROM pos_sale_items LIKE 'is_swapped_item'");
        $hasSwapped = $checkSwapped && $checkSwapped->rowCount() > 0;
        
        // Also check if products table has is_swapped_item column
        $hasProductsSwapped = false;
        $productsTable = 'products';
        try {
            $checkProductsSwapped = $this->conn->query("SHOW COLUMNS FROM {$productsTable} LIKE 'is_swapped_item'");
            $hasProductsSwapped = $checkProductsSwapped && $checkProductsSwapped->rowCount() > 0;
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        // Check if swapped_items table has inventory_product_id column
        $hasInventoryProductId = false;
        try {
            $checkInventoryProductId = $this->conn->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
            $hasInventoryProductId = $checkInventoryProductId && $checkInventoryProductId->rowCount() > 0;
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        // Build query to check for swapped items - check pos_sale_items, products table, and swapped_items
        if ($hasSwapped || $hasProductsSwapped || $hasInventoryProductId) {
            $conditions = [];
            if ($hasSwapped) {
                $conditions[] = "psi.is_swapped_item = 1";
            }
            if ($hasProductsSwapped) {
                $conditions[] = "p.is_swapped_item = 1";
            }
            if ($hasInventoryProductId) {
                $conditions[] = "si.inventory_product_id IS NOT NULL";
            }
            
            $joins = [];
            if ($hasProductsSwapped) {
                $joins[] = "LEFT JOIN {$productsTable} p ON psi.item_id = p.id";
            }
            if ($hasInventoryProductId) {
                $joins[] = "LEFT JOIN swapped_items si ON psi.item_id = si.inventory_product_id";
            }
            
            $isSwappedItemSelect = "(SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END 
                FROM pos_sale_items psi 
                " . (!empty($joins) ? implode(" ", $joins) : "") . "
                WHERE psi.pos_sale_id = s.id 
                AND psi.item_id IS NOT NULL
                AND (" . implode(" OR ", $conditions) . ")) as has_swapped_items";
        } else {
            $isSwappedItemSelect = "0 as has_swapped_items";
        }
        
        $sql = "
            SELECT 
                s.*, 
                u.full_name as cashier_name,
                u.role as cashier_role,
                u.username as cashier_username,
                c.full_name as customer_name_from_table,
                (SELECT COUNT(*) FROM pos_sale_items WHERE pos_sale_id = s.id) as item_count,
                (SELECT item_description FROM pos_sale_items WHERE pos_sale_id = s.id ORDER BY id LIMIT 1) as first_item_name,
                {$firstItemProductName},
                {$firstItemCategory},
                {$isSwappedItemSelect}
            FROM {$this->table} s
            LEFT JOIN users u ON s.created_by_user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.company_id = ?
        ";
        
        $params = [$company_id];
        
        // Always exclude swap sales from sales history (swaps should only appear on swap page)
        // Check if is_swap_mode column exists
        $hasIsSwapMode = false;
        try {
            $checkIsSwapMode = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("POSSale::findByCompanyWithRoles: Error checking is_swap_mode column: " . $e->getMessage());
        }
        
        if ($hasIsSwapMode) {
            $sql .= " AND (s.is_swap_mode = 0 OR s.is_swap_mode IS NULL)";
        }
        
        // Note: sale_type filter removed - swaps are always excluded from sales history
        // If sale_type === 'swap' was requested, it will return empty results (as intended)
        
        if ($date_from) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT " . intval($limit);
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("POSSale::findByCompanyWithRoles SQL Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Get sales statistics
     */
    public function getStats($company_id, $date_from = null, $date_to = null) {
        $sql = "
            SELECT 
                COUNT(*) as total_sales,
                SUM(final_amount) as total_revenue,
                AVG(final_amount) as average_sale,
                SUM(discount) as total_discount,
                SUM(tax) as total_tax
            FROM {$this->table}
            WHERE company_id = ?
        ";
        
        $params = [$company_id];
        
        if ($date_from) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily report
     */
    public function getDailyReport($company_id, $date) {
        $sql = "
            SELECT 
                COUNT(*) as total_sales,
                SUM(final_amount) as total_revenue,
                AVG(final_amount) as average_sale,
                SUM(discount) as total_discount,
                SUM(tax) as total_tax
            FROM {$this->table}
            WHERE company_id = ? AND DATE(created_at) = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$company_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of sales by company (excluding swap sales)
     */
    public function getTotalCountByCompany($company_id, $date_from = null, $date_to = null) {
        // Check if is_swap_mode column exists
        $hasIsSwapMode = false;
        try {
            $checkIsSwapMode = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_swap_mode'");
            $hasIsSwapMode = $checkIsSwapMode->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("POSSale::getTotalCountByCompany: Error checking is_swap_mode column: " . $e->getMessage());
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE company_id = ?";
        $params = [$company_id];
        
        // Exclude swap sales from sales history count
        if ($hasIsSwapMode) {
            $sql .= " AND (is_swap_mode = 0 OR is_swap_mode IS NULL)";
        }
        
        if ($date_from) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Find sales by company with pagination
     */
    public function findByCompanyPaginated($company_id, $page = 1, $limit = 20, $date_from = null, $date_to = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT p.*, c.full_name as customer_name, u.username as cashier 
                FROM {$this->table} p 
                LEFT JOIN customers c ON p.customer_id = c.id 
                LEFT JOIN users u ON p.created_by_user_id = u.id 
                WHERE p.company_id = ?";
        
        $params = [$company_id];
        
        if ($date_from) {
            $sql .= " AND DATE(p.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(p.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

