<?php
// Purchase Order View Page
?>

<div class="p-6">
            <div class="mb-6">
                <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Purchase Orders
                </a>
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800">Purchase Order #<?= htmlspecialchars($order['order_number']) ?></h2>
                        <p class="text-gray-600">Tracking and Record Keeping Only - No Inventory Updates</p>
                    </div>
            <div class="flex gap-2">
                <?php if ($order['status'] === 'draft'): ?>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/edit/<?= $order['id'] ?>" 
                       class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
                <?php if ($order['status'] !== 'received'): ?>
                    <button onclick="markAsReceived(<?= $order['id'] ?>)" 
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                            title="Mark as received for tracking purposes only">
                        <i class="fas fa-check"></i> Mark as Received
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Order Information -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Order Number</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($order['order_number']) ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Supplier</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($order['supplier_name']) ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Order Date</dt>
                    <dd class="text-sm text-gray-900"><?= date('F d, Y', strtotime($order['order_date'])) ?></dd>
                </div>
                <?php if (!empty($order['expected_delivery_date'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Expected Delivery</dt>
                    <dd class="text-sm text-gray-900"><?= date('F d, Y', strtotime($order['expected_delivery_date'])) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['delivery_date'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Delivery Date</dt>
                    <dd class="text-sm text-gray-900"><?= date('F d, Y', strtotime($order['delivery_date'])) ?></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="text-sm">
                        <?php
                        $statusColors = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'confirmed' => 'bg-blue-100 text-blue-800',
                            'partially_received' => 'bg-orange-100 text-orange-800',
                            'received' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-red-100 text-red-800'
                        ];
                        $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusColor ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment Status</dt>
                    <dd class="text-sm">
                        <?php
                        $paymentColors = [
                            'unpaid' => 'bg-red-100 text-red-800',
                            'partial' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800'
                        ];
                        $paymentColor = $paymentColors[$order['payment_status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $paymentColor ?>">
                            <?= ucfirst($order['payment_status']) ?>
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Financial Summary -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Financial Summary</h3>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Subtotal</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= number_format($order['subtotal'], 2) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Tax</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= number_format($order['tax_amount'], 2) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Shipping</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= number_format($order['shipping_cost'], 2) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Discount</dt>
                    <dd class="text-sm font-medium text-red-600">-<?= number_format($order['discount_amount'], 2) ?></dd>
                </div>
                <div class="flex justify-between border-t pt-3 mt-3">
                    <dt class="text-lg font-bold text-gray-800">Total</dt>
                    <dd class="text-lg font-bold text-gray-800"><?= number_format($order['total_amount'], 2) ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Order Items -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($item['product_sku'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($item['quantity']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($item['unit_cost'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= number_format($item['total_cost'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500">
                                    <span class="text-xs">Tracking only</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($order['notes'])): ?>
    <div class="mt-6 bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Notes</h3>
        <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
function markAsReceived(orderId) {
    if (!confirm('Mark this purchase order as received?\n\nNote: This is for tracking purposes only. Inventory will NOT be updated automatically.')) {
        return;
    }
    
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
    fetch(basePath + '/dashboard/purchase-orders/mark-received/' + orderId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update order'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>

