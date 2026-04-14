<?php
// Dashboard Layout with Reusable Sidebar
// Usage: include with $title, $content, $userRole, $currentPage, $userInfo, $companyInfo
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : ($title ?? 'Dashboard') ?> - SellApp</title>
    <!-- Custom Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL_PATH; ?>/assets/images/favicon.svg">
    
    <!-- Preconnect to CDN for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    
    <!-- Tailwind CSS - loaded synchronously to prevent FOUC (flash of unstyled content) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
        
        // Suppress harmless "Tracking Prevention" warnings from CDN resources
        (function() {
            const originalWarn = console.warn;
            console.warn = function(...args) {
                const message = args.join(' ');
                if (message.includes('Tracking Prevention blocked access to storage')) {
                    return;
                }
                originalWarn.apply(console, args);
            };
        })();
        /**
         * Move modal overlays to document.body (modal root) so position:fixed is viewport-based.
         * Parent <main overflow-y:auto> otherwise breaks fixed positioning.
         * Pair with Tailwind: fixed inset-0 flex items-center justify-center z-[10050] …
         */
        window.ensureModalInBody = function(modalEl) {
            if (!modalEl) return;
            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }
            modalEl.classList.add('modal-viewport-layer');
            if (typeof window.resetModalOverlayScroll === 'function') {
                window.resetModalOverlayScroll(modalEl);
            }
        };
        /** Reset overlay scroll so the dialog stays visually centered (viewport), not below the fold. */
        window.resetModalOverlayScroll = function(modalEl) {
            if (!modalEl) return;
            modalEl.scrollTop = 0;
            modalEl.scrollLeft = 0;
        };
        /** Lock background scroll (use class, not body { position:fixed } — avoids scroll-jump bugs). */
        window.setDashboardModalScrollLock = function(locked) {
            var root = document.documentElement;
            var body = document.body;
            if (locked) {
                root.classList.add('modal-open');
                body.classList.add('modal-open');
            } else {
                root.classList.remove('modal-open');
                body.classList.remove('modal-open');
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Styles for Layout Fix -->
    <style>
        /* Reset any conflicting styles */
        * {
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: auto;
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto; /* Allow vertical scrolling for sticky to work */
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        /* Ensure proper flex layout */
        .main-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Sidebar: #sidebar uses inner .sidebar-nav-scroll for scroll so outer has no visible gutter */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 14rem; /* w-56 */
            height: 100vh;
            height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto; /* legacy layouts without inner wrapper */
            flex-shrink: 0;
            z-index: 40;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        #sidebar.sidebar {
            overflow: hidden !important;
            overflow-x: hidden !important;
            overflow-y: hidden !important;
            display: flex !important;
            flex-direction: column !important;
        }
        #sidebar.sidebar::-webkit-scrollbar,
        .sidebar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent !important;
        }
        /* Inner nav scroller — hide scrollbar (manager/long menus) */
        #sidebar .sidebar-nav-scroll {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        #sidebar .sidebar-nav-scroll::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            max-width: 0 !important;
            height: 0 !important;
        }
        
        .main-content-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            margin-left: 14rem; /* Account for fixed sidebar width */
            /* Remove overflow to allow sticky positioning to work */
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        .main-content-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        /* Prevent overflow in main content */
        main {
            overflow-x: hidden;
            overflow-y: auto;
            max-width: 100%;
            flex: 1;
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        main::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        /* Ensure all cards and containers respect boundaries */
        .grid {
            max-width: 100%;
        }

        /* Global mobile-safe content guards */
        img,
        svg,
        canvas,
        video,
        iframe {
            max-width: 100%;
            height: auto;
        }
        table {
            max-width: 100%;
        }
        input,
        select,
        textarea,
        button {
            max-width: 100%;
        }
        
        /* Prevent text overflow in cards */
        .overflow-hidden {
            overflow: hidden;
        }
        
        /* Notification dropdown mobile improvements */
        @media (max-width: 640px) {
            #notificationDropdown {
                position: fixed;
                right: 1rem;
                left: 1rem;
                width: auto;
                max-width: none;
                margin-top: 0.5rem;
            }
        }
        
        /* Desktop: Sidebar always visible, no collapse */
        @media (min-width: 769px) {
            .sidebar {
                position: fixed !important;
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
            
            .sidebar.collapsed ~ .main-content-container {
                margin-left: 14rem !important;
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
        
        /* Viewport modal shell (Tailwind: flex items-center justify-center + min-h on same node) */
        .modal-viewport-layer {
            position: fixed !important;
            inset: 0 !important;
            width: 100% !important;
            min-height: 100vh !important;
            min-height: 100dvh !important;
            max-width: 100vw !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            z-index: 10050 !important;
        }
        /* Do NOT use body.modal-open { position: fixed } — it resets scroll / shifts layout */
        html.modal-open,
        body.modal-open {
            overflow: hidden !important;
        }
        html.modal-open main,
        body.modal-open main {
            overflow: hidden !important;
        }
        
        /* GPU hint on sidebar only — transform on .main-content-container breaks position:fixed modals
           inside <main> (they anchor to the container instead of the viewport when user has scrolled). */
        .sidebar {
            will-change: auto;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        .main-content-container {
            will-change: auto;
            /* backface-visibility removed here — it can create compositing layers that interact badly
               with descendants that were position:fixed before being moved to body */
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
                margin-left: 0 !important; /* Remove sidebar margin on mobile */
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

        .app-shell-bg {
            background: #f4f6f8;
        }
    </style>
</head>
<body class="app-shell-bg">
    <div class="main-layout">
        <!-- Sidebar -->
        <?php
        // Set up page detection for sidebar highlighting
        if (!isset($GLOBALS['currentPage'])) {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '';
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = str_replace($basePath, '', $currentPath);
            
            $GLOBALS['currentPage'] = 'dashboard'; // Default page
            
            // Determine current page from path
            // Check specific routes before general ones
            if (strpos($currentPath, '/companies/sms-config') !== false) {
                $GLOBALS['currentPage'] = 'sms-config';
            } elseif (strpos($currentPath, '/companies/') !== false && strpos($currentPath, '/modules') !== false) {
                $GLOBALS['currentPage'] = 'company-modules';
            } elseif (strpos($currentPath, '/pos') !== false) {
                $GLOBALS['currentPage'] = 'pos';
            } elseif (strpos($currentPath, '/customers') !== false) {
                $GLOBALS['currentPage'] = 'customers';
            } elseif (strpos($currentPath, '/reports') !== false) {
                $GLOBALS['currentPage'] = 'reports';
            } elseif (strpos($currentPath, '/products') !== false) {
                $GLOBALS['currentPage'] = 'products';
            } elseif (strpos($currentPath, '/swaps') !== false) {
                $GLOBALS['currentPage'] = 'swaps';
            } elseif (strpos($currentPath, '/repairs') !== false) {
                $GLOBALS['currentPage'] = 'repairs';
            } elseif (strpos($currentPath, '/inventory') !== false) {
                $GLOBALS['currentPage'] = 'inventory';
            } elseif (strpos($currentPath, '/restock') !== false) {
                $GLOBALS['currentPage'] = 'restock';
            } elseif (strpos($currentPath, '/staff') !== false) {
                $GLOBALS['currentPage'] = 'staff';
            } elseif (strpos($currentPath, '/companies') !== false) {
                $GLOBALS['currentPage'] = 'companies';
            } elseif (strpos($currentPath, '/backup') !== false) {
                $GLOBALS['currentPage'] = 'backup';
            } elseif (strpos($currentPath, '/analytics') !== false) {
                $GLOBALS['currentPage'] = 'analytics';
            } elseif (strpos($currentPath, '/profile') !== false) {
                $GLOBALS['currentPage'] = 'profile';
            } elseif (strpos($currentPath, '/sms-settings') !== false) {
                $GLOBALS['currentPage'] = 'sms-settings';
            } elseif (strpos($currentPath, '/settings') !== false) {
                $GLOBALS['currentPage'] = 'settings';
            }
        }
        
        // Set variables for sidebar
        $currentPage = $GLOBALS['currentPage'];
        $userRole = $_SESSION['user']['role'] ?? 'salesperson';
        $userInfo = $_SESSION['user']['username'] ?? 'User';
        $companyInfo = $_SESSION['user']['company_name'] ?? null;
        
        // Pass additional user data to sidebar
        $_SESSION['user']['full_name'] = $_SESSION['user']['full_name'] ?? null;
        $_SESSION['user']['email'] = $_SESSION['user']['email'] ?? null;
        $_SESSION['user']['created_at'] = $_SESSION['user']['created_at'] ?? null;
        $_SESSION['user']['updated_at'] = $_SESSION['user']['updated_at'] ?? null;
        
        // Include the reusable sidebar component
        include APP_PATH . '/Views/components/sidebar.php';
        ?>
        
        <!-- Main Content Area -->
        <div class="main-content-container flex flex-col">
            <!-- Top Navigation Bar -->
            <div class="px-3 sm:px-4 md:px-6 pt-3 sm:pt-4">
                <?php include APP_PATH . '/Views/components/top-navbar.php'; ?>
            </div>
            
        <!-- Page Content -->
        <main class="flex-1 p-3 sm:p-4 md:p-6 overflow-x-hidden max-w-full">
            <div class="max-w-full overflow-x-hidden">
                <?= $content ?>
            </div>
        </main>
    </div>
    </div>
    
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
        
        function expandSidebarIfCollapsed(event, pageName) {
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
        
        // Notification System
        class NotificationSystem {
            constructor() {
                this.notifications = [];
                this.unreadCount = 0;
                this.init();
                // Make it globally accessible so notification page can refresh it
                window.notificationSystem = this;
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
                
                // Close dropdown on mobile when scrolling the page (not the dropdown itself)
                this.scrollTimeout = null;
                this.handleScroll = () => {
                    if (window.innerWidth <= 640 && dropdown && !dropdown.classList.contains('hidden')) {
                        clearTimeout(this.scrollTimeout);
                        this.scrollTimeout = setTimeout(() => {
                            this.closeDropdown();
                        }, 150); // Small delay to avoid closing during smooth scrolling
                    }
                };
                
                // Setup scroll listener based on screen size
                this.setupScrollListener();
                
                // Update scroll listener on window resize
                window.addEventListener('resize', () => {
                    this.setupScrollListener();
                });
            }
            
            setupScrollListener() {
                // Remove existing listener if it exists
                if (this.handleScroll) {
                    window.removeEventListener('scroll', this.handleScroll);
                }
                
                // Only add scroll listener on mobile
                if (window.innerWidth <= 640) {
                    window.addEventListener('scroll', this.handleScroll, { passive: true });
                }
            }
            
            async loadNotifications() {
                try {
                    // Get token from localStorage
                    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
                    const headers = {
                        'Content-Type': 'application/json',
                    };
                    
                    // Add Authorization header if token exists
                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }
                    
                    const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                    const response = await fetch(baseUrl + '/api/notifications', {
                        method: 'GET',
                        headers: headers,
                        credentials: 'same-origin' // Include cookies for session fallback
                    });
                    
                    // Get response text first to handle both JSON and non-JSON responses
                    const responseText = await response.text();
                    
                    if (!response.ok) {
                        // Try to parse as JSON, but don't display raw JSON
                        try {
                            const errorData = JSON.parse(responseText);
                            const errorMessage = errorData.error || errorData.message || 'Failed to load notifications';
                            throw new Error(errorMessage);
                        } catch (parseError) {
                            // If not JSON, just throw a generic error
                            throw new Error('Failed to load notifications');
                        }
                    }
                    
                    // Parse JSON response
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Invalid JSON response from notifications API');
                        throw new Error('Invalid response from server');
                    }
                    
                    if (data.success) {
                        this.notifications = data.notifications || [];
                        this.unreadCount = data.unread_count || 0;
                        this.updateUI();
                    } else {
                        // Handle API error response without displaying raw JSON
                        const errorMessage = data.error || data.message || 'Failed to load notifications';
                        console.error('Notifications API error:', errorMessage);
                        // Don't display error in UI, just log it
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error.message || error);
                    // Silently fail to prevent UI disruption - never display raw JSON
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
                    
                    // Always redirect to notification center page with notification ID
                    // This works from any admin dashboard page (Companies, SMS Config, Analytics, etc.)
                    const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                    // Ensure we use absolute path - works from any page
                    const notificationPath = baseUrl + '/dashboard/notifications?view=' + encodeURIComponent(notification.id);
                    
                    return `
                        <div class="p-3 sm:p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer notification-item transition-colors" 
                             onclick="event.stopPropagation(); 
                                      if (!${notification.read} && ('${notification.type}' === 'repair' || '${notification.id}'.startsWith('notif_') || !isNaN(parseInt('${notification.id}')))) {
                                          fetch(typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '') + '/api/notifications/mark-read', {
                                              method: 'POST',
                                              headers: {
                                                  'Content-Type': 'application/json',
                                                  'Authorization': 'Bearer ' + (localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token') || '')
                                              },
                                              credentials: 'same-origin',
                                              body: JSON.stringify({ notification_id: '${notification.id}' })
                                          }).catch(() => {});
                                      }
                                      window.location.href='${notificationPath}';"
                             data-id="${notification.id}" 
                             data-read="${notification.read}">
                            <div class="flex items-start gap-2 sm:gap-3">
                                <div class="flex-shrink-0">
                                    <div class="w-7 h-7 sm:w-8 sm:h-8 ${priorityClass} rounded-full flex items-center justify-center">
                                        <i class="${iconClass} text-white text-xs sm:text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2">
                                        <p class="text-xs sm:text-sm font-medium text-gray-900 ${!notification.read ? 'font-bold' : ''} truncate">
                                            ${this.escapeHtml(notification.title)}
                                        </p>
                                        <p class="text-xs text-gray-500 whitespace-nowrap">${timeAgo}</p>
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1 line-clamp-2">${this.escapeHtml(notification.message)}</p>
                                </div>
                                ${!notification.read ? '<div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-1 sm:mt-0"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
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
                    // Get token from localStorage
                    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token') || localStorage.getItem('auth_token');
                    const headers = {
                        'Content-Type': 'application/json',
                    };
                    
                    // Add Authorization header if token exists
                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }
                    
                    const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                    const response = await fetch(baseUrl + '/api/notifications/mark-read', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin', // Include cookies for session fallback
                        body: JSON.stringify({ all: true })
                    });
                    
                    if (response.ok) {
                        this.unreadCount = 0;
                        this.updateUI();
                    }
                } catch (error) {
                    console.error('Error marking notifications as read:', error);
                }
            }
        }
        
        // Initialize notification system when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            new NotificationSystem();
        });
    </script>
</body>
</html>
