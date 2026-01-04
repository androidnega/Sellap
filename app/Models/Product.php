<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Product {
    private $db;
    private $table = 'products';  // Unified products table (after migration, products_new becomes products)

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Column cache for products table
     */
    private static $productsTableColumns = null;

    private function loadProductsTableColumns(): void {
        if (self::$productsTableColumns !== null) {
            return;
        }
        try {
            $stmt = $this->db->query("DESCRIBE products");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            self::$productsTableColumns = array_flip($cols ?: []);
        } catch (\Exception $e) {
            self::$productsTableColumns = [];
        }
    }

    private function productsHasColumn(string $column): bool {
        $this->loadProductsTableColumns();
        return isset(self::$productsTableColumns[$column]);
    }

    private function buildProductSelectFields(): string {
        // Always present mappings
        $base = [
            'p.*',
            'p.category as category_name',
            'p.brand as brand_name'
        ];

        // Quantity alias
        if ($this->productsHasColumn('qty')) {
            $base[] = 'p.qty as quantity';
        } elseif ($this->productsHasColumn('quantity')) {
            $base[] = 'p.quantity as quantity';
        } else {
            $base[] = '0 as quantity';
        }

        // Model alias
        if ($this->productsHasColumn('model_name')) {
            $base[] = 'p.model_name as model_name';
        } elseif ($this->productsHasColumn('model')) {
            $base[] = 'p.model as model_name';
        } else {
            $base[] = 'NULL as model_name';
        }

        // Location alias
        if ($this->productsHasColumn('item_location')) {
            $base[] = 'p.item_location as item_location';
        } elseif ($this->productsHasColumn('location')) {
            $base[] = 'p.location as item_location';
        } else {
            $base[] = 'NULL as item_location';
        }

        return implode(", ", $base);
    }

    /**
     * Generate a unique product ID based on product name
     */
    public function generateProductId($name, $companyId = null) {
        // Clean the product name
        $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
        $cleanName = preg_replace('/\s+/', '', $cleanName); // Remove spaces
        $cleanName = strtoupper(substr($cleanName, 0, 8)); // Take first 8 chars, uppercase
        
        // Generate a random number
        $randomNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create the product ID
        $productId = $cleanName . $randomNumber;
        
        // Check if this ID already exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE product_id = ?");
        $stmt->execute([$productId]);
        $count = $stmt->fetchColumn();
        
        // If exists, try with a different random number
        if ($count > 0) {
            $randomNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $productId = $cleanName . $randomNumber;
        }
        
        return $productId;
    }

    /**
     * Create a new product
     */
    public function create(array $data) {
        // Generate product ID if not provided
        if (empty($data['product_id'])) {
            $data['product_id'] = $this->generateProductId($data['name'], $data['company_id'] ?? null);
        }
        
        // Check which cost column exists and get cost value
        $hasCostPrice = $this->productsHasColumn('cost_price');
        $hasCost = $this->productsHasColumn('cost');
        $costValue = floatval($data['cost_price'] ?? $data['cost'] ?? 0);
        
        // Check if supplier_id column exists
        $hasSupplierId = $this->productsHasColumn('supplier_id');
        
        // Build columns and values dynamically
        $columns = [
            'company_id', 'name', 'category_id', 'brand_id', 'subcategory_id', 'specs', 'price',
            'quantity', 'item_location', 'available_for_swap', 'status', 'created_by', 'sku', 'model_name',
            'description', 'image_url', 'weight', 'dimensions', 'supplier', 'product_id'
        ];
        $values = [
            $data['company_id'] ?? null,
            $data['name'],
            $data['category_id'],
            $data['brand_id'] ?? null,
            $data['subcategory_id'] ?? null,
            $data['specs'] ? json_encode($data['specs']) : null,
            $data['price'] ?? 0,
            $data['quantity'] ?? 0,
            $data['item_location'] ?? null,
            $data['available_for_swap'] ? 1 : 0,
            $data['status'] ?? 'available',
            $data['created_by'] ?? null,
            $data['sku'] ?? null,
            $data['model_name'] ?? null,
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['weight'] ?? null,
            $data['dimensions'] ?? null,
            $data['supplier'] ?? null,
            $data['product_id']
        ];
        
        // Add supplier_id if column exists
        if ($hasSupplierId && isset($data['supplier_id'])) {
            $columns[] = 'supplier_id';
            $values[] = $data['supplier_id'];
        }
        
        // Add cost column (prioritize cost_price if both exist)
        if ($hasCostPrice) {
            array_splice($columns, 7, 0, 'cost_price'); // Insert after 'price'
            array_splice($values, 7, 0, $costValue);
        } elseif ($hasCost) {
            array_splice($columns, 7, 0, 'cost'); // Insert after 'price'
            array_splice($values, 7, 0, $costValue);
        }
        
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $columnsStr = implode(', ', $columns);
        
        $stmt = $this->db->prepare("
            INSERT INTO products ({$columnsStr}) VALUES ({$placeholders})
        ");
        
        $stmt->execute($values);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update an existing product
     */
    public function update($id, array $data, $company_id) {
        // Check which cost column exists and get cost value
        $hasCostPrice = $this->productsHasColumn('cost_price');
        $hasCost = $this->productsHasColumn('cost');
        $costValue = floatval($data['cost_price'] ?? $data['cost'] ?? 0);
        
        // Build SET clause dynamically
        $setClause = "name=?, category_id=?, brand_id=?, subcategory_id=?, specs=?, price=?";
        $values = [
            $data['name'],
            $data['category_id'],
            $data['brand_id'] ?? null,
            $data['subcategory_id'] ?? null,
            $data['specs'] ? json_encode($data['specs']) : null,
            $data['price'] ?? 0
        ];
        
        // Add cost column (prioritize cost_price if both exist)
        if ($hasCostPrice) {
            $setClause .= ", cost_price=?";
            $values[] = $costValue;
        } elseif ($hasCost) {
            $setClause .= ", cost=?";
            $values[] = $costValue;
        }
        
        $setClause .= ", quantity=?, available_for_swap=?, status=?, sku=?, model_name=?,
                description=?, image_url=?, weight=?, dimensions=?, supplier=?";
        $values = array_merge($values, [
            $data['quantity'] ?? 0,
            $data['available_for_swap'] ? 1 : 0,
            $data['status'] ?? 'available',
            $data['sku'] ?? null,
            $data['model_name'] ?? null,
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['weight'] ?? null,
            $data['dimensions'] ?? null,
            $data['supplier'] ?? null,
            $id,
            $company_id
        ]);
        
        $stmt = $this->db->prepare("
            UPDATE products SET {$setClause}
            WHERE id=? AND company_id=?
        ");
        
        return $stmt->execute($values);
    }

    /**
     * Find products by company for POS (includes swapped items even if quantity is 0)
     */
    public function findByCompanyForPOS($company_id, $limit = 100, $category_id = null, $offset = 0) {
        return $this->findByCompany($company_id, $limit, $category_id, $offset, false, true);
    }

    /**
     * Find products by company with category and brand information
     */
    public function findByCompany($company_id, $limit = 100, $category_id = null, $offset = 0, $swappedItemsOnly = false, $includeSwappedItemsAlways = false) {
        // Check if swapped item columns exist
        $hasIsSwappedItem = $this->productsHasColumn('is_swapped_item');
        $hasSwapRefId = $this->productsHasColumn('swap_ref_id');
        
        $isSwappedItemSelect = $hasIsSwappedItem ? 'COALESCE(p.is_swapped_item, 0) as is_swapped_item,' : '0 as is_swapped_item,';
        $swapRefIdSelect = $hasSwapRefId ? 'p.swap_ref_id,' : 'NULL as swap_ref_id,';
        
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        // Also check if product is linked via inventory_product_id in swapped_items table
        // This handles cases where swapped items were synced to products table but flags might not be set
        $hasInventoryProductId = false;
        try {
            $checkCol = $this->db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
            $hasInventoryProductId = $checkCol && $checkCol->rowCount() > 0;
        } catch (\Exception $e) {
            $hasInventoryProductId = false;
        }
        
        // Add additional join to check inventory_product_id
        $si2Join = '';
        if ($hasInventoryProductId) {
            $si2Join = "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id";
        }
        
        $sql = "
            SELECT 
                p.id,
                p.company_id,
                p.name,
                COALESCE(p.product_id, CONCAT('PID-', LPAD(p.id, 3, '0'))) as product_id,
                p.category_id,
                p.brand_id,
                p.price,
                p.cost,
                COALESCE(p.{$quantityColumn}, 0) as quantity,
                COALESCE(p.status, 'available') as status,
                COALESCE(p.available_for_swap, 0) as available_for_swap,
                {$isSwappedItemSelect}
                {$swapRefIdSelect}
                " . ($hasSwapRefId ? "COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as resell_price, COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as display_price," : ($hasInventoryProductId ? "COALESCE(si2.resell_price, p.price, 0) as resell_price, COALESCE(si2.resell_price, p.price, 0) as display_price," : "p.price as resell_price, p.price as display_price,")) . "
                " . ($hasInventoryProductId ? "CASE WHEN si2.id IS NOT NULL THEN 1 ELSE 0 END as has_inventory_link," : "") . "
                p.item_location,
                COALESCE(NULLIF(TRIM(p.model_name), ''), 'N/A') as model_name,
                COALESCE(c.name, 'N/A') as category_name,
                COALESCE(b.name, 'N/A') as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            " . ($hasSwapRefId ? "LEFT JOIN swapped_items si ON p.swap_ref_id = si.id" : "") . "
            {$si2Join}
            WHERE p.company_id = ?
        ";
        $params = [$company_id];
        
        if ($category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        if ($swappedItemsOnly && $hasIsSwappedItem) {
            $sql .= " AND p.is_swapped_item = 1";
        } elseif (!$includeSwappedItemsAlways && $hasIsSwappedItem) {
            // Exclude swapped items if not explicitly requested for POS or swappedItemsOnly
            // Only add this filter if the column exists
            // But also exclude items linked via inventory_product_id
            if ($hasInventoryProductId) {
                $sql .= " AND COALESCE(p.is_swapped_item, 0) = 0 AND (si2.id IS NULL)";
            } else {
                $sql .= " AND COALESCE(p.is_swapped_item, 0) = 0";
            }
        }
        
        // For POS (includeSwappedItemsAlways = true), show only products with quantity > 0
        // This includes both regular products and swapped items that are in stock
        // Swapped items with quantity > 0 should be visible for resale
        if ($includeSwappedItemsAlways) {
            // For POS, show only products with quantity > 0 (in stock)
            // This includes regular products AND swapped items that are in stock
            // Swapped items are already included because we don't exclude them when includeSwappedItemsAlways = true
            $sql .= " AND COALESCE(p.{$quantityColumn}, 0) > 0";
        } elseif (!$swappedItemsOnly && $hasIsSwappedItem) {
            // For regular product/inventory views: show all items including quantity = 0
            // Salespersons need to see all items regardless of quantity
            // Only hide swapped items that have been sold (quantity = 0) if they're not needed
            // But for salespersons viewing inventory, show everything
            // No quantity filter - show all items
        } elseif (!$swappedItemsOnly) {
            // For salespersons and regular inventory views: show all items including quantity = 0
            // No quantity filter - show all items
        }
        
        $sql .= " ORDER BY p.id DESC LIMIT " . intval($limit);
        
        if (isset($offset) && $offset > 0) {
            $sql .= " OFFSET " . intval($offset);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update is_swapped_item flag if product is linked via inventory_product_id
        if ($hasInventoryProductId && is_array($results)) {
            foreach ($results as &$row) {
                if (isset($row['has_inventory_link']) && intval($row['has_inventory_link']) > 0) {
                    // Product is linked via inventory_product_id, mark as swapped item
                    $row['is_swapped_item'] = 1;
                }
                unset($row['has_inventory_link']);
            }
        }
        
        return $results;
    }

    /**
     * Find products by company with pagination
     */
    public function findByCompanyPaginated($company_id, $page = 1, $limit = 10, $category_id = null, $swappedItemsOnly = false, $stockFilter = null) {
        // Ensure values are integers
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        // Ensure page is at least 1
        if ($page < 1) {
            $page = 1;
            $offset = 0;
        }
        
        // Check if swapped item columns exist
        $hasIsSwappedItem = $this->productsHasColumn('is_swapped_item');
        $hasSwapRefId = $this->productsHasColumn('swap_ref_id');
        
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        // Also check if product is linked via inventory_product_id in swapped_items table
        // This handles cases where swapped items were synced to products table but flags might not be set
        $hasInventoryProductId = false;
        try {
            $checkCol = $this->db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
            $hasInventoryProductId = $checkCol && $checkCol->rowCount() > 0;
        } catch (\Exception $e) {
            $hasInventoryProductId = false;
        }
        
        $isSwappedItemSelect = $hasIsSwappedItem ? 'COALESCE(p.is_swapped_item, 0) as is_swapped_item,' : '0 as is_swapped_item,';
        $swapRefIdSelect = $hasSwapRefId ? 'p.swap_ref_id,' : 'NULL as swap_ref_id,';
        
        // Add additional join to check inventory_product_id
        $si2Join = '';
        $hasInventoryLinkSelect = '';
        if ($hasInventoryProductId) {
            $si2Join = "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id";
            $hasInventoryLinkSelect = "CASE WHEN si2.id IS NOT NULL THEN 1 ELSE 0 END as has_inventory_link,";
        }
        
        // Join with swapped_items to get resell_price for swapped items
        $resellPriceSelect = $hasSwapRefId ? 'COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as resell_price, COALESCE(si.resell_price, COALESCE(si2.resell_price, p.price, 0), 0) as display_price,' : ($hasInventoryProductId ? 'COALESCE(si2.resell_price, p.price, 0) as resell_price, COALESCE(si2.resell_price, p.price, 0) as display_price,' : 'p.price as resell_price, p.price as display_price,');
        
        $sql = "
            SELECT 
                p.id,
                p.company_id,
                p.name,
                COALESCE(p.product_id, CONCAT('PID-', LPAD(p.id, 3, '0'))) as product_id,
                p.category_id,
                p.brand_id,
                p.price,
                p.cost,
                COALESCE(p.{$quantityColumn}, 0) as quantity,
                COALESCE(p.status, 'available') as status,
                COALESCE(p.available_for_swap, 0) as available_for_swap,
                {$isSwappedItemSelect}
                {$swapRefIdSelect}
                {$hasInventoryLinkSelect}
                {$resellPriceSelect}
                p.item_location,
                COALESCE(NULLIF(TRIM(p.model_name), ''), 'N/A') as model_name,
                COALESCE(c.name, 'N/A') as category_name,
                COALESCE(b.name, 'N/A') as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            " . ($hasSwapRefId ? "LEFT JOIN swapped_items si ON p.swap_ref_id = si.id" : "") . "
            {$si2Join}
            WHERE p.company_id = ?
        ";
        $params = [$company_id];
        
        if ($category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        if ($swappedItemsOnly && $hasIsSwappedItem) {
            $sql .= " AND (p.is_swapped_item = 1" . ($hasInventoryProductId ? " OR si2.id IS NOT NULL" : "") . ")";
        } elseif (!$swappedItemsOnly) {
            // For regular inventory view: show ALL products (including out-of-stock)
            // Salespersons need to see all products in their inventory view
            // Only hide swapped items that have been sold (quantity = 0)
            $conditions = [];
            if ($hasIsSwappedItem) {
                // Hide swapped items with quantity = 0 (they've been sold/resold)
                $conditions[] = "(COALESCE(p.is_swapped_item, 0) = 1 AND COALESCE(p.{$quantityColumn}, 0) = 0)";
            }
            if ($hasInventoryProductId) {
                // Hide swapped items linked via inventory_product_id with quantity = 0
                $conditions[] = "(si2.id IS NOT NULL AND COALESCE(p.{$quantityColumn}, 0) = 0)";
            }
            if (!empty($conditions)) {
                $sql .= " AND NOT (" . implode(" OR ", $conditions) . ")";
            }
            // Show all products (including regular products with quantity = 0)
            // This allows salespersons to see all 278 products in their dashboard
        }
        
        // Apply stock filter if specified
        if ($stockFilter) {
            if ($stockFilter === 'in_stock') {
                // In stock: quantity > 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) > 10";
            } elseif ($stockFilter === 'low_stock') {
                // Low stock: quantity > 0 and <= 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) > 0 AND COALESCE(p.{$quantityColumn}, 0) <= 10";
            } elseif ($stockFilter === 'out_of_stock') {
                // Out of stock: quantity = 0
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) = 0";
            } elseif ($stockFilter === 'low_and_out') {
                // Low and out of stock: quantity <= 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) <= 10";
            }
        }
        
        // Use direct integer values for LIMIT/OFFSET to avoid prepared statement issues
        $sql .= " ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update is_swapped_item flag if product is linked via inventory_product_id
            if ($hasInventoryProductId && is_array($results)) {
                foreach ($results as &$row) {
                    if (isset($row['has_inventory_link']) && intval($row['has_inventory_link']) > 0) {
                        // Product is linked via inventory_product_id, mark as swapped item
                        $row['is_swapped_item'] = 1;
                    }
                    unset($row['has_inventory_link']);
                }
            }
            
            return $results ?: [];
        } catch (\PDOException $e) {
            // Log error for debugging
            error_log("Product findByCompanyPaginated error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of products by company
     */
    public function getTotalCountByCompany($company_id, $category_id = null, $swappedItemsOnly = false, $stockFilter = null) {
        $sql = "SELECT COUNT(*) as total FROM products p WHERE p.company_id = ?";
        $params = [$company_id];

        if ($category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        // Check if swapped item columns exist and if inventory_product_id link exists
        $hasIsSwappedItem = $this->productsHasColumn('is_swapped_item');
        $hasInventoryProductId = false;
        try {
            $checkCol = $this->db->query("SHOW COLUMNS FROM swapped_items LIKE 'inventory_product_id'");
            $hasInventoryProductId = $checkCol && $checkCol->rowCount() > 0;
        } catch (\Exception $e) {
            $hasInventoryProductId = false;
        }
        
        // Add join for inventory_product_id check if needed
        // We need to check this even when not swappedItemsOnly to filter out sold swapped items
        $si2Join = '';
        if ($hasInventoryProductId) {
            $si2Join = "LEFT JOIN swapped_items si2 ON p.id = si2.inventory_product_id";
        }
        
        if ($si2Join) {
            $sql = str_replace("FROM products p WHERE", "FROM products p {$si2Join} WHERE", $sql);
        }
        
        if ($swappedItemsOnly && ($hasIsSwappedItem || $hasInventoryProductId)) {
            $conditions = [];
            if ($hasIsSwappedItem) {
                $conditions[] = "p.is_swapped_item = 1";
            }
            if ($hasInventoryProductId) {
                $conditions[] = "si2.id IS NOT NULL";
            }
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        } elseif (!$swappedItemsOnly) {
            // For regular inventory view: count ALL products (including out-of-stock and swapped items)
            // Salespersons need to see all products in their inventory view - same as managers
            // Don't exclude anything - show all 278 products
            // No filters applied - count everything
        }
        
        // Apply stock filter if specified
        if ($stockFilter) {
            // Detect quantity column name (qty or quantity)
            $quantityColumn = 'quantity';
            if ($this->productsHasColumn('qty')) {
                $quantityColumn = 'qty';
            } elseif ($this->productsHasColumn('quantity')) {
                $quantityColumn = 'quantity';
            }
            
            if ($stockFilter === 'in_stock') {
                // In stock: quantity > 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) > 10";
            } elseif ($stockFilter === 'low_stock') {
                // Low stock: quantity > 0 and <= 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) > 0 AND COALESCE(p.{$quantityColumn}, 0) <= 10";
            } elseif ($stockFilter === 'out_of_stock') {
                // Out of stock: quantity = 0
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) = 0";
            } elseif ($stockFilter === 'low_and_out') {
                // Low and out of stock: quantity <= 10
                $sql .= " AND COALESCE(p.{$quantityColumn}, 0) <= 10";
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Find a single product by ID and company with category and brand information
     */
    public function find($id, $company_id) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                p.{$quantityColumn} as quantity,
                COALESCE(c.name, 'N/A') as category_name,
                COALESCE(b.name, 'N/A') as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ? AND p.company_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decode JSON specs if present
        if ($product && isset($product['specs']) && $product['specs']) {
            $product['specs'] = json_decode($product['specs'], true);
        }
        
        return $product;
    }

    /**
     * Find products available for swap
     */
    public function findAvailableForSwap($company_id) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                p.{$quantityColumn} as quantity,
                COALESCE(c.name, 'N/A') as category_name,
                COALESCE(b.name, 'N/A') as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.company_id = ? 
            AND p.category_id = (SELECT id FROM categories WHERE name = 'Phone' LIMIT 1)
            AND p.available_for_swap = 1 
            AND p.status = 'available' 
            AND p.{$quantityColumn} > 0
            ORDER BY p.name ASC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find products by category for API endpoints
     */
    public function findByCategory($company_id, $category_id) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.price, p.{$quantityColumn} as quantity, p.available_for_swap, p.status,
                   c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.company_id = ? 
            AND p.category_id = ? 
            AND p.status = 'available'
            ORDER BY p.name ASC
        ");
        $stmt->execute([$company_id, $category_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Get product categories
     */
    public function getCategories() {
        $stmt = $this->db->prepare("
            SELECT id, name, description 
            FROM categories 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get brands by category
     */
    public function getBrandsByCategory($category_id) {
        $stmt = $this->db->prepare("
            SELECT id, name 
            FROM brands 
            WHERE category_id = ? AND is_active = 1 
            ORDER BY name ASC
        ");
        $stmt->execute([$category_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search products by name
     */
    public function search($company_id, $query, $category_id = null) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $sql = "
            SELECT 
                p.id,
                p.company_id,
                p.name,
                COALESCE(p.product_id, CONCAT('PID-', LPAD(p.id, 3, '0'))) as product_id,
                p.category_id,
                p.brand_id,
                p.price,
                p.cost,
                COALESCE(p.{$quantityColumn}, 0) as quantity,
                COALESCE(p.status, 'available') as status,
                p.item_location,
                COALESCE(NULLIF(TRIM(p.model_name), ''), 'N/A') as model_name,
                COALESCE(c.name, 'N/A') as category_name,
                COALESCE(b.name, 'N/A') as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.company_id = ? 
            AND p.name LIKE ?
        ";
        $params = [$company_id, "%$query%"];
        
        if ($category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY p.name ASC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a product
     */
    public function delete($id, $company_id) {
        $stmt = $this->db->prepare("
            DELETE FROM products 
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$id, $company_id]);
    }

    /**
     * Get product statistics for dashboard
     */
    public function getStats($company_id) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        // Use quantity = 0 for out of stock instead of status = 'out_of_stock'
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN COALESCE(p.{$quantityColumn}, 0) > 0 THEN 1 ELSE 0 END) as available_products,
                SUM(CASE WHEN COALESCE(p.{$quantityColumn}, 0) = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN p.available_for_swap = 1 THEN 1 ELSE 0 END) as swap_available,
                SUM(COALESCE(p.{$quantityColumn}, 0)) as total_quantity,
                SUM(p.price * COALESCE(p.{$quantityColumn}, 0)) as total_value
            FROM products p
            WHERE p.company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all products (for inventory management - simplified version)
     */
    public function all() {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, p.category as category_name, p.brand as brand_name, p.{$quantityColumn} as quantity 
            FROM products p
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find product by ID (simplified version for inventory management)
     */
    public function findById($id) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, p.category as category_name, p.brand as brand_name, p.{$quantityColumn} as quantity 
            FROM products p
            WHERE p.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decode JSON specs if present
        if ($product && isset($product['specs']) && $product['specs']) {
            $product['specs'] = json_decode($product['specs'], true);
        }
        
        return $product;
    }

    /**
     * Find product in unified products table (alias for find method)
     * Kept for backward compatibility
     */
    public function findInNew($id, $company_id) {
        return $this->find($id, $company_id);
    }

    /**
     * Create product (simplified version for inventory management)
     */
    public function createSimple($data) {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                name, category_id, brand_id, price, quantity, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['category_id'] ?? 1, // Default category
            $data['brand_id'] ?? null,
            $data['price'] ?? 0,
            $data['quantity'] ?? 0,
            $data['status'] ?? 'available',
            $data['created_by'] ?? 1 // Default user
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update product (simplified version for inventory management)
     */
    public function updateSimple($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE products SET 
                name=?, category_id=?, brand_id=?, price=?, quantity=?, status=?
            WHERE id=?
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['category_id'] ?? 1,
            $data['brand_id'] ?? null,
            $data['price'] ?? 0,
            $data['quantity'] ?? 0,
            $data['status'] ?? 'available',
            $id
        ]);
    }

    /**
     * Delete product (simplified version for inventory management)
     */
    public function deleteSimple($id) {
        $stmt = $this->db->prepare("
            DELETE FROM products 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Find product by SKU
     */
    public function findBySku($sku, $company_id = null) {
        $sql = "SELECT * FROM products WHERE sku = ?";
        $params = [$sku];
        
        if ($company_id) {
            $sql .= " AND company_id = ?";
            $params[] = $company_id;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find products available for swap (alias for findAvailableForSwap)
     */
    public function findSwapProducts($company_id) {
        return $this->findAvailableForSwap($company_id);
    }

    /**
     * Update product quantity (corrected method signature)
     */
    public function updateQuantity($id, $quantity, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET quantity = ?, 
                status = CASE 
                    WHEN ? <= 0 THEN 'out_of_stock' 
                    ELSE 'available' 
                END
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$quantity, $quantity, $id, $company_id]);
    }

    /**
     * Find product by product_id
     */
    public function findByProductId($productId) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Check for duplicate products based on name, category, and brand
     */
    public function findDuplicates($name, $categoryId, $brandId = null, $excludeId = null) {
        $sql = "SELECT * FROM products WHERE name = ? AND category_id = ?";
        $params = [$name, $categoryId];
        
        if ($brandId) {
            $sql .= " AND brand_id = ?";
            $params[] = $brandId;
        }
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check for similar products (fuzzy matching)
     */
    public function findSimilarProducts($name, $categoryId, $brandId = null, $excludeId = null) {
        $sql = "SELECT *, 
                (CASE 
                    WHEN name = ? THEN 100
                    WHEN name LIKE ? THEN 80
                    WHEN name LIKE ? THEN 60
                    ELSE 0
                END) as similarity_score
                FROM products 
                WHERE category_id = ?";
        
        $params = [
            $name, 
            '%' . $name . '%', 
            '%' . substr($name, 0, 5) . '%',
            $categoryId
        ];
        
        if ($brandId) {
            $sql .= " AND brand_id = ?";
            $params[] = $brandId;
        }
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " HAVING similarity_score > 50 ORDER BY similarity_score DESC LIMIT 5";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get products for restock with pagination
     */
    public function getProductsForRestock($whereClause, $params, $limit, $offset) {
        // Detect quantity column name (qty or quantity)
        $quantityColumn = 'quantity';
        if ($this->productsHasColumn('qty')) {
            $quantityColumn = 'qty';
        } elseif ($this->productsHasColumn('quantity')) {
            $quantityColumn = 'quantity';
        }
        
        $sql = "
            SELECT p.*, c.name as category_name, b.name as brand_name
            FROM {$this->table} p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE $whereClause
            ORDER BY p.{$quantityColumn} ASC, p.name ASC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get products count for restock
     */
    public function getProductsCountForRestock($whereClause, $params) {
        $sql = "
            SELECT COUNT(*) as count
            FROM {$this->table} p
            WHERE $whereClause
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'];
    }

    /**
     * Get categories by company
     */
    public function getCategoriesByCompany($companyId) {
        $sql = "
            SELECT DISTINCT c.id, c.name
            FROM categories c
            INNER JOIN {$this->table} p ON c.id = p.category_id
            WHERE p.company_id = ?
            ORDER BY c.name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Add a product from a swapped item to inventory
     * Creates a new product entry for a swapped item that can be resold
     */
    public function addFromSwap(array $swappedItemData) {
        try {
            // Check if columns exist for swapped item tracking
            $hasIsSwappedItem = $this->productsHasColumn('is_swapped_item');
            $hasSwapRefId = $this->productsHasColumn('swap_ref_id');
            $hasBrandId = $this->productsHasColumn('brand_id');
            $hasBrand = $this->productsHasColumn('brand');
            $hasCategoryId = $this->productsHasColumn('category_id');
            $hasCategory = $this->productsHasColumn('category');
            $hasQuantity = $this->productsHasColumn('quantity');
            $hasQty = $this->productsHasColumn('qty');
            $hasCostPrice = $this->productsHasColumn('cost_price');
            $hasCost = $this->productsHasColumn('cost');
            $hasPrice = $this->productsHasColumn('price');
            $hasAvailableForSwap = $this->productsHasColumn('available_for_swap');
            
            // Build product name
            $productName = trim(($swappedItemData['brand'] ?? '') . ' ' . ($swappedItemData['model'] ?? ''));
            if (empty($productName)) {
                $productName = 'Swapped Item #' . ($swappedItemData['id'] ?? 'N/A');
            }
            
            // Determine category - default to phone category (usually 1) if not provided
            $categoryId = $swappedItemData['category_id'] ?? 1;
            
            // Determine brand - use brand_id if available, otherwise brand name
            $brandId = $swappedItemData['brand_id'] ?? null;
            $brand = $swappedItemData['brand'] ?? '';
            
            // Cost price = estimated value (what we paid for it)
            $costPrice = $swappedItemData['estimated_value'] ?? $swappedItemData['cost_price'] ?? 0;
            
            // Build INSERT columns and values
            $insertCols = ['company_id', 'name'];
            $insertVals = [$swappedItemData['company_id'], $productName];
            $placeholders = ['?', '?'];
            
            // Add category (id or name)
            if ($hasCategoryId) {
                $insertCols[] = 'category_id';
                $insertVals[] = $categoryId;
                $placeholders[] = '?';
            } elseif ($hasCategory) {
                $insertCols[] = 'category';
                // Try to get category name
                $categoryName = 'Phones'; // Default
                try {
                    $catStmt = $this->db->prepare("SELECT name FROM categories WHERE id = ?");
                    $catStmt->execute([$categoryId]);
                    $catResult = $catStmt->fetch(PDO::FETCH_ASSOC);
                    if ($catResult) {
                        $categoryName = $catResult['name'];
                    }
                } catch (\Exception $e) {
                    // Use default
                }
                $insertVals[] = $categoryName;
                $placeholders[] = '?';
            }
            
            // Add brand (id or name)
            if ($hasBrandId && $brandId) {
                $insertCols[] = 'brand_id';
                $insertVals[] = $brandId;
                $placeholders[] = '?';
            } elseif ($hasBrand && $brand) {
                $insertCols[] = 'brand';
                $insertVals[] = $brand;
                $placeholders[] = '?';
            }
            
            // Add model if column exists
            if ($this->productsHasColumn('model_name')) {
                $insertCols[] = 'model_name';
                $insertVals[] = $swappedItemData['model'] ?? '';
                $placeholders[] = '?';
            } elseif ($this->productsHasColumn('model')) {
                $insertCols[] = 'model';
                $insertVals[] = $swappedItemData['model'] ?? '';
                $placeholders[] = '?';
            }
            
            // Add cost price
            if ($hasCostPrice) {
                $insertCols[] = 'cost_price';
                $insertVals[] = $costPrice;
                $placeholders[] = '?';
            } elseif ($hasCost) {
                $insertCols[] = 'cost';
                $insertVals[] = $costPrice;
                $placeholders[] = '?';
            }
            
            // Add selling price (use resell_price if provided, otherwise 0)
            $sellingPrice = $swappedItemData['resell_price'] ?? $swappedItemData['estimated_value'] ?? 0;
            if ($hasPrice) {
                $insertCols[] = 'price';
                $insertVals[] = floatval($sellingPrice);
                $placeholders[] = '?';
            }
            
            // Add quantity (1 item)
            $quantityColumn = $hasQuantity ? 'quantity' : ($hasQty ? 'qty' : null);
            if ($quantityColumn) {
                $insertCols[] = $quantityColumn;
                $insertVals[] = 1;
                $placeholders[] = '?';
            }
            
            // Add swap tracking flags
            if ($hasIsSwappedItem) {
                $insertCols[] = 'is_swapped_item';
                $insertVals[] = 1;
                $placeholders[] = '?';
            }
            
            if ($hasSwapRefId && isset($swappedItemData['id'])) {
                $insertCols[] = 'swap_ref_id';
                $insertVals[] = $swappedItemData['id'];
                $placeholders[] = '?';
            }
            
            // Set available_for_swap to 0 (swapped items shouldn't be swapped again by default)
            if ($hasAvailableForSwap) {
                $insertCols[] = 'available_for_swap';
                $insertVals[] = 0;
                $placeholders[] = '?';
            }
            
            // Set status
            if ($this->productsHasColumn('status')) {
                $insertCols[] = 'status';
                $insertVals[] = 'available';
                $placeholders[] = '?';
            }
            
            // Add notes if column exists
            if ($this->productsHasColumn('notes')) {
                $insertCols[] = 'notes';
                $notes = 'Swapped item - Received from customer. Swap ID: ' . ($swappedItemData['swap_id'] ?? 'N/A');
                if (!empty($swappedItemData['condition'])) {
                    $notes .= ', Condition: ' . $swappedItemData['condition'];
                }
                if (!empty($swappedItemData['imei'])) {
                    $notes .= ', IMEI: ' . $swappedItemData['imei'];
                }
                $insertVals[] = $notes;
                $placeholders[] = '?';
            }
            
            // Add created_by if column exists (required foreign key)
            $hasCreatedBy = $this->productsHasColumn('created_by');
            if ($hasCreatedBy) {
                $createdBy = $swappedItemData['created_by'] ?? $swappedItemData['user_id'] ?? null;
                // If still null, try to get from session
                if ($createdBy === null) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $createdBy = $_SESSION['user']['id'] ?? null;
                }
                // Default to 1 if still null (system user)
                if ($createdBy === null) {
                    $createdBy = 1;
                }
                $insertCols[] = 'created_by';
                $insertVals[] = $createdBy;
                $placeholders[] = '?';
            }
            
            // Build and execute INSERT
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($insertVals);
            
            $productId = $this->db->lastInsertId();
            
            // Update swapped_item with inventory_product_id if column exists
            if (isset($swappedItemData['swapped_item_id']) && $this->productsHasColumn('id')) {
                try {
                    $updateStmt = $this->db->prepare("UPDATE swapped_items SET inventory_product_id = ? WHERE id = ?");
                    $updateStmt->execute([$productId, $swappedItemData['swapped_item_id']]);
                } catch (\Exception $e) {
                    error_log("Product addFromSwap: Could not update swapped_items.inventory_product_id - " . $e->getMessage());
                }
            }
            
            return $productId;
        } catch (\Exception $e) {
            error_log("Product addFromSwap error: " . $e->getMessage());
            throw new \Exception('Failed to add swapped item to inventory: ' . $e->getMessage());
        }
    }
}
