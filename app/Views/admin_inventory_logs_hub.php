<?php
$viewerRole = $viewerRole ?? '';
$returnsOn = $returnsOn ?? false;
$companyId = (int)($companyId ?? 0);
?>
<div class="w-full max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Inventory &amp; order logs</h1>
    <p class="text-gray-600 mb-8">Read-only views for cancellations, returns, and stock movements. Company admins manage the returns feature below.</p>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs/cancellations" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-400 transition">
            <h2 class="font-semibold text-gray-900">Cancellations</h2>
            <p class="text-sm text-gray-600 mt-1">Orders marked cancelled with who cancelled and when.</p>
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs/returns" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-400 transition">
            <h2 class="font-semibold text-gray-900">Returns report</h2>
            <p class="text-sm text-gray-600 mt-1">Read-only list of returns for audit. Managers work returns under <strong>POS → Returns</strong> (or sale detail).</p>
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/admin/inventory-logs/stock-history" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-400 transition sm:col-span-2">
            <h2 class="font-semibold text-gray-900">Stock history</h2>
            <p class="text-sm text-gray-600 mt-1">Sale, cancel, and return movements recorded for inventory.</p>
        </a>
    </div>

    <div class="mt-10 rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-semibold text-amber-900">Returns feature</h2>
        <p class="text-sm text-amber-900/90 mt-1 mb-3">Managers can only process returns when this is enabled for your company.</p>
        <p class="text-sm text-gray-700">Status for your company: <span class="font-medium"><?= $returnsOn ? 'Enabled' : 'Disabled' ?></span></p>
        <a href="<?= BASE_URL_PATH ?>/dashboard/admin/company-features<?= $viewerRole === 'system_admin' && $companyId ? '?company_id=' . (int)$companyId : '' ?>" class="inline-block mt-3 text-sm font-medium text-blue-700 hover:text-blue-900">Open company features →</a>
    </div>
</div>
