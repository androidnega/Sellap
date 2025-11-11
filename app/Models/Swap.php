<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Swap {
    private $db;
    private $table = 'swaps';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new swap
     */
    public function create(array $data) {
        try {
            $this->db->beginTransaction();
        } catch (\Exception $e) {
            error_log("Swap create: Failed to begin transaction - " . $e->getMessage());
            throw new \Exception('Failed to start swap transaction: ' . $e->getMessage());
        }
        
        try {
            // Generate transaction code
            $transaction_code = 'SWP-' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Get company product price and cost
            // Try to get from products_new first (new schema), then products (old schema)
            $companyProduct = null;
            try {
                $stmt = $this->db->prepare("
                    SELECT id, price, cost, cost_price, purchase_price 
                    FROM products_new 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['company_product_id'], $data['company_id']]);
                $companyProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // products_new doesn't exist, try products table
            }
            
            if (!$companyProduct) {
                $stmt = $this->db->prepare("
                    SELECT id, price, cost, cost_price, purchase_price 
                    FROM products 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['company_product_id'], $data['company_id']]);
                $companyProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$companyProduct) {
                throw new \Exception('Company product not found');
            }
            
            // Get actual cost - prioritize cost_price, then cost, then purchase_price, fallback to 70% of price
            $companyProductCost = 0;
            if (!empty($companyProduct['cost_price'])) {
                $companyProductCost = floatval($companyProduct['cost_price']);
            } elseif (!empty($companyProduct['cost'])) {
                $companyProductCost = floatval($companyProduct['cost']);
            } elseif (!empty($companyProduct['purchase_price'])) {
                $companyProductCost = floatval($companyProduct['purchase_price']);
            } else {
                // Fallback to 70% estimate if no cost is stored
                $companyProductCost = floatval($companyProduct['price']) * 0.7;
                error_log("Swap create: No cost found for product ID {$data['company_product_id']}, using 70% estimate");
            }
            
            // Calculate total value (company product price is what customer pays)
            // Customer gives: estimated_value + added_cash
            // Customer gets: company_product price
            // Total value = company product price (what the swap is worth)
            $total_value = $companyProduct['price'];
            
            // Create customer product record
            // Check if resell_price column exists
            $hasResellPrice = false;
            try {
                $checkCol = $this->db->query("SHOW COLUMNS FROM customer_products LIKE 'resell_price'");
                $hasResellPrice = $checkCol->rowCount() > 0;
            } catch (\Exception $e) {
                // Column check failed, assume it doesn't exist
                error_log("Swap create: Could not check resell_price column - " . $e->getMessage());
            }
            
            // Check if customer_products table exists
            try {
                $this->db->query("SELECT 1 FROM customer_products LIMIT 1");
            } catch (\Exception $e) {
                throw new \Exception('customer_products table does not exist. Please run database migrations.');
            }
            
            if ($hasResellPrice) {
                $stmt = $this->db->prepare("
                    INSERT INTO customer_products (
                        company_id, brand, model, `condition`, estimated_value, 
                        resell_price, status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, 'in_stock', ?)
                ");
                $stmt->execute([
                    $data['company_id'],
                    $data['customer_brand'],
                    $data['customer_model'],
                    $data['customer_condition'] ?? 'used',
                    $data['estimated_value'],
                    $data['resell_price'] ?? $data['estimated_value'],
                    'Received in swap'
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO customer_products (
                        company_id, brand, model, `condition`, estimated_value, 
                        status, notes
                    ) VALUES (?, ?, ?, ?, ?, 'in_stock', ?)
                ");
                $stmt->execute([
                    $data['company_id'],
                    $data['customer_brand'],
                    $data['customer_model'],
                    $data['customer_condition'] ?? 'used',
                    $data['estimated_value'],
                    'Received in swap'
                ]);
            }
            
            $customer_product_id = $this->db->lastInsertId();
            
            if (!$customer_product_id) {
                throw new \Exception('Failed to create customer product record');
            }
            
            // First, check what columns actually exist in swaps table
            $swapsColumns = [];
            $columnMap = [];
            try {
                $columnCheck = $this->db->query("SHOW COLUMNS FROM swaps");
                $swapsColumns = $columnCheck->fetchAll(PDO::FETCH_COLUMN);
                $columnMap = array_flip($swapsColumns);
                error_log("Swap create: swaps table columns: " . implode(', ', $swapsColumns));
            } catch (\Exception $e) {
                error_log("Swap create: Could not get swaps table columns - " . $e->getMessage());
                throw new \Exception('Could not determine swaps table structure: ' . $e->getMessage());
            }
            
            // Check if transaction_code column exists
            $hasTransactionCode = isset($columnMap['transaction_code']);
            
            // Create swap record - handle both schemas (with and without transaction_code)
            if ($hasTransactionCode) {
                // Schema with transaction_code - build dynamically based on what columns exist
                $insertCols = ['transaction_code', 'company_id'];
                $insertVals = [$transaction_code, $data['company_id']];
                $placeholders = ['?', '?'];
                
                // Only add customer_name if column exists
                if (isset($columnMap['customer_name'])) {
                    $insertCols[] = 'customer_name';
                    $insertVals[] = $data['customer_name'];
                    $placeholders[] = '?';
                }
                
                // Only add customer_phone if column exists
                if (isset($columnMap['customer_phone'])) {
                    $insertCols[] = 'customer_phone';
                    $insertVals[] = $data['customer_phone'];
                    $placeholders[] = '?';
                }
                
                // customer_id if column exists
                if (isset($columnMap['customer_id'])) {
                    $insertCols[] = 'customer_id';
                    $insertVals[] = $data['customer_id'] ?? null;
                    $placeholders[] = '?';
                }
                
                // company_product_id
                if (isset($columnMap['company_product_id'])) {
                    $insertCols[] = 'company_product_id';
                    $insertVals[] = $data['company_product_id'];
                    $placeholders[] = '?';
                }
                
                // customer_product_id
                if (isset($columnMap['customer_product_id'])) {
                    $insertCols[] = 'customer_product_id';
                    $insertVals[] = $customer_product_id;
                    $placeholders[] = '?';
                }
                
                // added_cash
                if (isset($columnMap['added_cash'])) {
                    $insertCols[] = 'added_cash';
                    $insertVals[] = $data['added_cash'] ?? 0;
                    $placeholders[] = '?';
                }
                
                // total_value
                if (isset($columnMap['total_value'])) {
                    $insertCols[] = 'total_value';
                    $insertVals[] = $total_value;
                    $placeholders[] = '?';
                }
                
                // handled_by
                if (isset($columnMap['handled_by'])) {
                    $insertCols[] = 'handled_by';
                    $insertVals[] = $data['handled_by'];
                    $placeholders[] = '?';
                }
                
                // status
                if (isset($columnMap['status'])) {
                    $insertCols[] = 'status';
                    $insertVals[] = 'completed';
                    $placeholders[] = '?';
                }
                
                // notes
                if (isset($columnMap['notes'])) {
                    $insertCols[] = 'notes';
                    $insertVals[] = $data['notes'] ?? null;
                    $placeholders[] = '?';
                }
                
                $sql = "INSERT INTO swaps (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                error_log("Swap create: Using transaction_code schema. SQL: " . $sql);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($insertVals);
            } else {
                // Schema without transaction_code - use columnMap already populated above
                
                // Check if unique_id column exists
                $hasUniqueId = isset($columnMap['unique_id']);
                
                // Check if this is the old schema.sql structure (has new_phone_id, given_phone_description, etc.)
                if (isset($columnMap['new_phone_id']) && isset($columnMap['given_phone_description']) && $hasUniqueId) {
                    // Old schema.sql structure - requires customer_id (NOT NULL) and unique_id
                    if (!$data['customer_id']) {
                        throw new \Exception('customer_id is required for this swaps table schema');
                    }
                    
                    // new_phone_id must reference phones table, not products
                    // Need to find or create a phone record for the company product
                    // First, try to find an existing phone record for this product
                    $phoneId = null;
                    
                    // Get product details to search for matching phone
                    // Check if brand column exists in products table
                    $hasBrandColumn = false;
                    try {
                        $checkBrand = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand'");
                        $hasBrandColumn = $checkBrand->rowCount() > 0;
                    } catch (\Exception $e) {
                        error_log("Swap create: Could not check brand column - " . $e->getMessage());
                    }
                    
                    // Build query based on available columns
                    if ($hasBrandColumn) {
                    $stmt = $this->db->prepare("SELECT name, brand, price FROM products WHERE id = ? AND company_id = ?");
                    } else {
                        // Try to get brand from brand_id via join if brand_id exists
                        $checkBrandId = false;
                        try {
                            $checkBrandIdQuery = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand_id'");
                            $checkBrandId = $checkBrandIdQuery->rowCount() > 0;
                        } catch (\Exception $e) {
                            // brand_id doesn't exist either
                        }
                        
                        if ($checkBrandId) {
                            $stmt = $this->db->prepare("SELECT p.name, COALESCE(b.name, '') as brand, p.price FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.id = ? AND p.company_id = ?");
                        } else {
                            // No brand column at all, just get name and price
                            $stmt = $this->db->prepare("SELECT name, '' as brand, price FROM products WHERE id = ? AND company_id = ?");
                        }
                    }
                    
                    $stmt->execute([$data['company_product_id'], $data['company_id']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product) {
                        // Try to find existing phone with matching brand/model
                        $brand = trim($product['brand'] ?? '');
                        $model = trim($product['name'] ?? '');
                        
                        // Use model even if brand is empty, brand can be empty
                        if ($model) {
                            // Try to find existing phone with matching brand/model (brand can be empty)
                            $findPhoneQuery = "
                                SELECT id FROM phones 
                                WHERE company_id = ? 
                                AND brand = ? 
                                AND model = ? 
                                AND status = 'AVAILABLE'
                                LIMIT 1
                            ";
                            $stmt = $this->db->prepare($findPhoneQuery);
                            $stmt->execute([$data['company_id'], $brand ?: '', $model]);
                            $phone = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($phone) {
                                $phoneId = $phone['id'];
                                error_log("Swap create: Found existing phone record ID: $phoneId");
                            } else {
                                // Create a new phone record from the product
                                // Brand can be empty - use empty string if not available
                                $phoneUniqueId = 'PHN-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                try {
                                $stmt = $this->db->prepare("
                                    INSERT INTO phones (
                                        company_id, unique_id, brand, model, phone_value, 
                                        selling_price, status
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'AVAILABLE')
                                ");
                                $stmt->execute([
                                    $data['company_id'],
                                    $phoneUniqueId,
                                        $brand ?: '', // Use empty string if brand is not available
                                    $model,
                                    $product['price'],
                                    $product['price']
                                ]);
                                $phoneId = $this->db->lastInsertId();
                                    error_log("Swap create: Created new phone record ID: $phoneId from product ID: " . $data['company_product_id'] . " (brand: " . ($brand ?: 'N/A') . ", model: $model)");
                                } catch (\Exception $phoneException) {
                                    error_log("Swap create: Failed to create phone record - " . $phoneException->getMessage());
                                    // Continue without phone ID - we'll handle this below
                            }
                        }
                        } else {
                            error_log("Swap create: Product has no name/model, cannot create phone record");
                        }
                    } else {
                        error_log("Swap create: Product not found for ID: " . $data['company_product_id']);
                    }
                    
                    if (!$phoneId) {
                        // For old schema, phone_id is required, so we need to throw an error
                        // But provide a more helpful message
                        $errorMsg = 'Failed to find or create phone record for company product. ';
                        if ($product && empty($product['name'])) {
                            $errorMsg .= 'The product is missing a name/model.';
                        } elseif ($product && empty($product['brand'])) {
                            $errorMsg .= 'The product is missing brand information, but this should not prevent swap creation. Please contact support.';
                        } else {
                            $errorMsg .= 'Please ensure the product has at least a name/model.';
                        }
                        throw new \Exception($errorMsg);
                    }
                    
                    $phoneDesc = $data['customer_brand'] . ' ' . $data['customer_model'];
                    if ($data['customer_imei']) {
                        $phoneDesc .= ' (IMEI: ' . $data['customer_imei'] . ')';
                    }
                    
                    error_log("Swap create: Using old schema.sql structure with unique_id, new_phone_id, given_phone_description");
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO swaps (
                            unique_id, company_id, customer_id, new_phone_id,
                            given_phone_description, given_phone_value, final_price,
                            created_by_user_id, swap_status, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?)
                    ");
                    $stmt->execute([
                        $transaction_code,
                        $data['company_id'],
                        $data['customer_id'],
                        $phoneId, // Use phone ID, not product ID
                        $phoneDesc,
                        $data['estimated_value'],
                        $total_value,
                        $data['handled_by'],
                        $data['notes'] ?? null
                    ]);
                } else {
                    // Build INSERT statement based on available columns (fallback for other schemas)
                    $insertCols = [];
                    $insertVals = [];
                    $placeholders = [];
                        
                    // Required fields first - unique_id if it exists
                    if (isset($columnMap['unique_id'])) {
                        $insertCols[] = 'unique_id';
                        $insertVals[] = $transaction_code;
                        $placeholders[] = '?';
                    }
                    
                    if (isset($columnMap['company_id'])) {
                        $insertCols[] = 'company_id';
                        $insertVals[] = $data['company_id'];
                        $placeholders[] = '?';
                    }
                    
                    // customer_id is required in old schema
                    if (isset($columnMap['customer_id'])) {
                        $insertCols[] = 'customer_id';
                        $insertVals[] = $data['customer_id'] ?? null;
                        $placeholders[] = '?';
                    }
                    
                    // Check for customer_name (new schema)
                    if (isset($columnMap['customer_name'])) {
                        $insertCols[] = 'customer_name';
                        $insertVals[] = $data['customer_name'];
                        $placeholders[] = '?';
                    }
                    
                    // Check for customer_phone (new schema)
                    if (isset($columnMap['customer_phone'])) {
                        $insertCols[] = 'customer_phone';
                        $insertVals[] = $data['customer_phone'];
                        $placeholders[] = '?';
                    }
                    
                    // Check for company_product_id or new_phone_id
                    if (isset($columnMap['company_product_id'])) {
                        $insertCols[] = 'company_product_id';
                        $insertVals[] = $data['company_product_id'];
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['new_phone_id'])) {
                        // new_phone_id must reference phones table, not products
                        // Need to find or create a phone record for the company product
                        $phoneId = null;
                        
                        // Get product details to search for matching phone
                        // Check if brand column exists in products table
                        $hasBrandColumn = false;
                        try {
                            $checkBrand = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand'");
                            $hasBrandColumn = $checkBrand->rowCount() > 0;
                        } catch (\Exception $e) {
                            error_log("Swap create: Could not check brand column - " . $e->getMessage());
                        }
                        
                        // Build query based on available columns
                        if ($hasBrandColumn) {
                        $stmt = $this->db->prepare("SELECT name, brand, price FROM products WHERE id = ? AND company_id = ?");
                        } else {
                            // Try to get brand from brand_id via join if brand_id exists
                            $checkBrandId = false;
                            try {
                                $checkBrandIdQuery = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand_id'");
                                $checkBrandId = $checkBrandIdQuery->rowCount() > 0;
                            } catch (\Exception $e) {
                                // brand_id doesn't exist either
                            }
                            
                            if ($checkBrandId) {
                                $stmt = $this->db->prepare("SELECT p.name, COALESCE(b.name, '') as brand, p.price FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.id = ? AND p.company_id = ?");
                            } else {
                                // No brand column at all, just get name and price
                                $stmt = $this->db->prepare("SELECT name, '' as brand, price FROM products WHERE id = ? AND company_id = ?");
                            }
                        }
                        
                        $stmt->execute([$data['company_product_id'], $data['company_id']]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($product) {
                            // Try to find existing phone with matching brand/model
                            $brand = trim($product['brand'] ?? '');
                            $model = trim($product['name'] ?? '');
                            
                            // Use model even if brand is empty
                            if ($model) {
                                // Try to find existing phone with matching brand/model (brand can be empty)
                                $stmt = $this->db->prepare("
                                    SELECT id FROM phones 
                                    WHERE company_id = ? 
                                    AND brand = ? 
                                    AND model = ? 
                                    AND status = 'AVAILABLE'
                                    LIMIT 1
                                ");
                                $stmt->execute([$data['company_id'], $brand ?: '', $model]);
                                $phone = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($phone) {
                                    $phoneId = $phone['id'];
                                } else {
                                    // Create a new phone record from the product
                                    // Brand can be empty - use empty string if not available
                                    $phoneUniqueId = 'PHN-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                    try {
                                    $stmt = $this->db->prepare("
                                        INSERT INTO phones (
                                            company_id, unique_id, brand, model, phone_value, 
                                            selling_price, status
                                        ) VALUES (?, ?, ?, ?, ?, ?, 'AVAILABLE')
                                    ");
                                    $stmt->execute([
                                        $data['company_id'],
                                        $phoneUniqueId,
                                            $brand ?: '', // Use empty string if brand is not available
                                        $model,
                                        $product['price'],
                                        $product['price']
                                    ]);
                                    $phoneId = $this->db->lastInsertId();
                                    } catch (\Exception $phoneException) {
                                        error_log("Swap create: Failed to create phone record - " . $phoneException->getMessage());
                                        // Continue without phone ID
                                    }
                                }
                            }
                        }
                        
                        if ($phoneId) {
                            $insertCols[] = 'new_phone_id';
                            $insertVals[] = $phoneId; // Use phone ID, not product ID
                            $placeholders[] = '?';
                        } else {
                            error_log("Swap create: Warning - Could not find or create phone for product ID: " . $data['company_product_id'] . ". Skipping new_phone_id.");
                        }
                    }
                    
                    // Check for customer_product_id (new schema) or given_phone_description (old schema)
                    if (isset($columnMap['customer_product_id'])) {
                        $insertCols[] = 'customer_product_id';
                        $insertVals[] = $customer_product_id;
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['given_phone_description'])) {
                        $phoneDesc = $data['customer_brand'] . ' ' . $data['customer_model'];
                        if ($data['customer_imei']) {
                            $phoneDesc .= ' (IMEI: ' . $data['customer_imei'] . ')';
                        }
                        $insertCols[] = 'given_phone_description';
                        $insertVals[] = $phoneDesc;
                        $placeholders[] = '?';
                    }
                    
                    // Check for value fields
                    if (isset($columnMap['given_phone_value'])) {
                        $insertCols[] = 'given_phone_value';
                        $insertVals[] = $data['estimated_value'];
                        $placeholders[] = '?';
                    }
                    
                    if (isset($columnMap['added_cash'])) {
                        $insertCols[] = 'added_cash';
                        $insertVals[] = $data['added_cash'] ?? 0;
                        $placeholders[] = '?';
                    }
                    
                    // Check for price/value fields
                    if (isset($columnMap['total_value'])) {
                        $insertCols[] = 'total_value';
                        $insertVals[] = $total_value;
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['final_price'])) {
                        $insertCols[] = 'final_price';
                        $insertVals[] = $total_value;
                        $placeholders[] = '?';
                    }
                    
                    // Check for user reference fields
                    if (isset($columnMap['handled_by'])) {
                        $insertCols[] = 'handled_by';
                        $insertVals[] = $data['handled_by'];
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['created_by_user_id'])) {
                        $insertCols[] = 'created_by_user_id';
                        $insertVals[] = $data['handled_by'];
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['created_by'])) {
                        $insertCols[] = 'created_by';
                        $insertVals[] = $data['handled_by'];
                        $placeholders[] = '?';
                    }
                    
                    // Check for status fields - set to 'pending' initially (will be 'completed' when swap is finalized, 'resold' when item is resold)
                    if (isset($columnMap['status'])) {
                        $insertCols[] = 'status';
                        $insertVals[] = $data['status'] ?? 'pending';
                        $placeholders[] = '?';
                    } elseif (isset($columnMap['swap_status'])) {
                        $insertCols[] = 'swap_status';
                        $insertVals[] = 'PENDING';
                        $placeholders[] = '?';
                    }
                    
                    if (isset($columnMap['notes'])) {
                        $insertCols[] = 'notes';
                        $insertVals[] = $data['notes'] ?? null;
                        $placeholders[] = '?';
                    }
                    
                    if (empty($insertCols)) {
                        throw new \Exception('Could not map any columns to swaps table structure');
                    }
                    
                    $sql = "INSERT INTO swaps (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    error_log("Swap create: Using dynamic SQL: " . $sql);
                    error_log("Swap create: With values count: " . count($insertVals));
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($insertVals);
                }
            }
            
            $swap_id = $this->db->lastInsertId();
            
            if (!$swap_id) {
                throw new \Exception('Failed to create swap record');
            }
            
            // Create swapped item record (if table exists)
            $swapped_item_id = null;
            try {
                $this->db->query("SELECT 1 FROM swapped_items LIMIT 1");
                // Table exists, try to insert
                // Include specs in notes field as JSON if provided
                $notesValue = null;
                if (!empty($data['customer_specs'])) {
                    // If it's already JSON string, use it; otherwise encode it
                    if (is_string($data['customer_specs'])) {
                        $notesValue = $data['customer_specs'];
                    } else {
                        $notesValue = json_encode($data['customer_specs']);
                    }
                } elseif (!empty($data['notes'])) {
                    $notesValue = $data['notes'];
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO swapped_items (
                        swap_id, brand, model, imei, `condition`, estimated_value, resell_price, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $swap_id,
                    $data['customer_brand'],
                    $data['customer_model'],
                    $data['customer_imei'] ?? null,
                    $data['customer_condition'] ?? 'used',
                    $data['estimated_value'],
                    $data['resell_price'] ?? $data['estimated_value'],
                    $notesValue
                ]);
                
                $swapped_item_id = $this->db->lastInsertId();
                
                // Automatically add swapped item to products inventory for resale
                if ($swapped_item_id && !empty($data['auto_add_to_inventory'])) {
                    try {
                        $productModel = new \App\Models\Product();
                        
                        // Get brand_id if available
                        $brandId = null;
                        if (!empty($data['customer_brand_id'])) {
                            $brandId = $data['customer_brand_id'];
                        } elseif (!empty($data['customer_brand'])) {
                            // Try to find brand by name
                            try {
                                $brandStmt = $this->db->prepare("SELECT id FROM brands WHERE name = ? LIMIT 1");
                                $brandStmt->execute([$data['customer_brand']]);
                                $brandResult = $brandStmt->fetch(PDO::FETCH_ASSOC);
                                if ($brandResult) {
                                    $brandId = $brandResult['id'];
                                }
                            } catch (\Exception $e) {
                                // Brand lookup failed, continue without brand_id
                            }
                        }
                        
                        // Get category_id (default to 1 for phones)
                        $categoryId = $data['category_id'] ?? 1;
                        
                        // Get user ID for created_by
                        $userId = $data['handled_by'] ?? $data['user_id'] ?? null;
                        if ($userId === null && session_status() !== PHP_SESSION_NONE && isset($_SESSION['user']['id'])) {
                            $userId = $_SESSION['user']['id'];
                        }
                        
                        $productModel->addFromSwap([
                            'id' => $swapped_item_id,
                            'swapped_item_id' => $swapped_item_id,
                            'swap_id' => $swap_id,
                            'brand' => $data['customer_brand'],
                            'model' => $data['customer_model'],
                            'brand_id' => $brandId,
                            'category_id' => $categoryId,
                            'estimated_value' => $data['estimated_value'],
                            'condition' => $data['customer_condition'] ?? 'used',
                            'imei' => $data['customer_imei'] ?? null,
                            'company_id' => $data['company_id'],
                            'created_by' => $userId
                        ]);
                        
                        error_log("Swap create: Successfully added swapped item to products inventory");
                    } catch (\Exception $inventoryException) {
                        // Log but don't fail the swap transaction
                        error_log("Swap create: Failed to add swapped item to inventory - " . $inventoryException->getMessage());
                    }
                }
            } catch (\Exception $e) {
                // Table doesn't exist, skip this step (not critical)
                error_log("Swap create: swapped_items table not found - " . $e->getMessage());
            }
            
            // Create profit link record (if table exists)
            // Note: sale IDs will be linked later by POSController when sales are created
            try {
                $this->db->query("SELECT 1 FROM swap_profit_links LIMIT 1");
                // Table exists, create profit link using SwapProfitLink model for consistency
                $resellPrice = $data['resell_price'] ?? $data['estimated_value'];
                // Use the actual cost we calculated above (not 70% estimate unless cost wasn't found)
                $profitEstimate = ($companyProduct['price'] + $resellPrice) - ($companyProductCost + $data['estimated_value']);
                
                $swapProfitLinkModel = new \App\Models\SwapProfitLink();
                $swapProfitLinkModel->create([
                    'swap_id' => $swap_id,
                    'company_product_cost' => $companyProductCost,
                    'customer_phone_value' => $data['estimated_value'],
                    'amount_added_by_customer' => $data['added_cash'] ?? 0,
                    'profit_estimate' => $profitEstimate,
                    'status' => 'pending'
                    // company_item_sale_id and customer_item_sale_id will be NULL initially
                    // They will be linked later by POSController when sales are created
                ]);
            } catch (\Exception $e) {
                // Table doesn't exist or model not found, skip this step (not critical)
                error_log("Swap create: Could not create profit link - " . $e->getMessage());
            }
            
            // Update customer product with swap reference (if swap_id column exists)
            try {
                $checkSwapIdCol = $this->db->query("SHOW COLUMNS FROM customer_products LIKE 'swap_id'");
                if ($checkSwapIdCol->rowCount() > 0) {
                    $stmt = $this->db->prepare("UPDATE customer_products SET swap_id = ? WHERE id = ?");
                    $stmt->execute([$swap_id, $customer_product_id]);
                }
            } catch (\Exception $e) {
                // Column doesn't exist, skip this update
                error_log("Swap create: swap_id column not found in customer_products - " . $e->getMessage());
            }
            
            // Update company product quantity
            $stmt = $this->db->prepare("UPDATE products SET quantity = quantity - 1 WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['company_product_id'], $data['company_id']]);
            
            $this->db->commit();
            
            return [
                'swap_id' => $swap_id,
                'transaction_code' => $transaction_code
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update a swap
     */
    public function update($id, array $data, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE swaps SET 
                customer_name = ?, customer_phone = ?, customer_id = ?, 
                added_cash = ?, difference_paid_by_company = ?, total_value = ?, 
                status = ?, notes = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([
            $data['customer_name'],
            $data['customer_phone'],
            $data['customer_id'] ?? null,
            $data['added_cash'] ?? 0,
            $data['difference_paid_by_company'] ?? 0,
            $data['total_value'],
            $data['status'] ?? 'pending',
            $data['notes'] ?? null,
            $id,
            $company_id
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
     * Find swap by ID
     */
    public function find($id, $company_id) {
        // Build query dynamically based on available columns
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasHandledBy = $this->swapsHasColumn('handled_by');
        $hasCreatedByUserId = $this->swapsHasColumn('created_by_user_id');
        $hasCreatedBy = $this->swapsHasColumn('created_by');
        
        // Determine user reference column
        $userRefCol = null;
        if ($hasHandledBy) {
            $userRefCol = 'handled_by';
        } elseif ($hasCreatedByUserId) {
            $userRefCol = 'created_by_user_id';
        } elseif ($hasCreatedBy) {
            $userRefCol = 'created_by';
        }
        
        // Check for transaction_code and swap_date columns
        $hasTransactionCode = $this->swapsHasColumn('transaction_code');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        
        // Check if swaps table has customer_name and customer_phone columns
        $hasCustomerName = $this->swapsHasColumn('customer_name');
        $hasCustomerPhone = $this->swapsHasColumn('customer_phone');
        
        // Check if products table has brand_id for company brand
        $hasBrandId = false;
        if ($hasCompanyProductId) {
            try {
                $checkBrandId = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand_id'");
                $hasBrandId = $checkBrandId->rowCount() > 0;
            } catch (\Exception $e) {
                $hasBrandId = false;
            }
        }
        
        // Build customer name and phone with proper fallback
        $customerNameSelect = $hasCustomerName 
            ? "COALESCE(s.customer_name, c.full_name, '') as customer_name"
            : "COALESCE(c.full_name, '') as customer_name";
        
        $customerPhoneSelect = $hasCustomerPhone
            ? "COALESCE(s.customer_phone, c.phone_number, '') as customer_phone"
            : "COALESCE(c.phone_number, '') as customer_phone";
        
        // Check which products table exists
        $productsTableName = 'products';
        if ($hasCompanyProductId) {
            try {
                $checkTable = $this->db->query("SHOW TABLES LIKE 'products_new'");
                if ($checkTable->rowCount() > 0) {
                    $productsTableName = 'products_new';
                }
            } catch (\Exception $e) {
                // Fallback to products
            }
        }
        
        // Check if swap_profit_links table exists and has sale ID columns
        $hasProfitLinksTable = false;
        $hasSaleIdColumns = false;
        try {
            $this->db->query("SELECT 1 FROM swap_profit_links LIMIT 1");
            $hasProfitLinksTable = true;
            try {
                $checkSaleIdCols = $this->db->query("SHOW COLUMNS FROM swap_profit_links LIKE 'company_item_sale_id'");
                $hasSaleIdColumns = $checkSaleIdCols->rowCount() > 0;
            } catch (\Exception $e) {
                $hasSaleIdColumns = false;
            }
        } catch (\Exception $e) {
            $hasProfitLinksTable = false;
        }
        
        // Build profit columns select
        $profitSelect = '';
        if ($hasProfitLinksTable) {
            if ($hasSaleIdColumns) {
                $profitSelect = "spl.company_product_cost, spl.customer_phone_value,
                       spl.amount_added_by_customer, spl.profit_estimate, spl.final_profit,
                       spl.status as profit_status, spl.company_item_sale_id, spl.customer_item_sale_id,";
            } else {
                $profitSelect = "spl.company_product_cost, spl.customer_phone_value,
                       spl.amount_added_by_customer, spl.profit_estimate, spl.final_profit,
                       spl.status as profit_status, NULL as company_item_sale_id, NULL as customer_item_sale_id,";
            }
        } else {
            $profitSelect = "NULL as company_product_cost, NULL as customer_phone_value,
                   NULL as amount_added_by_customer, NULL as profit_estimate, NULL as final_profit,
                   NULL as profit_status, NULL as company_item_sale_id, NULL as customer_item_sale_id,";
        }
        
        $sql = "
            SELECT s.*, 
                   " . ($hasCompanyProductId ? "sp.name as company_product_name, sp.price as company_product_price," : "NULL as company_product_name, NULL as company_product_price,") . "
                   " . ($hasCompanyProductId ? "sp.specs as company_specs_json," : "NULL as company_specs_json,") . "
                   " . ($hasBrandId ? "COALESCE(b.name, '') as company_brand," : "NULL as company_brand,") . "
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.imei as customer_imei, si.condition as customer_condition,
                   si.estimated_value as customer_product_value, si.resell_price,
                   si.status as resale_status, si.resold_on, si.notes as customer_notes,
                   " . ($userRefCol ? "u.full_name as handled_by_name," : "NULL as handled_by_name,") . "
                   {$customerNameSelect},
                   {$customerPhoneSelect},
                   c.full_name as customer_name_from_table, c.phone_number as customer_phone_from_table,
                   {$profitSelect}
            FROM swaps s
            " . ($hasCompanyProductId ? "LEFT JOIN {$productsTableName} sp ON s.company_product_id = sp.id" : "") . "
            " . ($hasBrandId ? "LEFT JOIN brands b ON sp.brand_id = b.id" : "") . "
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            LEFT JOIN swap_profit_links spl ON s.id = spl.swap_id
            " . ($userRefCol ? "LEFT JOIN users u ON s.{$userRefCol} = u.id" : "") . "
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.company_id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find swaps by company
     */
    public function findByCompany($company_id, $limit = 100, $status = null) {
        // Build query dynamically based on available columns
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        $hasStatus = $this->swapsHasColumn('status');
        $hasHandledBy = $this->swapsHasColumn('handled_by');
        $hasCreatedByUserId = $this->swapsHasColumn('created_by_user_id');
        $hasCreatedBy = $this->swapsHasColumn('created_by');
        
        // Determine user reference column
        $userRefCol = null;
        if ($hasHandledBy) {
            $userRefCol = 'handled_by';
        } elseif ($hasCreatedByUserId) {
            $userRefCol = 'created_by_user_id';
        } elseif ($hasCreatedBy) {
            $userRefCol = 'created_by';
        }
        
        // Check which products table exists and has brand_id column
        $productsTableName = 'products';
        $hasBrandId = false;
        if ($hasCompanyProductId) {
            try {
                // Try products_new first
                $checkTable = $this->db->query("SHOW TABLES LIKE 'products_new'");
                if ($checkTable->rowCount() > 0) {
                    $productsTableName = 'products_new';
                }
            } catch (\Exception $e) {
                // Fallback to products
            }
            
            try {
                $checkBrandId = $this->db->query("SHOW COLUMNS FROM {$productsTableName} LIKE 'brand_id'");
                $hasBrandId = $checkBrandId->rowCount() > 0;
            } catch (\Exception $e) {
                $hasBrandId = false;
            }
        }
        
        // Check for transaction_code and unique_id columns
        $hasTransactionCode = $this->swapsHasColumn('transaction_code');
        $hasUniqueId = $this->swapsHasColumn('unique_id');
        
        $transactionCodeSelect = '';
        if ($hasTransactionCode) {
            $transactionCodeSelect = "COALESCE(s.transaction_code, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } elseif ($hasUniqueId) {
            $transactionCodeSelect = "COALESCE(s.unique_id, CONCAT('SWAP-', LPAD(s.id, 6, '0'))) as transaction_code,";
        } else {
            $transactionCodeSelect = "CONCAT('SWAP-', LPAD(s.id, 6, '0')) as transaction_code,";
        }
        
        // Check if swaps table uses new_phone_id (old schema)
        $hasNewPhoneId = $this->swapsHasColumn('new_phone_id');
        
        // Check if swap_profit_links table exists
        $hasProfitLinksTable = false;
        try {
            $checkProfitTable = $this->db->query("SHOW TABLES LIKE 'swap_profit_links'");
            $hasProfitLinksTable = $checkProfitTable && $checkProfitTable->rowCount() > 0;
        } catch (\Exception $e) {
            $hasProfitLinksTable = false;
        }
        
        // Build product name select - try products first, then phones if new_phone_id exists
        $productNameSelect = '';
        $productPriceExpression = ''; // Expression for price calculation (without alias)
        if ($hasCompanyProductId) {
            $productNameSelect = "COALESCE(sp.name, ph.brand, ph.model, CONCAT('Product ', s.company_product_id)) as company_product_name,";
            $productPriceSelect = "COALESCE(sp.price, ph.selling_price, 0) as company_product_price,";
            $productPriceExpression = "COALESCE(sp.price, ph.selling_price, 0)";
        } elseif ($hasNewPhoneId) {
            $productNameSelect = "COALESCE(CONCAT(ph.brand, ' ', ph.model), ph.model, ph.brand, CONCAT('Phone ', s.new_phone_id)) as company_product_name,";
            $productPriceSelect = "COALESCE(ph.selling_price, 0) as company_product_price,";
            $productPriceExpression = "COALESCE(ph.selling_price, 0)";
        } else {
            $productNameSelect = "CONCAT('Product ID: ', COALESCE(s.company_product_id, s.new_phone_id, s.id)) as company_product_name,";
            $productPriceSelect = "0 as company_product_price,";
            $productPriceExpression = "0";
        }
        
        // Check if total_value column exists
        $hasTotalValue = $this->swapsHasColumn('total_value');
        
        // Build total_value select - use s.total_value if exists, otherwise use company_product_price
        if ($hasTotalValue) {
            $totalValueSelect = "COALESCE(s.total_value, {$productPriceExpression}) as total_value,";
        } else {
            // If total_value column doesn't exist, use company product price as total value
            $totalValueSelect = "{$productPriceExpression} as total_value,";
        }
        
        // Build profit columns select - only if table exists
        $profitSelect = '';
        if ($hasProfitLinksTable) {
            // Check if sale ID columns exist in swap_profit_links
            $hasSaleIdColumns = false;
            try {
                $checkSaleIdCols = $this->db->query("SHOW COLUMNS FROM swap_profit_links LIKE 'company_item_sale_id'");
                $hasSaleIdColumns = $checkSaleIdCols->rowCount() > 0;
            } catch (\Exception $e) {
                $hasSaleIdColumns = false;
            }
            
            if ($hasSaleIdColumns) {
                $profitSelect = "spl.company_product_cost, spl.customer_phone_value,
                       spl.amount_added_by_customer, spl.profit_estimate, spl.final_profit,
                       spl.status as profit_status, spl.company_item_sale_id, spl.customer_item_sale_id";
            } else {
                $profitSelect = "spl.company_product_cost, spl.customer_phone_value,
                       spl.amount_added_by_customer, spl.profit_estimate, spl.final_profit,
                       spl.status as profit_status, NULL as company_item_sale_id, NULL as customer_item_sale_id";
            }
        } else {
            $profitSelect = "NULL as company_product_cost, NULL as customer_phone_value,
                   NULL as amount_added_by_customer, NULL as profit_estimate, NULL as final_profit,
                   NULL as profit_status, NULL as company_item_sale_id, NULL as customer_item_sale_id";
        }
        
        // Check if added_cash column exists
        $hasAddedCash = $this->swapsHasColumn('added_cash');
        $hasCashAdded = $this->swapsHasColumn('cash_added');
        $hasDifferencePaid = $this->swapsHasColumn('difference_paid_by_company');
        
        // Build cash received select
        $cashReceivedSelect = '';
        if ($hasAddedCash) {
            $cashReceivedSelect = "s.added_cash as added_cash,";
        } elseif ($hasCashAdded) {
            $cashReceivedSelect = "s.cash_added as added_cash,";
        } else {
            $cashReceivedSelect = "COALESCE(" . ($hasDifferencePaid ? "-s.difference_paid_by_company" : "0") . ", 0) as added_cash,";
        }
        
        $sql = "
            SELECT s.*,
                   {$transactionCodeSelect}
                   {$productNameSelect}
                   {$productPriceSelect}
                   {$totalValueSelect}
                   {$cashReceivedSelect}
                   " . ($hasCompanyProductId ? "sp.specs as company_specs_json," : "NULL as company_specs_json,") . "
                   " . (($hasCompanyProductId && $hasBrandId) ? "COALESCE(b.name, ph.brand, '') as company_brand," : ($hasNewPhoneId ? "COALESCE(ph.brand, '') as company_brand," : "NULL as company_brand,")) . "
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.imei as customer_imei, si.condition as customer_condition,
                   si.estimated_value as customer_product_value, si.resell_price,
                   si.status as resale_status, si.notes as customer_notes,
                   si.inventory_product_id,
                   " . ($userRefCol ? "u.full_name as handled_by_name," : "NULL as handled_by_name,") . "
                   c.full_name as customer_name_from_table,
                   {$profitSelect}
            FROM swaps s
            " . ($hasCompanyProductId ? "LEFT JOIN {$productsTableName} sp ON s.company_product_id = sp.id" : "") . "
            " . ($hasNewPhoneId ? "LEFT JOIN phones ph ON s.new_phone_id = ph.id" : "") . "
            " . (($hasCompanyProductId && $hasBrandId) ? "LEFT JOIN brands b ON sp.brand_id = b.id" : "") . "
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            " . ($hasProfitLinksTable ? "LEFT JOIN swap_profit_links spl ON s.id = spl.swap_id" : "") . "
            " . ($userRefCol ? "LEFT JOIN users u ON s.{$userRefCol} = u.id" : "") . "
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.company_id = ?
        ";
        $params = [$company_id];
        
        // Filter by status - handle both swap status and resale status
        if ($status) {
            if ($hasStatus) {
                // Status column exists in swaps table
                if ($status === 'resold') {
                    // For resold, check both swap status and swapped_items status
                    $sql .= " AND (s.status = 'resold' OR si.status = 'sold')";
                } elseif ($status === 'completed') {
                    // For completed: swap must have swapped_item (transaction done) AND item is in_stock (not sold yet)
                    // Status can be 'completed' OR if swapped_item exists, we consider it completed
                    $sql .= " AND si.id IS NOT NULL AND si.status = 'in_stock' AND (s.status = 'completed' OR s.status = 'pending')";
                } elseif ($status === 'pending') {
                    // For pending, swap should be pending
                    $sql .= " AND s.status = 'pending'";
                } else {
                    // For any other status, use direct match
                    $sql .= " AND s.status = ?";
                    $params[] = $status;
                }
            } else {
                // If status column doesn't exist in swaps table, try to infer from swapped_items status
                if ($status === 'resold') {
                    $sql .= " AND si.status = 'sold'";
                } elseif ($status === 'completed') {
                    // Completed means item is in stock (ready for resale)
                    $sql .= " AND si.status = 'in_stock'";
                } elseif ($status === 'pending') {
                    // Pending means no swapped item created yet or item doesn't exist
                    $sql .= " AND (si.status IS NULL OR si.id IS NULL)";
                }
            }
        }
        
        $orderBy = $hasSwapDate ? "s.swap_date" : "s.created_at";
        $sql .= " ORDER BY {$orderBy} DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update swap status
     */
    public function updateStatus($id, $company_id, $status, $notes = null) {
        $stmt = $this->db->prepare("
            UPDATE swaps SET 
                status = ?, 
                notes = COALESCE(?, notes)
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([$status, $notes, $id, $company_id]);
    }

    /**
     * Get swap statistics
     */
    public function getStats($company_id) {
        // Check if total_value column exists
        $hasTotalValue = $this->swapsHasColumn('total_value');
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        
        // Build total_value calculation
        if ($hasTotalValue) {
            $totalValueSelect = "SUM(COALESCE(s.total_value, 0)) as total_value,";
        } elseif ($hasCompanyProductId) {
            // If total_value doesn't exist, use company product price
            $totalValueSelect = "SUM(COALESCE(sp.price, 0)) as total_value,";
        } else {
            $totalValueSelect = "0 as total_value,";
        }
        
        // Check if added_cash column exists
        $hasAddedCash = $this->swapsHasColumn('added_cash');
        $hasCashAdded = $this->swapsHasColumn('cash_added');
        
        // Build cash received calculation
        if ($hasAddedCash) {
            $cashReceivedSelect = "SUM(COALESCE(s.added_cash, 0)) as total_cash_received,";
        } elseif ($hasCashAdded) {
            $cashReceivedSelect = "SUM(COALESCE(s.cash_added, 0)) as total_cash_received,";
        } else {
            $cashReceivedSelect = "0 as total_cash_received,";
        }
        
        $joinClause = $hasCompanyProductId ? "LEFT JOIN products sp ON s.company_product_id = sp.id" : "";
        
        $sql = "
            SELECT 
                COUNT(*) as total_swaps,
                SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_swaps,
                SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_swaps,
                SUM(CASE WHEN s.status = 'resold' THEN 1 ELSE 0 END) as resold_swaps,
                {$totalValueSelect}
                {$cashReceivedSelect}
                SUM(CASE WHEN s.status = 'resold' THEN 
                    (SELECT final_profit FROM swap_profit_links WHERE swap_id = s.id) 
                    ELSE 0 END) as total_profit
            FROM swaps s
            {$joinClause}
            WHERE s.company_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$company_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Find pending swaps (for dashboard)
     */
    public function findPending($company_id) {
        // Build query dynamically based on available columns
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        $hasStatus = $this->swapsHasColumn('status');
        $hasHandledBy = $this->swapsHasColumn('handled_by');
        $hasCreatedByUserId = $this->swapsHasColumn('created_by_user_id');
        $hasCreatedBy = $this->swapsHasColumn('created_by');
        
        // Determine user reference column
        $userRefCol = null;
        if ($hasHandledBy) {
            $userRefCol = 'handled_by';
        } elseif ($hasCreatedByUserId) {
            $userRefCol = 'created_by_user_id';
        } elseif ($hasCreatedBy) {
            $userRefCol = 'created_by';
        }
        
        $sql = "
            SELECT s.*, 
                   " . ($hasCompanyProductId ? "sp.name as company_product_name, sp.price as company_product_price," : "NULL as company_product_name, NULL as company_product_price,") . "
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.estimated_value as customer_product_value, si.resell_price,
                   " . ($userRefCol ? "u.full_name as handled_by_name" : "NULL as handled_by_name") . "
            FROM swaps s
            " . ($hasCompanyProductId ? "LEFT JOIN products sp ON s.company_product_id = sp.id" : "") . "
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            " . ($userRefCol ? "LEFT JOIN users u ON s.{$userRefCol} = u.id" : "") . "
            WHERE s.company_id = ?
        ";
        
        $params = [$company_id];
        if ($hasStatus) {
            $sql .= " AND s.status = 'pending'";
        }
        
        $orderBy = $hasSwapDate ? "s.swap_date" : "s.created_at";
        $sql .= " ORDER BY {$orderBy} DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find completed swaps for resale
     */
    public function findCompletedForResale($company_id) {
        // Build query dynamically based on available columns
        $hasCompanyProductId = $this->swapsHasColumn('company_product_id');
        $hasSwapDate = $this->swapsHasColumn('swap_date');
        $hasStatus = $this->swapsHasColumn('status');
        
        $sql = "
            SELECT s.*, 
                   " . ($hasCompanyProductId ? "sp.name as company_product_name," : "NULL as company_product_name,") . "
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.estimated_value as customer_product_value, si.resell_price,
                   si.status as resale_status
            FROM swaps s
            " . ($hasCompanyProductId ? "LEFT JOIN products sp ON s.company_product_id = sp.id" : "") . "
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            WHERE s.company_id = ?
        ";
        
        $params = [$company_id];
        if ($hasStatus) {
            $sql .= " AND s.status = 'completed'";
        }
        $sql .= " AND si.status = 'in_stock'";
        
        $orderBy = $hasSwapDate ? "s.swap_date" : "s.created_at";
        $sql .= " ORDER BY {$orderBy} DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a swap
     */
    public function delete($id, $company_id) {
        $stmt = $this->db->prepare("
            DELETE FROM swaps 
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$id, $company_id]);
    }

    /**
     * Get active swaps for dashboard
     */
    public function getActiveSwaps($company_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM active_swaps 
            WHERE company_id = ?
            ORDER BY swap_date DESC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get swap profit tracking data
     */
    public function getProfitTracking($company_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM swap_profit_tracking 
            WHERE company_id = ?
            ORDER BY swap_date DESC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get products available for swap
     */
    public function getAvailableProductsForSwap($company_id) {
        // Check which quantity column exists (qty or quantity)
        $hasQty = false;
        $hasQuantity = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM products LIKE 'qty'");
            $hasQty = $columnCheck->rowCount() > 0;
            $columnCheck = $this->db->query("SHOW COLUMNS FROM products LIKE 'quantity'");
            $hasQuantity = $columnCheck->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("Swap getAvailableProductsForSwap: Could not check quantity column - " . $e->getMessage());
        }
        
        // Build query with correct quantity column name
        $quantityColumn = $hasQuantity ? 'quantity' : ($hasQty ? 'qty' : '0');
        
        // Build SELECT fields - include brand name if possible
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM products LIKE 'brand_id'");
            if ($columnCheck->rowCount() > 0) {
                // If brand_id exists, join with brands table to get brand name
                $sql = "
                    SELECT 
                        p.*,
                        COALESCE(b.name, '') as brand_name,
                        COALESCE(c.name, '') as category_name
                    FROM products p
                    LEFT JOIN brands b ON p.brand_id = b.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.company_id = ? 
                    AND (p." . $quantityColumn . " > 0 OR p." . $quantityColumn . " IS NULL)
                    AND (p.available_for_swap = 1 OR p.available_for_swap = TRUE)
                    AND (p.status != 'sold' AND p.status != 'SOLD')
                    ORDER BY p.name
                ";
            } else {
                // No brand_id, use brand column directly
                $sql = "
                    SELECT 
                        p.*,
                        COALESCE(p.brand, '') as brand_name,
                        COALESCE(p.category, '') as category_name
                    FROM products p
                    WHERE p.company_id = ? 
                    AND (p." . $quantityColumn . " > 0 OR p." . $quantityColumn . " IS NULL)
                    AND (p.available_for_swap = 1 OR p.available_for_swap = TRUE)
                    AND (p.status != 'sold' AND p.status != 'SOLD')
                    ORDER BY p.name
                ";
            }
        } catch (\Exception $e) {
            // Fallback to simple query
            $sql = "
            SELECT * FROM products 
                WHERE company_id = ? 
                AND (" . $quantityColumn . " > 0 OR " . $quantityColumn . " IS NULL)
                AND (available_for_swap = 1 OR available_for_swap = TRUE)
            ORDER BY name
            ";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}