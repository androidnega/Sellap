<?php

namespace App\Services;

use App\Models\SmsVendorPlan;
use App\Models\CompanySmsPricing;

/**
 * SMS Pricing Service
 * Computes dynamic pricing for SMS bundles
 */
class SmsPricingService {
    
    private $vendorPlanModel;
    private $pricingModel;
    
    public function __construct() {
        $this->vendorPlanModel = new SmsVendorPlan();
        $this->pricingModel = new CompanySmsPricing();
    }

    /**
     * Compute company price for a vendor plan
     * 
     * @param int $companyId Company ID
     * @param int $vendorPlanId Vendor plan ID
     * @return array Pricing details
     */
    public function computeCompanyPrice($companyId, $vendorPlanId) {
        try {
            // Get vendor plan
            $plan = $this->vendorPlanModel->getById($vendorPlanId);
            if (!$plan) {
                throw new \Exception('Vendor plan not found');
            }
            
            // Get company pricing settings
            $pricing = $this->pricingModel->getPricing($companyId, $vendorPlanId);
            
            // Determine company price
            if ($pricing && !is_null($pricing['custom_price'])) {
                // Use custom override price
                $companyPrice = (float)$pricing['custom_price'];
            } else {
                // Compute using markup
                $markupPercent = $pricing ? (float)$pricing['markup_percent'] : $this->pricingModel->getDefaultMarkup($companyId);
                $factor = 1 + ($markupPercent / 100.0);
                $companyPrice = round((float)$plan['cost_amount'] * $factor, 2);
            }
            
            // Calculate derived values
            $messages = (int)$plan['messages'];
            $vendorCost = (float)$plan['cost_amount'];
            $unitPrice = $companyPrice / $messages;
            $profitTotal = $companyPrice - $vendorCost;
            $profitPerSms = $profitTotal / $messages;
            $vendorCostPerSms = $vendorCost / $messages;
            
            return [
                'success' => true,
                'vendor_price' => $vendorCost,
                'vendor_cost_per_sms' => round($vendorCostPerSms, 5),
                'messages' => $messages,
                'company_price' => $companyPrice,
                'unit_price' => round($unitPrice, 5),
                'profit_total' => round($profitTotal, 2),
                'profit_per_sms' => round($profitPerSms, 5),
                'markup_percent' => $pricing ? (float)$pricing['markup_percent'] : $this->pricingModel->getDefaultMarkup($companyId),
                'using_custom_price' => ($pricing && !is_null($pricing['custom_price'])),
                'plan' => [
                    'id' => $plan['id'],
                    'label' => $plan['label'],
                    'vendor_name' => $plan['vendor_name'],
                    'expires_in_days' => $plan['expires_in_days']
                ]
            ];
        } catch (\Exception $e) {
            error_log("SmsPricingService::computeCompanyPrice error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all available bundles with pricing for a company
     * 
     * @param int $companyId Company ID
     * @return array List of bundles with pricing
     */
    public function getAvailableBundles($companyId) {
        try {
            $plans = $this->vendorPlanModel->getAll();
            
            if (empty($plans)) {
                return [
                    'success' => false,
                    'error' => 'No vendor plans found. Please run database migrations to seed vendor plans.',
                    'bundles' => [],
                    'debug_info' => 'The sms_vendor_plans table may not exist or is empty. Run: database/migrations/create_sms_vendor_plans_table.sql and seed_sms_vendor_plans.sql'
                ];
            }
            
            $bundles = [];
            
            foreach ($plans as $plan) {
                $pricing = $this->computeCompanyPrice($companyId, $plan['id']);
                if ($pricing['success']) {
                    $bundles[] = $pricing;
                } else {
                    error_log("Failed to compute price for plan {$plan['id']}: " . ($pricing['error'] ?? 'Unknown error'));
                }
            }
            
            if (empty($bundles)) {
                return [
                    'success' => false,
                    'error' => 'No bundles available. Unable to compute pricing for vendor plans.',
                    'bundles' => []
                ];
            }
            
            return [
                'success' => true,
                'bundles' => $bundles
            ];
        } catch (\Exception $e) {
            error_log("SmsPricingService::getAvailableBundles error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'bundles' => []
            ];
        }
    }
}

