<?php
// Swap Receipt View
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Swap Receipt</h1>
        <div class="flex space-x-4">
            <button onclick="window.print()" class="btn btn-outline">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Print Receipt
            </button>
            <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="btn btn-primary">Back to Swaps</a>
        </div>
    </div>

    <!-- Receipt Content -->
    <div class="bg-white p-8 rounded-lg shadow-sm border" id="receipt-content">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">SWAP RECEIPT</h1>
            <p class="text-gray-600">Transaction Code: <span class="font-mono font-bold"><?= htmlspecialchars($swap['transaction_code'] ?? 'SWAP-' . ($swap['id'] ?? 'N/A')) ?></span></p>
            <?php
            $swapDate = $swap['swap_date'] ?? $swap['created_at'] ?? null;
            if ($swapDate) {
                echo '<p class="text-gray-600">Date: ' . date('F j, Y \a\t g:i A', strtotime($swapDate)) . '</p>';
            } else {
                echo '<p class="text-gray-600">Date: N/A</p>';
            }
            ?>
        </div>

        <!-- Customer Information -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Customer Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-600">Customer Name</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($swap['customer_name'] ?? $swap['customer_name_from_table'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Contact</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($swap['customer_phone'] ?? $swap['customer_phone_from_table'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($swap['customer_id'])): ?>
                <div>
                    <p class="text-sm text-gray-600">Customer ID</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($swap['customer_id']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($swap['handled_by_name'])): ?>
                <div>
                    <p class="text-sm text-gray-600">Handled By</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($swap['handled_by_name']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Swap Details -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Swap Details</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Company Product (Given to Customer) -->
                <div class="border rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">Product Given to Customer</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">Product Name</p>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($swap['company_product_name'] ?? 'N/A') ?></p>
                        </div>
                        <?php if (!empty($swap['company_product_price'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Selling Price</p>
                            <p class="font-bold text-green-600 text-lg">₵<?= number_format($swap['company_product_price'], 2) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($swap['company_product_cost'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Cost Price (Estimated)</p>
                            <p class="font-medium text-gray-800">₵<?= number_format($swap['company_product_cost'], 2) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer Product (Received from Customer) -->
                <div class="border rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">Product Received from Customer</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">Brand & Model</p>
                            <p class="font-medium text-gray-800">
                                <?= htmlspecialchars(trim(($swap['customer_product_brand'] ?? '') . ' ' . ($swap['customer_product_model'] ?? ''))) ?>
                            </p>
                        </div>
                        <?php if (!empty($swap['customer_condition'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Condition</p>
                            <p class="font-medium text-gray-800 capitalize"><?= htmlspecialchars($swap['customer_condition']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($swap['customer_product_value'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Estimated Value</p>
                            <p class="font-medium text-gray-800">₵<?= number_format($swap['customer_product_value'], 2) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($swap['resell_price'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Resell Price</p>
                            <p class="font-bold text-blue-600 text-lg">₵<?= number_format($swap['resell_price'], 2) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($swap['resale_status'])): ?>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $swap['resale_status'] === 'sold' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                <?= ucfirst(str_replace('_', ' ', $swap['resale_status'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Financial Summary</h2>
            
            <div class="bg-gray-50 rounded-lg p-6">
                <div class="space-y-4">
                    <?php if (!empty($swap['company_product_price'])): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Company Product Price:</span>
                        <span class="font-medium">₵<?= number_format($swap['company_product_price'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($swap['customer_product_value'])): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Customer Phone Value:</span>
                        <span class="font-medium">₵<?= number_format($swap['customer_product_value'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($swap['added_cash']) && $swap['added_cash'] > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Cash Top-up:</span>
                        <span class="font-medium text-green-600">+₵<?= number_format($swap['added_cash'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($swap['difference_paid_by_company']) && $swap['difference_paid_by_company'] > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Company Paid Difference:</span>
                        <span class="font-medium text-red-600">-₵<?= number_format($swap['difference_paid_by_company'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total Transaction Value:</span>
                        <span class="text-blue-600">₵<?= number_format($swap['total_value'] ?? 0, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit Information -->
        <?php if (!empty($swap['profit_estimate']) || !empty($swap['final_profit'])): ?>
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Profit Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (!empty($swap['profit_estimate'])): ?>
                <div class="bg-blue-50 rounded-lg p-6">
                    <h3 class="font-semibold text-blue-800 mb-3">Estimated Profit</h3>
                    <p class="text-2xl font-bold text-blue-600">₵<?= number_format($swap['profit_estimate'], 2) ?></p>
                    <p class="text-sm text-blue-600 mt-2">Based on estimated resell price</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($swap['final_profit'])): ?>
                <div class="bg-green-50 rounded-lg p-6">
                    <h3 class="font-semibold text-green-800 mb-3">Final Profit</h3>
                    <p class="text-2xl font-bold text-green-600">₵<?= number_format($swap['final_profit'], 2) ?></p>
                    <p class="text-sm text-green-600 mt-2">After customer product was resold</p>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 rounded-lg p-6">
                    <h3 class="font-semibold text-yellow-800 mb-3">Status</h3>
                    <p class="text-lg font-bold text-yellow-600">Pending Resale</p>
                    <p class="text-sm text-yellow-600 mt-2">Customer product not yet sold</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($swap['notes'])): ?>
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Notes</h2>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-gray-700"><?= nl2br(htmlspecialchars($swap['notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-sm text-gray-500 border-t pt-6">
            <p>Thank you for your business!</p>
            <p class="mt-2">This receipt was generated on <?= date('F j, Y \a\t g:i A') ?></p>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #receipt-content, #receipt-content * {
        visibility: visible;
    }
    #receipt-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .btn {
        display: none !important;
    }
}
</style>
