<?php
// Repair receipt view - printable format
?>

<div class="max-w-4xl mx-auto">
    <!-- Print Button -->
    <div class="mb-6 flex justify-between items-center">
        <div class="flex items-center">
            <a href="/repairs/<?= $repair['id'] ?>" class="text-gray-500 hover:text-gray-700 mr-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-800">Repair Receipt</h1>
        </div>
        <button onclick="window.print()" class="btn btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print Receipt
        </button>
    </div>

    <!-- Receipt Content -->
    <div class="bg-white rounded-lg shadow-sm border p-8 print:p-4">
        <!-- Header -->
        <div class="text-center mb-8 print:mb-4">
            <h2 class="text-3xl font-bold text-gray-800 print:text-2xl">Repair Receipt</h2>
            <p class="text-gray-600 mt-2">Repair ID: #<?= $repair['id'] ?></p>
            <?php if (!empty($repair['tracking_code'])): ?>
                <p class="text-gray-600">Tracking Code: <?= htmlspecialchars($repair['tracking_code']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Company Info (if available) -->
        <div class="text-center mb-6 print:mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Repair Service</h3>
            <p class="text-gray-600">Professional Device Repair</p>
        </div>

        <!-- Customer Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 print:mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Customer Information</h3>
                <div class="space-y-2">
                    <p><strong>Name:</strong> <?= htmlspecialchars($repair['customer_name']) ?></p>
                    <p><strong>Contact:</strong> <?= htmlspecialchars($repair['customer_contact']) ?></p>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Repair Information</h3>
                <div class="space-y-2">
                    <p><strong>Technician:</strong> <?= htmlspecialchars($repair['technician_name'] ?? 'Unknown') ?></p>
                    <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($repair['created_at'])) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                            <?= ucfirst(str_replace('_', ' ', $repair['status'])) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Device Information -->
        <div class="mb-8 print:mb-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Device Information</h3>
            <div class="bg-gray-50 p-4 rounded-lg print:bg-white print:border">
                <p><strong>Device:</strong> 
                    <?php if ($repair['product_name']): ?>
                        <?= htmlspecialchars($repair['product_name']) ?>
                    <?php else: ?>
                        Customer's own device
                    <?php endif; ?>
                </p>
                <p class="mt-2"><strong>Issue Description:</strong></p>
                <p class="text-gray-700"><?= htmlspecialchars($repair['issue_description']) ?></p>
            </div>
        </div>

        <!-- Accessories Used -->
        <?php if (!empty($accessories)): ?>
            <div class="mb-8 print:mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Accessories Used</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                    Accessory
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                    Quantity
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                    Unit Price
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($accessories as $accessory): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                        <?= htmlspecialchars($accessory['product_name']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-900 border-r border-gray-200">
                                        <?= $accessory['quantity'] ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 border-r border-gray-200">
                                        ₵<?= number_format($accessory['price'], 2) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">
                                        ₵<?= number_format($accessory['quantity'] * $accessory['price'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cost Breakdown -->
        <div class="mb-8 print:mb-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Cost Breakdown</h3>
            <div class="bg-gray-50 p-4 rounded-lg print:bg-white print:border">
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Repair Cost:</span>
                        <span class="font-medium">₵<?= number_format($repair['repair_cost'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Parts Cost:</span>
                        <span class="font-medium">₵<?= number_format($repair['parts_cost'], 2) ?></span>
                    </div>
                    <?php if ($repair['accessory_cost'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Accessories:</span>
                            <span class="font-medium">₵<?= number_format($repair['accessory_cost'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <hr class="border-gray-300 my-2">
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total Cost:</span>
                        <span>₵<?= number_format($repair['total_cost'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Status -->
        <div class="mb-8 print:mb-4">
            <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-gray-800">Payment Status:</span>
                <?php
                $paymentColors = [
                    'unpaid' => 'bg-red-100 text-red-800',
                    'partial' => 'bg-yellow-100 text-yellow-800',
                    'paid' => 'bg-green-100 text-green-800'
                ];
                $paymentColor = $paymentColors[$repair['payment_status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <span class="px-3 py-1 text-sm font-medium rounded-full <?= $paymentColor ?>">
                    <?= ucfirst($repair['payment_status']) ?>
                </span>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($repair['notes'])): ?>
            <div class="mb-8 print:mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Additional Notes</h3>
                <div class="bg-gray-50 p-4 rounded-lg print:bg-white print:border">
                    <p class="text-gray-700"><?= htmlspecialchars($repair['notes']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-gray-500 text-sm print:text-xs">
            <p>Thank you for choosing our repair service!</p>
            <p class="mt-2">For any questions, please contact us with Repair ID #<?= $repair['id'] ?></p>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .print\\:p-4 { padding: 1rem !important; }
    .print\\:mb-4 { margin-bottom: 1rem !important; }
    .print\\:text-2xl { font-size: 1.5rem !important; }
    .print\\:bg-white { background-color: white !important; }
    .print\\:border { border: 1px solid #d1d5db !important; }
    .print\\:text-xs { font-size: 0.75rem !important; }
    
    /* Hide print button when printing */
    button[onclick="window.print()"] { display: none !important; }
    
    /* Ensure proper page breaks */
    .print\\:break-inside-avoid { break-inside: avoid; }
    
    /* Optimize for A4 paper */
    body { font-size: 12px; }
    .max-w-4xl { max-width: none !important; }
}
</style>
