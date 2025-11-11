<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * Company SMS Pricing Model
 * Manages dynamic pricing per company for SMS bundles
 */
class CompanySmsPricing {
    private $conn;
    private $table = 'company_sms_pricing';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Get pricing for a company and vendor plan
     * 
     * @param int $companyId Company ID
     * @param int $vendorPlanId Vendor plan ID
     * @return array|false Pricing data or false
     */
    public function getPricing($companyId, $vendorPlanId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM {$this->table} 
                WHERE company_id = ? AND vendor_plan_id = ? AND active = 1
            ");
            $stmt->execute([$companyId, $vendorPlanId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: false;
        } catch (\Exception $e) {
            error_log("CompanySmsPricing::getPricing error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all pricing for a company
     * 
     * @param int $companyId Company ID
     * @return array List of pricing records
     */
    public function getCompanyPricing($companyId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT csp.*, svp.label, svp.cost_amount as vendor_cost, svp.messages, svp.vendor_name
                FROM {$this->table} csp
                JOIN sms_vendor_plans svp ON csp.vendor_plan_id = svp.id
                WHERE csp.company_id = ? AND csp.active = 1
                ORDER BY svp.cost_amount ASC
            ");
            $stmt->execute([$companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("CompanySmsPricing::getCompanyPricing error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set or update pricing for a company and vendor plan
     * 
     * @param int $companyId Company ID
     * @param int $vendorPlanId Vendor plan ID
     * @param float $markupPercent Markup percentage (default: 90)
     * @param float|null $customPrice Custom override price (optional)
     * @return bool Success status
     */
    public function setPricing($companyId, $vendorPlanId, $markupPercent = 90.00, $customPrice = null) {
        try {
            // Check if pricing exists
            $existing = $this->getPricing($companyId, $vendorPlanId);
            
            if ($existing) {
                // Update existing
                $stmt = $this->conn->prepare("
                    UPDATE {$this->table} 
                    SET markup_percent = ?, custom_price = ?, active = 1, updated_at = NOW()
                    WHERE company_id = ? AND vendor_plan_id = ?
                ");
                return $stmt->execute([$markupPercent, $customPrice, $companyId, $vendorPlanId]);
            } else {
                // Create new
                $stmt = $this->conn->prepare("
                    INSERT INTO {$this->table} (company_id, vendor_plan_id, markup_percent, custom_price, active)
                    VALUES (?, ?, ?, ?, 1)
                ");
                return $stmt->execute([$companyId, $vendorPlanId, $markupPercent, $customPrice]);
            }
        } catch (\Exception $e) {
            error_log("CompanySmsPricing::setPricing error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate pricing for a company and vendor plan
     * 
     * @param int $companyId Company ID
     * @param int $vendorPlanId Vendor plan ID
     * @return bool Success status
     */
    public function deactivate($companyId, $vendorPlanId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET active = 0, updated_at = NOW()
                WHERE company_id = ? AND vendor_plan_id = ?
            ");
            return $stmt->execute([$companyId, $vendorPlanId]);
        } catch (\Exception $e) {
            error_log("CompanySmsPricing::deactivate error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get default markup for a company (if not set, returns system default)
     * 
     * @param int $companyId Company ID
     * @return float Default markup percentage
     */
    public function getDefaultMarkup($companyId) {
        // For now, return 90% as default. Can be made configurable per company later
        return 90.00;
    }
}

