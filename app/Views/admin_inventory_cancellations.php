<?php
$rows = $rows ?? [];
$companies = $companies ?? [];
$viewerRole = $viewerRole ?? '';
$filterCompany = $filterCompany ?? null;
?>
<div class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs" class="text-sm text-blue-600 hover:text-blue-800">← Back to hub</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Cancellations</h1>
        </div>
        <?php if ($viewerRole === 'system_admin' && !empty($companies)): ?>
        <form method="get" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Company</label>
            <select name="company_id" class="border rounded-md px-2 py-1 text-sm" onchange="this.form.submit()">
                <option value="">All companies</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= (int)$co['id'] ?>" <?= $filterCompany === (int)$co['id'] ? 'selected' : '' ?>><?= htmlspecialchars($co['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
        <p class="text-gray-600">No cancellations recorded yet, or the database migration has not been applied.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                    <tr>
                        <?php if ($viewerRole === 'system_admin'): ?><th class="px-3 py-2">Company</th><?php endif; ?>
                        <th class="px-3 py-2">Sale</th>
                        <th class="px-3 py-2">Cancelled at</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">User</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <?php if ($viewerRole === 'system_admin'): ?>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['company_name'] ?? '') ?></td>
                        <?php endif; ?>
                        <td class="px-3 py-2">
                            <a class="text-blue-600 hover:underline" href="<?= BASE_URL_PATH ?>/dashboard/sales/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($r['unique_id'] ?? '') ?></div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['cancelled_at'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['cancelled_role'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['cancelled_by_username'] ?? '') ?></td>
                        <td class="px-3 py-2 text-right">₵<?= number_format((float)($r['final_amount'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
