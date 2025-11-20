<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class SwapProfitLink {
    private $db;
    private $table = 'swap_profit_links';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new profit link
     */
    public function create(array $data) {
        // Check if sale ID columns exist
        $hasSaleIdColumns = $this->checkSaleIdColumnsExist();
        
        if ($hasSaleIdColumns) {
            $stmt = $this->db->prepare("
                INSERT INTO swap_profit_links (
                    swap_id, company_item_sale_id, customer_item_sale_id,
                    company_product_cost, customer_phone_value, 
                    amount_added_by_customer, profit_estimate, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['swap_id'],
                $data['company_item_sale_id'] ?? null,
                $data['customer_item_sale_id'] ?? null,
                $data['company_product_cost'],
                $data['customer_phone_value'],
                $data['amount_added_by_customer'] ?? 0,
                $data['profit_estimate'],
                $data['status'] ?? 'pending'
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO swap_profit_links (
                    swap_id, company_product_cost, customer_phone_value, 
                    amount_added_by_customer, profit_estimate, status
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['swap_id'],
                $data['company_product_cost'],
                $data['customer_phone_value'],
                $data['amount_added_by_customer'] ?? 0,
                $data['profit_estimate'],
                $data['status'] ?? 'pending'
            ]);
        }
        
        return $this->db->lastInsertId();
    }

    /**
     * Check if sale ID columns exist in swap_profit_links table
     */
    private function checkSaleIdColumnsExist() {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM swap_profit_links LIKE 'company_item_sale_id'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update a profit link
     */
    public function update($id, array $data) {
        $stmt = $this->db->prepare("
            UPDATE swap_profit_links SET 
                company_product_cost = ?, customer_phone_value = ?, 
                amount_added_by_customer = ?, profit_estimate = ?, 
                final_profit = ?, status = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['company_product_cost'],
            $data['customer_phone_value'],
            $data['amount_added_by_customer'] ?? 0,
            $data['profit_estimate'],
            $data['final_profit'] ?? null,
            $data['status'] ?? 'pending',
            $id
        ]);
    }

    /**
     * Find profit link by ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT spl.*, s.transaction_code, s.customer_name, s.customer_phone,
                   s.swap_date, sp.name as company_product_name,
                   si.brand as customer_product_brand, si.model as customer_product_model
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            LEFT JOIN products_new sp ON s.company_product_id = sp.id
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            WHERE spl.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find profit link by swap ID
     */
    public function findBySwapId($swap_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM swap_profit_links 
            WHERE swap_id = ?
            LIMIT 1
        ");
        $stmt->execute([$swap_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find profit links by company
     */
    public function findByCompany($company_id, $status = null) {
        $sql = "
            SELECT spl.*, s.transaction_code, s.customer_name, s.customer_phone,
                   s.swap_date, sp.name as company_product_name,
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.resell_price as customer_product_resell_price
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            LEFT JOIN products_new sp ON s.company_product_id = sp.id
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            WHERE s.company_id = ?
        ";
        $params = [$company_id];
        
        if ($status) {
            $sql .= " AND spl.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY s.swap_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Finalize profit
     */
    public function finalizeProfit($swap_id, $actual_resell_price) {
        $this->db->beginTransaction();
        
        try {
            // Get profit link data
            $stmt = $this->db->prepare("
                SELECT company_product_cost, customer_phone_value, amount_added_by_customer
                FROM swap_profit_links
                WHERE swap_id = ?
            ");
            $stmt->execute([$swap_id]);
            $profitData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profitData) {
                throw new \Exception('Profit link not found');
            }
            
            // Calculate final profit
            $final_profit = $actual_resell_price - $profitData['customer_phone_value'];
            
            // Update profit link
            $stmt = $this->db->prepare("
                UPDATE swap_profit_links 
                SET final_profit = ?, 
                    status = 'finalized', 
                    finalized_at = NOW()
                WHERE swap_id = ?
            ");
            $stmt->execute([$final_profit, $swap_id]);
            
            // Update swapped item status
            $stmt = $this->db->prepare("
                UPDATE swapped_items 
                SET status = 'sold', resold_on = NOW()
                WHERE swap_id = ?
            ");
            $stmt->execute([$swap_id]);
            
            // Update swap status
            $stmt = $this->db->prepare("
                UPDATE swaps 
                SET status = 'resold'
                WHERE id = ?
            ");
            $stmt->execute([$swap_id]);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update final profit manually
     */
    public function updateFinalProfit($id, $final_profit) {
        $stmt = $this->db->prepare("
            UPDATE swap_profit_links SET 
                final_profit = ?, 
                status = 'finalized', 
                finalized_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$final_profit, $id]);
    }

    /**
     * Get profit statistics
     * Only count finalized profits where customer item has been resold (realized gains)
     */
    public function getStats($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_links,
                SUM(CASE WHEN spl.status = 'pending' THEN 1 ELSE 0 END) as pending_links,
                SUM(CASE WHEN spl.status = 'finalized' THEN 1 ELSE 0 END) as finalized_links,
                SUM(spl.profit_estimate) as total_estimated_profit,
                -- Only count final_profit when customer item has been resold AND profit is finalized
                SUM(CASE 
                    WHEN spl.customer_item_sale_id IS NOT NULL 
                    AND spl.status = 'finalized' 
                    AND spl.final_profit IS NOT NULL 
                    THEN spl.final_profit 
                    WHEN spl.customer_item_sale_id IS NOT NULL 
                    AND spl.final_profit IS NOT NULL 
                    THEN spl.final_profit
                    ELSE 0 
                END) as total_final_profit,
                AVG(spl.profit_estimate) as avg_estimated_profit,
                -- Only average finalized profits where customer item has been resold
                AVG(CASE 
                    WHEN spl.customer_item_sale_id IS NOT NULL 
                    AND spl.status = 'finalized' 
                    AND spl.final_profit IS NOT NULL 
                    THEN spl.final_profit 
                    WHEN spl.customer_item_sale_id IS NOT NULL 
                    AND spl.final_profit IS NOT NULL 
                    THEN spl.final_profit
                    ELSE NULL 
                END) as avg_final_profit
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            WHERE s.company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending profit links
     */
    public function findPending($company_id) {
        $stmt = $this->db->prepare("
            SELECT spl.*, s.transaction_code, s.customer_name, s.customer_phone,
                   s.swap_date, sp.name as company_product_name,
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.resell_price as customer_product_resell_price, si.status as resale_status
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            LEFT JOIN products_new sp ON s.company_product_id = sp.id
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            WHERE s.company_id = ? AND spl.status = 'pending'
            ORDER BY s.swap_date DESC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get finalized profit links
     */
    public function findFinalized($company_id) {
        $stmt = $this->db->prepare("
            SELECT spl.*, s.transaction_code, s.customer_name, s.customer_phone,
                   s.swap_date, sp.name as company_product_name,
                   si.brand as customer_product_brand, si.model as customer_product_model,
                   si.resell_price as customer_product_resell_price
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            LEFT JOIN products_new sp ON s.company_product_id = sp.id
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            WHERE s.company_id = ? AND spl.status = 'finalized'
            ORDER BY spl.finalized_at DESC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate profit estimate
     */
    public function calculateProfitEstimate($company_product_cost, $customer_phone_value, $amount_added_by_customer, $estimated_resell_price) {
        // Profit = (Company Product Selling Price + Resold Swap Price) - (Company Cost Price + Customer Phone Value)
        // For estimate: use estimated resell price
        return ($estimated_resell_price + $amount_added_by_customer) - ($company_product_cost + $customer_phone_value);
    }

    /**
     * Delete a profit link
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM swap_profit_links WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Link company item sale to profit link
     * Called when swap is created - company phone sale is immediate
     */
    public function linkCompanyItemSale($swapId, $saleId) {
        $hasSaleIdColumns = $this->checkSaleIdColumnsExist();
        
        if (!$hasSaleIdColumns) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE swap_profit_links 
            SET company_item_sale_id = ?
            WHERE swap_id = ?
        ");
        
        $result = $stmt->execute([$saleId, $swapId]);
        
        // If both sales are now linked, calculate profit automatically
        if ($result) {
            $this->calculateSwapProfit($swapId);
        }
        
        return $result;
    }

    /**
     * Link customer item sale to profit link
     * Called when customer's phone is resold
     */
    public function linkCustomerItemSale($swapId, $saleId) {
        $hasSaleIdColumns = $this->checkSaleIdColumnsExist();
        
        if (!$hasSaleIdColumns) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE swap_profit_links 
            SET customer_item_sale_id = ?
            WHERE swap_id = ?
        ");
        
        $result = $stmt->execute([$saleId, $swapId]);
        
        // If both sales are now linked, calculate profit automatically
        if ($result) {
            $this->calculateSwapProfit($swapId);
        }
        
        return $result;
    }

    /**
     * Calculate swap profit when both items have been sold
     * 
     * 🧮 PROFIT FORMULA (Based on Manager's Inventory Input):
     * 
     * Profit = (Company Item Sale Price + Resold Customer Item Price)
     *        - (Company Item Cost + Customer Item Value)
     * 
     * Where:
     * - Company Item Sale Price = Actual POS sale price (from pos_sales.final_amount)
     *   This is what the customer paid for the company's phone during the swap
     * - Company Item Cost = Cost price from inventory (manager-defined cost_price)
     *   This is what the company originally paid for the phone in inventory
     * - Customer Item Value = Estimated value given to customer during swap
     *   This is the trade-in value assigned to the customer's phone
     * - Resold Customer Item Price = Actual POS sale price when customer's phone was resold
     *   This is what a new customer paid when the company resold the swapped phone
     * 
     * 💡 EQUIVALENT FORMULAS:
     * Profit = [Company Profit] + [Resale Profit]
     * Company Profit = Company Sale Price - Company Cost
     * Resale Profit = Customer Resell Price - Customer Value
     * 
     * 📊 EXAMPLE:
     * Company Phone: Cost ₵2,000, Sold for ₵2,800 → Company Profit = ₵800
     * Customer Phone: Value ₵1,200, Resold for ₵1,700 → Resale Profit = ₵500
     * Total Profit = ₵800 + ₵500 = ₵1,300
     * 
     * ✅ This formula uses real, traceable numbers:
     * - Manager-defined cost prices from inventory
     * - Actual POS sale transactions
     * - Transparent calculation for both sides of the swap
     * 
     * @param int $swapId The swap ID
     * @return float|null The calculated profit, or null if not ready (both items must be sold)
     */
    public function calculateSwapProfit($swapId) {
        $hasSaleIdColumns = $this->checkSaleIdColumnsExist();
        
        if (!$hasSaleIdColumns) {
            error_log("SwapProfitLink: Sale ID columns not available, skipping profit calculation");
            return null;
        }
        
        // Get the profit link with sale IDs
        $link = $this->findBySwapId($swapId);
        
        if (!$link) {
            error_log("SwapProfitLink: Profit link not found for swap ID: {$swapId}");
            return null;
        }
        
        // Check if customer item sale is linked (customer item must be resold for profit to be realized)
        if (empty($link['customer_item_sale_id'])) {
            // Not ready yet - waiting for customer item to be resold
            error_log("SwapProfitLink: Customer item not yet resold for swap ID {$swapId}. Customer sale: " . ($link['customer_item_sale_id'] ?? 'NULL'));
            return null;
        }
        
        try {
            // Get customer sale (required - this is when the swapped item was resold)
            $customerSaleStmt = $this->db->prepare("
                SELECT id, final_amount 
                FROM pos_sales 
                WHERE id = ?
            ");
            $customerSaleStmt->execute([$link['customer_item_sale_id']]);
            $customerSale = $customerSaleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customerSale) {
                error_log("SwapProfitLink: Customer sale not found: {$link['customer_item_sale_id']}");
                return null;
            }
            
            // Get company sale price - use actual sale if linked, otherwise use swap total_value or product price
            $companySalePrice = 0;
            if (!empty($link['company_item_sale_id'])) {
                // Company sale exists - use actual sale price
                $companySaleStmt = $this->db->prepare("
                    SELECT id, final_amount 
                    FROM pos_sales 
                    WHERE id = ?
                ");
                $companySaleStmt->execute([$link['company_item_sale_id']]);
                $companySale = $companySaleStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($companySale) {
                    $companySalePrice = floatval($companySale['final_amount']);
                } else {
                    error_log("SwapProfitLink: Company sale not found: {$link['company_item_sale_id']}, will use fallback");
                }
            }
            
            // If company sale not found or not linked, use swap's final_price, total_value, or product price as fallback
            if ($companySalePrice == 0) {
                // Check what columns exist in swaps table
                $swapColumns = $this->db->query("SHOW COLUMNS FROM swaps")->fetchAll(PDO::FETCH_COLUMN);
                $hasFinalPrice = in_array('final_price', $swapColumns);
                $hasTotalValue = in_array('total_value', $swapColumns);
                $hasCompanyProductId = in_array('company_product_id', $swapColumns);
                $hasNewPhoneId = in_array('new_phone_id', $swapColumns);
                
                // Build SELECT based on available columns
                $selectFields = ['s.id', 's.company_id'];
                if ($hasFinalPrice) {
                    $selectFields[] = 's.final_price';
                }
                if ($hasTotalValue) {
                    $selectFields[] = 's.total_value';
                }
                if ($hasCompanyProductId) {
                    $selectFields[] = 's.company_product_id';
                }
                if ($hasNewPhoneId) {
                    $selectFields[] = 's.new_phone_id';
                }
                
                $swapStmt = $this->db->prepare("
                    SELECT " . implode(', ', $selectFields) . "
                    FROM swaps s
                    WHERE s.id = ?
                ");
                $swapStmt->execute([$swapId]);
                $swap = $swapStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($swap) {
                    // Try final_price first (most accurate - what customer actually paid)
                    if ($hasFinalPrice && !empty($swap['final_price'])) {
                        $companySalePrice = floatval($swap['final_price']);
                    } elseif ($hasTotalValue && !empty($swap['total_value'])) {
                        // Try total_value if final_price not available
                        $companySalePrice = floatval($swap['total_value']);
                    } elseif ($hasCompanyProductId && !empty($swap['company_product_id'])) {
                        // Try to get product price
                        $productStmt = $this->db->prepare("
                            SELECT price 
                            FROM products_new 
                            WHERE id = ? AND company_id = ?
                            UNION ALL
                            SELECT price 
                            FROM products 
                            WHERE id = ? AND company_id = ?
                            LIMIT 1
                        ");
                        $productStmt->execute([$swap['company_product_id'], $swap['company_id'], $swap['company_product_id'], $swap['company_id']]);
                        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                        if ($product) {
                            $companySalePrice = floatval($product['price']);
                        }
                    } elseif ($hasNewPhoneId && !empty($swap['new_phone_id'])) {
                        // Try to get phone price (old schema)
                        $phoneStmt = $this->db->prepare("
                            SELECT selling_price 
                            FROM phones 
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $phoneStmt->execute([$swap['new_phone_id']]);
                        $phone = $phoneStmt->fetch(PDO::FETCH_ASSOC);
                        if ($phone) {
                            $companySalePrice = floatval($phone['selling_price']);
                        }
                    }
                }
                
                if ($companySalePrice == 0) {
                    error_log("SwapProfitLink: Could not determine company sale price for swap ID {$swapId}");
                    return null;
                }
                
                error_log("SwapProfitLink: Using fallback company sale price: ₵{$companySalePrice} (swap final_price/total_value or product price)");
            }
            
            // Get values from stored profit link (set during swap creation)
            $companyCost = floatval($link['company_product_cost']); // Manager-defined cost from inventory
            $customerValue = floatval($link['customer_phone_value']); // Value given to customer during swap
            
            // Get actual sale price from POS transaction (customer item resale)
            $customerResellPrice = floatval($customerSale['final_amount']); // What company got when reselling customer phone
            
            // Calculate profit using the refined formula
            // Profit = (Company Sale Price + Customer Resell Price) - (Company Cost + Customer Value)
            $profit = ($companySalePrice + $customerResellPrice) - ($companyCost + $customerValue);
            
            // Breakdown for logging
            $companyProfit = $companySalePrice - $companyCost;
            $resaleProfit = $customerResellPrice - $customerValue;
            
            // Update profit link with calculated profit
            $updateStmt = $this->db->prepare("
                UPDATE swap_profit_links 
                SET final_profit = ?, 
                    status = 'finalized', 
                    finalized_at = NOW()
                WHERE swap_id = ?
            ");
            $updateStmt->execute([$profit, $swapId]);
            
            error_log("SwapProfitLink: Profit calculated for swap ID {$swapId}:");
            error_log("  Company: Sale ₵{$companySalePrice} - Cost ₵{$companyCost} = Profit ₵{$companyProfit}" . (empty($link['company_item_sale_id']) ? " (using fallback price)" : ""));
            error_log("  Resale: Resell ₵{$customerResellPrice} - Value ₵{$customerValue} = Profit ₵{$resaleProfit}");
            error_log("  TOTAL PROFIT: ₵{$profit} = (₵{$companySalePrice} + ₵{$customerResellPrice}) - (₵{$companyCost} + ₵{$customerValue})");
            
            return $profit;
            
        } catch (\Exception $e) {
            error_log("SwapProfitLink: Error calculating profit for swap ID {$swapId}: " . $e->getMessage());
            error_log("SwapProfitLink: Error trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get profit link by swap ID with sale details
     */
    public function getProfitLinkWithSales($swapId) {
        $hasSaleIdColumns = $this->checkSaleIdColumnsExist();
        
        if (!$hasSaleIdColumns) {
            return $this->findBySwapId($swapId);
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                spl.*,
                s.transaction_code,
                s.customer_name,
                s.customer_phone,
                s.swap_date,
                sp.name as company_product_name,
                si.brand as customer_product_brand,
                si.model as customer_product_model,
                company_sale.final_amount as company_sale_price,
                customer_sale.final_amount as customer_resell_price
            FROM swap_profit_links spl
            LEFT JOIN swaps s ON spl.swap_id = s.id
            LEFT JOIN products_new sp ON s.company_product_id = sp.id
            LEFT JOIN swapped_items si ON s.id = si.swap_id
            LEFT JOIN pos_sales company_sale ON spl.company_item_sale_id = company_sale.id
            LEFT JOIN pos_sales customer_sale ON spl.customer_item_sale_id = customer_sale.id
            WHERE spl.swap_id = ?
            LIMIT 1
        ");
        $stmt->execute([$swapId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
