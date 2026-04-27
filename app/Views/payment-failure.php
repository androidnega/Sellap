<?php
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
$userMessage = $userMessage ?? 'We could not complete the payment. Please go back to SMS settings and try again.';
?>
<div class="w-full max-w-full">
    <div class="mb-5">
        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-times-circle text-3xl sm:text-5xl text-red-600"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Payment did not go through</h1>
        <p class="text-gray-600 text-sm sm:text-base max-w-3xl"><?= htmlspecialchars($userMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 w-full max-w-3xl">
        <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/dashboard/sms-settings" class="inline-flex items-center justify-center w-full sm:w-auto min-h-[48px] px-5 py-2.5 rounded-lg font-semibold bg-blue-600 text-white hover:bg-blue-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to SMS settings
        </a>
        <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/dashboard" class="inline-flex items-center justify-center w-full sm:w-auto min-h-[48px] px-5 py-2.5 rounded-lg font-medium bg-slate-100 text-slate-800 hover:bg-slate-200 transition">
            <i class="fas fa-tachometer-alt mr-2"></i>
            Dashboard
        </a>
    </div>
</div>
