<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class SwappedItem {
    private $db;
    private $table = 'swapped_items';
    public $resellPrice = null; // Temporary storage for resell price from modal

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new swapped item
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO swapped_items (
                swap_id, brand, model, imei, condition, estimated_value, 
                resell_price, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['swap_id'],
            $data['brand'],
            $data['model'],
            $data['imei'] ?? null,
            $data['condition'],
            $data['estimated_value'],
            $data['resell_price'],
            $data['status'] ?? 'in_stock',
            $data['notes'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update a swapped item
     */
    public function update($id, array $data) {
        $stmt = $this->db->prepare("
            UPDATE swapped_items SET 
                brand = ?, model = ?, imei = ?, condition = ?, 
                estimated_value = ?, resell_price = ?, status = ?, 
                inventory_product_id = ?, notes = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['brand'],
            $data['model'],
            $data['imei'] ?? null,
            $data['condition'],
            $data['estimated_value'],
            $data['resell_price'],
            $data['status'] ?? 'in_stock',
            $data['inventory_product_id'] ?? null,
            $data['notes'] ?? null,
            $id
        ]);
    }

    /**
     * Helper method to check if swaps table has a column
     */
    private function swapsHasColumn($columnName) {
        static $columnsCache = null;
        if ($columnsCache === null) {
            try {
                $columnCheck = $this->db->query("SHOW COLUMNS FROM swaps");
                $columnsCache = array_flip($columnCheck->fetchAll(PDO::FETCH_COLUMN));
            } catch (\Exception $e) {
                $columnsCache = [];
            }
        }
        return isset($columnsCache[$columnName]);
    }

    /**
     * Find swapped item by ID
     */
    public function find($id) {
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        
        // Check for transaction_code column
        $hasTransactionCode = false;
        $hasUniqueId = false;
        try {
            $checkTransactionCode = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'transaction_code'");
            $hasTransactionCode = $checkTransactionCode->rowCount() > 0;
            $checkUniqueId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'unique_id'");
            $hasUniqueId = $checkUniqueId->rowCount() > 0;
        } catch (\Exception $e) {
            $hasTransactionCode = false;
            $hasUniqueId = false;
        }
        
        // Build transaction code select
        $transactionCodeSelect = '';
        if ($hasTransactionCode) {
            $transactionCodeSelect = "COALESCE(s.transaction_code, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } elseif ($hasUniqueId) {
            $transactionCodeSelect = "COALESCE(s.unique_id, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } else {
            $transactionCodeSelect = "CONCAT('SWAP-', LPAD(s.id, 6, '0')) as transaction_code,";
        }
        
        $sql = "
            SELECT si.*, {$transactionCodeSelect} s.customer_name, s.customer_phone,
                   " . ($hasCompanyProductId ? "sp.name as company_product_name" : "NULL as company_product_name") . "
            FROM swapped_items si
            LEFT JOIN swaps s ON si.swap_id = s.id
            " . ($hasCompanyProductId ? "LEFT JOIN products_new sp ON s.company_product_id = sp.id" : "") . "
            WHERE si.id = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find swapped items by swap ID
     */
    public function findBySwapId($swap_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM swapped_items 
            WHERE swap_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$swap_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find swapped items by company
     */
    public function findByCompany($company_id, $status = null) {
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        
        // Check if swaps table has company_id column
        $hasCompanyId = false;
        try {
            $checkCompanyId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_id'");
            $hasCompanyId = $checkCompanyId->rowCount() > 0;
        } catch (\Exception $e) {
            $hasCompanyId = false;
        }
        
        // Check for transaction_code column
        $hasTransactionCode = false;
        $hasUniqueId = false;
        try {
            $checkTransactionCode = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'transaction_code'");
            $hasTransactionCode = $checkTransactionCode->rowCount() > 0;
            $checkUniqueId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'unique_id'");
            $hasUniqueId = $checkUniqueId->rowCount() > 0;
        } catch (\Exception $e) {
            $hasTransactionCode = false;
            $hasUniqueId = false;
        }
        
        // Build transaction code select
        $transactionCodeSelect = '';
        if ($hasTransactionCode) {
            $transactionCodeSelect = "COALESCE(s.transaction_code, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } elseif ($hasUniqueId) {
            $transactionCodeSelect = "COALESCE(s.unique_id, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } else {
            $transactionCodeSelect = "CONCAT('SWAP-', LPAD(s.id, 6, '0')) as transaction_code,";
        }
        
        // Check if swaps table has customer_name and customer_phone columns
        $hasCustomerName = false;
        $hasCustomerPhone = false;
        try {
            $checkCustomerName = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_name'");
            $hasCustomerName = $checkCustomerName && $checkCustomerName->rowCount() > 0;
            $checkCustomerPhone = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_phone'");
            $hasCustomerPhone = $checkCustomerPhone && $checkCustomerPhone->rowCount() > 0;
        } catch (\Exception $e) {
            $hasCustomerName = false;
            $hasCustomerPhone = false;
        }
        
        // Check if swaps table has customer_id column (needed for JOIN)
        $hasCustomerId = false;
        try {
            $checkCustomerId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_id'");
            $hasCustomerId = $checkCustomerId && $checkCustomerId->rowCount() > 0;
        } catch (\Exception $e) {
            $hasCustomerId = false;
        }
        
        // Build customer name/phone select
        $customerSelect = '';
        if ($hasCustomerName && $hasCustomerPhone) {
            $customerSelect = "COALESCE(s.customer_name, " . ($hasCustomerId ? "c.full_name" : "''") . ", '') as customer_name, COALESCE(s.customer_phone, " . ($hasCustomerId ? "c.phone_number" : "''") . ", '') as customer_phone,";
        } else {
            if ($hasCustomerId) {
                $customerSelect = "COALESCE(c.full_name, '') as customer_name, COALESCE(c.phone_number, '') as customer_phone,";
            } else {
                $customerSelect = "'' as customer_name, '' as customer_phone,";
            }
        }
        
        $sql = "
            SELECT si.*, 
                   s.id as swap_table_id,
                   {$transactionCodeSelect}
                   {$customerSelect}
                   " . ($hasSwapDate ? "s.swap_date," : "s.created_at as swap_date,") . "
                   " . ($hasCompanyProductId ? "sp.name as company_product_name" : "NULL as company_product_name") . "
            FROM swapped_items si
            LEFT JOIN swaps s ON si.swap_id = s.id
            " . ($hasCustomerId ? "LEFT JOIN customers c ON s.customer_id = c.id" : "") . "
            " . ($hasCompanyProductId ? "LEFT JOIN products sp ON s.company_product_id = sp.id" : "") . "
            WHERE " . ($hasCompanyId ? "s.company_id = ?" : "1=1") . "
        ";
        $params = [];
        
        if ($hasCompanyId) {
            $params[] = $company_id;
        }
        
        if ($status) {
            $sql .= " AND si.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY si.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If company_id column doesn't exist, filter by checking swap ownership through other means
        if (!$hasCompanyId && !empty($results)) {
            // Try to verify company ownership through other means or return all
            // For now, return all if company_id doesn't exist
            return $results;
        }
        
        return $results;
    }

    /**
     * Update status to sold
     */
    public function markAsSold($id, $actual_resell_price = null) {
        // Get swap_id before updating
        $getSwapStmt = $this->db->prepare("SELECT swap_id FROM swapped_items WHERE id = ?");
        $getSwapStmt->execute([$id]);
        $item = $getSwapStmt->fetch(PDO::FETCH_ASSOC);
        $swap_id = $item['swap_id'] ?? null;
        
        $stmt = $this->db->prepare("
            UPDATE swapped_items SET 
                status = 'sold', 
                resold_on = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$id]);
        
        // If actual resell price is provided, update it
        if ($actual_resell_price !== null) {
            $updatePriceStmt = $this->db->prepare("
                UPDATE swapped_items SET resell_price = ? WHERE id = ?
            ");
            $updatePriceStmt->execute([$actual_resell_price, $id]);
            
            // Update swap's total_value to include resale value
            // total_value should now be: added_cash (cash top-up) + resale_value
            if ($swap_id) {
                try {
                    $swapModel = new \App\Models\Swap();
                    $swapModel->updateTotalValueOnResale($swap_id, $actual_resell_price);
                } catch (\Exception $e) {
                    error_log("SwappedItem markAsSold: Error updating swap total_value - " . $e->getMessage());
                    // Don't fail the operation if this update fails
                }
            }
        }
        
        return $result;
    }

    /**
     * Get statistics
     */
    public function getStats($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN si.status = 'in_stock' THEN 1 ELSE 0 END) as in_stock_items,
                SUM(CASE WHEN si.status = 'sold' THEN 1 ELSE 0 END) as sold_items,
                SUM(si.estimated_value) as total_estimated_value,
                SUM(si.resell_price) as total_resell_value,
                SUM(CASE WHEN si.status = 'sold' THEN si.resell_price ELSE 0 END) as total_sold_value
            FROM swapped_items si
            LEFT JOIN swaps s ON si.swap_id = s.id
            WHERE s.company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending resales (alias for findReadyForResale for compatibility)
     */
    public function getPendingResales($company_id) {
        return $this->findReadyForResale($company_id);
    }

    /**
     * Sync existing swapped items to products inventory
     * Adds products for swapped items that don't have inventory_product_id set
     * Returns array with success count and errors
     * @param int $company_id Company ID
     * @param array|null $selectedIds Array of swapped item IDs to sync (null = sync all)
     */
    public function syncToInventory($company_id, $selectedIds = null) {
        try {
            // Find all swapped items that need to be synced
            // Get items where inventory_product_id is NULL or the product doesn't exist
            $hasCompanyId = false;
            try {
                $checkCompanyId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'company_id'");
                $hasCompanyId = $checkCompanyId->rowCount() > 0;
            } catch (\Exception $e) {
                $hasCompanyId = false;
            }
            
            $sql = "
                SELECT si.*, s.company_id as swap_company_id
                FROM swapped_items si
                LEFT JOIN swaps s ON si.swap_id = s.id
                WHERE (si.inventory_product_id IS NULL OR si.inventory_product_id = 0)
                AND si.status = 'in_stock'
            ";
            
            $params = [];
            if ($hasCompanyId) {
                $sql .= " AND s.company_id = ?";
                $params[] = $company_id;
            }
            
            // If specific IDs are provided, only sync those
            if ($selectedIds && is_array($selectedIds) && !empty($selectedIds)) {
                $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                $sql .= " AND si.id IN ($placeholders)";
                $params = array_merge($params, $selectedIds);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $swappedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($swappedItems)) {
                return [
                    'success' => true,
                    'synced' => 0,
                    'errors' => [],
                    'message' => 'All swapped items are already synced to inventory'
                ];
            }
            
            $productModel = new \App\Models\Product();
            $synced = 0;
            $errors = [];
            
            foreach ($swappedItems as $item) {
                try {
                    // Use swap_company_id if available, otherwise use provided company_id
                    $itemCompanyId = $item['swap_company_id'] ?? $company_id;
                    
                    // Get brand_id if available
                    $brandId = null;
                    if (!empty($item['brand'])) {
                        try {
                            $brandStmt = $this->db->prepare("SELECT id FROM brands WHERE name = ? LIMIT 1");
                            $brandStmt->execute([$item['brand']]);
                            $brandResult = $brandStmt->fetch(PDO::FETCH_ASSOC);
                            if ($brandResult) {
                                $brandId = $brandResult['id'];
                            }
                        } catch (\Exception $e) {
                            // Brand lookup failed, continue without brand_id
                        }
                    }
                    
                    // Default category to 1 (phones)
                    $categoryId = 1;
                    
                    // Get current user ID from session for created_by
                    $userId = null;
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $userId = $_SESSION['user']['id'] ?? null;
                    
                    // Use resell_price if provided from modal, otherwise use existing resell_price or estimated_value
                    $resellPrice = null;
                    if (isset($this->resellPrice)) {
                        $resellPrice = $this->resellPrice;
                    } elseif (isset($item['resell_price']) && floatval($item['resell_price']) > 0) {
                        $resellPrice = $item['resell_price'];
                    } else {
                        $resellPrice = $item['estimated_value'];
                    }
                    
                    // Add to products inventory
                    $productId = $productModel->addFromSwap([
                        'id' => $item['id'],
                        'swapped_item_id' => $item['id'],
                        'swap_id' => $item['swap_id'],
                        'brand' => $item['brand'],
                        'model' => $item['model'],
                        'brand_id' => $brandId,
                        'category_id' => $categoryId,
                        'estimated_value' => $item['estimated_value'],
                        'resell_price' => $resellPrice,
                        'condition' => $item['condition'] ?? 'used',
                        'imei' => $item['imei'] ?? null,
                        'company_id' => $itemCompanyId,
                        'created_by' => $userId
                    ]);
                    
                    // Update swapped_items resell_price if it was set from modal
                    if (isset($this->resellPrice) && $productId) {
                        $updatePriceStmt = $this->db->prepare("UPDATE swapped_items SET resell_price = ? WHERE id = ?");
                        $updatePriceStmt->execute([$this->resellPrice, $item['id']]);
                    }
                    
                    if ($productId) {
                        $synced++;
                        error_log("SwappedItem sync: Successfully synced swapped item #{$item['id']} to product #{$productId}");
                    } else {
                        $errors[] = "Failed to create product for swapped item #{$item['id']} (Brand: {$item['brand']}, Model: {$item['model']})";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error syncing swapped item #{$item['id']}: " . $e->getMessage();
                    error_log("SwappedItem sync error for item #{$item['id']}: " . $e->getMessage());
                }
            }
            
            return [
                'success' => true,
                'synced' => $synced,
                'total' => count($swappedItems),
                'errors' => $errors,
                'message' => "Successfully synced {$synced} of " . count($swappedItems) . " swapped items to inventory"
            ];
            
        } catch (\Exception $e) {
            error_log("SwappedItem sync error: " . $e->getMessage());
            return [
                'success' => false,
                'synced' => 0,
                'errors' => [$e->getMessage()],
                'message' => 'Error syncing swapped items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Find items ready for resale
     */
    public function findReadyForResale($company_id) {
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        
        // Check if transaction_code column exists
        $hasTransactionCode = false;
        $hasUniqueId = false;
        try {
            $checkTxCode = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'transaction_code'");
            $hasTransactionCode = $checkTxCode && $checkTxCode->rowCount() > 0;
            if (!$hasTransactionCode) {
                $checkUniqueId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'unique_id'");
                $hasUniqueId = $checkUniqueId && $checkUniqueId->rowCount() > 0;
            }
        } catch (\Exception $e) {
            $hasTransactionCode = false;
            $hasUniqueId = false;
        }
        
        // Build transaction code select
        $transactionCodeSelect = '';
        if ($hasTransactionCode) {
            $transactionCodeSelect = "COALESCE(s.transaction_code, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } elseif ($hasUniqueId) {
            $transactionCodeSelect = "COALESCE(s.unique_id, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } else {
            $transactionCodeSelect = "CONCAT('SWAP-', LPAD(s.id, 6, '0')) as transaction_code,";
        }
        
        // Check if swaps table has customer_name and customer_phone columns
        $hasCustomerName = false;
        $hasCustomerPhone = false;
        try {
            $checkCustomerName = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_name'");
            $hasCustomerName = $checkCustomerName && $checkCustomerName->rowCount() > 0;
            $checkCustomerPhone = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_phone'");
            $hasCustomerPhone = $checkCustomerPhone && $checkCustomerPhone->rowCount() > 0;
        } catch (\Exception $e) {
            $hasCustomerName = false;
            $hasCustomerPhone = false;
        }
        
        // Build customer name/phone select
        $customerSelect = '';
        if ($hasCustomerName && $hasCustomerPhone) {
            $customerSelect = "s.customer_name, s.customer_phone,";
        } else {
            // Try to join with customers table if customer_id exists
            $hasCustomerId = false;
            try {
                $checkCustomerId = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'customer_id'");
                $hasCustomerId = $checkCustomerId && $checkCustomerId->rowCount() > 0;
            } catch (\Exception $e) {
                $hasCustomerId = false;
            }
            
            if ($hasCustomerId) {
                $customerSelect = "COALESCE(c.full_name, '') as customer_name, COALESCE(c.phone_number, '') as customer_phone,";
            } else {
                $customerSelect = "'' as customer_name, '' as customer_phone,";
            }
        }
        
        // Check if total_value column exists in swaps table
        $hasTotalValue = false;
        try {
            $checkTotalValue = $this->db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
            $hasTotalValue = $checkTotalValue && $checkTotalValue->rowCount() > 0;
        } catch (\Exception $e) {
            $hasTotalValue = false;
        }
        
        // Build total_value select
        $totalValueSelect = '';
        if ($hasTotalValue) {
            $totalValueSelect = "s.total_value,";
        } else {
            // Try to get from company_product price if available
            if ($hasCompanyProductId) {
                $totalValueSelect = "COALESCE(sp.price, 0) as total_value,";
            } else {
                $totalValueSelect = "0 as total_value,";
            }
        }
        
        $sql = "
            SELECT si.*, 
                   {$transactionCodeSelect}
                   {$customerSelect}
                   {$totalValueSelect}
                   " . ($hasSwapDate ? "s.swap_date," : "s.created_at as swap_date,") . "
                   " . ($hasCompanyProductId ? "sp.name as company_product_name" : "NULL as company_product_name") . "
            FROM swapped_items si
            LEFT JOIN swaps s ON si.swap_id = s.id
            " . ($hasCompanyProductId ? "LEFT JOIN products sp ON s.company_product_id = sp.id" : "") . "
            " . (strpos($customerSelect, 'c.full_name') !== false ? "LEFT JOIN customers c ON s.customer_id = c.id" : "") . "
            WHERE s.company_id = ? AND si.status = 'in_stock'
            ORDER BY si.created_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete a swapped item
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM swapped_items WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}
