<?php
// User info will be available from the dashboard controller
// Authentication is handled by the controller before this view is rendered
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - SellApp</title>
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
    <link rel="stylesheet" href="<?php echo defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp'; ?>/assets/css/styles.css">
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
        <div class="sidebar sidebar-manager w-64 p-4">
            <div class="flex items-center mb-8">
                <i class="fas fa-user-tie text-2xl mr-3 sidebar-text"></i>
                <h1 class="text-xl font-bold sidebar-text">SellApp Manager</h1>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center mb-2">
                    <i class="fas fa-user-circle text-lg mr-2 sidebar-text"></i>
                    <span class="font-semibold sidebar-text">Manager</span>
                </div>
                <div class="text-sm sidebar-text opacity-75" id="user-info">Loading...</div>
            </div>
            
            <nav class="space-y-2">
                <a href="<?= BASE_URL_PATH ?>/dashboard" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-tachometer-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/staff" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-users mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Staff Management</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/categories" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-tags mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Categories</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/subcategories" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-layer-group mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Subcategories</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/brands" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-star mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Brands</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/inventory" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-boxes mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Product Management</span>
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/restock" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-warehouse mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Restock Inventory</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-chart-line mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Sales Reports</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-building mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Company Settings</span>
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
                <h2 class="text-3xl font-bold text-gray-800">Manager Dashboard</h2>
                <p class="text-gray-600">Manage your team and company operations</p>
            </div>
            
            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-revenue">₵0.00</p>
                            <p class="text-xs text-gray-500">All time</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-dollar-sign text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Gross Profit</p>
                            <p class="text-2xl font-bold text-gray-900" id="gross-profit">₵0.00</p>
                            <p class="text-xs text-gray-500" id="profit-margin">0% margin</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-boxes text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Inventory Value</p>
                            <p class="text-2xl font-bold text-gray-900" id="inventory-value">₵0.00</p>
                            <p class="text-xs text-gray-500" id="total-products">0 products</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-shopping-cart text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Sales</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-sales">0</p>
                            <p class="text-xs text-gray-500" id="average-sale">₵0.00 avg</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Performance -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Revenue</p>
                            <p class="text-2xl font-bold text-gray-900" id="today-revenue">₵0.00</p>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Sales</p>
                            <p class="text-2xl font-bold text-gray-900" id="today-sales">0</p>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-shopping-bag text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">This Month</p>
                            <p class="text-2xl font-bold text-gray-900" id="month-revenue">₵0.00</p>
                        </div>
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Out of Stock</p>
                            <p class="text-2xl font-bold text-gray-900" id="out-of-stock">0</p>
                            <p class="text-xs text-gray-500">products</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">In Stock</p>
                            <p class="text-2xl font-bold text-gray-900" id="in-stock">0</p>
                            <p class="text-xs text-gray-500">products</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-tools text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Repairs</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-repairs">0</p>
                            <p class="text-xs text-gray-500" id="repairs-revenue">₵0.00 revenue</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Customers</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-customers">0</p>
                            <p class="text-xs text-gray-500" id="team-members">0 team members</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Products Section -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Top Selling Products</h3>
                <div id="top-products-container">
                    <div class="text-center text-gray-500 py-4">Loading top products...</div>
                </div>
            </div>

            <!-- Manager Features Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Management Features</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Team Management</h4>
                        <p class="text-sm text-gray-600">Manage salespersons and technicians</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Sales Reports</h4>
                        <p class="text-sm text-gray-600">View detailed sales analytics</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Company Analytics</h4>
                        <p class="text-sm text-gray-600">Monitor company performance</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">User Management</h4>
                        <p class="text-sm text-gray-600">Add and manage team members</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Performance Tracking</h4>
                        <p class="text-sm text-gray-600">Track team performance metrics</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Company Settings</h4>
                        <p class="text-sm text-gray-600">Configure company preferences</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Load manager dashboard data
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            loadManagerStats();
        });
        
        function updateTopProducts(products) {
            const container = document.getElementById('top-products-container');
            
            if (!products || products.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-4">No sales data available</div>';
                return;
            }
            
            const productsHtml = products.map((product, index) => `
                <div class="flex items-center justify-between p-3 border rounded-lg mb-2">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                            ${index + 1}
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">${product.name}</h4>
                            <p class="text-sm text-gray-600">${product.total_sold} units sold</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-green-600">₵${parseFloat(product.total_revenue || 0).toFixed(2)}</p>
                        <p class="text-sm text-gray-500">revenue</p>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = productsHtml;
        }
        
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
                    const userInfoEl = document.getElementById('user-info');
                    if (userInfoEl && typeof userInfoEl.textContent !== 'undefined') {
                        userInfoEl.textContent = data.user.username;
                    }
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
        
        function loadManagerStats() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) return;
            
            fetch(BASE + '/api/dashboard/stats?t=' + Date.now(), {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => response.json())
            .then(data => {
                // Debug logging
                console.log('Manager Dashboard Stats Data:', data);
                
                // Safely update elements with null checks
                const elements = [
                    // Key Performance Indicators
                    { id: 'total-revenue', value: '₵' + (data.total_revenue || 0).toFixed(2) },
                    { id: 'gross-profit', value: '₵' + (data.gross_profit || 0).toFixed(2) },
                    { id: 'profit-margin', value: (data.profit_margin || 0) + '% margin' },
                    { id: 'inventory-value', value: '₵' + (data.inventory_value || 0).toFixed(2) },
                    { id: 'total-products', value: (data.total_products || 0) + ' products' },
                    { id: 'total-sales', value: data.total_sales || 0 },
                    { id: 'average-sale', value: '₵' + (data.average_sale || 0).toFixed(2) + ' avg' },
                    
                    // Today's Performance
                    { id: 'today-revenue', value: '₵' + (data.today_revenue || 0).toFixed(2) },
                    { id: 'today-sales', value: data.today_sales || 0 },
                    { id: 'month-revenue', value: '₵' + (data.month_revenue || 0).toFixed(2) },
                    
                    // Business Metrics
                    { id: 'out-of-stock', value: data.out_of_stock_products || 0 },
                    { id: 'in-stock', value: data.in_stock_products || 0 },
                    { id: 'total-repairs', value: data.total_repairs || 0 },
                    { id: 'repairs-revenue', value: '₵' + (data.repairs_revenue || 0).toFixed(2) + ' revenue' },
                    { id: 'total-customers', value: data.total_customers || 0 },
                    { id: 'team-members', value: (data.total_users || 0) + ' team members' }
                ];
                
                // Debug: Log each element being updated
                console.log('Updating dashboard elements:', elements);
                
                elements.forEach(element => {
                    const el = document.getElementById(element.id);
                    if (el && typeof el.textContent !== 'undefined') {
                        el.textContent = element.value;
                    }
                });
                
                // Update top products
                updateTopProducts(data.top_products || []);
            })
            .catch(error => {
                console.error('Error loading manager stats:', error);
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
