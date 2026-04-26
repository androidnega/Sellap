<?php
$rows = $rows ?? [];
$companies = $companies ?? [];
$viewerRole = $viewerRole ?? '';
$filterCompany = $filterCompany ?? null;
$typeFilter = $typeFilter ?? null;
?>
<div class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex flex-col gap-4 mb-6">
        <div>
            <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs" class="text-sm text-blue-600 hover:text-blue-800">← Back to hub</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Stock movement history</h1>
        </div>
        <form method="get" class="flex flex-wrap items-end gap-3">
            <?php if ($viewerRole === 'system_admin' && !empty($companies)): ?>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                <select name="company_id" class="border rounded-md px-2 py-1 text-sm">
                    <option value="">All</option>
                    <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= $filterCompany === (int)$co['id'] ? 'selected' : '' ?>><?= htmlspecialchars($co['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select name="type" class="border rounded-md px-2 py-1 text-sm">
                    <option value="">All</option>
                    <option value="sale" <?= $typeFilter === 'sale' ? 'selected' : '' ?>>Sale</option>
                    <option value="cancel" <?= $typeFilter === 'cancel' ? 'selected' : '' ?>>Cancel</option>
                    <option value="return" <?= $typeFilter === 'return' ? 'selected' : '' ?>>Return</option>
                </select>
            </div>
            <button type="submit" class="rounded-md bg-gray-800 text-white text-sm px-3 py-1.5 hover:bg-gray-900">Apply</button>
        </form>
    </div>

    <?php if (empty($rows)): ?>
        <p class="text-gray-600">No stock movements recorded yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                    <tr>
                        <?php if ($viewerRole === 'system_admin'): ?><th class="px-3 py-2">Company</th><?php endif; ?>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Product</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2">Ref</th>
                        <th class="px-3 py-2">Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <?php if ($viewerRole === 'system_admin'): ?>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['company_name'] ?? '') ?></td>
                        <?php endif; ?>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                        <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-800"><?= htmlspecialchars($r['type'] ?? '') ?></span></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['product_name'] ?? ('#' . (int)($r['product_id'] ?? 0))) ?></td>
                        <td class="px-3 py-2 text-right"><?= (int)($r['quantity'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars(($r['reference_type'] ?? '') . ' ' . (string)($r['reference_id'] ?? '')) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['created_role'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
