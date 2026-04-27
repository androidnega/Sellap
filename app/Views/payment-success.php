<?php
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="w-full max-w-full">
    <div class="mb-5">
        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-green-100 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-check-circle text-3xl sm:text-5xl text-green-600"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">You're all set</h1>
        <p class="text-gray-600 text-sm sm:text-base">Your new SMS credit is ready to use.</p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-5 text-left max-w-3xl">
            <p class="text-green-800 font-medium text-sm sm:text-base">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php else: ?>
        <p class="text-gray-600 text-sm sm:text-base mb-5 max-w-3xl">Credits have been added to your company balance.</p>
    <?php endif; ?>

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

<script>
if (typeof sessionStorage !== 'undefined') {
    sessionStorage.setItem('refreshSMSData', 'true');
}
if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('refreshSMSBalance'));
    try {
        if (window.opener && window.opener !== window) {
            window.opener.postMessage({ type: 'refreshSMSBalance' }, '*');
            if (typeof window.opener.loadSMSData === 'function') {
                window.opener.loadSMSData();
            }
        }
    } catch (e) { /* ignore */ }
    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'refreshSMSBalance' }, '*');
        }
    } catch (e) { /* ignore */ }
}
</script>
