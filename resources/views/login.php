<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SellApp | Login</title>
  <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  <link rel="shortcut icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  <link rel="apple-touch-icon" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/images/favicon.svg">
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : ''; ?>/assets/css/styles.css">
  <style>
    :root {
      --login-accent: #4f46e5;
      --login-accent-hover: #4338ca;
      --login-bg: #f8fafc;
      --login-text: #1e293b;
      --login-muted: #64748b;
    }
    
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; min-height: 100vh; }
    
    .login-split {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
    }
    
    /* Left: Form section only */
    .login-form-section {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      background: var(--login-bg);
    }
    
    .login-form-wrapper {
      width: 100%;
      max-width: 380px;
    }
    
    /* Right: Image section with SellApp branding */
    .login-image-section {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    }
    
    .login-image-section img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      opacity: 0.85;
    }
    
    .login-image-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(79, 70, 229, 0.5) 0%, rgba(30, 41, 59, 0.7) 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
    }
    
    .login-brand-on-image {
      font-size: 3.5rem;
      font-weight: 800;
      color: white;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
      margin: 0 0 0.5rem 0;
      letter-spacing: -0.02em;
    }
    
    .login-tagline-on-image {
      color: rgba(255, 255, 255, 0.9);
      font-size: 1.1rem;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      margin-bottom: 1.5rem;
    }
    
    .login-hero-image {
      max-width: 320px;
      width: 100%;
      border-radius: 12px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
      border: 3px solid rgba(255, 255, 255, 0.2);
      object-fit: cover;
    }
    
    .login-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.04);
    }
    
    .login-card h2 {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--login-text);
      margin: 0 0 0.25rem 0;
    }
    
    .login-card .subtitle {
      color: var(--login-muted);
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
    }
    
    .login-field label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--login-text);
      margin-bottom: 0.35rem;
    }
    
    .login-input-wrap {
      position: relative;
    }
    
    .login-input-wrap .icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--login-muted);
      font-size: 0.95rem;
      pointer-events: none;
    }
    
    .login-input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.75rem;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: inherit;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    
    .login-input:focus {
      outline: none;
      border-color: var(--login-accent);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    
    .login-input::placeholder {
      color: #94a3b8;
    }
    
    .login-field {
      margin-bottom: 1.25rem;
    }
    
    .login-btn {
      width: 100%;
      padding: 0.85rem 1.5rem;
      background: linear-gradient(135deg, var(--login-accent), #6366f1);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: transform 0.15s, box-shadow 0.15s;
      box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
    }
    
    .login-btn:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
    }
    
    .login-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
    
    .login-notification {
      padding: 0.75rem 1rem;
      border-radius: 10px;
      margin-bottom: 1.25rem;
      font-size: 0.9rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }
    
    .login-notification.is-danger {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }
    
    .login-notification .delete-btn {
      background: none;
      border: none;
      color: inherit;
      cursor: pointer;
      padding: 0;
      margin-left: auto;
      opacity: 0.7;
    }
    
    .login-notification .delete-btn:hover {
      opacity: 1;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
      .login-split {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
      }
      
      .login-form-section {
        order: 2;
        padding: 1.5rem 1rem;
      }
      
      .login-image-section {
        order: 1;
        min-height: 200px;
        max-height: 240px;
      }
      
      .login-brand-on-image {
        font-size: 2.25rem;
      }
      
      .login-tagline-on-image {
        font-size: 0.95rem;
        margin-bottom: 1rem;
      }
      
      .login-hero-image {
        max-width: 240px;
      }
    }
    
    @media (max-width: 480px) {
      .login-card {
        padding: 1.5rem;
      }
      
      .login-brand-on-image {
        font-size: 1.9rem;
      }
    }
  </style>
</head>
<body>
  <?php
  $loginImageUrl = null;
  try {
    require_once __DIR__ . '/../../config/database.php';
    $db = \Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'login_page_image_url' LIMIT 1");
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($result && !empty($result['setting_value'])) {
      $loginImageUrl = $result['setting_value'];
    }
  } catch (\Exception $e) {
    error_log("Login page image error: " . $e->getMessage());
  }
  // Unsplash - clean smartphone/retail image for Phone Management System
  $defaultImage = 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=640&q=85';
  $imageUrl = $loginImageUrl ?: $defaultImage;
  ?>
  
  <div class="login-split">
    <!-- Left: Login form only -->
    <section class="login-form-section">
      <div class="login-form-wrapper">
        <div class="login-card">
          <h2>Welcome back</h2>
          <p class="subtitle">Sign in to continue to your account</p>
          
          <?php
          $error = $_GET['error'] ?? '';
          if (!empty($error)):
          ?>
          <div class="login-notification is-danger" id="errorNotification">
            <span><?php echo htmlspecialchars(urldecode($error)); ?></span>
            <button type="button" class="delete-btn" aria-label="Dismiss" onclick="this.parentElement.remove()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <?php endif; ?>
          
          <form method="post" action="<?php echo htmlspecialchars(BASE_URL_PATH . '/login' . (!empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '')); ?>">
            <div class="login-field">
              <label for="username">Username or Email</label>
              <div class="login-input-wrap">
                <i class="fas fa-user icon"></i>
                <input
                  class="login-input"
                  type="text"
                  name="username"
                  id="username"
                  placeholder="Enter username or email"
                  required
                  autocomplete="username"
                  value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
              </div>
            </div>
            
            <div class="login-field">
              <label for="password">Password</label>
              <div class="login-input-wrap">
                <i class="fas fa-lock icon"></i>
                <input
                  class="login-input"
                  type="password"
                  name="password"
                  id="password"
                  placeholder="Enter your password"
                  required
                  autocomplete="current-password"
                >
              </div>
            </div>
            
            <div class="login-field" style="margin-bottom: 0;">
              <button type="submit" id="loginSubmitBtn" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Sign In</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
    
    <!-- Right: SellApp branding + image -->
    <section class="login-image-section">
      <div class="login-image-overlay">
        <h1 class="login-brand-on-image">SellApp</h1>
        <p class="login-tagline-on-image">Multi-Tenant Phone Management System</p>
        <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Phone retail" class="login-hero-image" loading="eager" onerror="this.onerror=null; this.src='https://picsum.photos/seed/phones/640/480';">
      </div>
    </section>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form[method="post"]');
      const submitBtn = document.getElementById('loginSubmitBtn');
      if (form && submitBtn) {
        form.addEventListener('submit', function() {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Signing in...</span>';
        });
      }
    });
  </script>
</body>
</html>
