<?php
/**
 * Test page that mimics the exact dashboard flow
 * This will help identify where the refresh is coming from
 */

// Load everything like the real dashboard does
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

// Use the WebAuthMiddleware exactly like the dashboard route does
\App\Middleware\WebAuthMiddleware::handle(['system_admin', 'admin', 'manager', 'salesperson', 'technician']);

// Get user from session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userData = $_SESSION['user'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mimic Test</title>
    <script>
        window.APP_BASE_PATH = '<?php echo BASE_URL_PATH; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        // Track page loads
        let loadCount = parseInt(sessionStorage.getItem('mimicLoadCount') || '0');
        loadCount++;
        sessionStorage.setItem('mimicLoadCount', loadCount.toString());
        
        console.log('=== PAGE LOAD #' + loadCount + ' ===');
        console.log('Current URL:', window.location.href);
        console.log('Has _auth param:', window.location.search.includes('_auth'));
        
        // Monitor for any redirects
        let redirectCount = 0;
        const originalLocation = window.location.href;
        
        // Override location methods to catch redirects
        const originalReplace = window.location.replace;
        window.location.replace = function(url) {
            redirectCount++;
            console.error('🔄 REDIRECT DETECTED #' + redirectCount + ':', url);
            console.trace('Redirect called from:');
            return originalReplace.call(window.location, url);
        };
        
        const originalHrefSetter = Object.getOwnPropertyDescriptor(window.location, 'href').set;
        Object.defineProperty(window.location, 'href', {
            set: function(url) {
                redirectCount++;
                console.error('🔄 REDIRECT DETECTED (via href) #' + redirectCount + ':', url);
                console.trace('Redirect called from:');
                return originalHrefSetter.call(window.location, url);
            },
            get: function() {
                return window.location.toString();
            }
        });
        
        // Log beforeunload
        window.addEventListener('beforeunload', function() {
            console.error('⚠️ PAGE UNLOADING - Redirect count:', redirectCount);
        });
        
        // Check every 500ms
        setInterval(() => {
            if (window.location.href !== originalLocation) {
                console.error('URL CHANGED:', window.location.href);
            }
        }, 500);
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .status {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            border: 2px solid #4CAF50;
        }
        .error {
            border-color: #f44336;
            background: #ffebee;
        }
        .warning {
            border-color: #ff9800;
            background: #fff3e0;
        }
        h1 { color: #333; }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .counter {
            font-size: 48px;
            font-weight: bold;
            color: #4CAF50;
        }
        #refreshWarning {
            display: none;
            background: #f44336;
            color: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🧪 Dashboard Flow Mimic Test</h1>
    <p>This page mimics the exact dashboard authentication flow.</p>
    
    <div class="status" id="mainStatus">
        <h2>✅ Page Loaded Successfully</h2>
        <p><strong>Load Count:</strong> <span class="counter" id="loadCount">0</span></p>
        <p><strong>Time:</strong> <?= date('H:i:s') ?></p>
    </div>
    
    <div id="refreshWarning">
        ⚠️ WARNING: Page is refreshing automatically! Load count is increasing!
    </div>
    
    <div class="status">
        <h2>📊 Authentication Status</h2>
        <?php if ($userData): ?>
            <p>✅ <strong>User Authenticated:</strong> <?= htmlspecialchars($userData['username']) ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars($userData['role']) ?></p>
            <p><strong>Company ID:</strong> <?= htmlspecialchars($userData['company_id']) ?></p>
        <?php else: ?>
            <p class="error">❌ No user data in session!</p>
        <?php endif; ?>
    </div>
    
    <div class="status">
        <h2>🔗 URL Information</h2>
        <p><strong>Current URL:</strong> <code id="currentUrl">Loading...</code></p>
        <p><strong>Has _auth param:</strong> <span id="hasAuthParam">Loading...</span></p>
        <p><strong>Base Path:</strong> <code><?= BASE_URL_PATH ?></code></p>
    </div>
    
    <div class="status">
        <h2>📝 Console Monitor</h2>
        <p>Open browser console (F12) to see real-time logs of any redirects.</p>
        <p id="redirectInfo">No redirects detected yet...</p>
    </div>
    
    <div class="status">
        <h2>🎯 Expected Behavior</h2>
        <ul>
            <li>✅ Load count should stay at 1</li>
            <li>✅ No redirects should occur</li>
            <li>✅ Page should remain stable</li>
            <li>❌ If load count increases, there's a refresh loop</li>
        </ul>
    </div>
    
    <script>
        // Update display
        const loadCount = parseInt(sessionStorage.getItem('mimicLoadCount') || '0');
        document.getElementById('loadCount').textContent = loadCount;
        document.getElementById('currentUrl').textContent = window.location.href;
        document.getElementById('hasAuthParam').textContent = window.location.search.includes('_auth') ? '❌ YES' : '✅ NO';
        
        // Show warning if multiple loads
        if (loadCount > 2) {
            document.getElementById('refreshWarning').style.display = 'block';
            document.getElementById('mainStatus').className = 'status error';
        }
        
        // Update redirect info
        setInterval(() => {
            document.getElementById('redirectInfo').innerHTML = 
                `Redirects detected: <strong>${redirectCount || 0}</strong><br>` +
                `Current URL: <code>${window.location.href}</code>`;
        }, 1000);
        
        console.log('=== TEST PAGE READY ===');
        console.log('If you see multiple "PAGE LOAD" messages, there is a refresh loop.');
        console.log('Check the load count above. It should stay at 1.');
    </script>
</body>
</html>

