<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SellApp | Login</title>
  <!-- Custom Favicon - Overrides XAMPP default favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
  <link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
  <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
  <script>
    // Base path for application URLs (auto-detected)
    window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
    const BASE = window.APP_BASE_PATH || '';
    
    // Check if user is already logged in
    (function() {
      const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
      
      if (token) {
        // Get redirect URL from query params or default to dashboard
        const urlParams = new URLSearchParams(window.location.search);
        const redirectUrl = urlParams.get('redirect') || '/dashboard';
        
        // Validate token and redirect to original URL if valid
        fetch(BASE + '/api/auth/validate', {
          headers: {
            'Authorization': 'Bearer ' + token
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.user) {
            // Valid token, redirect to original URL or dashboard
            window.location.href = BASE + redirectUrl;
          } else {
            // Invalid token, clear storage and continue to login
            localStorage.removeItem('token');
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('sellapp_user');
          }
        })
        .catch(error => {
          console.error('Auth check error:', error);
          // Error occurred, continue to login
        });
      }
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/css/styles.css">
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-gray-50 min-h-screen flex items-center justify-center px-4 py-8">
  <div class="w-full max-w-md">
    <!-- Logo and Branding -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg">
        <span class="text-3xl">📱</span>
      </div>
      <h1 class="text-3xl font-bold text-gray-900 mb-2">SellApp</h1>
      <p class="text-gray-600 text-sm">Multi-Tenant Phone Management System</p>
    </div>
    
    <!-- Login Card -->
    <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
      <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Welcome Back</h2>
      
      <form id="loginForm" class="space-y-5">
        <div>
          <label class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
          <input 
            type="text" 
            id="username" 
            class="w-full border-2 border-gray-200 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            placeholder="Enter your username"
            required>
        </div>
        
        <div>
          <label class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
          <input 
            type="password" 
            id="password" 
            class="w-full border-2 border-gray-200 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            placeholder="Enter your password"
            required>
        </div>
        
        <button 
          type="submit" 
          id="loginBtn"
          class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-3.5 rounded-xl font-semibold text-sm transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
          Sign In
        </button>
      </form>
      
      <div id="errorMessage" class="mt-4 p-3 bg-red-50 border-2 border-red-200 text-red-700 rounded-xl text-sm hidden"></div>
      
      <script>
        // Check for error parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        if (error) {
          const errorMessage = document.getElementById('errorMessage');
          errorMessage.textContent = decodeURIComponent(error);
          errorMessage.classList.remove('hidden');
        }
      </script>
      
      <div class="mt-6 pt-6 text-center border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-2">Demo Credentials</p>
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-lg">
          <span class="text-sm font-mono text-gray-700"><strong>admin</strong></span>
          <span class="text-gray-400">/</span>
          <span class="text-sm font-mono text-gray-700"><strong>admin123</strong></span>
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <p class="text-center text-xs text-gray-500 mt-6">
      © 2024 SellApp. All rights reserved.
    </p>
  </div>

  <script src="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/js/simple.js"></script>
</body>
</html>
