<?php
/**
 * Technician Dashboard Redirect Monitor
 * 
 * This file monitors real-time redirect behavior in the browser.
 * Access it via: /test_technician_redirect_monitor.php
 * 
 * It will:
 * 1. Track each page load
 * 2. Show redirect count
 * 3. Display session state
 * 4. Show localStorage token status
 * 5. Monitor URL parameter changes
 * 6. Detect infinite loops and STOP them
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize redirect counter
if (!isset($_SESSION['_redirect_monitor_count'])) {
    $_SESSION['_redirect_monitor_count'] = 0;
    $_SESSION['_redirect_monitor_start'] = time();
}

$_SESSION['_redirect_monitor_count']++;
$redirectCount = $_SESSION['_redirect_monitor_count'];
$timeElapsed = time() - $_SESSION['_redirect_monitor_start'];

// Detect loop (more than 5 redirects in 10 seconds)
$isLoop = ($redirectCount > 5 && $timeElapsed < 10);

// Get request info
$requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? 'none';

// Get session info
$hasSession = isset($_SESSION['user']) && !empty($_SESSION['user']);
$hasToken = isset($_SESSION['token']);
$sessionData = $_SESSION['user'] ?? null;

// Get URL parameters
$urlParams = $_GET;

// Reset button handler
if (isset($_GET['reset_monitor'])) {
    unset($_SESSION['_redirect_monitor_count']);
    unset($_SESSION['_redirect_monitor_start']);
    header('Location: ' . BASE_URL_PATH . '/test_technician_redirect_monitor.php');
    exit;
}

// Test dashboard button handler
if (isset($_GET['test_dashboard'])) {
            // Record that we're testing
            $_SESSION['_testing_dashboard'] = true;
            $_SESSION['_redirect_monitor_test_start'] = time();
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard Redirect Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok { background-color: #10b981; }
        .status-warning { background-color: #f59e0b; }
        .status-error { background-color: #ef4444; }
        
        .code-block {
            background: #1f2937;
            color: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
        }
        
        <?php if ($isLoop): ?>
        @keyframes pulse-red {
            0%, 100% { background-color: #fee2e2; }
            50% { background-color: #fecaca; }
        }
        .loop-detected {
            animation: pulse-red 1s ease-in-out infinite;
        }
        <?php endif; ?>
    </style>
    <script>
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        // Monitor localStorage
        function checkLocalStorage() {
            const tokens = {
                token: localStorage.getItem('token'),
                sellapp_token: localStorage.getItem('sellapp_token'),
                sellapp_user: localStorage.getItem('sellapp_user')
            };
            
            document.getElementById('localStorage-status').innerHTML = 
                Object.entries(tokens)
                    .map(([key, value]) => {
                        const status = value ? 'ok' : 'error';
                        const icon = value ? 'check-circle' : 'times-circle';
                        return `<div class="flex items-center mb-2">
                            <i class="fas fa-${icon} text-${status === 'ok' ? 'green' : 'red'}-500 mr-2"></i>
                            <span class="font-medium">${key}:</span>
                            <span class="ml-2 text-gray-600">${value ? 'Present (' + value.substring(0, 20) + '...)' : 'Not found'}</span>
                        </div>`;
                    }).join('');
        }
        
        // Monitor URL changes
        let urlChangeCount = 0;
        let lastUrl = window.location.href;
        
        function monitorUrlChanges() {
            const currentUrl = window.location.href;
            if (currentUrl !== lastUrl) {
                urlChangeCount++;
                lastUrl = currentUrl;
                
                const logDiv = document.getElementById('url-change-log');
                const entry = document.createElement('div');
                entry.className = 'text-sm text-gray-600 mb-1';
                entry.innerHTML = `<span class="font-medium">${urlChangeCount}.</span> ${new Date().toLocaleTimeString()} - ${currentUrl}`;
                logDiv.insertBefore(entry, logDiv.firstChild);
                
                // Keep only last 10 entries
                while (logDiv.children.length > 10) {
                    logDiv.removeChild(logDiv.lastChild);
                }
            }
        }
        
        // Auto-refresh data every 2 seconds
        setInterval(() => {
            checkLocalStorage();
            monitorUrlChanges();
        }, 2000);
        
        // Initial check
        document.addEventListener('DOMContentLoaded', () => {
            checkLocalStorage();
            monitorUrlChanges();
        });
        
        // Clear monitor
        function clearMonitor() {
            if (confirm('Reset the redirect monitor?')) {
                window.location.href = BASE + '/test_technician_redirect_monitor.php?reset_monitor=1';
            }
        }
        
        // Test dashboard
        function testDashboard() {
            // Store the monitor state
            sessionStorage.setItem('monitor_active', 'true');
            sessionStorage.setItem('monitor_start_time', Date.now());
            
            // Open dashboard in new tab to monitor
            window.open(BASE + '/dashboard', '_blank');
        }
        
        // Check if we came back from dashboard
        if (sessionStorage.getItem('monitor_active') === 'true') {
            const startTime = parseInt(sessionStorage.getItem('monitor_start_time'));
            const elapsed = Date.now() - startTime;
            
            if (elapsed < 5000) {
                // Came back too quickly - likely a redirect loop
                alert('⚠️ REDIRECT LOOP DETECTED!\n\nYou were redirected back in ' + (elapsed/1000).toFixed(2) + ' seconds.\nThis indicates a redirect loop issue.');
            }
            
            sessionStorage.removeItem('monitor_active');
            sessionStorage.removeItem('monitor_start_time');
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                        Technician Dashboard Redirect Monitor
                    </h1>
                    <p class="text-gray-600">Real-time monitoring of redirect behavior and authentication state</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Session ID</div>
                    <div class="font-mono text-xs text-gray-700"><?php echo session_id(); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Loop Detection Alert -->
        <?php if ($isLoop): ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-6 mb-6 rounded-lg loop-detected">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-3xl mr-4"></i>
                <div>
                    <h2 class="text-xl font-bold text-red-900 mb-2">🚨 REDIRECT LOOP DETECTED!</h2>
                    <p class="text-red-700 mb-2">
                        This page has been loaded <strong><?php echo $redirectCount; ?> times</strong> 
                        in <strong><?php echo $timeElapsed; ?> seconds</strong>.
                    </p>
                    <p class="text-red-600 text-sm">
                        This indicates a redirect loop in the technician dashboard. Check the diagnostics below.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Redirect Count -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Redirect Count</h3>
                    <i class="fas fa-redo text-blue-600 text-2xl"></i>
                </div>
                <div class="text-4xl font-bold <?php echo $isLoop ? 'text-red-600' : 'text-blue-600'; ?>">
                    <?php echo $redirectCount; ?>
                </div>
                <div class="text-sm text-gray-500 mt-2">
                    in <?php echo $timeElapsed; ?> seconds
                </div>
            </div>
            
            <!-- Session Status -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Session Status</h3>
                    <i class="fas fa-user-check text-<?php echo $hasSession ? 'green' : 'red'; ?>-600 text-2xl"></i>
                </div>
                <div class="flex items-center">
                    <span class="status-indicator status-<?php echo $hasSession ? 'ok' : 'error'; ?>"></span>
                    <span class="text-lg font-medium">
                        <?php echo $hasSession ? 'Active' : 'No Session'; ?>
                    </span>
                </div>
                <?php if ($hasSession): ?>
                <div class="text-sm text-gray-600 mt-2">
                    Role: <strong><?php echo $sessionData['role'] ?? 'unknown'; ?></strong>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Token Status -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Token Status</h3>
                    <i class="fas fa-key text-<?php echo $hasToken ? 'green' : 'red'; ?>-600 text-2xl"></i>
                </div>
                <div class="flex items-center">
                    <span class="status-indicator status-<?php echo $hasToken ? 'ok' : 'error'; ?>"></span>
                    <span class="text-lg font-medium">
                        <?php echo $hasToken ? 'Present' : 'Missing'; ?>
                    </span>
                </div>
                <?php if ($hasToken): ?>
                <div class="text-sm text-gray-600 mt-2 font-mono overflow-hidden text-ellipsis">
                    <?php echo substr($_SESSION['token'], 0, 30); ?>...
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detailed Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Session Data -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-database text-blue-600 mr-2"></i>
                    Session Data
                </h3>
                <div class="code-block">
                    <?php if ($hasSession): ?>
                        <pre><?php echo json_encode($sessionData, JSON_PRETTY_PRINT); ?></pre>
                    <?php else: ?>
                        <pre>No session data</pre>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- LocalStorage (Client-Side) -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-hdd text-blue-600 mr-2"></i>
                    LocalStorage Status
                </h3>
                <div id="localStorage-status" class="text-sm">
                    <div class="text-gray-500">Loading...</div>
                </div>
            </div>
        </div>
        
        <!-- URL Parameters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-link text-blue-600 mr-2"></i>
                URL Parameters
            </h3>
            <?php if (!empty($urlParams)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($urlParams as $key => $value): ?>
                        <div class="flex items-start">
                            <span class="font-medium text-gray-700 mr-2"><?php echo htmlspecialchars($key); ?>:</span>
                            <span class="text-gray-600"><?php echo htmlspecialchars($value); ?></span>
                            <?php if ($key === '_auth'): ?>
                                <span class="ml-2 px-2 py-1 bg-red-100 text-red-700 text-xs rounded">⚠️ Loop Risk</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No URL parameters</p>
            <?php endif; ?>
        </div>
        
        <!-- Request Information -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Request Information
            </h3>
            <div class="space-y-2 text-sm">
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Request URI:</span>
                    <span class="text-gray-600 font-mono"><?php echo htmlspecialchars($requestUri); ?></span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Method:</span>
                    <span class="text-gray-600"><?php echo htmlspecialchars($requestMethod); ?></span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Referer:</span>
                    <span class="text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($referer); ?></span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Time:</span>
                    <span class="text-gray-600"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- URL Change Log -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-history text-blue-600 mr-2"></i>
                URL Change Log (Last 10)
            </h3>
            <div id="url-change-log" class="space-y-1">
                <div class="text-gray-500 text-sm">No changes detected yet...</div>
            </div>
        </div>
        
        <!-- Diagnostics -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-stethoscope text-blue-600 mr-2"></i>
                Diagnostics
            </h3>
            <div class="space-y-3">
                <?php
                $diagnostics = [];
                
                // Check 1: Session without token
                if ($hasSession && !$hasToken) {
                    $diagnostics[] = [
                        'type' => 'warning',
                        'message' => 'Session exists but no token - may cause issues if token validation is required'
                    ];
                }
                
                // Check 2: Token without session
                if (!$hasSession && $hasToken) {
                    $diagnostics[] = [
                        'type' => 'warning',
                        'message' => 'Token exists but no session - this may cause redirect loops as middleware tries to create session'
                    ];
                }
                
                // Check 3: Neither session nor token
                if (!$hasSession && !$hasToken) {
                    $diagnostics[] = [
                        'type' => 'error',
                        'message' => 'No authentication data found - will redirect to login'
                    ];
                }
                
                // Check 4: _auth parameter present
                if (isset($_GET['_auth'])) {
                    $diagnostics[] = [
                        'type' => 'error',
                        'message' => '_auth parameter detected in URL - this is a primary cause of redirect loops'
                    ];
                }
                
                // Check 5: High redirect count
                if ($redirectCount > 3 && $timeElapsed < 10) {
                    $diagnostics[] = [
                        'type' => 'error',
                        'message' => 'High redirect frequency detected - likely in a redirect loop'
                    ];
                }
                
                // Check 6: Session timeout tracking
                if (isset($_SESSION['last_activity'])) {
                    $lastActivity = $_SESSION['last_activity'];
                    $timeSinceActivity = time() - $lastActivity;
                    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : (30 * 60);
                    
                    if ($timeSinceActivity > $timeout) {
                        $diagnostics[] = [
                            'type' => 'warning',
                            'message' => 'Session has expired due to inactivity'
                        ];
                    }
                }
                
                // Check 7: All OK
                if (empty($diagnostics) && $hasSession && $hasToken) {
                    $diagnostics[] = [
                        'type' => 'success',
                        'message' => 'All checks passed - authentication state is healthy'
                    ];
                }
                
                // Display diagnostics
                foreach ($diagnostics as $diagnostic) {
                    $bgColor = [
                        'success' => 'bg-green-50 border-green-200',
                        'warning' => 'bg-yellow-50 border-yellow-200',
                        'error' => 'bg-red-50 border-red-200'
                    ][$diagnostic['type']];
                    
                    $icon = [
                        'success' => 'fa-check-circle text-green-600',
                        'warning' => 'fa-exclamation-triangle text-yellow-600',
                        'error' => 'fa-times-circle text-red-600'
                    ][$diagnostic['type']];
                    
                    echo "<div class='flex items-center p-3 border rounded {$bgColor}'>";
                    echo "<i class='fas {$icon} mr-3'></i>";
                    echo "<span class='text-sm'>{$diagnostic['message']}</span>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-cog text-blue-600 mr-2"></i>
                Actions
            </h3>
            <div class="flex flex-wrap gap-4">
                <button onclick="clearMonitor()" 
                        class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-redo mr-2"></i>
                    Reset Monitor
                </button>
                
                <button onclick="testDashboard()" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-play mr-2"></i>
                    Test Dashboard (New Tab)
                </button>
                
                <a href="<?php echo BASE_URL_PATH; ?>/dashboard" 
                   class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition inline-block">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Go to Dashboard
                </a>
                
                <a href="<?php echo BASE_URL_PATH; ?>/test_technician_dashboard.php" 
                   class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition inline-block">
                    <i class="fas fa-terminal mr-2"></i>
                    Run CLI Diagnostic
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>This monitor automatically updates every 2 seconds.</p>
            <p class="mt-2">Created for debugging technician dashboard redirect loops.</p>
        </div>
    </div>
</body>
</html>

