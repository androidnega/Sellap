<?php
// Purchase Order Form View
$isEdit = isset($order) && !empty($order);
$formTitle = $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/purchase-orders/update/' . $order['id'] : BASE_URL_PATH . '/dashboard/purchase-orders/store';
$items = $isEdit ? ($items ?? []) : [];
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Purchase Orders
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border p-6">
        <form method="POST" action="<?= $formAction ?>" id="purchaseOrderForm">
            <div class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Supplier <span class="text-red-500">*</span>
                        </label>
                        <select id="supplier_id" name="supplier_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" 
                                        <?= ($order['supplier_id'] ?? $_GET['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="order_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Order Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="order_date" name="order_date" 
                               value="<?= $order['order_date'] ?? date('Y-m-d') ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Expected Delivery Date
                        </label>
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date" 
                               value="<?= $order['expected_delivery_date'] ?? '' ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Order Items -->
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Order Items <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">For tracking and record keeping only</p>
                        </div>
                        <button type="button" onclick="addItemRow()" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div id="itemsContainer" class="space-y-2">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $index => $item): ?>
                                <?php include __DIR__ . '/partials/purchase_order_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-sm text-gray-500 p-4 border border-dashed rounded">
                                Click "Add Item" to add products to this purchase order
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Totals -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t pt-4">
                    <div>
                        <label for="tax_amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Tax Amount
                        </label>
                        <input type="number" id="tax_amount" name="tax_amount" 
                               value="<?= $order['tax_amount'] ?? 0 ?>" step="0.01" min="0"
                               onchange="calculateTotal()"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="shipping_cost" class="block text-sm font-medium text-gray-700 mb-2">
                            Shipping Cost
                        </label>
                        <input type="number" id="shipping_cost" name="shipping_cost" 
                               value="<?= $order['shipping_cost'] ?? 0 ?>" step="0.01" min="0"
                               onchange="calculateTotal()"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="discount_amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Discount
                        </label>
                        <input type="number" id="discount_amount" name="discount_amount" 
                               value="<?= $order['discount_amount'] ?? 0 ?>" step="0.01" min="0"
                               onchange="calculateTotal()"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Total Amount
                        </label>
                        <div id="totalAmount" class="text-2xl font-bold text-gray-800">
                            <?= number_format($order['total_amount'] ?? 0, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Status and Payment -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status
                        </label>
                        <select id="status" name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="draft" <?= ($order['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="pending" <?= ($order['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= ($order['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="received" <?= ($order['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                        </select>
                    </div>

                    <div>
                        <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Status
                        </label>
                        <select id="payment_status" name="payment_status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="unpaid" <?= ($order['payment_status'] ?? 'unpaid') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="partial" <?= ($order['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="paid" <?= ($order['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>

                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Method
                        </label>
                        <input type="text" id="payment_method" name="payment_method" 
                               value="<?= htmlspecialchars($order['payment_method'] ?? '') ?>"
                               placeholder="e.g., Cash, Bank Transfer"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea id="notes" name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders" 
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?= $isEdit ? 'Update Order' : 'Create Order' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let itemIndex = <?= count($items) ?>;

function addItemRow() {
    const container = document.getElementById('itemsContainer');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-2 items-end border p-2 rounded purchase-order-item-row';
    row.setAttribute('data-index', itemIndex);
    row.innerHTML = `
        <div class="col-span-4">
            <input type="hidden" name="items[${itemIndex}][product_id]" 
                   id="item_${itemIndex}_product_id" value="">
            <input type="text" 
                   name="items[${itemIndex}][product_name]" 
                   id="item_${itemIndex}_product_name"
                   placeholder="Enter product/item name" 
                   required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">For tracking purposes only</p>
        </div>
        <div class="col-span-2">
            <input type="text" name="items[${itemIndex}][product_sku]" 
                   id="item_${itemIndex}_product_sku"
                   placeholder="SKU"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
            <input type="number" name="items[${itemIndex}][quantity]" placeholder="Qty" required min="1"
                   onchange="calculateTotal()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
            <input type="number" name="items[${itemIndex}][unit_cost]" placeholder="Unit Cost" required step="0.01" min="0"
                   onchange="calculateTotal()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
            <button type="button" onclick="this.parentElement.parentElement.remove(); calculateTotal();" 
                    class="w-full bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
    `;
    container.appendChild(row);
    itemIndex++;
}

// Purchase orders are for tracking only - no product search needed

function calculateTotal() {
    // Simple calculation - can be enhanced
    let subtotal = 0;
    document.querySelectorAll('input[name*="[unit_cost]"]').forEach((input, index) => {
        const quantity = parseFloat(document.querySelectorAll('input[name*="[quantity]"]')[index]?.value || 0);
        const cost = parseFloat(input.value || 0);
        subtotal += quantity * cost;
    });
    
    const tax = parseFloat(document.getElementById('tax_amount')?.value || 0);
    const shipping = parseFloat(document.getElementById('shipping_cost')?.value || 0);
    const discount = parseFloat(document.getElementById('discount_amount')?.value || 0);
    
    const total = subtotal + tax + shipping - discount;
    document.getElementById('totalAmount').textContent = total.toFixed(2);
}
</script>

