<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SellApp | Login</title>
  <!-- Custom Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
  <link rel="shortcut icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
  <link rel="apple-touch-icon" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
  <script>
    // Base path for application URLs (auto-detected)
    window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : "/sellapp"; ?>';
    const BASE = window.APP_BASE_PATH || '';
    
    // Remove kabz_events from URL if present (completely remove this path)
    (function() {
      if (window.location.pathname.includes('kabz_events')) {
        let newPath = window.location.pathname.replace(/\/kabz_events\/?/g, '/');
        newPath = newPath.replace(/kabz_events/g, '');
        // Ensure path starts with /sellapp if it's empty or root
        if (newPath === '/' || newPath === '') {
          newPath = '/sellapp';
        } else if (!newPath.startsWith('/sellapp') && !newPath.startsWith('/api') && !newPath.startsWith('/dashboard') && !newPath.startsWith('/login')) {
          newPath = '/sellapp' + (newPath.startsWith('/') ? '' : '/') + newPath;
        }
        const newUrl = newPath + window.location.search + window.location.hash;
        window.location.replace(newUrl);
        return;
      }
    })();
    
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
  
  <!-- Preconnect to CDN for faster loading -->
  <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
  
  <!-- Robust Tailwind CSS loader with online/offline detection and retry mechanism -->
  <script>
      (function() {
          let tailwindLoaded = false;
          let retryCount = 0;
          const maxRetries = 10;
          const retryDelay = 1000; // 1 second
          
          function loadTailwind() {
              // Check if already loaded
              if (tailwindLoaded || window.tailwind) {
                  return;
              }
              
              // Check if script already exists
              const existingScript = document.querySelector('script[data-tailwind-loader]');
              if (existingScript) {
                  return;
              }
              
              const script = document.createElement('script');
              script.src = 'https://cdn.tailwindcss.com';
              script.async = true;
              script.setAttribute('data-tailwind-loader', 'true');
              
              script.onload = function() {
                  tailwindLoaded = true;
                  retryCount = 0;
                  // Trigger a re-render to apply styles
                  if (window.tailwind && typeof window.tailwind.refresh === 'function') {
                      window.tailwind.refresh();
                  }
                  // Show body once Tailwind is loaded
                  document.body.classList.add('tailwind-loaded');
                  // Dispatch custom event for other scripts
                  window.dispatchEvent(new CustomEvent('tailwindLoaded'));
              };
              
              script.onerror = function() {
                  // Script failed to load
                  if (retryCount < maxRetries) {
                      retryCount++;
                      setTimeout(loadTailwind, retryDelay);
                  }
              };
              
              document.head.appendChild(script);
          }
          
          // Try loading immediately
          loadTailwind();
          
          // Listen for online event and retry
          window.addEventListener('online', function() {
              if (!tailwindLoaded) {
                  retryCount = 0; // Reset retry count when back online
                  loadTailwind();
              }
          });
          
          // Periodic check when offline (in case online event doesn't fire)
          let offlineCheckInterval = setInterval(function() {
              if (navigator.onLine && !tailwindLoaded) {
                  retryCount = 0;
                  loadTailwind();
              }
          }, 2000); // Check every 2 seconds
          
          // Clear interval when Tailwind is loaded
          const checkLoaded = setInterval(function() {
              if (tailwindLoaded) {
                  clearInterval(offlineCheckInterval);
                  clearInterval(checkLoaded);
              }
          }, 500);
          
          // Fallback: Show body after 5 seconds even if Tailwind hasn't loaded
          setTimeout(function() {
              if (!tailwindLoaded) {
                  document.body.classList.add('tailwind-loaded');
              }
          }, 5000);
      })();
  </script>
  <link rel="stylesheet" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/css/styles.css">
  <style>
    /* Hide body until Tailwind is loaded to prevent FOUC */
    body {
      visibility: hidden;
      opacity: 0;
      transition: opacity 0.2s ease-in;
    }
    
    body.tailwind-loaded {
      visibility: visible;
      opacity: 1;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-gray-50 min-h-screen flex items-center justify-center px-4 py-8">
  <div class="w-full max-w-xs">
    <!-- Logo and Branding -->
    <div class="text-center mb-4">
      <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl mb-2 shadow-md">
        <span class="text-2xl">📱</span>
      </div>
      <h1 class="text-xl font-bold text-gray-900 mb-1">SellApp</h1>
      <p class="text-gray-600 text-xs">Multi-Tenant Phone Management System</p>
    </div>
    
    <!-- Login Card -->
    <div class="bg-white rounded-xl shadow-lg p-4 border border-gray-100">
      <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">Welcome Back</h2>
      
      <form id="loginForm" class="space-y-3">
        <div>
          <label class="block text-gray-700 text-xs font-semibold mb-1">Username or Email</label>
          <input 
            type="text" 
            id="username" 
            class="w-full border-2 border-gray-200 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            placeholder="Enter username or email"
            required>
        </div>
        
        <div>
          <label class="block text-gray-700 text-xs font-semibold mb-1">Password</label>
          <input 
            type="password" 
            id="password" 
            class="w-full border-2 border-gray-200 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            placeholder="Enter your password"
            required>
        </div>
        
        <button 
          type="submit" 
          id="loginBtn"
          class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-2 rounded-lg font-semibold text-sm transition-all duration-200 shadow-md hover:shadow-lg">
          Sign In
        </button>
      </form>
      
      <div id="errorMessage" class="mt-3 p-2 bg-red-50 border-2 border-red-200 text-red-700 rounded-lg text-xs hidden"></div>
      
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
      
    </div>
    
    <!-- Footer -->
    <p class="text-center text-xs text-gray-500 mt-4">
      © 2024 SellApp. All rights reserved.
    </p>
  </div>

  <script src="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/js/simple.js"></script>
</body>
</html>
