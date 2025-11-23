<?php
// Ensure config is loaded before rendering
if (!defined('BASE_URL_PATH')) {
    require_once __DIR__ . '/../../config/app.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SellApp' ?> - SellApp</title>
    <!-- Custom Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    
    <!-- Base path -->
    <script>
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        // Clean up _auth parameter from URL if present (prevents redirect loops)
        // Use replaceState to change URL without reloading page
        (function() {
            try {
                if (window.location.search.includes('_auth=')) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('_auth');
                    const cleanUrl = url.pathname + (url.search || '') + (url.hash || '');
                    window.history.replaceState({}, '', cleanUrl);
                    console.log('Cleaned _auth parameter from URL');
                }
            } catch (e) {
                console.error('Error cleaning _auth parameter:', e);
            }
        })();
        
        // Suppress harmless "Tracking Prevention" warnings from CDN resources
        // These are browser privacy warnings and don't affect functionality
        (function() {
            const originalWarn = console.warn;
            console.warn = function(...args) {
                const message = args.join(' ');
                // Filter out tracking prevention warnings from CDN resources
                if (message.includes('Tracking Prevention blocked access to storage')) {
                    return; // Suppress this warning
                }
                originalWarn.apply(console, args);
            };
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Load Alpine.js with multiple fallbacks
        (function() {
            const alpineScript = document.createElement('script');
            alpineScript.defer = true;
            alpineScript.onerror = function() {
                // First fallback
                const fallback1 = document.createElement('script');
                fallback1.src = 'https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js';
                fallback1.defer = true;
                fallback1.onerror = function() {
                    // Second fallback - use jsDelivr
                    const fallback2 = document.createElement('script');
                    fallback2.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js';
                    fallback2.defer = true;
                    fallback2.onerror = function() {
                        console.warn('Alpine.js failed to load from all CDNs. Some interactive features may not work.');
                    };
                    document.head.appendChild(fallback2);
                };
                document.head.appendChild(fallback1);
            };
            alpineScript.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
            document.head.appendChild(alpineScript);
        })();
    </script>
    
    <!-- Custom Styles for Layout Fix -->
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
        
        /* Reset any conflicting styles */
        * {
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto; /* Allow vertical scrolling for sticky to work */
        }
        
        /* Ensure proper flex layout */
        .main-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Sidebar styles - Sticky position */
        .sidebar {
            position: sticky;
            top: 0;
            width: 14rem; /* w-56 - increased from 12rem */
            height: 100vh;
            min-height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }
        
        .main-content-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            overflow-x: hidden;
            position: relative; /* Ensure sticky positioning works */
        }
        
        /* Ensure sticky header works */
        .main-content-container > div:first-child {
            position: sticky;
            top: 0;
            z-index: 50;
            background-color: white;
        }
        
        /* Ensure main content doesn't overflow */
        main {
            overflow-x: hidden;
            max-width: 100%;
            flex: 1;
        }
        
        /* Desktop: Sidebar always visible, no collapse */
        @media (min-width: 769px) {
            .sidebar {
                position: sticky !important;
                left: 0 !important;
                width: 14rem !important;
            }
            
            .sidebar.collapsed {
                width: 14rem !important;
            }
            
            .sidebar.collapsed .sidebar-text,
            .sidebar.collapsed .sidebar-item span {
                opacity: 1 !important;
                width: auto !important;
                overflow: visible !important;
            }
            
            .sidebar.collapsed .sidebar-item {
                justify-content: flex-start !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            .sidebar.collapsed .sidebar-item i {
                margin-right: 0.75rem !important;
            }
            
            /* Hide collapse button on desktop */
            #sidebarCollapseBtn {
                display: none !important;
            }
            
            /* Hide breadcrumb on desktop */
            #breadcrumb-nav {
                display: none !important;
            }
            
            /* Hide sidebar toggle button on desktop */
            #sidebarToggle {
                display: none !important;
            }
        }
        
        /* Mobile: Sidebar collapse functionality */
        @media (max-width: 768px) {
            .sidebar.collapsed {
                width: 4rem;
            }
            
            .sidebar.collapsed .sidebar-text,
            .sidebar.collapsed .sidebar-item span {
                opacity: 0;
                width: 0;
                overflow: hidden;
                margin: 0;
                padding: 0;
            }
            
            .sidebar.collapsed .sidebar-item {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            .sidebar.collapsed .sidebar-item i {
                margin-right: 0 !important;
                width: 1rem !important;
                min-width: 1rem !important;
                max-width: 1rem !important;
                flex-shrink: 0;
            }
            
            .sidebar.collapsed .sidebar-item i.fas,
            .sidebar.collapsed .sidebar-item i.far {
                font-size: 1rem !important;
                line-height: 1.5rem !important;
            }
            
            .sidebar.collapsed ~ .main-content-container {
                margin-left: 4rem;
            }
            
            /* Ensure icons maintain size when collapsed */
            .sidebar.collapsed .flex.items-center i {
                flex-shrink: 0;
                display: inline-block;
            }
        }
        
        /* Prevent blur/backdrop effects during transitions */
        .sidebar,
        .main-content-container {
            will-change: auto;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .main-layout {
                position: relative;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .main-content-container {
                width: 100%;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            /* Hide collapse button on mobile */
            #sidebarCollapseBtn {
                display: none !important;
            }
        }
        
        /* Hide scrollbars for cart */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .login-card {
            min-height: 300px !important;
            max-height: 340px !important;
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="main-layout">
        <!-- Sidebar -->
        <?php
        // Get user data from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $GLOBALS['user_data'] ?? $_SESSION['user'] ?? null;
        $userRole = $userData['role'] ?? 'salesperson';
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        // Remove base path from current path for sidebar matching
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        $currentPath = str_replace($basePath, '', $currentPath);
        
        // Render sidebar using the new dynamic sidebar component
        $userInfo = $userData['username'] ?? 'User';
        $companyInfo = $userData['company_name'] ?? null;
        
        // Ensure user data is in session for sidebar
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user']['full_name'] = $userData['full_name'] ?? null;
        $_SESSION['user']['email'] = $userData['email'] ?? null;
        $_SESSION['user']['created_at'] = $userData['created_at'] ?? null;
        $_SESSION['user']['updated_at'] = $userData['updated_at'] ?? null;
        
        // Check if currentPage was set by controller first
        if (!isset($GLOBALS['currentPage'])) {
            $currentPage = 'dashboard'; // Default page
            
            // Determine current page from path
            // Check specific routes before general ones
            if (strpos($currentPath, '/reset/history') !== false) {
                $currentPage = 'reset-history';
            } elseif (strpos($currentPath, '/reset') !== false && strpos($currentPath, '/reset/history') === false && strpos($currentPath, '/companies/') === false) {
                // Check if it's system reset (not company reset and not reset history)
                $currentPage = 'system-reset';
            } elseif (strpos($currentPath, '/companies/sms-config') !== false) {
                $currentPage = 'sms-config';
            } elseif (strpos($currentPath, '/companies/modules') !== false || (strpos($currentPath, '/companies/') !== false && strpos($currentPath, '/modules') !== false)) {
                $currentPage = 'company-modules';
            } elseif (strpos($currentPath, '/pos/sales-history') !== false || strpos($currentPath, '/sales-history') !== false) {
                $currentPage = 'sales-history';
            } elseif (strpos($currentPath, '/pos') !== false) {
                $currentPage = 'pos';
            } elseif (strpos($currentPath, '/customers') !== false) {
                $currentPage = 'customers';
            } elseif (strpos($currentPath, '/reports') !== false) {
                $currentPage = 'reports';
            } elseif (strpos($currentPath, '/products') !== false) {
                $currentPage = 'products';
            } elseif (strpos($currentPath, '/swaps') !== false) {
                $currentPage = 'swaps';
            } elseif (strpos($currentPath, '/booking') !== false) {
                $currentPage = 'booking';
            } elseif (strpos($currentPath, '/technician/repairs') !== false) {
                $currentPage = 'repairs';
            } elseif (strpos($currentPath, '/repairs') !== false) {
                $currentPage = 'repairs';
            } elseif (strpos($currentPath, '/inventory') !== false) {
                $currentPage = 'inventory';
            } elseif (strpos($currentPath, '/restock') !== false) {
                $currentPage = 'restock';
            } elseif (strpos($currentPath, '/staff') !== false) {
                $currentPage = 'staff';
            } elseif (strpos($currentPath, '/companies') !== false) {
                $currentPage = 'companies';
            } elseif (strpos($currentPath, '/analytics') !== false) {
                $currentPage = 'analytics';
            } elseif (strpos($currentPath, '/profile') !== false) {
                $currentPage = 'profile';
            } elseif (strpos($currentPath, '/sms-settings') !== false) {
                $currentPage = 'sms-settings';
            } elseif (strpos($currentPath, '/settings') !== false) {
                $currentPage = 'settings';
            }
            
            $GLOBALS['currentPage'] = $currentPage;
        } else {
            $currentPage = $GLOBALS['currentPage'];
        }
        
        // Include the dynamic sidebar component
        include __DIR__ . '/components/sidebar.php';
        ?>
        
        <!-- Main Content Area -->
        <div class="main-content-container">
        <!-- Top Navigation Bar - Sticky -->
        <div class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200" style="position: -webkit-sticky; position: sticky; top: 0;">
            <?php include __DIR__ . '/components/top-navbar.php'; ?>
        </div>
        
        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
        </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Simple JavaScript -->
    <script src="<?= BASE_URL_PATH ?>/assets/js/simple.js?v=<?= time() ?>"></script>
    <script>
        // Sidebar Toggle Functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleIcon = document.getElementById('sidebarToggleIcon');
            
            if (sidebar) {
                sidebar.classList.toggle('open');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
                if (toggleIcon) {
                    if (sidebar.classList.contains('open')) {
                        toggleIcon.className = 'fas fa-times text-lg';
                    } else {
                        toggleIcon.className = 'fas fa-bars text-lg';
                    }
                }
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleIcon = document.getElementById('sidebarToggleIcon');
            
            if (sidebar) {
                sidebar.classList.remove('open');
                if (overlay) {
                    overlay.classList.remove('active');
                }
                if (toggleIcon) {
                    toggleIcon.className = 'fas fa-bars text-lg';
                }
            }
        }
        
        function toggleSidebarCollapse() {
            // Only allow collapse on mobile devices
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('collapsed');
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    // Save state to localStorage (only for mobile)
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                }
            }
        }
        
        function expandSidebarIfCollapsed(event) {
            // Only handle expansion on mobile devices
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    // Prevent default navigation temporarily
                    if (event) {
                        event.preventDefault();
                    }
                    
                    // Expand sidebar smoothly
                    sidebar.classList.remove('collapsed');
                    localStorage.setItem('sidebarCollapsed', 'false');
                    
                    // Small delay to ensure smooth transition, then navigate
                    setTimeout(() => {
                        if (event && event.target && event.target.closest('a')) {
                            const link = event.target.closest('a');
                            if (link.href) {
                                window.location.href = link.href;
                            }
                        }
                    }, 150);
                    
                    return false;
                }
            }
        }
        
        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (sidebar) {
                // On desktop: always ensure sidebar is expanded and visible
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.remove('open');
                    // Sidebar should always be visible on desktop
                } else {
                    // On mobile: restore collapsed state if it was collapsed
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                    }
                    // Close sidebar on mobile by default
                    closeSidebar();
                }
            }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    closeSidebar();
                }
            }
        });
        
        async function logout() {
            if (confirm('Are you sure you want to logout?')) {
                try {
                    // Call logout API endpoint to destroy server session
                    const response = await fetch(BASE + '/api/auth/logout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });
                    
                    // Clear local storage
                    localStorage.removeItem('token');
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('sellapp_user');
                    
                    // Clear session storage
                    sessionStorage.clear();
                    
                    // Redirect to login page
                    window.location.href = BASE + '/';
                } catch (error) {
                    console.error('Logout error:', error);
                    // Even if API call fails, clear local storage and redirect
            localStorage.removeItem('token');
                    localStorage.removeItem('sellapp_token');
                    localStorage.removeItem('sellapp_user');
                    sessionStorage.clear();
            window.location.href = BASE + '/';
                }
            }
        }
        
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }
        }
        
        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                closeSidebar();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Notification System
        class NotificationSystem {
            constructor() {
                this.notifications = [];
                this.unreadCount = 0;
                this.init();
            }
            
            init() {
                this.loadNotifications();
                this.setupEventListeners();
                
                // Auto-refresh notifications every 30 seconds
                setInterval(() => {
                    this.loadNotifications();
                }, 30000);
            }
            
            setupEventListeners() {
                const bell = document.getElementById('notificationBell');
                const dropdown = document.getElementById('notificationDropdown');
                const markAllRead = document.getElementById('markAllRead');
                
                if (bell) {
                    bell.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.toggleDropdown();
                    });
                }
                
                if (markAllRead) {
                    markAllRead.addEventListener('click', () => {
                        this.markAllAsRead();
                    });
                }
                
                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (bell && dropdown && !bell.contains(e.target) && !dropdown.contains(e.target)) {
                        this.closeDropdown();
                    }
                });
            }
            
            async loadNotifications() {
                try {
                    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
                    const headers = {
                        'Content-Type': 'application/json',
                    };
                    
                    // Add Authorization header if token exists
                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }
                    
                    const response = await fetch(BASE + '/api/notifications', {
                        method: 'GET',
                        headers: headers,
                        credentials: 'same-origin' // Include cookies for session fallback
                    });
                    
                    if (!response.ok) {
                        // Check if response is JSON before parsing
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            const errorData = await response.json();
                            throw new Error(errorData.error || 'Failed to load notifications');
                        } else {
                            throw new Error('Failed to load notifications');
                        }
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.notifications = data.notifications || [];
                        this.unreadCount = data.unread_count || 0;
                        this.updateUI();
                    }
                } catch (error) {
                    // Silently fail to prevent UI disruption
                    // Don't log to console to avoid cluttering
                    this.notifications = [];
                    this.unreadCount = 0;
                }
            }
            
            updateUI() {
                const badge = document.getElementById('notificationBadge');
                const list = document.getElementById('notificationList');
                const noNotifications = document.getElementById('noNotifications');
                
                // Update badge
                if (badge) {
                    if (this.unreadCount > 0) {
                        badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
                
                // Update notification list
                if (list && noNotifications) {
                    if (this.notifications.length === 0) {
                        list.classList.add('hidden');
                        noNotifications.classList.remove('hidden');
                    } else {
                        list.classList.remove('hidden');
                        noNotifications.classList.add('hidden');
                        list.innerHTML = this.renderNotifications();
                    }
                }
            }
            
            renderNotifications() {
                return this.notifications.map(notification => {
                    const timeAgo = this.getTimeAgo(notification.created_at);
                    const priorityClass = this.getPriorityClass(notification.priority);
                    const iconClass = this.getIconClass(notification.type);
                    
                    return `
                        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer notification-item" 
                             data-id="${notification.id}" 
                             data-read="${notification.read}">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 ${priorityClass} rounded-full flex items-center justify-center">
                                        <i class="${iconClass} text-white text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 ${!notification.read ? 'font-bold' : ''}">
                                            ${notification.title || 'Notification'}
                                        </p>
                                        <p class="text-xs text-gray-500">${timeAgo}</p>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">${notification.message || ''}</p>
                                </div>
                                ${!notification.read ? '<div class="w-2 h-2 bg-blue-500 rounded-full"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            getPriorityClass(priority) {
                switch (priority) {
                    case 'critical': return 'bg-red-500';
                    case 'high': return 'bg-orange-500';
                    case 'medium': return 'bg-blue-500';
                    default: return 'bg-gray-500';
                }
            }
            
            getIconClass(type) {
                switch (type) {
                    case 'low_stock': return 'fas fa-exclamation-triangle';
                    case 'out_of_stock': return 'fas fa-times-circle';
                    case 'system': return 'fas fa-info-circle';
                    default: return 'fas fa-bell';
                }
            }
            
            getTimeAgo(dateString) {
                const now = new Date();
                const date = new Date(dateString);
                const diffInSeconds = Math.floor((now - date) / 1000);
                
                if (diffInSeconds < 60) return 'Just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                return `${Math.floor(diffInSeconds / 86400)}d ago`;
            }
            
            toggleDropdown() {
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            }
            
            closeDropdown() {
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) {
                    dropdown.classList.add('hidden');
                }
            }
            
            async markAllAsRead() {
                try {
                    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
                    const headers = {
                        'Content-Type': 'application/json',
                    };
                    
                    // Add Authorization header if token exists
                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }
                    
                    const response = await fetch(BASE + '/api/notifications/mark-read', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin', // Include cookies for session fallback
                        body: JSON.stringify({ notification_id: 'all' })
                    });
                    
                    if (response.ok) {
                        this.unreadCount = 0;
                        this.notifications = this.notifications.map(n => ({...n, read: true}));
                        this.updateUI();
                    }
                } catch (error) {
                    console.error('Error marking notifications as read:', error);
                }
            }
        }
        
        // Initialize notification system when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                window.notificationSystem = new NotificationSystem();
            });
        } else {
            window.notificationSystem = new NotificationSystem();
        }
    </script>
    
    <!-- Hide Tailwind CSS production warning -->
    <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            const message = args.join(' ');
            if (message.includes('cdn.tailwindcss.com should not be used in production')) {
                return;
            }
            originalWarn.apply(console, args);
        };
    </script>
</body>
</html>
