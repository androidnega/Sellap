<?php
// Swap Receipt View
?>

<!-- Action Bar -->
<div class="w-full bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="px-4 py-3">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Swap Receipt</h1>
                <p class="text-xs text-gray-500">Transaction #<?= htmlspecialchars($swap['transaction_code'] ?? 'SWAP-' . ($swap['id'] ?? 'N/A')) ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="downloadReceipt()" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded text-sm font-medium hover:bg-green-700 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download
                </button>
                <button onclick="window.print()" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print
                </button>
                <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Content - Compact -->
<div class="w-full bg-gray-50 py-4" id="receipt-content">
    <div class="w-full px-4 max-w-2xl mx-auto">
        <div class="bg-white rounded border overflow-hidden" id="receipt-document">
            
            <!-- Receipt Header -->
            <div class="bg-blue-600 text-white px-4 py-3 text-center">
                <h1 class="text-xl font-bold mb-1">SWAP RECEIPT</h1>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 text-sm text-blue-100">
                    <span class="font-mono font-semibold text-white"><?= htmlspecialchars($swap['transaction_code'] ?? 'SWAP-' . ($swap['id'] ?? 'N/A')) ?></span>
                    <?php
                    $swapDate = $swap['swap_date'] ?? $swap['created_at'] ?? null;
                    if ($swapDate):
                    ?>
                    <span class="hidden sm:inline">•</span>
                    <span><?= date('M j, Y g:i A', strtotime($swapDate)) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="px-4 py-4 space-y-4 text-sm">
                
                <!-- Customer Information -->
                <div class="border-l-2 border-blue-500 pl-3">
                    <h2 class="text-sm font-bold text-gray-900 mb-2">Customer Information</h2>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="text-gray-500">Name</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($swap['customer_name'] ?? $swap['customer_name_from_table'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500">Contact</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($swap['customer_phone'] ?? $swap['customer_phone_from_table'] ?? 'N/A') ?></p>
                        </div>
                        <?php if (!empty($swap['customer_id'])): ?>
                        <div>
                            <p class="text-gray-500">Customer ID</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($swap['customer_id']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($swap['handled_by_name'])): ?>
                        <div>
                            <p class="text-gray-500">Handled By</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($swap['handled_by_name']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Swap Details -->
                <div class="border-l-2 border-green-500 pl-3">
                    <h2 class="text-sm font-bold text-gray-900 mb-2">Swap Details</h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Company Product -->
                        <div class="bg-green-50 rounded p-3 border border-green-200">
                            <h3 class="text-xs font-bold text-gray-900 mb-2 text-center">Given to Customer</h3>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <p class="text-gray-500">Product</p>
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($swap['company_product_name'] ?? 'N/A') ?></p>
                                </div>
                                <?php if (!empty($swap['company_product_price'])): ?>
                                <div>
                                    <p class="text-gray-500">Price</p>
                                    <p class="font-bold text-green-600 text-base">₵<?= number_format($swap['company_product_price'], 2) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($swap['company_product_cost'])): ?>
                                <div>
                                    <p class="text-gray-500">Cost</p>
                                    <p class="font-semibold text-gray-700">₵<?= number_format($swap['company_product_cost'], 2) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Customer Product -->
                        <div class="bg-blue-50 rounded p-3 border border-blue-200">
                            <h3 class="text-xs font-bold text-gray-900 mb-2 text-center">Received from Customer</h3>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <p class="text-gray-500">Brand & Model</p>
                                    <p class="font-semibold text-gray-900">
                                        <?= htmlspecialchars(trim(($swap['customer_product_brand'] ?? '') . ' ' . ($swap['customer_product_model'] ?? ''))) ?>
                                    </p>
                                </div>
                                <?php if (!empty($swap['customer_condition'])): ?>
                                <div>
                                    <p class="text-gray-500">Condition</p>
                                    <p class="font-semibold text-gray-900 capitalize"><?= htmlspecialchars($swap['customer_condition']) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($swap['customer_product_value'])): ?>
                                <div>
                                    <p class="text-gray-500">Value</p>
                                    <p class="font-semibold text-gray-700">₵<?= number_format($swap['customer_product_value'], 2) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($swap['resell_price'])): ?>
                                <div>
                                    <p class="text-gray-500">Resell Price</p>
                                    <p class="font-bold text-blue-600 text-base">₵<?= number_format($swap['resell_price'], 2) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($swap['resale_status'])): ?>
                                <div>
                                    <p class="text-gray-500">Status</p>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?= $swap['resale_status'] === 'sold' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $swap['resale_status'])) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="border-l-2 border-purple-500 pl-3">
                    <h2 class="text-sm font-bold text-gray-900 mb-2">Financial Summary</h2>
                    
                    <div class="bg-purple-50 rounded p-3 border border-purple-200">
                        <div class="space-y-2 text-xs">
                            <?php if (!empty($swap['company_product_price'])): ?>
                            <div class="flex justify-between py-1 border-b border-purple-200">
                                <span class="text-gray-700">Company Product Price:</span>
                                <span class="font-bold text-gray-900">₵<?= number_format($swap['company_product_price'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($swap['customer_product_value'])): ?>
                            <div class="flex justify-between py-1 border-b border-purple-200">
                                <span class="text-gray-700">Customer Phone Value:</span>
                                <span class="font-bold text-gray-900">₵<?= number_format($swap['customer_product_value'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($swap['added_cash']) && $swap['added_cash'] > 0): ?>
                            <div class="flex justify-between py-1 border-b border-purple-200">
                                <span class="text-gray-700">Cash Top-up:</span>
                                <span class="font-bold text-green-600">+₵<?= number_format($swap['added_cash'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($swap['difference_paid_by_company']) && $swap['difference_paid_by_company'] > 0): ?>
                            <div class="flex justify-between py-1 border-b border-purple-200">
                                <span class="text-gray-700">Company Paid Difference:</span>
                                <span class="font-bold text-red-600">-₵<?= number_format($swap['difference_paid_by_company'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between pt-2 mt-2 border-t-2 border-purple-300">
                                <span class="text-sm font-bold text-gray-900">Total Transaction Value:</span>
                                <span class="text-base font-bold text-purple-600">₵<?= number_format($swap['total_value'] ?? 0, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit Information -->
                <?php if (!empty($swap['profit_estimate']) || !empty($swap['final_profit'])): ?>
                <div class="border-l-2 border-amber-500 pl-3">
                    <h2 class="text-sm font-bold text-gray-900 mb-2">Profit Information</h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php if (!empty($swap['profit_estimate'])): ?>
                        <div class="bg-blue-50 rounded p-3 border border-blue-200">
                            <h3 class="text-xs font-bold text-blue-900 mb-1">Estimated Profit</h3>
                            <p class="text-lg font-bold text-blue-600">₵<?= number_format($swap['profit_estimate'], 2) ?></p>
                            <p class="text-xs text-blue-700 mt-1">Based on estimated resell price</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($swap['final_profit'])): ?>
                        <div class="bg-green-50 rounded p-3 border border-green-200">
                            <h3 class="text-xs font-bold text-green-900 mb-1">Final Profit</h3>
                            <p class="text-lg font-bold text-green-600">₵<?= number_format($swap['final_profit'], 2) ?></p>
                            <p class="text-xs text-green-700 mt-1">After customer product was resold</p>
                        </div>
                        <?php else: ?>
                        <div class="bg-yellow-50 rounded p-3 border border-yellow-200">
                            <h3 class="text-xs font-bold text-yellow-900 mb-1">Status</h3>
                            <p class="text-base font-bold text-yellow-600">Pending Resale</p>
                            <p class="text-xs text-yellow-700 mt-1">Customer product not yet sold</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes -->
                <?php if (!empty($swap['notes'])): ?>
                <div class="border-l-2 border-gray-400 pl-3">
                    <h2 class="text-sm font-bold text-gray-900 mb-2">Notes</h2>
                    <div class="bg-gray-50 rounded p-3 border border-gray-200">
                        <p class="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap"><?= nl2br(htmlspecialchars($swap['notes'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Footer -->
            <div class="bg-gray-50 border-t border-gray-200 px-4 py-3 text-center">
                <p class="text-xs font-semibold text-gray-700 mb-1">Thank you for your business!</p>
                <p class="text-xs text-gray-500">Generated on <?= date('M j, Y g:i A') ?></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadReceipt() {
    const element = document.getElementById('receipt-document');
    const opt = {
        margin: 0.5,
        filename: 'swap-receipt-<?= htmlspecialchars($swap['transaction_code'] ?? 'SWAP-' . ($swap['id'] ?? 'N/A')) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}
</script>

<style>
/* Print Styles */
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
        background: white !important;
        padding: 0 !important;
    }
    
    .bg-gradient-to-r,
    .bg-gradient-to-br {
        background: white !important;
        color: black !important;
    }
    
    .text-white {
        color: black !important;
    }
    
    button,
    a[href] {
        display: none !important;
    }
}
</style>
