<?php
$title = 'Payment Successful';
$userRole = 'manager';
$currentPage = 'dashboard';
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - SellApp</title>
    <!-- Custom Favicon - Overrides XAMPP default favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
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
            <p class="text-gray-600">Your SMS credits have been added to your account.</p>
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
        
        // Refresh SMS balance on parent page if opened from there
        if (window.opener && typeof window.opener.loadSMSData === 'function') {
            window.opener.loadSMSData();
        }
        </script>
    </div>
</body>
</html>

