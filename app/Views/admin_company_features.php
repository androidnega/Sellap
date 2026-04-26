<?php
$viewerRole = $viewerRole ?? '';
$targetCompany = (int)($targetCompany ?? 0);
$companies = $companies ?? [];
$returnsEnabled = !empty($returnsEnabled);
$flash = $flash ?? null;
$canEditFeatures = !empty($canEditFeatures);
?>
<div class="w-full max-w-xl mx-auto px-4 sm:px-6 py-8">
    <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs" class="text-sm text-blue-600 hover:text-blue-800">← Back to hub</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2 mb-2">Company features</h1>
    <p class="text-gray-600 text-sm mb-6">Returns and other company-level toggles. Only the company administrator can change settings (no direct stock impact from this page).</p>

    <?php if ($flash): ?>
        <div class="mb-4 rounded-md px-3 py-2 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['text'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($viewerRole === 'system_admin' && !empty($companies)): ?>
        <form method="get" action="<?= BASE_URL_PATH ?>/dashboard/admin/company-features" class="mb-6 flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">View company</label>
                <select name="company_id" class="border rounded-md px-3 py-2 text-sm min-w-[12rem]" onchange="this.form.submit()">
                    <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= $targetCompany === (int)$co['id'] ? 'selected' : '' ?>><?= htmlspecialchars($co['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!\App\Models\CompanyFeature::tableExists()): ?>
        <p class="text-red-700 text-sm">The company_features table is missing. Run the database migration first.</p>
    <?php elseif ($targetCompany <= 0): ?>
        <p class="text-gray-600 text-sm">No company selected.</p>
    <?php elseif (!$canEditFeatures): ?>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm space-y-2">
            <p class="text-sm text-gray-700"><span class="font-medium text-gray-900">Returns enabled:</span> <?= $returnsEnabled ? 'Yes' : 'No' ?></p>
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">Only the company <strong>admin</strong> can change features. This page is view-only for your role.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL_PATH ?>/dashboard/admin/company-features/save" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <input type="hidden" name="company_id" value="<?= $targetCompany ?>">

            <div class="flex items-start gap-3">
                <input type="checkbox" id="returns_enabled" name="returns_enabled" value="1" class="mt-1 rounded border-gray-300" <?= $returnsEnabled ? 'checked' : '' ?>>
                <div>
                    <label for="returns_enabled" class="font-medium text-gray-900">Returns enabled</label>
                    <p class="text-sm text-gray-600 mt-0.5">When off, managers will not see return actions on sale details.</p>
                </div>
            </div>

            <button type="submit" class="w-full sm:w-auto rounded-md bg-blue-600 text-white text-sm font-medium px-4 py-2 hover:bg-blue-700">Save</button>
        </form>
    <?php endif; ?>
</div>
