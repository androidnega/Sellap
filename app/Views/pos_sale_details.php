<?php
/**
 * POS Sale Details View
 */
// Ensure variables are available
$sale = $sale ?? null;
$items = $items ?? [];
?>

<div class="w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
    <!-- Header -->
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Sales History
        </a>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Sale Details</h2>
        <p class="text-sm sm:text-base text-gray-600">Transaction #<?= htmlspecialchars($sale['id'] ?? 'N/A') ?></p>
    </div>
    
    <?php if (!$sale): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            Sale not found.
        </div>
    <?php else: ?>
        <!-- Sale Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Sale Info Card -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Sale Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Sale ID</label>
                        <p class="text-lg font-semibold text-gray-900">#<?= htmlspecialchars($sale['id'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Date & Time</label>
                        <p class="text-gray-900">
                            <?php if (!empty($sale['created_at'])): ?>
                                <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Customer</label>
                        <p class="text-gray-900">
                            <?= htmlspecialchars($sale['customer_name'] ?? $sale['customer_name_from_table'] ?? 'Walk-in Customer') ?>
                        </p>
                    </div>
                    <?php if (!empty($sale['customer_contact'])): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Contact</label>
                        <p class="text-gray-900"><?= htmlspecialchars($sale['customer_contact']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($sale['cashier_name'])): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Cashier</label>
                        <p class="text-gray-900"><?= htmlspecialchars($sale['cashier_name']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Info Card -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Payment Method</label>
                        <p class="text-gray-900">
                            <span class="px-2 py-1 rounded-full text-xs font-medium 
                                <?php
                                $method = strtoupper($sale['payment_method'] ?? 'CASH');
                                switch($method) {
                                    case 'CASH':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'CARD':
                                        echo 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'MOBILE_MONEY':
                                        echo 'bg-purple-100 text-purple-800';
                                        break;
                                    default:
                                        echo 'bg-gray-100 text-gray-800';
                                }
                                ?>
                            ">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'Cash'))) ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Payment Status</label>
                        <p class="text-gray-900">
                            <?php
                            $status = strtoupper($sale['payment_status'] ?? 'PAID');
                            $statusClass = '';
                            $statusText = '';
                            switch($status) {
                                case 'PAID':
                                    $statusClass = 'bg-green-100 text-green-800';
                                    $statusText = 'Paid';
                                    break;
                                case 'PARTIAL':
                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                    $statusText = 'Partial';
                                    break;
                                case 'UNPAID':
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $statusText = 'Unpaid';
                                    break;
                                default:
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $statusText = ucfirst($status);
                            }
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                <?= $statusText ?>
                            </span>
                        </p>
                    </div>
                    <?php if (isset($sale['total_paid'])): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Total Paid</label>
                        <p class="text-gray-900 text-lg font-semibold">₵<?= number_format($sale['total_paid'], 2) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Total Amount</label>
                        <p class="text-gray-900 text-2xl font-bold text-green-600">
                            ₵<?= number_format($sale['final_amount'] ?? $sale['total'] ?? 0, 2) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="bg-white rounded-lg shadow-sm border mb-6">
            <div class="p-4 sm:p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Items (<?= count($items) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Category</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No items found for this sale.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['item_description'] ?? $item['product_name'] ?? 'Product') ?>
                                        </div>
                                        <?php if (!empty($item['product_name']) && ($item['item_description'] ?? '') !== $item['product_name']): ?>
                                            <div class="text-xs text-blue-600 mt-1">
                                                Product: <?= htmlspecialchars($item['product_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['product_id'])): ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                Product ID: <?= htmlspecialchars($item['product_id']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 hidden md:table-cell">
                                        <?= htmlspecialchars($item['category_name'] ?? '-') ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= htmlspecialchars($item['quantity'] ?? 0) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ₵<?= number_format($item['unit_price'] ?? 0, 2) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                        ₵<?= number_format($item['total_price'] ?? 0, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Subtotal:
                            </td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900">
                                ₵<?= number_format(($sale['final_amount'] ?? $sale['total'] ?? 0) + ($sale['discount'] ?? 0) - ($sale['tax'] ?? 0), 2) ?>
                            </td>
                        </tr>
                        <?php if (!empty($sale['discount']) && $sale['discount'] > 0): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Discount:
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-red-600">
                                -₵<?= number_format($sale['discount'], 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($sale['tax']) && $sale['tax'] > 0): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Tax:
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-900">
                                ₵<?= number_format($sale['tax'], 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-right text-lg font-bold text-gray-900">
                                Total:
                            </td>
                            <td class="px-4 py-4 text-right text-lg font-bold text-green-600">
                                ₵<?= number_format($sale['final_amount'] ?? $sale['total'] ?? 0, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-end gap-3">
            <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 text-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Sales History
            </a>
            <a href="<?= BASE_URL_PATH ?>/pos/receipt/<?= $sale['id'] ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-center">
                <i class="fas fa-print mr-2"></i>Print Receipt
            </a>
        </div>
    <?php endif; ?>
</div>

