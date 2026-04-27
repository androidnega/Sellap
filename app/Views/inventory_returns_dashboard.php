<?php
$rows = $rows ?? [];
$detail = $detail ?? null;
$detailItems = $detailItems ?? [];
$pageHeading = $pageHeading ?? 'Returns';
$readOnlyUi = $readOnlyUi ?? false;
$canProcessReturns = $canProcessReturns ?? false;
$returnStats = $returnStats ?? ['total' => 0, 'last_30_days' => 0];
$returnsTableOk = $returnsTableOk ?? true;
?>
<div class="w-full max-w-full min-w-0">
    <div class="mb-5 sm:mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="text-sm text-blue-600 hover:text-blue-800">Back to sales history</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($pageHeading) ?></h1>
        <?php if (!$returnsTableOk): ?>
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2.5 text-sm">
                This screen isn’t available yet. Please contact your administrator.
            </div>
        <?php endif; ?>
        <?php if ($readOnlyUi): ?>
            <p class="mt-2 text-sm text-gray-600">Only returns for your own sales are shown. To add a return, ask a manager.</p>
        <?php elseif ($canProcessReturns): ?>
            <p class="mt-2 text-sm text-gray-600">Open a past sale in <a class="text-blue-600 hover:text-blue-800 font-medium" href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history">Sales history</a> and use <strong>Return items</strong> on the sale you need.</p>
        <?php endif; ?>
    </div>

    <?php if ($returnsTableOk && empty($rows)): ?>
    <div class="rounded-lg border border-gray-200 bg-white p-5 sm:p-6 shadow-sm mb-6 w-full max-w-full">
        <h2 class="text-base font-semibold text-gray-900">No returns yet</h2>
        <p class="text-sm text-gray-600 mt-2 max-w-2xl">When someone records a return, it will show up in this list.</p>
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="inline-block mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">Go to sales history</a>
    </div>
    <?php endif; ?>

    <?php if ($returnsTableOk && !empty($rows)): ?>
    <div class="flex flex-wrap gap-3 mb-6">
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm">
            <span class="text-gray-500">Total returns</span>
            <div class="text-lg font-semibold text-gray-900"><?= (int)($returnStats['total'] ?? 0) ?></div>
        </div>
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm">
            <span class="text-gray-500">Last 30 days</span>
            <div class="text-lg font-semibold text-gray-900"><?= (int)($returnStats['last_30_days'] ?? 0) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($returnsTableOk && !empty($rows)): ?>
        <div class="overflow-x-auto w-full min-w-0 rounded-lg border border-gray-200 bg-white shadow-sm mb-8">
            <table class="w-full min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Return</th>
                        <th class="px-3 py-2">Sale</th>
                        <th class="px-3 py-2">Sold by</th>
                        <th class="px-3 py-2">Created</th>
                        <th class="px-3 py-2">Recorded by</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">#<?= (int)$r['id'] ?></td>
                        <td class="px-3 py-2">
                            <a class="text-blue-600 hover:underline" href="<?= BASE_URL_PATH ?>/dashboard/sales/<?= (int)$r['pos_sale_id'] ?>"><?= htmlspecialchars($r['sale_code'] ?? ('#' . (int)$r['pos_sale_id'])) ?></a>
                        </td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['sale_owner_name'] ?? '—') ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['created_by_username'] ?? '') ?> <span class="text-gray-500">(<?= htmlspecialchars($r['created_role'] ?? '') ?>)</span></td>
                        <td class="px-3 py-2 text-right">
                            <a class="text-blue-600 text-sm hover:underline" href="?id=<?= (int)$r['id'] ?>">View items</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (!$returnsTableOk): ?>
        <p class="text-sm text-gray-600">This list is not available right now.</p>
    <?php endif; ?>

    <?php if ($detail): ?>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Return #<?= (int)$detail['id'] ?> · Items</h2>
        <?php if (!empty($detail['notes'])): ?>
            <p class="text-sm text-gray-700 mb-3 rounded-md border border-gray-100 bg-gray-50 px-3 py-2">Notes: <?= nl2br(htmlspecialchars((string)$detail['notes'])) ?></p>
        <?php endif; ?>
        <div class="overflow-x-auto w-full min-w-0 rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="w-full min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Product</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($detailItems as $it): ?>
                    <tr>
                        <td class="px-3 py-2"><?= htmlspecialchars($it['item_description'] ?? '') ?></td>
                        <td class="px-3 py-2 text-right"><?= (int)($it['quantity'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-right"><?= $it['product_id'] ? (int)$it['product_id'] : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
