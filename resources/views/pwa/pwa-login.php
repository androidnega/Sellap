<?php
/**
 * PWA login — email/username + password, JWT in localStorage.
 */
if (!defined('BASE_URL_PATH')) {
    http_response_code(500);
    exit('Configuration error');
}
$base = rtrim(BASE_URL_PATH, '/');
$assetBase = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
?><!DOCTYPE html>
<html lang="en" class="pwa-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>SellApp Scanner — Sign in</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBase . '/api/pwa/manifest.webmanifest', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/assets/css/pwa.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="pwa-body pwa-body--login">
    <div class="pwa-login">
        <div class="pwa-login__brand">
            <h1>SellApp Scanner</h1>
            <p>Sign in with your username or email</p>
        </div>
        <form id="loginForm" class="pwa-form">
            <div class="pwa-field">
                <label for="pwa-username">Username or email</label>
                <input type="text" id="pwa-username" name="username" autocomplete="username" required class="pwa-input">
            </div>
            <div class="pwa-field">
                <label for="pwa-password">Password</label>
                <input type="password" id="pwa-password" name="password" autocomplete="current-password" required class="pwa-input">
            </div>
            <p id="err" class="pwa-error hidden" role="alert"></p>
            <button type="submit" id="btn" class="pwa-btn pwa-btn--primary">Continue</button>
        </form>
    </div>
    <script>
    const BASE = <?php echo json_encode($base, JSON_UNESCAPED_SLASHES); ?>;
    const api = (p) => (BASE ? BASE + '/' : '') + p.replace(/^\//, '');
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = document.getElementById('err');
        const btn = document.getElementById('btn');
        err.classList.add('hidden');
        btn.disabled = true;
        const fd = new FormData(e.target);
        const username = (fd.get('username') || '').toString().trim();
        const password = (fd.get('password') || '').toString();
        try {
            const res = await fetch(api('/api/auth/login'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const j = await res.json();
            if (!j.success || !j.data || !j.data.token) {
                throw new Error(j.error || 'Login failed');
            }
            const u = j.data.user;
            if (!u.company_id) {
                throw new Error('This app requires a company account. Use the full dashboard.');
            }
            if (u.role === 'technician') {
                throw new Error('Scanner app is for sales staff and managers only.');
            }
            localStorage.setItem('sellapp_token', j.data.token);
            localStorage.setItem('sellapp_user', JSON.stringify(u));
            window.location.href = api('/pwa-scan');
        } catch (x) {
            err.textContent = x.message || 'Sign in failed';
            err.classList.remove('hidden');
        } finally {
            btn.disabled = false;
        }
    });
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(api('/sw.js')).catch(() => {});
    }
    </script>
</body>
</html>
