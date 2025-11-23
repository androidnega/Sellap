<?php
/**
 * Dashboard Debug Test Page
 * This page helps diagnose the refresh loop issue
 */

// Start output buffering to capture everything
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config
require_once __DIR__ . '/config/app.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Debug Test</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #00ff00;
        }
        .section {
            background: #2d2d2d;
            border: 2px solid #00ff00;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .section h2 {
            color: #00ffff;
            margin-top: 0;
        }
        .key {
            color: #ffff00;
            font-weight: bold;
        }
        .value {
            color: #00ff00;
        }
        .error {
            color: #ff0000;
            font-weight: bold;
        }
        .success {
            color: #00ff00;
            font-weight: bold;
        }
        .warning {
            color: #ff9900;
            font-weight: bold;
        }
        pre {
            background: #000;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        #console-log {
            max-height: 300px;
            overflow-y: auto;
            background: #000;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #00ff00;
        }
        .log-entry {
            margin: 5px 0;
            padding: 3px;
            border-bottom: 1px solid #333;
        }
    </style>
</head>
<body>
    <h1>🔍 Dashboard Debug Test</h1>
    <p>Current Time: <?= date('Y-m-d H:i:s') ?></p>

    <!-- SESSION INFO -->
    <div class="section">
        <h2>📦 Session Information</h2>
        <p><span class="key">Session Status:</span> <span class="value"><?= session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE ✓' : 'INACTIVE ✗' ?></span></p>
        <p><span class="key">Session ID:</span> <span class="value"><?= session_id() ?></span></p>
        <p><span class="key">Session Save Path:</span> <span class="value"><?= session_save_path() ?></span></p>
        
        <h3>User Data in Session:</h3>
        <?php if (isset($_SESSION['user'])): ?>
            <pre><?= htmlspecialchars(json_encode($_SESSION['user'], JSON_PRETTY_PRINT)) ?></pre>
            <p class="success">✓ User data exists in session</p>
        <?php else: ?>
            <p class="error">✗ No user data in session!</p>
        <?php endif; ?>

        <h3>Token in Session:</h3>
        <?php if (isset($_SESSION['token'])): ?>
            <p class="success">✓ Token exists: <?= substr($_SESSION['token'], 0, 20) ?>...</p>
        <?php else: ?>
            <p class="error">✗ No token in session!</p>
        <?php endif; ?>

        <h3>All Session Data:</h3>
        <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
    </div>

    <!-- URL INFO -->
    <div class="section">
        <h2>🔗 URL Information</h2>
        <p><span class="key">Request URI:</span> <span class="value"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></span></p>
        <p><span class="key">Query String:</span> <span class="value"><?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'N/A') ?></span></p>
        <p><span class="key">Base URL Path:</span> <span class="value"><?= BASE_URL_PATH ?></span></p>
        
        <h3>GET Parameters:</h3>
        <?php if (!empty($_GET)): ?>
            <pre><?= htmlspecialchars(json_encode($_GET, JSON_PRETTY_PRINT)) ?></pre>
            <?php if (isset($_GET['_auth'])): ?>
                <p class="warning">⚠️ _auth parameter detected: <?= htmlspecialchars($_GET['_auth']) ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p>No GET parameters</p>
        <?php endif; ?>
    </div>

    <!-- LOCALSTORAGE TEST -->
    <div class="section">
        <h2>💾 LocalStorage Information</h2>
        <p id="localStorage-info">Loading...</p>
        <pre id="localStorage-data"></pre>
    </div>

    <!-- BROWSER INFO -->
    <div class="section">
        <h2>🌐 Browser Information</h2>
        <p><span class="key">User Agent:</span> <span class="value"><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') ?></span></p>
        <p><span class="key">Remote Address:</span> <span class="value"><?= $_SERVER['REMOTE_ADDR'] ?? 'N/A' ?></span></p>
        <p><span class="key">HTTP Host:</span> <span class="value"><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A') ?></span></p>
        <p><span class="key">Server Protocol:</span> <span class="value"><?= $_SERVER['SERVER_PROTOCOL'] ?? 'N/A' ?></span></p>
    </div>

    <!-- CONSOLE LOG -->
    <div class="section">
        <h2>📝 JavaScript Console Log</h2>
        <p>Real-time JavaScript events will appear below:</p>
        <div id="console-log">
            <div class="log-entry">Waiting for events...</div>
        </div>
    </div>

    <!-- AUTO-REFRESH DETECTOR -->
    <div class="section">
        <h2>🔄 Auto-Refresh Detection</h2>
        <p><span class="key">Page Load Count:</span> <span id="load-count" class="value">0</span></p>
        <p><span class="key">Time Since First Load:</span> <span id="time-elapsed" class="value">0s</span></p>
        <p id="refresh-warning" class="error" style="display: none;">⚠️ WARNING: Page is refreshing automatically!</p>
    </div>

    <script>
        // Base path
        const BASE = '<?= BASE_URL_PATH ?>';
        
        // Console logger
        function logToConsole(message, type = 'info') {
            const consoleLog = document.getElementById('console-log');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'info': '#00ff00',
                'error': '#ff0000',
                'warning': '#ff9900',
                'success': '#00ffff'
            };
            entry.style.color = colors[type] || '#00ff00';
            entry.textContent = `[${timestamp}] ${message}`;
            consoleLog.insertBefore(entry, consoleLog.firstChild);
            
            // Keep only last 20 entries
            while (consoleLog.children.length > 20) {
                consoleLog.removeChild(consoleLog.lastChild);
            }
        }

        // Load localStorage data
        function checkLocalStorage() {
            const localStorageData = {};
            const keys = ['token', 'sellapp_token', 'sellapp_user', 'auth_token'];
            
            keys.forEach(key => {
                const value = localStorage.getItem(key);
                if (value) {
                    localStorageData[key] = value.substring(0, 50) + (value.length > 50 ? '...' : '');
                }
            });

            const infoEl = document.getElementById('localStorage-info');
            const dataEl = document.getElementById('localStorage-data');
            
            if (Object.keys(localStorageData).length > 0) {
                infoEl.innerHTML = '<span class="success">✓ Found ' + Object.keys(localStorageData).length + ' items</span>';
                dataEl.textContent = JSON.stringify(localStorageData, null, 2);
                logToConsole('LocalStorage data loaded', 'success');
            } else {
                infoEl.innerHTML = '<span class="warning">⚠️ No auth-related items in localStorage</span>';
                dataEl.textContent = 'Empty';
                logToConsole('No localStorage data found', 'warning');
            }
        }

        // Track page loads
        let loadCount = parseInt(sessionStorage.getItem('loadCount') || '0');
        let firstLoadTime = parseInt(sessionStorage.getItem('firstLoadTime') || Date.now().toString());
        
        loadCount++;
        sessionStorage.setItem('loadCount', loadCount.toString());
        sessionStorage.setItem('firstLoadTime', firstLoadTime.toString());
        
        document.getElementById('load-count').textContent = loadCount;
        logToConsole(`Page load #${loadCount}`, loadCount > 2 ? 'warning' : 'info');

        // Update elapsed time
        function updateElapsedTime() {
            const elapsed = Math.floor((Date.now() - firstLoadTime) / 1000);
            document.getElementById('time-elapsed').textContent = elapsed + 's';
            
            // Warn if multiple loads in short time
            if (loadCount > 3 && elapsed < 30) {
                document.getElementById('refresh-warning').style.display = 'block';
                logToConsole(`WARNING: ${loadCount} loads in ${elapsed} seconds!`, 'error');
            }
        }
        
        setInterval(updateElapsedTime, 1000);

        // Check for _auth parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('_auth')) {
            logToConsole('⚠️ _auth parameter detected in URL: ' + urlParams.get('_auth'), 'warning');
        }

        // Monitor for redirects
        let originalLocation = window.location.href;
        const locationObserver = setInterval(() => {
            if (window.location.href !== originalLocation) {
                logToConsole('🔄 URL changed: ' + window.location.href, 'warning');
                originalLocation = window.location.href;
            }
        }, 100);

        // Listen for beforeunload (page leaving)
        window.addEventListener('beforeunload', function() {
            logToConsole('🚪 Page is unloading/refreshing', 'error');
        });

        // Check if page is being refreshed by JavaScript
        if (performance.navigation.type === 1) {
            logToConsole('↻ Page was REFRESHED', 'warning');
        } else if (performance.navigation.type === 0) {
            logToConsole('→ Page was NAVIGATED to', 'info');
        }

        // Initialize
        checkLocalStorage();
        updateElapsedTime();
        logToConsole('Debug page initialized', 'success');
        
        // Log URL info
        logToConsole('Current URL: ' + window.location.href, 'info');
        logToConsole('Base path: ' + BASE, 'info');
    </script>
</body>
</html>
<?php
// Output everything
ob_end_flush();
?>

