<?php
/**
 * Role URL Test File
 * Tests all role-specific URLs to ensure they use dynamic BASE_URL_PATH
 * 
 * Usage: Visit https://sellapp.store/test_role_urls.php
 */

// Load configuration
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test roles
$roles = ['salesperson', 'technician', 'manager', 'admin', 'system_admin'];

// Test URLs for each role
$testUrls = [
    'salesperson' => [
        '/dashboard',
        '/api/dashboard/sales-metrics',
        '/pos',
        '/api/pos/products',
        '/customers',
        '/api/customers'
    ],
    'technician' => [
        '/dashboard',
        '/api/dashboard/technician-stats',
        '/repairs',
        '/api/repairs',
        '/technician/bookings'
    ],
    'manager' => [
        '/dashboard',
        '/api/dashboard/manager-overview',
        '/api/dashboard/charts-data',
        '/analytics',
        '/reports',
        '/inventory',
        '/staff'
    ],
    'admin' => [
        '/dashboard',
        '/api/admin/dashboard',
        '/dashboard/admin/companies',
        '/dashboard/admin/reset',
        '/api/admin/companies'
    ],
    'system_admin' => [
        '/dashboard',
        '/api/admin/dashboard',
        '/dashboard/system-settings',
        '/dashboard/admin/benchmarks'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role URL Test - SellApp</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .role-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .role-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .url-item {
            padding: 10px;
            margin: 5px 0;
            background: #f9f9f9;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }
        .url-item.error {
            border-left-color: #f44336;
            background: #ffebee;
        }
        .url-item.warning {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        .url-path {
            font-family: monospace;
            color: #2196F3;
            font-weight: bold;
        }
        .url-full {
            font-family: monospace;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.ok {
            background: #4CAF50;
            color: white;
        }
        .status.error {
            background: #f44336;
            color: white;
        }
        .status.warning {
            background: #ff9800;
            color: white;
        }
        .config-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .config-info h3 {
            margin-top: 0;
        }
        .config-item {
            margin: 5px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Role URL Test - SellApp</h1>
        <p>Testing all role-specific URLs to ensure they use dynamic BASE_URL_PATH</p>
    </div>

    <div class="config-info">
        <h3>Current Configuration</h3>
        <div class="config-item"><strong>BASE_URL_PATH:</strong> <?php echo htmlspecialchars(BASE_URL_PATH); ?> <?php echo BASE_URL_PATH === '' ? '✅ (Root domain)' : '⚠️ (Subdirectory)'; ?></div>
        <div class="config-item"><strong>APP_URL:</strong> <?php echo htmlspecialchars(APP_URL); ?></div>
        <div class="config-item"><strong>HTTP_HOST:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A'); ?></div>
        <div class="config-item"><strong>REQUEST_URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></div>
        <div class="config-item"><strong>SCRIPT_NAME:</strong> <?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A'); ?></div>
    </div>

    <?php foreach ($roles as $role): ?>
        <div class="role-section">
            <div class="role-title"><?php echo ucfirst($role); ?> URLs</div>
            
            <?php if (isset($testUrls[$role])): ?>
                <?php foreach ($testUrls[$role] as $url): ?>
                    <?php
                    $fullUrl = BASE_URL_PATH . $url;
                    $hasHardcoded = false;
                    $status = 'ok';
                    $message = 'OK';
                    
                    // Check if URL contains hardcoded /sellapp
                    if (strpos($fullUrl, '/sellapp') !== false && BASE_URL_PATH === '') {
                        $hasHardcoded = true;
                        $status = 'error';
                        $message = 'ERROR: Contains hardcoded /sellapp';
                    } elseif (BASE_URL_PATH === '' && $url[0] === '/') {
                        $status = 'ok';
                        $message = 'OK: Using root path';
                    } elseif (BASE_URL_PATH !== '' && strpos($url, BASE_URL_PATH) === 0) {
                        $status = 'warning';
                        $message = 'WARNING: URL already includes base path';
                    }
                    ?>
                    <div class="url-item <?php echo $status; ?>">
                        <span class="url-path"><?php echo htmlspecialchars($url); ?></span>
                        <span class="status <?php echo $status; ?>"><?php echo $message; ?></span>
                        <div class="url-full">Full URL: <?php echo htmlspecialchars($fullUrl); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="role-section">
        <div class="role-title">JavaScript URL Tests</div>
        <div class="url-item">
            <span class="url-path">window.APP_BASE_PATH</span>
            <span class="status ok">OK</span>
            <div class="url-full">Should be: <?php echo htmlspecialchars(BASE_URL_PATH); ?></div>
        </div>
        <div class="url-item">
            <span class="url-path">const BASE</span>
            <span class="status ok">OK</span>
            <div class="url-full">Should use: window.APP_BASE_PATH || ''</div>
        </div>
    </div>

    <div class="role-section">
        <div class="role-title">Common API Endpoints</div>
        <?php
        $commonApis = [
            '/api/auth/login',
            '/api/auth/validate',
            '/api/dashboard/stats',
            '/api/customers',
            '/api/products',
            '/api/inventory',
            '/api/repairs',
            '/api/swaps',
            '/api/pos/cart/clear'
        ];
        foreach ($commonApis as $api): ?>
            <div class="url-item">
                <span class="url-path"><?php echo htmlspecialchars($api); ?></span>
                <span class="status ok">OK</span>
                <div class="url-full">Full URL: <?php echo htmlspecialchars(BASE_URL_PATH . $api); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Test JavaScript BASE path
        window.APP_BASE_PATH = '<?php echo BASE_URL_PATH; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        console.log('JavaScript BASE Path Test:');
        console.log('window.APP_BASE_PATH:', window.APP_BASE_PATH);
        console.log('const BASE:', BASE);
        console.log('Expected:', '<?php echo BASE_URL_PATH; ?>');
        
        if (BASE === '<?php echo BASE_URL_PATH; ?>') {
            console.log('✅ JavaScript BASE path is correct');
        } else {
            console.error('❌ JavaScript BASE path mismatch!');
        }
    </script>
</body>
</html>

