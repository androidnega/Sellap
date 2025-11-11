<?php
// Salesperson Dashboard - Clean and Simple
// Get user info from session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$userInfo = $user['username'] ?? 'User';
$userRole = $user['role'] ?? 'salesperson';

$title = 'Dashboard';
$currentPage = 'dashboard';

ob_start();
?>
<div class="p-6">
    <!-- Welcome Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Welcome back, <?= htmlspecialchars($userInfo) ?>!</h1>
        <p class="text-gray-500 text-sm">Quick access to your tools</p>
    </div>
    
    <!-- Quick Actions - Smaller Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos" class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-4 hover:shadow-md hover:border-blue-300 transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="p-2 rounded-lg bg-blue-200 text-blue-700 mb-2 group-hover:bg-blue-300 transition-colors">
                    <i class="fas fa-cash-register text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Point of Sale</h3>
                <p class="text-xs text-gray-600">New sale</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/customers" class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-4 hover:shadow-md hover:border-green-300 transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="p-2 rounded-lg bg-green-200 text-green-700 mb-2 group-hover:bg-green-300 transition-colors">
                    <i class="fas fa-user-friends text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Customers</h3>
                <p class="text-xs text-gray-600">Manage</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/products" class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-4 hover:shadow-md hover:border-purple-300 transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="p-2 rounded-lg bg-purple-200 text-purple-700 mb-2 group-hover:bg-purple-300 transition-colors">
                    <i class="fas fa-boxes text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Products</h3>
                <p class="text-xs text-gray-600">Browse</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg border border-orange-200 p-4 hover:shadow-md hover:border-orange-300 transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="p-2 rounded-lg bg-orange-200 text-orange-700 mb-2 group-hover:bg-orange-300 transition-colors">
                    <i class="fas fa-exchange-alt text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Swaps</h3>
                <p class="text-xs text-gray-600">Process</p>
            </div>
        </a>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="p-2 rounded-lg bg-blue-200 text-blue-700">
                    <i class="fas fa-shopping-cart text-sm"></i>
                </div>
                <span class="text-xs font-medium text-blue-700">Sales Today</span>
            </div>
            <div class="text-2xl font-bold text-blue-900" id="today-sales">0</div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="p-2 rounded-lg bg-green-200 text-green-700">
                    <i class="fas fa-money-bill-wave text-sm"></i>
                </div>
                <span class="text-xs font-medium text-green-700">Revenue Today</span>
            </div>
            <div class="text-2xl font-bold text-green-900" id="today-revenue">₵0.00</div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="p-2 rounded-lg bg-purple-200 text-purple-700">
                    <i class="fas fa-boxes text-sm"></i>
                </div>
                <span class="text-xs font-medium text-purple-700">Total Products</span>
            </div>
            <div class="text-2xl font-bold text-purple-900" id="total-products">0</div>
        </div>
        
        <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg border border-teal-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="p-2 rounded-lg bg-teal-200 text-teal-700">
                    <i class="fas fa-user-friends text-sm"></i>
                </div>
                <span class="text-xs font-medium text-teal-700">Total Customers</span>
            </div>
            <div class="text-2xl font-bold text-teal-900" id="total-customers">0</div>
        </div>
    </div>
    
    <!-- Payment Status Section -->
    <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-money-bill-wave text-gray-500"></i>
            <h3 class="text-sm font-semibold text-gray-800">Payment Status</h3>
        </div>
        <div class="grid grid-cols-3 gap-6">
            <div class="text-center border-r border-gray-200 last:border-r-0">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <i class="fas fa-check-circle text-emerald-600"></i>
                    <p class="text-sm font-medium text-gray-700">Fully Paid</p>
                </div>
                <p class="text-3xl font-bold text-gray-900" id="fully-paid-count">0</p>
            </div>
            <div class="text-center border-r border-gray-200 last:border-r-0">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <i class="fas fa-exclamation-circle text-yellow-600"></i>
                    <p class="text-sm font-medium text-gray-700">Partial</p>
                </div>
                <p class="text-3xl font-bold text-gray-900" id="partial-payments-count">0</p>
            </div>
            <div class="text-center">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <i class="fas fa-times-circle text-red-600"></i>
                    <p class="text-sm font-medium text-gray-700">Unpaid</p>
                </div>
                <p class="text-3xl font-bold text-gray-900" id="unpaid-count">0</p>
            </div>
        </div>
    </div>
    
    <!-- Inventory Alerts Section -->
    <div id="inventory-alerts-section" class="mb-6" style="display: none;">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="fas fa-bell text-red-600 text-lg"></i>
                <h2 class="text-lg font-semibold text-gray-800">Inventory Alerts</h2>
            </div>
            <span id="inventory-alerts-count" class="bg-red-600 text-white text-xs font-semibold px-3 py-1 rounded-full">0 Items</span>
        </div>
        
        <!-- Yellow-bordered Alert Container -->
        <div class="border-2 border-yellow-400 rounded-lg bg-white p-4">
            <!-- Low Stock Alert Banner -->
            <div id="low-stock-banner" class="mb-4" style="display: none;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-black text-sm"></i>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-800">Low Stock</span>
                        <span id="low-stock-count" class="bg-yellow-400 text-gray-800 text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
                    </div>
                </div>
                <p class="text-sm text-gray-600 ml-11">Running low on inventory.</p>
            </div>
            
            <!-- Out of Stock Alert Banner -->
            <div id="out-of-stock-banner" class="mb-4" style="display: none;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-full bg-red-400 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-800">Out of Stock</span>
                        <span id="out-of-stock-count" class="bg-red-400 text-white text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
                    </div>
                </div>
                <p class="text-sm text-gray-600 ml-11">Items that need immediate restocking.</p>
            </div>
            
            <!-- Items Grid -->
            <div id="inventory-items-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                <!-- Items will be loaded here -->
            </div>
            
            <!-- View All Link -->
            <div id="view-all-link" class="text-center" style="display: none;">
                <a href="<?= BASE_URL_PATH ?>/dashboard/products" class="text-orange-600 hover:text-orange-700 text-sm font-medium inline-flex items-center gap-1">
                    View all <span id="view-all-count">0</span> alert items
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Load dashboard metrics
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardMetrics();
        loadInventoryAlerts();
    });
    
    function loadDashboardMetrics() {
        // Load sales metrics
        const baseUrl = typeof BASE_URL_PATH !== 'undefined' ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        fetch(baseUrl + '/api/dashboard/sales-metrics', {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Sales metrics response:', data);
            if (data.success && data.metrics) {
                document.getElementById('today-sales').textContent = data.metrics.sales || 0;
                document.getElementById('today-revenue').textContent = '₵' + parseFloat(data.metrics.revenue || 0).toFixed(2);
                
                // Update partial payment stats if available
                if (data.metrics.payment_stats) {
                    const stats = data.metrics.payment_stats;
                    document.getElementById('fully-paid-count').textContent = stats.fully_paid || 0;
                    document.getElementById('partial-payments-count').textContent = stats.partial || 0;
                    document.getElementById('unpaid-count').textContent = stats.unpaid || 0;
                }
            } else {
                console.error('Sales metrics error:', data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading sales metrics:', error);
        });
        
        // Load total products count
        fetch(baseUrl + '/api/pos/quick-stats', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                document.getElementById('total-products').textContent = data.data.total_items || 0;
            }
        })
        .catch(error => {
            console.error('Error loading products count:', error);
        });
        
        // Load total customers count
        fetch(baseUrl + '/api/customers/count', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count !== undefined) {
                document.getElementById('total-customers').textContent = data.count || 0;
            }
        })
        .catch(error => {
            console.error('Error loading customers count:', error);
        });
    }
    
    function loadInventoryAlerts() {
        console.log('Loading inventory alerts...');
        fetch('<?= BASE_URL_PATH ?>/api/dashboard/inventory-alerts', {
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        })
        .then(async response => {
            console.log('Inventory alerts response status:', response.status);
            const data = await response.json().catch(() => ({ success: false, error: 'Invalid JSON response' }));
            
            if (!response.ok) {
                console.error('Inventory alerts API error:', data);
                const errorMsg = data.message || data.error || `HTTP error! status: ${response.status}`;
                throw new Error(errorMsg);
            }
            
            return data;
        })
        .then(data => {
            console.log('Inventory alerts data:', data);
            console.log('Data type:', typeof data);
            console.log('Data keys:', Object.keys(data || {}));
            
            if (data && data.success) {
                const totalCount = data.total_count || 0;
                const lowStock = data.low_stock || [];
                const outOfStock = data.out_of_stock || [];
                
                console.log(`Found ${totalCount} alert items: ${outOfStock.length} out of stock, ${lowStock.length} low stock`);
                console.log('Low stock items:', lowStock);
                console.log('Out of stock items:', outOfStock);
                
                const alertsSection = document.getElementById('inventory-alerts-section');
                if (!alertsSection) {
                    console.error('Inventory alerts section element not found!');
                    return;
                }
                
                if (totalCount > 0) {
                    console.log('Displaying inventory alerts section with', totalCount, 'items');
                    alertsSection.style.display = 'block';
                    const countElement = document.getElementById('inventory-alerts-count');
                    if (countElement) {
                        countElement.textContent = totalCount + ' Items';
                    }
                    
                    // Show/hide banners
                    const lowStockBanner = document.getElementById('low-stock-banner');
                    const outOfStockBanner = document.getElementById('out-of-stock-banner');
                    
                    console.log('Low stock banner element:', lowStockBanner);
                    console.log('Out of stock banner element:', outOfStockBanner);
                    
                    if (lowStock.length > 0) {
                        console.log('Showing low stock banner with', lowStock.length, 'items');
                        if (lowStockBanner) {
                            lowStockBanner.style.display = 'block';
                            const lowStockCountEl = document.getElementById('low-stock-count');
                            if (lowStockCountEl) {
                                lowStockCountEl.textContent = lowStock.length;
                            }
                        }
                    } else {
                        if (lowStockBanner) lowStockBanner.style.display = 'none';
                    }
                    
                    if (outOfStock.length > 0) {
                        console.log('Showing out of stock banner with', outOfStock.length, 'items');
                        if (outOfStockBanner) {
                            outOfStockBanner.style.display = 'block';
                            const outOfStockCountEl = document.getElementById('out-of-stock-count');
                            if (outOfStockCountEl) {
                                outOfStockCountEl.textContent = outOfStock.length;
                            }
                        }
                    } else {
                        if (outOfStockBanner) outOfStockBanner.style.display = 'none';
                    }
                    
                    // Render items grid
                    const grid = document.getElementById('inventory-items-grid');
                    console.log('Inventory items grid element:', grid);
                    if (grid) {
                        grid.innerHTML = '';
                        
                        // Combine and limit to 6 items for display
                        const allItems = [...outOfStock, ...lowStock].slice(0, 6);
                        console.log('Rendering', allItems.length, 'items in grid');
                        
                        allItems.forEach((item, index) => {
                            console.log(`Rendering item ${index}:`, item);
                            const card = document.createElement('div');
                            card.className = 'bg-white rounded-lg border border-gray-200 p-3';
                            const isOutOfStock = (item.quantity || 0) <= 0;
                            card.innerHTML = `
                                <div class="font-semibold text-gray-800 text-sm mb-1">${escapeHtml(item.name || 'Unknown')}</div>
                                ${item.brand ? `<div class="text-xs text-gray-500 mb-1">${escapeHtml(item.brand)}</div>` : ''}
                                ${item.supplier ? `<div class="text-xs text-gray-500 mb-1">${escapeHtml(item.supplier)}</div>` : ''}
                                <div class="text-sm ${isOutOfStock ? 'text-red-600' : 'text-orange-600'} font-medium">
                                    Stock: ${item.quantity || 0} / Min: ${item.min_quantity || 5}
                                </div>
                            `;
                            grid.appendChild(card);
                        });
                    } else {
                        console.error('Inventory items grid element not found!');
                    }
                    
                    // Show view all link if there are more items
                    const viewAllLink = document.getElementById('view-all-link');
                    if (viewAllLink) {
                        if (totalCount > 6) {
                            viewAllLink.style.display = 'block';
                            document.getElementById('view-all-count').textContent = totalCount;
                        } else {
                            viewAllLink.style.display = 'none';
                        }
                    }
                } else {
                    alertsSection.style.display = 'none';
                    console.log('No inventory alerts to display');
                }
            } else {
                console.error('API returned error:', data.error || 'Unknown error');
                const alertsSection = document.getElementById('inventory-alerts-section');
                if (alertsSection) alertsSection.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading inventory alerts:', error);
            const alertsSection = document.getElementById('inventory-alerts-section');
            if (alertsSection) alertsSection.style.display = 'none';
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Refresh every 5 minutes
    setInterval(loadDashboardMetrics, 300000);
    setInterval(loadInventoryAlerts, 300000);
</script>
<?php
$content = ob_get_clean();

// Include the dashboard layout
include APP_PATH . '/Views/layouts/dashboard.php';
?>
