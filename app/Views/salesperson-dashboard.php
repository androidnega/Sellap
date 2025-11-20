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
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?= htmlspecialchars($userInfo) ?>!</h1>
        <p class="text-gray-600">Quick access to your sales tools</p>
    </div>
    
    <!-- Quick Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <a href="<?= BASE_URL_PATH ?>/dashboard/pos" class="bg-white rounded-lg border-2 border-gray-200 p-6 hover:border-blue-500 hover:shadow-lg transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                    <i class="fas fa-cash-register text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Point of Sale</h3>
                <p class="text-xs text-gray-500">New sale</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/customers" class="bg-white rounded-lg border-2 border-gray-200 p-6 hover:border-green-500 hover:shadow-lg transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-lg bg-green-100 text-green-600 flex items-center justify-center mb-3 group-hover:bg-green-500 group-hover:text-white transition-colors">
                    <i class="fas fa-user-friends text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Customers</h3>
                <p class="text-xs text-gray-500">Manage</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/products" class="bg-white rounded-lg border-2 border-gray-200 p-6 hover:border-purple-500 hover:shadow-lg transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center mb-3 group-hover:bg-purple-500 group-hover:text-white transition-colors">
                    <i class="fas fa-boxes text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Products</h3>
                <p class="text-xs text-gray-500">Browse</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="bg-white rounded-lg border-2 border-gray-200 p-6 hover:border-orange-500 hover:shadow-lg transition-all duration-200 group">
            <div class="flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center mb-3 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                    <i class="fas fa-exchange-alt text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Swaps</h3>
                <p class="text-xs text-gray-500">Process</p>
            </div>
        </a>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-lg border-2 border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <p class="text-xs font-medium text-gray-600 mb-1">Sales Today</p>
            <p class="text-2xl font-bold text-gray-900" id="today-sales">0</p>
        </div>
        
        <div class="bg-white rounded-lg border-2 border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <p class="text-xs font-medium text-gray-600 mb-1">Revenue Today</p>
            <p class="text-2xl font-bold text-gray-900" id="today-revenue">₵0.00</p>
        </div>
    </div>
    
    <!-- Additional Stats Cards: Total Swap, Swap Revenue, Sales Revenue, Total Sales -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded bg-orange-100 text-orange-600 flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-sm"></i>
                </div>
                <p class="text-xs text-gray-600">Total Swap (Monthly)</p>
            </div>
            <p class="text-xl font-bold text-gray-900" id="total-swaps">0</p>
        </div>
        
        <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-sm"></i>
                </div>
                <p class="text-xs text-gray-600">Swap Revenue (Monthly)</p>
            </div>
            <p class="text-xl font-bold text-gray-900" id="swap-revenue">₵0.00</p>
        </div>
        
        <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded bg-teal-100 text-teal-600 flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-sm"></i>
                </div>
                <p class="text-xs text-gray-600">Sales Revenue (Monthly)</p>
            </div>
            <p class="text-xl font-bold text-gray-900" id="sales-revenue">₵0.00</p>
        </div>
        
        <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded bg-cyan-100 text-cyan-600 flex items-center justify-center">
                    <i class="fas fa-receipt text-sm"></i>
                </div>
                <p class="text-xs text-gray-600">Total Sales (Monthly)</p>
            </div>
            <p class="text-xl font-bold text-gray-900" id="total-sales">0</p>
        </div>
    </div>
    
    <!-- Partial Payments Section (if enabled) -->
    <div id="partial-payments-section" class="border-2 border-orange-400 rounded-lg bg-white p-3 sm:p-4 mb-6" style="display: none;">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-orange-400 flex items-center justify-center">
                    <i class="fas fa-credit-card text-white text-sm"></i>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-gray-800">Pending Payments</span>
                    <span id="partial-payments-badge" class="bg-orange-400 text-white text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <a href="<?= BASE_URL_PATH ?>/dashboard/pos/partial-payments" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                View All <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
        <p class="text-xs sm:text-sm text-gray-600 ml-9 sm:ml-11 mb-4">Sales with outstanding payments that need attention.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-800 text-sm mb-1">Partial Payments</div>
                <div class="text-sm text-orange-600 font-semibold">
                    <span id="partial-payments-count">0</span> sales
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-800 text-sm mb-1">Pending Amount</div>
                <div class="text-sm text-red-600 font-semibold" id="pending-amount">₵0.00</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Ensure page is visible and load immediately
    function initializeSalespersonDashboard() {
        // Make page visible immediately
        if (document.body) {
            document.body.style.display = '';
        }
        // Load data immediately
        loadDashboardMetrics();
    }
    
    // Initialize immediately when ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSalespersonDashboard);
    } else {
        // DOM already loaded - initialize immediately
        initializeSalespersonDashboard();
    }
    
    function loadDashboardMetrics() {
        try {
        const baseUrl = typeof BASE_URL_PATH !== 'undefined' ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        
        // Load sales metrics
        fetch(baseUrl + '/api/dashboard/sales-metrics', {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
            return response.json();
        })
        .then(data => {
            try {
                if (data.success && data.metrics) {
                    const metrics = data.metrics;
                    
                    const safeUpdate = (id, value) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = value;
                    };
                    
                    // Update today's sales and revenue
                    safeUpdate('today-sales', metrics.sales || 0);
                    safeUpdate('today-revenue', '₵' + parseFloat(metrics.revenue || 0).toFixed(2));
                
                // Update partial payments section if available
                if (metrics.payment_stats) {
                    const partialSection = document.getElementById('partial-payments-section');
                    const partialCount = metrics.payment_stats.total_partial_payments || 0;
                    const pendingAmount = metrics.payment_stats.pending_amount || 0;
                    
                    if (partialSection) {
                        if (partialCount > 0 || pendingAmount > 0) {
                            partialSection.style.display = 'block';
                            const countEl = document.getElementById('partial-payments-count');
                            const badgeEl = document.getElementById('partial-payments-badge');
                            const amountEl = document.getElementById('pending-amount');
                            if (countEl) countEl.textContent = partialCount;
                            if (badgeEl) badgeEl.textContent = partialCount;
                            if (amountEl) amountEl.textContent = '₵' + parseFloat(pendingAmount).toFixed(2);
                        } else {
                            partialSection.style.display = 'none';
                        }
                    }
                } else {
                    // Hide partial payments section if not enabled
                    const partialSection = document.getElementById('partial-payments-section');
                    if (partialSection) partialSection.style.display = 'none';
                }
                } catch (error) {
                    console.error('Error processing sales metrics:', error);
                }
            }
        })
        .catch(error => {
            console.error('Error loading sales metrics:', error);
            // Ensure page is visible even on error
            if (document.body) {
                document.body.style.display = '';
            }
        });
        
        // Load dashboard stats for the new cards (Total Swap, Swap Revenue, Sales Revenue, Total Sales)
        fetch(baseUrl + '/api/dashboard/stats', {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(response => {
            console.log('Dashboard stats response:', response);
            
            // Handle response structure: {success: true, data: {...}}
            const data = response.data || response;
            
            console.log('Dashboard stats data:', data);
            
            if (response.success || data) {
                // Update new cards
                const totalSwapsEl = document.getElementById('total-swaps');
                const swapRevenueEl = document.getElementById('swap-revenue');
                const salesRevenueEl = document.getElementById('sales-revenue');
                const totalSalesEl = document.getElementById('total-sales');
                
                if (totalSwapsEl) {
                    const totalSwaps = data.total_swaps || 0;
                    totalSwapsEl.textContent = totalSwaps;
                    console.log('Total swaps:', totalSwaps);
                }
                if (swapRevenueEl) {
                    const swapRevenue = parseFloat(data.swap_revenue || 0);
                    swapRevenueEl.textContent = '₵' + swapRevenue.toFixed(2);
                    console.log('Swap revenue:', swapRevenue);
                }
                if (salesRevenueEl) {
                    const salesRevenue = parseFloat(data.all_time_sales_revenue || 0);
                    salesRevenueEl.textContent = '₵' + salesRevenue.toFixed(2);
                    console.log('Sales revenue:', salesRevenue);
                }
                if (totalSalesEl) {
                    const totalSales = data.all_time_total_sales || 0;
                    totalSalesEl.textContent = totalSales;
                    console.log('Total sales:', totalSales);
                }
            } else {
                console.warn('No data found in response:', response);
            }
        })
        .catch(error => {
            console.error('Error loading dashboard stats:', error);
            console.error('Error details:', error.message, error.stack);
            // Ensure page is visible even on error
            if (document.body) {
                document.body.style.display = '';
            }
        });
        } catch (error) {
            console.error('Error in loadDashboardMetrics:', error);
            // Ensure page is visible even on error
            if (document.body) {
                document.body.style.display = '';
            }
        }
    }
    
    // Refresh every 5 minutes
    setInterval(loadDashboardMetrics, 300000);
</script>
<?php
$content = ob_get_clean();

// Include the dashboard layout
include APP_PATH . '/Views/layouts/dashboard.php';
?>
