<?php
// User info will be available from the dashboard controller
// Authentication is handled by the controller before this view is rendered
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salesperson Dashboard - SellApp</title>
    <!-- Custom Favicon - Overrides XAMPP default favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📱</text></svg>">
    <script>
        // Base path for application URLs (auto-detected)
        window.APP_BASE_PATH = '<?php echo defined("BASE_URL_PATH") ? BASE_URL_PATH : ""; ?>';
        const BASE = window.APP_BASE_PATH || '';
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <div class="sidebar sidebar-salesperson w-64 p-4">
            <div class="flex items-center mb-8">
                <i class="fas fa-handshake text-2xl mr-3 sidebar-text"></i>
                <h1 class="text-xl font-bold sidebar-text">SellApp Sales</h1>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center mb-2">
                    <i class="fas fa-user-circle text-lg mr-2 sidebar-text"></i>
                    <span class="font-semibold sidebar-text">Salesperson</span>
                </div>
                <div class="text-sm sidebar-text opacity-75" id="user-info">Loading...</div>
            </div>
            
            <nav class="space-y-2">
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-tachometer-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-cash-register mr-3 sidebar-text"></i>
                    <span class="sidebar-text">POS Sales</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-users mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Customers</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-mobile-alt mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Phone Inventory</span>
                </a>
                <a href="#" class="sidebar-item flex items-center p-3 rounded-lg transition">
                    <i class="fas fa-chart-bar mr-3 sidebar-text"></i>
                    <span class="sidebar-text">Sales Reports</span>
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
                <h2 class="text-3xl font-bold text-gray-800">Sales Dashboard</h2>
                <p class="text-gray-600">Manage your sales and customer interactions</p>
            </div>
            
            <!-- Salesperson Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">My Sales</p>
                            <p class="text-2xl font-bold text-gray-900" id="my-sales">₵0.00</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-shopping-cart text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Transactions</p>
                            <p class="text-2xl font-bold text-gray-900" id="transactions">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Customers</p>
                            <p class="text-2xl font-bold text-gray-900" id="customers">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-mobile-alt text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Available Phones</p>
                            <p class="text-2xl font-bold text-gray-900" id="available-phones">0</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Salesperson Features Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Sales Features</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">POS Sales</h4>
                        <p class="text-sm text-gray-600">Process new sales transactions</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Customer Management</h4>
                        <p class="text-sm text-gray-600">Manage customer information</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Phone Inventory</h4>
                        <p class="text-sm text-gray-600">View and manage phone stock</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Sales Reports</h4>
                        <p class="text-sm text-gray-600">View your sales performance</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Customer History</h4>
                        <p class="text-sm text-gray-600">Track customer purchase history</p>
                    </div>
                    <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">Quick Actions</h4>
                        <p class="text-sm text-gray-600">Fast access to common tasks</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Load salesperson dashboard data
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            loadSalespersonStats();
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
        
        function loadSalespersonStats() {
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
                    { id: 'my-sales', value: '₵' + (data.total_revenue || 0).toFixed(2) },
                    { id: 'transactions', value: data.total_sales || 0 },
                    { id: 'customers', value: data.total_customers || 0 },
                    { id: 'available-phones', value: data.total_phones || 0 }
                ];
                
                elements.forEach(element => {
                    const el = document.getElementById(element.id);
                    if (el && typeof el.textContent !== 'undefined') {
                        el.textContent = element.value;
                    }
                });
            })
            .catch(error => {
                console.error('Error loading salesperson stats:', error);
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
