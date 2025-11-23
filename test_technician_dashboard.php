<?php
/**
 * Technician Dashboard Debug Test
 * 
 * This test file diagnoses the redirect/refresh loop issue in the technician dashboard.
 * It checks:
 * 1. Session state and persistence
 * 2. Authentication flow and token validation
 * 3. Middleware behavior
 * 4. Redirect loops caused by _auth parameter
 * 5. Cookie and localStorage handling
 * 6. URL parameter propagation
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Enable detailed error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/technician_dashboard_test.log');

// Color output helpers
function print_header($text) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "  " . $text . "\n";
    echo str_repeat("=", 80) . "\n";
}

function print_success($text) {
    echo "✓ SUCCESS: $text\n";
}

function print_error($text) {
    echo "✗ ERROR: $text\n";
}

function print_warning($text) {
    echo "⚠ WARNING: $text\n";
}

function print_info($text) {
    echo "ℹ INFO: $text\n";
}

print_header("TECHNICIAN DASHBOARD REDIRECT LOOP DIAGNOSTIC TEST");

// Test 1: Session State Analysis
print_header("Test 1: Session State Analysis");

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    print_success("Session started successfully");
    print_info("Session ID: " . session_id());
} else {
    print_warning("Session already active: " . session_id());
}

// Check session data
if (isset($_SESSION['user'])) {
    print_success("User session data exists");
    print_info("User ID: " . ($_SESSION['user']['id'] ?? 'NULL'));
    print_info("Username: " . ($_SESSION['user']['username'] ?? 'NULL'));
    print_info("Role: " . ($_SESSION['user']['role'] ?? 'NULL'));
    print_info("Company ID: " . ($_SESSION['user']['company_id'] ?? 'NULL'));
} else {
    print_warning("No user session data found - This will cause redirect to login");
}

// Check for redirect-causing flags
if (isset($_SESSION['_auth_attempt'])) {
    print_error("Auth attempt flag set: " . $_SESSION['_auth_attempt']);
    print_warning("This flag can cause redirect loops");
}

if (isset($_SESSION['_auth_retry_count'])) {
    print_error("Auth retry count set: " . $_SESSION['_auth_retry_count']);
    print_warning("Multiple retries detected - indicates redirect loop");
}

// Check session timeout tracking
if (isset($_SESSION['last_activity'])) {
    $lastActivity = $_SESSION['last_activity'];
    $timeSinceActivity = time() - $lastActivity;
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : (30 * 60);
    
    print_info("Last activity: " . date('Y-m-d H:i:s', $lastActivity));
    print_info("Time since activity: {$timeSinceActivity} seconds");
    print_info("Session timeout: {$timeout} seconds");
    
    if ($timeSinceActivity > $timeout) {
        print_error("Session has expired due to inactivity");
    } else {
        print_success("Session is still active");
    }
} else {
    print_warning("No last_activity tracking found");
}

// Test 2: Token Validation
print_header("Test 2: Token Validation");

$tokenFound = false;
$token = null;

// Check session token
if (isset($_SESSION['token'])) {
    $token = $_SESSION['token'];
    print_success("Token found in session");
    $tokenFound = true;
} else {
    print_warning("No token in session");
}

// Simulate cookie check
if (isset($_COOKIE['sellapp_token'])) {
    print_info("Token found in sellapp_token cookie");
    if (!$tokenFound) {
        $token = $_COOKIE['sellapp_token'];
        $tokenFound = true;
    }
} else {
    print_info("No sellapp_token cookie");
}

if (isset($_COOKIE['token'])) {
    print_info("Token found in token cookie");
    if (!$tokenFound) {
        $token = $_COOKIE['token'];
        $tokenFound = true;
    }
} else {
    print_info("No token cookie");
}

// If token found, try to validate it
if ($tokenFound && $token) {
    try {
        $auth = new \App\Services\AuthService();
        $payload = $auth->validateToken($token);
        print_success("Token is valid");
        print_info("Token user ID: " . ($payload->sub ?? 'NULL'));
        print_info("Token role: " . ($payload->role ?? 'NULL'));
        print_info("Token company ID: " . ($payload->company_id ?? 'NULL'));
    } catch (\Exception $e) {
        print_error("Token validation failed: " . $e->getMessage());
        print_warning("Invalid token will cause redirect to login");
    }
} else {
    print_error("No token found - will redirect to login");
}

// Test 3: URL Parameter Analysis
print_header("Test 3: URL Parameter Analysis (Simulated)");

// Simulate different URL scenarios
$testUrls = [
    '/technician/dashboard',
    '/technician/dashboard?_auth=1',
    '/technician/dashboard?_auth=1&redirect=%2Ftechnician%2Fdashboard',
    '/technician/dashboard?_auth=2',
    '/technician/repairs',
];

foreach ($testUrls as $url) {
    echo "\nAnalyzing URL: $url\n";
    
    $parsedUrl = parse_url($url);
    $path = $parsedUrl['path'] ?? '';
    $query = $parsedUrl['query'] ?? '';
    
    if (!empty($query)) {
        parse_str($query, $params);
        
        if (isset($params['_auth'])) {
            print_error("  _auth parameter present: " . $params['_auth']);
            print_warning("  This parameter can cause infinite redirects if not cleaned up");
        }
        
        if (isset($params['redirect'])) {
            print_info("  redirect parameter: " . $params['redirect']);
        }
    } else {
        print_success("  No problematic parameters");
    }
}

// Test 4: Middleware Behavior Simulation
print_header("Test 4: Middleware Behavior Simulation");

print_info("Simulating WebAuthMiddleware::handle() call...");

// Check what would happen when middleware runs
$hasSession = isset($_SESSION['user']) && !empty($_SESSION['user']);
$hasToken = isset($_SESSION['token']) || isset($_COOKIE['sellapp_token']) || isset($_COOKIE['token']);

if ($hasSession) {
    print_success("User session exists - middleware should PASS");
    print_info("Expected: Page loads normally");
} elseif ($hasToken) {
    print_warning("No session but token exists - middleware will attempt validation");
    print_info("Expected: Validate token, create session, then load page");
} else {
    print_error("No session and no token - middleware will REDIRECT");
    print_info("Expected: Redirect to login page");
}

// Test 5: Redirect Loop Detection
print_header("Test 5: Redirect Loop Detection");

// Simulate multiple rapid requests (what happens in a redirect loop)
$_SESSION['_test_request_count'] = ($_SESSION['_test_request_count'] ?? 0) + 1;
$requestCount = $_SESSION['_test_request_count'];

print_info("Request count in this session: $requestCount");

if ($requestCount > 5) {
    print_error("HIGH REQUEST COUNT DETECTED - POSSIBLE REDIRECT LOOP");
    print_warning("The page is being requested multiple times rapidly");
} else {
    print_success("Request count is normal");
}

// Test 6: JavaScript Token Validation Flow
print_header("Test 6: JavaScript Token Validation Flow Analysis");

print_info("Analyzing client-side authentication flow...");

$issues = [];

// Check if redirectToLogin() would be called
if (!$hasSession && !$hasToken) {
    $issues[] = "No authentication - redirectToLogin() will be called";
}

// Check if the JavaScript validation would trigger
if (!$hasSession && $hasToken) {
    $issues[] = "JavaScript will attempt to validate localStorage token";
    $issues[] = "POST to /api/auth/validate-local-token will be made";
    $issues[] = "If validation succeeds, page will reload with session";
    $issues[] = "If _auth parameter exists, it could cause another redirect";
}

if (empty($issues)) {
    print_success("No client-side authentication issues detected");
} else {
    print_warning("Client-side authentication flow will execute:");
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
}

// Test 7: Session Cookie Configuration
print_header("Test 7: Session Cookie Configuration");

$cookieParams = session_get_cookie_params();
print_info("Session cookie lifetime: " . $cookieParams['lifetime'] . " seconds");
print_info("Session cookie path: " . $cookieParams['path']);
print_info("Session cookie domain: " . ($cookieParams['domain'] ?: 'default'));
print_info("Session cookie secure: " . ($cookieParams['secure'] ? 'Yes' : 'No'));
print_info("Session cookie httponly: " . ($cookieParams['httponly'] ? 'Yes' : 'No'));

if ($cookieParams['lifetime'] == 0) {
    print_warning("Session cookie expires when browser closes");
} else {
    print_info("Session cookie persists for " . ($cookieParams['lifetime'] / 3600) . " hours");
}

// Test 8: Root Cause Analysis
print_header("Test 8: Root Cause Analysis - Why Redirects Keep Happening");

$rootCauses = [];

// Reason 1: _auth parameter not being cleaned up
print_info("\nChecking for _auth parameter propagation...");
if (isset($_GET['_auth'])) {
    $rootCauses[] = [
        'issue' => '_auth parameter in URL',
        'description' => 'The _auth parameter is present in the URL and may be causing redirects',
        'solution' => 'JavaScript should clean up _auth parameter using history.replaceState()',
        'severity' => 'HIGH'
    ];
}

// Reason 2: Session not persisting between requests
print_info("\nChecking session persistence...");
if (!$hasSession && $hasToken) {
    $rootCauses[] = [
        'issue' => 'Session not persisting',
        'description' => 'Token exists but session is not persisting between requests',
        'solution' => 'Check session.save_path, session.cookie_* settings, and /api/auth/validate-local-token',
        'severity' => 'CRITICAL'
    ];
}

// Reason 3: Multiple redirects in middleware
print_info("\nChecking middleware redirect logic...");
if (!$hasSession && !$hasToken) {
    $rootCauses[] = [
        'issue' => 'Multiple redirect points',
        'description' => 'Middleware has multiple redirect points that may stack',
        'solution' => 'Consolidate redirect logic and ensure only one redirect happens',
        'severity' => 'MEDIUM'
    ];
}

// Reason 4: Server-side redirects conflicting with client-side redirects
print_info("\nChecking redirect conflict potential...");
$rootCauses[] = [
    'issue' => 'Server vs Client redirect conflict',
    'description' => 'Server may redirect while JavaScript also tries to redirect',
    'solution' => 'Use only client-side redirects in WebAuthMiddleware redirectToLogin()',
    'severity' => 'MEDIUM'
];

// Reason 5: Token validation endpoint creating new session but not returning properly
print_info("\nChecking token validation endpoint behavior...");
$rootCauses[] = [
    'issue' => 'Token validation may not set session cookie',
    'description' => '/api/auth/validate-local-token may validate but not persist session',
    'solution' => 'Ensure validate-local-token endpoint sets session and returns Set-Cookie header',
    'severity' => 'HIGH'
];

// Display root causes
if (!empty($rootCauses)) {
    print_error("\nPOTENTIAL ROOT CAUSES IDENTIFIED:");
    foreach ($rootCauses as $idx => $cause) {
        echo "\n" . ($idx + 1) . ". {$cause['issue']} [Severity: {$cause['severity']}]\n";
        echo "   Description: {$cause['description']}\n";
        echo "   Solution: {$cause['solution']}\n";
    }
} else {
    print_success("No obvious root causes detected");
}

// Test 9: Recommended Actions
print_header("Test 9: Recommended Actions to Fix Redirect Loop");

$actions = [
    "1. IMMEDIATE: Check if session is persisting between requests",
    "   - Run this test twice and check if session ID remains the same",
    "   - Check PHP session.save_path is writable",
    "   - Verify session files are being created",
    "",
    "2. VERIFY: Token validation endpoint (/api/auth/validate-local-token)",
    "   - Ensure it creates a session when token is valid",
    "   - Ensure it returns proper Set-Cookie header",
    "   - Ensure it saves session before returning response",
    "",
    "3. CLEANUP: Remove _auth parameter from URL",
    "   - The JavaScript in simple_layout.php should clean this up",
    "   - Use history.replaceState() instead of window.location redirects",
    "   - Verify this cleanup is happening in browser console",
    "",
    "4. SIMPLIFY: Reduce redirect points in WebAuthMiddleware",
    "   - Line 44-45: Don't redirect if _auth exists and user is authenticated",
    "   - Line 73: Ensure this is the ONLY redirect to login",
    "   - Line 109: Token validation failure should clear session and redirect once",
    "",
    "5. DEBUG: Add logging to track redirect count",
    "   - Add a counter in session to track how many times user hits dashboard",
    "   - If counter > 3 in quick succession, stop redirects and show error",
    "",
    "6. BROWSER TEST: Check localStorage token",
    "   - Open browser console on technician dashboard",
    "   - Run: localStorage.getItem('sellapp_token')",
    "   - Verify token exists and is not expired",
    "",
    "7. NETWORK TEST: Monitor redirect chain",
    "   - Open browser Network tab",
    "   - Navigate to /technician/dashboard",
    "   - Check how many 302/301 redirects occur",
    "   - If > 2 redirects, there's a loop",
];

foreach ($actions as $action) {
    echo "$action\n";
}

// Test 10: Create Test Session
print_header("Test 10: Create Test Technician Session");

print_info("Creating a test technician session for manual testing...");

// Get a technician user from database
try {
    // Get database connection
    require_once __DIR__ . '/config/database.php';
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'technician' LIMIT 1");
    $stmt->execute();
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($technician) {
        // Create session
        $_SESSION['user'] = [
            'id' => $technician['id'],
            'username' => $technician['username'],
            'role' => $technician['role'],
            'company_id' => $technician['company_id'],
            'company_name' => $technician['company_name'] ?? 'Test Company'
        ];
        $_SESSION['last_activity'] = time();
        
        // Create token
        $auth = new \App\Services\AuthService();
        $token = $auth->generateToken(
            $technician['id'],
            $technician['username'],
            $technician['role'],
            $technician['company_id']
        );
        
        $_SESSION['token'] = $token;
        
        print_success("Test session created for technician: " . $technician['username']);
        print_info("User ID: " . $technician['id']);
        print_info("Company ID: " . $technician['company_id']);
        print_info("Token: " . substr($token, 0, 20) . "...");
        
        print_warning("\nYou can now test the dashboard by navigating to:");
        print_info("  " . BASE_URL_PATH . "/dashboard");
        print_warning("\nIf redirect loop still occurs, check the following:");
        print_info("  1. Browser console for JavaScript errors");
        print_info("  2. Network tab for redirect chain");
        print_info("  3. Application tab -> Cookies for session cookie");
        print_info("  4. Application tab -> Local Storage for sellapp_token");
        
    } else {
        print_error("No technician user found in database");
        print_info("Please create a technician user first");
    }
} catch (\Exception $e) {
    print_error("Failed to create test session: " . $e->getMessage());
}

// Test 11: Session File Check
print_header("Test 11: Session File Persistence Check");

$sessionPath = session_save_path();
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}

print_info("Session save path: $sessionPath");

if (is_writable($sessionPath)) {
    print_success("Session directory is writable");
    
    // Try to list session files
    $sessionId = session_id();
    $sessionFile = $sessionPath . '/sess_' . $sessionId;
    
    if (file_exists($sessionFile)) {
        print_success("Session file exists: sess_$sessionId");
        $fileSize = filesize($sessionFile);
        print_info("Session file size: $fileSize bytes");
        
        if ($fileSize > 0) {
            print_success("Session file has data");
        } else {
            print_warning("Session file is empty - session not saving properly");
        }
    } else {
        print_warning("Session file not found - may not have been saved yet");
    }
} else {
    print_error("Session directory is NOT writable");
    print_warning("This will prevent session persistence!");
    print_info("Fix permissions on: $sessionPath");
}

// Summary
print_header("DIAGNOSTIC SUMMARY");

$criticalIssues = 0;
$warnings = 0;

if (!$hasSession && !$hasToken) {
    print_error("CRITICAL: No authentication data found");
    $criticalIssues++;
}

if (!$hasSession && $hasToken) {
    print_warning("WARNING: Token exists but session not persisting");
    $warnings++;
}

if (isset($_SESSION['_auth_retry_count']) && $_SESSION['_auth_retry_count'] > 2) {
    print_error("CRITICAL: Multiple authentication retries detected - REDIRECT LOOP");
    $criticalIssues++;
}

if (!is_writable($sessionPath)) {
    print_error("CRITICAL: Session directory not writable");
    $criticalIssues++;
}

echo "\nCritical Issues: $criticalIssues\n";
echo "Warnings: $warnings\n";

if ($criticalIssues == 0 && $warnings == 0) {
    print_success("\n✓ No issues detected - dashboard should work normally");
} elseif ($criticalIssues > 0) {
    print_error("\n✗ Critical issues found - dashboard will NOT work");
} else {
    print_warning("\n⚠ Warnings found - dashboard may have issues");
}

print_header("TEST COMPLETE");

// Clean up test counter
unset($_SESSION['_test_request_count']);

echo "\nLog file: " . __DIR__ . '/storage/logs/technician_dashboard_test.log' . "\n";
echo "Session ID: " . session_id() . "\n";
echo "\n";

