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
        <?php
        $orderStatus = $sale['status'] ?? 'completed';
        $statusColors = [
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'returned' => 'bg-amber-100 text-amber-900',
        ];
        $stClass = $statusColors[$orderStatus] ?? 'bg-gray-100 text-gray-800';
        ?>
        <?php
        $roleLower = strtolower(trim($roleLower ?? $_SESSION['user']['role'] ?? ''));
        $isSalesUser = in_array($roleLower, ['salesperson', 'sales'], true);
        $isManagerUser = ($roleLower === 'manager');
        ?>
        <?php if (!empty($sale['deleted_at'])): ?>
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            This sale is <strong>archived</strong> (hidden from standard sales lists). Stock was not changed by archiving; cancel/return actions are disabled here.
        </div>
        <?php endif; ?>
        <?php if (in_array($roleLower, ['admin', 'system_admin'], true)): ?>
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
            <?php if ($roleLower === 'admin'): ?>
                Company administrators can view this sale and use <strong>Inventory &amp; order logs</strong> for reporting. Cancelling and returns are done by sales (within 30 minutes) or managers.
            <?php else: ?>
                View-only for this account. Use company <strong>manager</strong> or <strong>sales</strong> roles to cancel or return orders.
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600">Order status:</span>
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $stClass ?>"><?= htmlspecialchars(ucfirst($orderStatus)) ?></span>
            <?php if (!empty($sale['cancelled_at'])): ?>
                <span class="text-xs text-gray-500">Cancelled <?= htmlspecialchars($sale['cancelled_at']) ?> (<?= htmlspecialchars($sale['cancelled_role'] ?? '') ?>)</span>
            <?php endif; ?>
        </div>
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
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Returned</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
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
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-right hidden sm:table-cell">
                                        <?= htmlspecialchars((string)($item['returned_quantity'] ?? 0)) ?>
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
                            <td colspan="5" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Subtotal:
                            </td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900">
                                ₵<?= number_format(($sale['final_amount'] ?? $sale['total'] ?? 0) + ($sale['discount'] ?? 0) - ($sale['tax'] ?? 0), 2) ?>
                            </td>
                        </tr>
                        <?php if (!empty($sale['discount']) && $sale['discount'] > 0): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Discount:
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-red-600">
                                -₵<?= number_format($sale['discount'], 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($sale['tax']) && $sale['tax'] > 0): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-right text-sm font-medium text-gray-700">
                                Tax:
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-900">
                                ₵<?= number_format($sale['tax'], 2) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-right text-lg font-bold text-gray-900">
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
        <div class="flex flex-col gap-4 mb-4">
            <div id="posSaleActionMsg" class="hidden text-sm rounded-md px-3 py-2"></div>
            <div class="flex flex-col sm:flex-row flex-wrap justify-end gap-3">
                <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 text-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sales History
                </a>
                <?php if (!empty($canCancelOrder)): ?>
                <button type="button" id="btnCancelOrder" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-center">
                    <i class="fas fa-ban mr-2"></i>Cancel order
                </button>
                <?php elseif (!empty($schema) && ($orderStatus ?? '') === 'completed' && $isSalesUser && empty($swapBlocked)): ?>
                <button type="button" disabled class="bg-gray-200 text-gray-500 px-4 py-2 rounded cursor-not-allowed text-center text-sm" title="Sales can cancel within 30 minutes of checkout only">
                    Cancel unavailable (30 min window)
                </button>
                <?php endif; ?>
                <?php if (!empty($canReturnItems)): ?>
                <button type="button" id="btnOpenReturn" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700 text-center">
                    <i class="fas fa-undo mr-2"></i>Return items
                </button>
                <?php elseif (!empty($schema) && $isManagerUser && ($orderStatus ?? '') !== 'cancelled' && empty($returnsEnabled)): ?>
                <p class="text-sm text-gray-500 self-center">Returns are disabled for your company. A company admin can enable them under <strong>Company features</strong>.</p>
                <?php endif; ?>
                <a href="<?= BASE_URL_PATH ?>/pos/receipt/<?= $sale['id'] ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-center">
                    <i class="fas fa-print mr-2"></i>Print Receipt
                </a>
            </div>
        </div>

        <?php if (!empty($canReturnItems)): ?>
        <div id="returnPanel" class="hidden border border-amber-200 rounded-lg bg-amber-50/50 p-4 sm:p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Process return</h3>
            <p class="text-sm text-gray-600 mb-4">Enter quantity to return per line (cannot exceed purchased minus already returned).</p>
            <form id="formReturnItems" class="space-y-3">
                <?php foreach ($items as $item):
                    $sold = (int)($item['quantity'] ?? 0);
                    $ret = (int)($item['returned_quantity'] ?? 0);
                    $maxR = $sold - $ret;
                    $pid = (int)($item['item_id'] ?? 0);
                    $sw = isset($item['is_swapped_item']) && (int)$item['is_swapped_item'] === 1;
                    if ($pid <= 0 || $maxR <= 0 || $sw) {
                        continue;
                    }
                ?>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 border-b border-amber-100 pb-3">
                    <div class="flex-1 text-sm">
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($item['item_description'] ?? $item['product_name'] ?? 'Item') ?></span>
                        <span class="text-gray-500">(max <?= (int)$maxR ?>)</span>
                    </div>
                    <label class="text-sm text-gray-700 flex items-center gap-2">
                        Qty
                        <input type="number" min="0" max="<?= (int)$maxR ?>" value="0" name="ret_<?= (int)$item['id'] ?>"
                            data-line-id="<?= (int)$item['id'] ?>"
                            class="return-qty w-24 border rounded px-2 py-1 text-sm border-gray-300">
                    </label>
                </div>
                <?php endforeach; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                    <textarea name="return_notes" id="return_notes" rows="2" class="w-full border rounded-md px-3 py-2 text-sm border-gray-300"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700 text-sm font-medium">Submit return</button>
                    <button type="button" id="btnCloseReturn" class="bg-white border border-gray-300 text-gray-800 px-4 py-2 rounded text-sm">Close</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <script>
        (function() {
            var base = <?= json_encode(rtrim(defined('BASE_URL_PATH') ? (string)BASE_URL_PATH : '', '/')) ?>;
            var saleId = <?= (int)($sale['id'] ?? 0) ?>;
            var msgEl = document.getElementById('posSaleActionMsg');
            function showMsg(text, ok) {
                if (!msgEl) return;
                msgEl.textContent = text;
                msgEl.classList.remove('hidden', 'bg-red-50', 'text-red-800', 'bg-green-50', 'text-green-800');
                msgEl.classList.add(ok ? 'bg-green-50' : 'bg-red-50', ok ? 'text-green-800' : 'text-red-800');
            }
            var btnCancel = document.getElementById('btnCancelOrder');
            if (btnCancel) {
                btnCancel.addEventListener('click', function() {
                    if (!confirm('Cancel this order and restore stock?')) return;
                    fetch(base + '/api/pos/sale/' + saleId + '/cancel', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: '{}'
                    }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                    .then(function(x) {
                        if (x.ok && x.j.success) {
                            showMsg(x.j.message || 'Cancelled.', true);
                            setTimeout(function() { location.reload(); }, 900);
                        } else {
                            showMsg((x.j && x.j.message) || 'Could not cancel.', false);
                        }
                    }).catch(function() { showMsg('Network error.', false); });
                });
            }
            var panel = document.getElementById('returnPanel');
            var btnOpen = document.getElementById('btnOpenReturn');
            var btnClose = document.getElementById('btnCloseReturn');
            if (btnOpen && panel) {
                btnOpen.addEventListener('click', function() { panel.classList.toggle('hidden'); });
            }
            if (btnClose && panel) {
                btnClose.addEventListener('click', function() { panel.classList.add('hidden'); });
            }
            var formRet = document.getElementById('formReturnItems');
            if (formRet) {
                formRet.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    var inputs = formRet.querySelectorAll('input.return-qty');
                    var items = [];
                    inputs.forEach(function(inp) {
                        var q = parseInt(inp.value, 10) || 0;
                        if (q > 0) {
                            items.push({ pos_sale_item_id: parseInt(inp.getAttribute('data-line-id'), 10), quantity: q });
                        }
                    });
                    if (!items.length) {
                        showMsg('Enter at least one return quantity.', false);
                        return;
                    }
                    var notes = (document.getElementById('return_notes') || {}).value || '';
                    fetch(base + '/api/pos/sale/' + saleId + '/return', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ items: items, notes: notes })
                    }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                    .then(function(x) {
                        if (x.ok && x.j.success) {
                            showMsg(x.j.message || 'Return saved.', true);
                            setTimeout(function() { location.reload(); }, 900);
                        } else {
                            showMsg((x.j && x.j.message) || 'Return failed.', false);
                        }
                    }).catch(function() { showMsg('Network error.', false); });
                });
            }
        })();
        </script>
    <?php endif; ?>
</div>

