<div class="max-w-3xl mx-auto">
    <div class="bg-white shadow rounded-lg border p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Laptop Category Migration</h1>
        <p class="text-gray-600">Run date: <?= date('Y-m-d H:i:s') ?></p>

        <?php if (($GLOBALS['migration_status'] ?? 'success') === 'success'): ?>
            <div class="mt-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded">
                <strong>Success!</strong> The laptop category and default brands are ready to use.
            </div>
        <?php else: ?>
            <div class="mt-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                <strong>Migration failed:</strong> <?= htmlspecialchars($GLOBALS['migration_error'] ?? 'Unknown error') ?>
            </div>
        <?php endif; ?>

        <div class="mt-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Detailed Log</h2>
            <div class="space-y-2">
                <?php foreach (($GLOBALS['migration_logs'] ?? []) as $log): ?>
                    <?php
                        $colorMap = [
                            'success' => 'text-green-700',
                            'error' => 'text-red-700',
                            'info' => 'text-gray-700',
                        ];
                        $color = $colorMap[$log['type']] ?? 'text-gray-700';
                    ?>
                    <div class="<?= $color ?> flex items-center">
                        <span class="mr-2">•</span>
                        <span><?= htmlspecialchars($log['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="<?= BASE_URL_PATH ?>/dashboard/brands"
               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Go to Brands
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/inventory/create"
               class="px-4 py-2 bg-gray-100 text-gray-800 rounded hover:bg-gray-200">
                Add Laptop Product
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard"
               class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>

