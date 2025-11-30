<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SellApp | Login</title>
  <!-- Custom Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  <link rel="shortcut icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  <link rel="apple-touch-icon" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  
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
    /* Show body immediately - don't hide it */
    body {
      visibility: visible;
      opacity: 1;
    }
  </style>
</head>
<?php
// Get login page image URL from system settings or Cloudinary
$loginImageUrl = null;

try {
    require_once __DIR__ . '/../../config/database.php';
    $db = \Database::getInstance()->getConnection();
    
    // Try to get image URL from system settings
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'login_page_image_url' LIMIT 1");
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['setting_value'])) {
        $loginImageUrl = $result['setting_value'];
    } else {
        // Try to upload image if Cloudinary is configured
        if (class_exists('\App\Services\CloudinaryService')) {
            $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            if (!empty($settings['cloudinary_cloud_name'])) {
                $cloudinaryService = new \App\Services\CloudinaryService();
                $cloudinaryService->loadFromSettings($settings);
                
                if ($cloudinaryService->isConfigured()) {
                    $imagePath = __DIR__ . '/../../assets/images/login page.png';
                    if (file_exists($imagePath)) {
                        $uploadResult = $cloudinaryService->uploadImage($imagePath, 'sellapp/login', [
                            'public_id' => 'login_page_background',
                            'overwrite' => true
                        ]);
                        
                        if ($uploadResult['success']) {
                            $loginImageUrl = $uploadResult['secure_url'];
                            // Save to settings
                            $saveStmt = $db->prepare("
                                INSERT INTO system_settings (setting_key, setting_value) 
                                VALUES ('login_page_image_url', ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?
                            ");
                            $saveStmt->execute([$loginImageUrl, $loginImageUrl]);
                        }
                    }
                }
            }
        }
    }
} catch (\Exception $e) {
    error_log("Login page image error: " . $e->getMessage());
}
?>
<body class="min-h-screen flex flex-col bg-gray-900">
  <!-- Background with blur effect -->
  <?php if ($loginImageUrl): ?>
  <div class="fixed inset-0 z-0" style="background-image: url('<?php echo htmlspecialchars($loginImageUrl); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; filter: blur(10px); transform: scale(1.1);"></div>
  <?php endif; ?>
  <!-- Dark Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-60 z-0"></div>
  
  <!-- Content -->
  <div class="relative z-10 flex-1 flex items-center justify-center px-4 py-8 min-h-screen">
      <div class="w-full max-w-sm">
        <!-- Logo and Branding -->
        <div class="mb-5 text-center">
          <div class="flex items-center justify-center gap-2 mb-2">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-white/90 backdrop-blur-sm rounded-xl shadow-xl">
              <span class="text-2xl">📱</span>
            </div>
            <h1 class="text-2xl font-bold text-white drop-shadow-lg">SellApp</h1>
          </div>
          <p class="text-white/90 text-xs font-medium drop-shadow">Multi-Tenant Phone Management System</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-5 md:p-6 border border-white/20">
          <h2 class="text-xl font-bold text-gray-900 mb-1 text-center">Welcome Back</h2>
          <p class="text-gray-600 text-xs text-center mb-5">Sign in to continue to your account</p>
          <?php
          // Display error message if present
          $error = $_GET['error'] ?? '';
          if (!empty($error)):
          ?>
          <div class="mb-6 p-4 bg-red-50 border-2 border-red-300 text-red-700 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars(urldecode($error)); ?>
          </div>
          <?php endif; ?>
          
          <form method="post" action="<?php echo htmlspecialchars(BASE_URL_PATH . '/login' . (!empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '')); ?>" style="display: block;">
            <div style="margin-bottom: 16px;">
              <label class="block text-gray-700 text-xs font-semibold mb-1.5">Username or Email</label>
              <input 
                type="text" 
                name="username"
                id="username" 
                class="w-full border-2 border-gray-200 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white" 
                placeholder="Enter username or email"
                required
                autocomplete="username"
                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                style="display: block; width: 100%;">
            </div>
            
            <div style="margin-bottom: 18px;">
              <label class="block text-gray-700 text-xs font-semibold mb-1.5">Password</label>
              <input 
                type="password" 
                name="password"
                id="password" 
                class="w-full border-2 border-gray-200 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white" 
                placeholder="Enter your password"
                required
                autocomplete="current-password"
                style="display: block; width: 100%;">
            </div>
            
            <button 
              type="submit" 
              id="loginSubmitBtn"
              class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-2.5 rounded-lg font-semibold text-sm transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-[1.01]"
              style="display: block; width: 100%; cursor: pointer;">
              Sign In
            </button>
          </form>
        </div>
      </div>
      
      <script>
      // Simple form submission test - ensure form works
      document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[method="post"]');
        const submitBtn = document.getElementById('loginSubmitBtn');
        
        if (form && submitBtn) {
          // Ensure button is clickable
          submitBtn.style.pointerEvents = 'auto';
          submitBtn.style.cursor = 'pointer';
          
          // Test click
          submitBtn.addEventListener('click', function(e) {
            console.log('Login button clicked!');
            // Don't prevent default - let form submit naturally
          });
          
          // Test form submit
          form.addEventListener('submit', function(e) {
            console.log('Form submitting!');
            // Don't prevent default - let form submit naturally
          });
        }
      });
      </script>
    
  </div>

</body>
</html>
