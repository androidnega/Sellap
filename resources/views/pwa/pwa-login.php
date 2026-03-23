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
<html lang="en" class="h-full bg-white text-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>SellApp Scanner — Sign in</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBase . '/pwa/manifest.webmanifest', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full min-h-[100dvh] flex flex-col items-center justify-center p-6 bg-white">
    <div class="w-full max-w-sm space-y-8">
        <div class="text-center space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">SellApp Scanner</h1>
            <p class="text-sm text-gray-600">Sign in with your username or email</p>
        </div>
        <form id="loginForm" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Username or email</label>
                <input type="text" name="username" autocomplete="username" required
                    class="w-full rounded-xl bg-white border border-gray-200 px-4 py-3 text-base text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Password</label>
                <input type="password" name="password" autocomplete="current-password" required
                    class="w-full rounded-xl bg-white border border-gray-200 px-4 py-3 text-base text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-400 outline-none">
            </div>
            <p id="err" class="text-sm text-red-600 hidden"></p>
            <button type="submit" id="btn"
                class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3.5 text-base active:scale-[0.99] transition">
                Continue
            </button>
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
