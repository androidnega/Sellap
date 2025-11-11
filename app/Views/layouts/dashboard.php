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
    <!-- Custom Favicon - Overrides XAMPP default favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <script>
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
            height: 100%;
            overflow-x: hidden;
        }
        
        /* Ensure proper flex layout */
        .main-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }
        
        /* Sidebar styles - Fixed position */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 14rem; /* w-56 - increased from 12rem */
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            flex-shrink: 0;
            z-index: 40;
        }
        
        .main-content-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            margin-left: 14rem; /* Account for fixed sidebar width */
            overflow-x: hidden;
        }
        
        /* Prevent overflow in main content */
        main {
            overflow-x: hidden;
            max-width: 100%;
        }
        
        /* Ensure all cards and containers respect boundaries */
        .grid {
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
                margin-left: 0; /* Remove sidebar margin on mobile */
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
        }
        
        /* Hide scrollbars for cart */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="main-layout">
        <!-- Sidebar -->
        <?php
        // Set up page detection for sidebar highlighting
        if (!isset($GLOBALS['currentPage'])) {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '';
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp';
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
        
        // Include the reusable sidebar component
        include APP_PATH . '/Views/components/sidebar.php';
        ?>
        
        <!-- Main Content Area -->
        <div class="main-content-container">
            <!-- Top Navigation Bar - Sticky -->
            <div class="sticky top-0 z-50 bg-white shadow-sm">
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
                             onclick="event.stopPropagation(); window.location.href='${notificationPath}';"
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
