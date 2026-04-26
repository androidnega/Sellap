<?php
$rows = $rows ?? [];
$detail = $detail ?? null;
$detailItems = $detailItems ?? [];
$pageHeading = $pageHeading ?? 'Returns';
$readOnlyUi = $readOnlyUi ?? false;
$canProcessReturns = $canProcessReturns ?? false;
?>
<div class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos/sales-history" class="text-sm text-blue-600 hover:text-blue-800">← Sales history</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($pageHeading) ?></h1>
        <?php if ($readOnlyUi): ?>
            <p class="mt-2 text-sm rounded-md border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2">
                View only. Return processing is done by a <strong>manager</strong> on each sale’s detail page.
            </p>
        <?php elseif ($canProcessReturns): ?>
            <p class="mt-2 text-sm text-gray-600">To restock inventory, open a sale and use <strong>Return items</strong> on the sale detail page.</p>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
        <p class="text-gray-600">No returns found<?= $readOnlyUi ? ' for your sales.' : '.' ?></p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm mb-8">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Return</th>
                        <th class="px-3 py-2">POS sale</th>
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
                            <a class="text-blue-600 text-sm hover:underline" href="?id=<?= (int)$r['id'] ?>">Lines</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($detail): ?>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Return #<?= (int)$detail['id'] ?> — lines</h2>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
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
