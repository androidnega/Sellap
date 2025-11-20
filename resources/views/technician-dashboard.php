<?php
// User info will be available from the dashboard controller
// Authentication is handled by the controller before this view is rendered
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - SellApp</title>
    <!-- Custom Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/images/favicon.svg">
    <script>
        // Base path for application URLs (auto-detected)
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
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
        })();
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="sidebar sidebar-technician w-64 p-4">
            <div class="flex items-center mb-8">
                <i class="fas fa-tools text-2xl mr-3 sidebar-text"></i>
                <h1 class="text-xl font-bold sidebar-text">SellApp Tech</h1>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center mb-2">
                    <i class="fas fa-user-circle text-lg mr-2 sidebar-text"></i>
                    <span class="font-semibold sidebar-text">Technician</span>
                </div>
                <div class="text-sm sidebar-text opacity-75" id="user-info">Loading...</div>
            </div>
            
            <nav class="space-y-2">
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-tachometer-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-tools mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Repairs</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-exchange-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Swaps</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-clipboard-list mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Service Reports</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-cog mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Settings</span>
                </a>
                <a href="#" onclick="logout()" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Logout</span>
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content flex-1 p-6">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Technician Dashboard</h2>
                <p class="text-gray-600">Manage repairs, swaps, and technical services</p>
            </div>
            
            <!-- Technician Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-tools text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Repairs</p>
                            <p class="text-2xl font-bold text-gray-900" id="active-repairs">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-900" id="completed-repairs">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-exchange-alt text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Swaps</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-swaps">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Revenue</p>
                            <p class="text-2xl font-bold text-gray-900" id="repair-revenue">₵0.00</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Technician Features Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Technical Services</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Repair Management</h4>
                        <p class="text-sm text-gray-600">Handle phone repairs and diagnostics</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Swap Transactions</h4>
                        <p class="text-sm text-gray-600">Process phone swap transactions</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Phone Diagnostics</h4>
                        <p class="text-sm text-gray-600">Test and evaluate phone conditions</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Service Reports</h4>
                        <p class="text-sm text-gray-600">Generate technical service reports</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Quality Control</h4>
                        <p class="text-sm text-gray-600">Ensure service quality standards</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Inventory Check</h4>
                        <p class="text-sm text-gray-600">Verify phone conditions and stock</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Load technician dashboard data
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            loadTechnicianStats();
        });
        
        function loadUserInfo() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) {
                window.location.href = BASE + '/';
                return;
            }
            
            fetch(BASE + '/api/auth/validate', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('user-info').textContent = data.user.username;
                } else {
                    localStorage.removeItem('token');
                    localStorage.removeItem('sellapp_token');
                    window.location.href = BASE + '/';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                localStorage.removeItem('token');
                localStorage.removeItem('sellapp_token');
                window.location.href = BASE + '/';
            });
        }
        
        function loadTechnicianStats() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) return;
            
            fetch(BASE + '/api/dashboard/stats', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => response.json())
            .then(data => {
                // Safely update elements with null checks
                const elements = [
                    { id: 'active-repairs', value: data.total_repairs || 0 },
                    { id: 'completed-repairs', value: data.total_repairs || 0 },
                    { id: 'total-swaps', value: data.total_swaps || 0 },
                    { id: 'repair-revenue', value: '₵' + (data.repairs_revenue || 0).toFixed(2) }
                ];
                
                elements.forEach(element => {
                    const el = document.getElementById(element.id);
                    if (el && typeof el.textContent !== 'undefined') {
                        el.textContent = element.value;
                    }
                });
            })
            .catch(error => {
                console.error('Error loading technician stats:', error);
            });
        }
        
        function logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('sellapp_user');
            window.location.href = BASE + '/';
        }
        
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
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
            const toggle = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>
