<?php
// Start session to access success message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$title = 'Payment Successful';
$userRole = 'manager';
$currentPage = 'dashboard';
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
$paymentId = $_GET['payment_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - SellApp</title>
    <!-- Custom Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo $basePath; ?>/assets/images/favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="<?php echo $basePath; ?>/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo $basePath; ?>/assets/images/favicon.svg">
    
    <!-- Preconnect to CDN for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    
    <!-- Tailwind CSS - loaded synchronously to prevent FOUC -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-8 text-center">
        <div class="mb-6">
            <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-check-circle text-5xl text-green-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <p class="text-green-800 font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </p>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php else: ?>
                <p class="text-gray-600">Your SMS credits have been added to your account.</p>
            <?php endif; ?>
        </div>
        
        <?php if (isset($paymentId) && $paymentId): ?>
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <p class="text-sm text-gray-600">Payment ID:</p>
            <p class="text-sm font-mono text-gray-900"><?= htmlspecialchars($paymentId) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="space-y-4">
            <a href="<?= $basePath ?>/dashboard/sms-settings" class="block w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Return to SMS Settings
            </a>
            <a href="<?= $basePath ?>/dashboard" class="block w-full bg-gray-200 text-gray-700 py-3 px-4 rounded-lg font-medium hover:bg-gray-300 transition">
                <i class="fas fa-tachometer-alt mr-2"></i>
                Go to Dashboard
            </a>
        </div>
        
        <script>
        // Set flag to refresh SMS data when returning to SMS settings
        sessionStorage.setItem('refreshSMSData', 'true');
        
        // Trigger refresh of SMS balance across all open pages
        // Dispatch custom event to refresh balance
        window.dispatchEvent(new CustomEvent('refreshSMSBalance'));
        
        // If opened in popup/iframe, notify parent window
        if (window.opener && window.opener !== window) {
            window.opener.postMessage({ type: 'refreshSMSBalance' }, '*');
            if (typeof window.opener.loadSMSData === 'function') {
                window.opener.loadSMSData();
            }
        }
        
        // If in iframe, notify parent
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'refreshSMSBalance' }, '*');
        }
        </script>
    </div>
</body>
</html>

