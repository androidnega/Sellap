<?php
/**
 * Live Server Technician Dashboard Redirect Fix
 * 
 * This script diagnoses and fixes redirect loops on the LIVE server.
 * It assumes you should already be logged in but are stuck in a redirect loop.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? 'diagnose';
$output = [];

// Function to check authentication state
function checkAuthState() {
    $state = [
        'has_session' => isset($_SESSION['user']) && !empty($_SESSION['user']),
        'has_session_token' => isset($_SESSION['token']),
        'has_cookie_token' => isset($_COOKIE['sellapp_token']) || isset($_COOKIE['token']),
        'session_data' => $_SESSION['user'] ?? null,
        'session_id' => session_id(),
        'cookies' => $_COOKIE
    ];
    return $state;
}

// Handle different actions
switch ($action) {
    case 'check_localStorage':
        // This will be called via AJAX from JavaScript
        header('Content-Type: application/json');
        $token = $_POST['token'] ?? null;
        
        if ($token) {
            try {
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                
                // Token is valid - create session
                $_SESSION['user'] = [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id,
                    'company_name' => $payload->company_name ?? ''
                ];
                $_SESSION['token'] = $token;
                $_SESSION['last_activity'] = time();
                
                // Force session write
                session_write_close();
                session_start();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Session created from localStorage token',
                    'user' => $_SESSION['user']
                ]);
            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid token: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No token provided'
            ]);
        }
        exit;
        
    case 'force_session':
        // Force create a session from any available token
        $token = null;
        
        // Check cookies first
        if (isset($_COOKIE['sellapp_token'])) {
            $token = $_COOKIE['sellapp_token'];
        } elseif (isset($_COOKIE['token'])) {
            $token = $_COOKIE['token'];
        }
        
        if ($token) {
            try {
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                
                // Token is valid - create session
                $_SESSION['user'] = [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id,
                    'company_name' => $payload->company_name ?? ''
                ];
                $_SESSION['token'] = $token;
                $_SESSION['last_activity'] = time();
                
                // Clear any redirect loop flags
                unset($_SESSION['_auth_attempt']);
                unset($_SESSION['_auth_retry_count']);
                unset($_SESSION['_redirect_monitor_count']);
                
                // Force session write
                session_write_close();
                
                $output['success'] = true;
                $output['message'] = 'Session created successfully from cookie token';
                $output['user'] = $_SESSION['user'];
            } catch (\Exception $e) {
                $output['success'] = false;
                $output['error'] = 'Token validation failed: ' . $e->getMessage();
            }
        } else {
            $output['success'] = false;
            $output['error'] = 'No token found in cookies';
        }
        break;
        
    case 'clear_redirect_flags':
        // Clear any flags that might cause redirect loops
        unset($_SESSION['_auth_attempt']);
        unset($_SESSION['_auth_retry_count']);
        unset($_SESSION['_redirect_monitor_count']);
        unset($_SESSION['_redirect_monitor_start']);
        unset($_SESSION['_testing_dashboard']);
        unset($_SESSION['_redirect_monitor_test_start']);
        
        $output['success'] = true;
        $output['message'] = 'Redirect flags cleared';
        break;
        
    case 'diagnose':
    default:
        $output = checkAuthState();
        break;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Technician Dashboard Redirect - Live Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }
        .code-block {
            background: #1f2937;
            color: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
        }
    </style>
    <script>
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        // Check localStorage and attempt to restore session
        async function checkAndRestoreSession() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            const logDiv = document.getElementById('log-output');
            
            function log(message, type = 'info') {
                const colors = {
                    info: 'text-blue-600',
                    success: 'text-green-600',
                    error: 'text-red-600',
                    warning: 'text-yellow-600'
                };
                const icons = {
                    info: 'fa-info-circle',
                    success: 'fa-check-circle',
                    error: 'fa-times-circle',
                    warning: 'fa-exclamation-triangle'
                };
                
                const entry = document.createElement('div');
                entry.className = `flex items-start mb-2 ${colors[type]}`;
                entry.innerHTML = `
                    <i class="fas ${icons[type]} mt-1 mr-2"></i>
                    <span>${message}</span>
                `;
                logDiv.appendChild(entry);
            }
            
            log('Checking localStorage for authentication token...', 'info');
            
            if (token) {
                log('Token found in localStorage: ' + token.substring(0, 30) + '...', 'success');
                log('Attempting to restore session from token...', 'info');
                
                try {
                    const response = await fetch(BASE + '/fix_technician_redirect_live.php?action=check_localStorage', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'token=' + encodeURIComponent(token)
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        log('✓ Session restored successfully!', 'success');
                        log('User: ' + data.user.username + ' (Role: ' + data.user.role + ')', 'success');
                        log('You can now access the technician dashboard', 'success');
                        
                        // Update status display
                        document.getElementById('session-status').innerHTML = 
                            '<i class="fas fa-check-circle status-ok mr-2"></i>Session Active';
                        document.getElementById('token-status').innerHTML = 
                            '<i class="fas fa-check-circle status-ok mr-2"></i>Token Valid';
                        
                        // Enable dashboard button
                        document.getElementById('go-dashboard-btn').classList.remove('opacity-50', 'cursor-not-allowed');
                        document.getElementById('go-dashboard-btn').classList.add('hover:bg-green-700');
                        document.getElementById('go-dashboard-btn').disabled = false;
                    } else {
                        log('✗ Failed to restore session: ' + data.error, 'error');
                        log('Token may be expired or invalid', 'warning');
                        log('You need to login again', 'warning');
                    }
                } catch (error) {
                    log('✗ Error restoring session: ' + error.message, 'error');
                }
            } else {
                log('✗ No token found in localStorage', 'error');
                log('You need to login first', 'warning');
                
                // Show login button
                document.getElementById('login-required').classList.remove('hidden');
            }
        }
        
        // Force create session from cookie token
        async function forceSessionFromCookie() {
            const logDiv = document.getElementById('log-output');
            logDiv.innerHTML = '';
            
            function log(message, type = 'info') {
                const colors = {
                    info: 'text-blue-600',
                    success: 'text-green-600',
                    error: 'text-red-600',
                    warning: 'text-yellow-600'
                };
                const icons = {
                    info: 'fa-info-circle',
                    success: 'fa-check-circle',
                    error: 'fa-times-circle',
                    warning: 'fa-exclamation-triangle'
                };
                
                const entry = document.createElement('div');
                entry.className = `flex items-start mb-2 ${colors[type]}`;
                entry.innerHTML = `
                    <i class="fas ${icons[type]} mt-1 mr-2"></i>
                    <span>${message}</span>
                `;
                logDiv.appendChild(entry);
            }
            
            log('Attempting to force session creation from cookie token...', 'info');
            
            try {
                const response = await fetch(BASE + '/fix_technician_redirect_live.php?action=force_session');
                const data = await response.json();
                
                if (data.success) {
                    log('✓ Session created successfully!', 'success');
                    log('User: ' + data.user.username + ' (Role: ' + data.user.role + ')', 'success');
                    
                    // Reload page to refresh session data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    log('✗ Failed: ' + data.error, 'error');
                }
            } catch (error) {
                log('✗ Error: ' + error.message, 'error');
            }
        }
        
        // Clear redirect flags
        async function clearFlags() {
            const logDiv = document.getElementById('log-output');
            logDiv.innerHTML = '';
            
            function log(message, type = 'info') {
                const colors = {
                    info: 'text-blue-600',
                    success: 'text-green-600',
                    error: 'text-red-600',
                    warning: 'text-yellow-600'
                };
                const icons = {
                    info: 'fa-info-circle',
                    success: 'fa-check-circle',
                    error: 'fa-times-circle',
                    warning: 'fa-exclamation-triangle'
                };
                
                const entry = document.createElement('div');
                entry.className = `flex items-start mb-2 ${colors[type]}`;
                entry.innerHTML = `
                    <i class="fas ${icons[type]} mt-1 mr-2"></i>
                    <span>${message}</span>
                `;
                logDiv.appendChild(entry);
            }
            
            log('Clearing redirect loop flags...', 'info');
            
            try {
                const response = await fetch(BASE + '/fix_technician_redirect_live.php?action=clear_redirect_flags');
                const data = await response.json();
                
                if (data.success) {
                    log('✓ Flags cleared successfully', 'success');
                }
            } catch (error) {
                log('✗ Error: ' + error.message, 'error');
            }
        }
        
        // Go to dashboard
        function goToDashboard() {
            // Clear any _auth parameters that might exist
            window.location.href = BASE + '/technician/dashboard';
        }
        
        // Auto-run on page load
        document.addEventListener('DOMContentLoaded', () => {
            checkAndRestoreSession();
        });
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-wrench text-blue-600 mr-2"></i>
                Fix Technician Dashboard Redirect
            </h1>
            <p class="text-gray-600">Live Server - Automatic Session Restoration</p>
        </div>
        
        <!-- Current Status -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <span class="font-medium">Session:</span>
                    <span id="session-status">
                        <?php if ($output['has_session'] ?? false): ?>
                            <i class="fas fa-check-circle status-ok mr-2"></i>Active
                        <?php else: ?>
                            <i class="fas fa-times-circle status-error mr-2"></i>Not Active
                        <?php endif; ?>
                    </span>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <span class="font-medium">Token:</span>
                    <span id="token-status">
                        <?php if (($output['has_session_token'] ?? false) || ($output['has_cookie_token'] ?? false)): ?>
                            <i class="fas fa-check-circle status-ok mr-2"></i>Found
                        <?php else: ?>
                            <i class="fas fa-times-circle status-error mr-2"></i>Missing
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <?php if (isset($output['session_data']) && !empty($output['session_data'])): ?>
                <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <h3 class="font-semibold text-green-900 mb-2">✓ Logged in as:</h3>
                    <div class="text-sm text-green-800">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($output['session_data']['username']); ?></p>
                        <p><strong>Role:</strong> <?php echo htmlspecialchars($output['session_data']['role']); ?></p>
                        <p><strong>Company ID:</strong> <?php echo htmlspecialchars($output['session_data']['company_id']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Auto-Fix Log -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-terminal text-blue-600 mr-2"></i>
                Auto-Fix Log
            </h2>
            <div id="log-output" class="space-y-2 min-h-[100px]">
                <div class="flex items-center text-gray-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    <span>Initializing...</span>
                </div>
            </div>
        </div>
        
        <!-- Manual Actions -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Manual Actions</h2>
            <div class="space-y-3">
                <button onclick="forceSessionFromCookie()" 
                        class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                    <i class="fas fa-magic mr-2"></i>
                    Force Create Session from Cookie
                </button>
                
                <button onclick="clearFlags()" 
                        class="w-full px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition flex items-center justify-center">
                    <i class="fas fa-broom mr-2"></i>
                    Clear Redirect Loop Flags
                </button>
                
                <button id="go-dashboard-btn" 
                        onclick="goToDashboard()" 
                        class="w-full px-6 py-3 bg-green-600 text-white rounded-lg transition flex items-center justify-center <?php echo ($output['has_session'] ?? false) ? 'hover:bg-green-700' : 'opacity-50 cursor-not-allowed'; ?>"
                        <?php echo ($output['has_session'] ?? false) ? '' : 'disabled'; ?>>
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Go to Technician Dashboard
                </button>
                
                <div id="login-required" class="hidden p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-800 mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        No authentication token found. You need to login first.
                    </p>
                    <a href="<?php echo BASE_URL_PATH; ?>/" 
                       class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Go to Login Page
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Debug Information -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-bug text-red-600 mr-2"></i>
                Debug Information
            </h2>
            <div class="code-block">
                <pre><?php echo json_encode($output, JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
        
        <!-- Instructions -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="font-semibold text-blue-900 mb-3">
                <i class="fas fa-lightbulb mr-2"></i>
                How This Works
            </h3>
            <ol class="list-decimal list-inside space-y-2 text-blue-800 text-sm">
                <li>This page automatically checks for authentication tokens in localStorage</li>
                <li>If found, it validates the token and creates a PHP session</li>
                <li>Once session is created, you can access the technician dashboard</li>
                <li>If automatic restoration fails, use the manual "Force Create Session" button</li>
                <li>If you're stuck in a redirect loop, clear the redirect flags</li>
            </ol>
        </div>
    </div>
</body>
</html>
<?php
// Output as JSON if requested
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
    exit;
}
?>

