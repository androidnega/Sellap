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
  
  <!-- Bulma CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  
  <link rel="stylesheet" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/css/styles.css">
  <style>
    /* Custom styles for login page */
    body {
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }
    
    .login-background {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
      filter: blur(10px);
      transform: scale(1.1);
    }
    
    .login-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.6);
      z-index: 1;
    }
    
    .login-container {
      position: relative;
      z-index: 10;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    
    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.2);
      width: 100%;
      max-width: 400px;
    }
    
    .logo-container {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      font-size: 1.5rem;
    }
    
    .brand-title {
      color: white;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
      font-weight: 700;
    }
    
    .brand-subtitle {
      color: rgba(255, 255, 255, 0.9);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
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
<body>
  <!-- Background with blur effect -->
  <?php if ($loginImageUrl): ?>
  <div class="login-background" style="background-image: url('<?php echo htmlspecialchars($loginImageUrl); ?>');"></div>
  <?php endif; ?>
  
  <!-- Dark Overlay -->
  <div class="login-overlay"></div>
  
  <!-- Content -->
  <div class="login-container">
    <div style="width: 100%; max-width: 400px;">
      <!-- Logo and Branding (Above Card) -->
      <div class="has-text-centered mb-5">
        <div class="is-flex is-justify-content-center is-align-items-center mb-3" style="gap: 0.5rem;">
          <div class="logo-container">
            <span>📱</span>
          </div>
          <h1 class="brand-title title is-3 mb-0">SellApp</h1>
        </div>
        <p class="brand-subtitle is-size-7 has-text-weight-medium">Multi-Tenant Phone Management System</p>
      </div>
      
      <!-- Login Card -->
      <div class="login-card">
        <div class="card-content">
          <!-- Login Form -->
          <div class="content">
            <h2 class="title is-4 has-text-centered mb-2">Welcome Back</h2>
            <p class="has-text-centered has-text-grey is-size-7 mb-5">Sign in to continue to your account</p>
          
          <?php
          // Display error message if present
          $error = $_GET['error'] ?? '';
          if (!empty($error)):
          ?>
          <div class="notification is-danger is-light mb-5">
            <button class="delete"></button>
            <?php echo htmlspecialchars(urldecode($error)); ?>
          </div>
          <?php endif; ?>
          
          <form method="post" action="<?php echo htmlspecialchars(BASE_URL_PATH . '/login' . (!empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '')); ?>">
            <!-- Username Field -->
            <div class="field mb-4">
              <label class="label is-size-7 has-text-weight-semibold">Username or Email</label>
              <div class="control has-icons-left">
                <input 
                  class="input" 
                  type="text" 
                  name="username"
                  id="username" 
                  placeholder="Enter username or email"
                  required
                  autocomplete="username"
                  value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
                <span class="icon is-small is-left">
                  <i class="fas fa-user"></i>
                </span>
              </div>
            </div>
            
            <!-- Password Field -->
            <div class="field mb-5">
              <label class="label is-size-7 has-text-weight-semibold">Password</label>
              <div class="control has-icons-left">
                <input 
                  class="input" 
                  type="password" 
                  name="password"
                  id="password" 
                  placeholder="Enter your password"
                  required
                  autocomplete="current-password"
                >
                <span class="icon is-small is-left">
                  <i class="fas fa-lock"></i>
                </span>
              </div>
            </div>
            
            <!-- Submit Button -->
            <div class="field">
              <div class="control">
                <button 
                  type="submit" 
                  id="loginSubmitBtn"
                  class="button is-primary is-fullwidth is-medium"
                >
                  <span class="icon">
                    <i class="fas fa-sign-in-alt"></i>
                  </span>
                  <span>Sign In</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    // Handle notification dismiss
    document.addEventListener('DOMContentLoaded', function() {
      // Close notification on delete button click
      (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
        const $notification = $delete.parentNode;
        $delete.addEventListener('click', () => {
          $notification.parentNode.removeChild($notification);
        });
      });
      
      // Form submission handling
      const form = document.querySelector('form[method="post"]');
      const submitBtn = document.getElementById('loginSubmitBtn');
      
      if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
          // Disable button during submission
          submitBtn.classList.add('is-loading');
          submitBtn.disabled = true;
        });
      }
    });
  </script>

</body>
</html>
