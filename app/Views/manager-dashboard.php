<?php
// Manager Dashboard - Company-specific metrics and performance
$title = 'Manager Dashboard';
$userRole = 'manager';
$currentPage = 'dashboard';

// Get user data from session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userData = $_SESSION['user'] ?? null;
$companyId = $userData['company_id'] ?? null;

// Ensure metrics are set (defaults if not calculated)
$today_revenue = $today_revenue ?? 0;
$today_sales = $today_sales ?? 0;
$active_repairs = $active_repairs ?? 0;
$pending_swaps = $pending_swaps ?? 0;

ob_start();

// Show New Year message in January
use App\Helpers\DashboardWidgets;
$currentYear = date('Y');
$showNewYear = DashboardWidgets::shouldShowNewYearMessage();
$stats = DashboardWidgets::getNewYearMessageStats($companyId, null, 'manager');
?>
            <?php if ($showNewYear): ?>
            <div class="mb-3 bg-gradient-to-r from-yellow-400 via-pink-500 to-purple-600 rounded-lg shadow p-3 text-white">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="text-2xl">🎉</div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold">Happy New Year <?= $currentYear ?>!</h2>
                            <p class="text-xs sm:text-sm opacity-90">
                                <?php
                                if ($stats['value'] > 0 || $stats['amount'] > 0) {
                                    if ($stats['type'] === 'pending_payments') {
                                        echo "You have <strong>" . number_format($stats['value']) . "</strong> " . $stats['label'] . " worth <strong>₵" . number_format($stats['amount'], 2) . "</strong>!";
                                    } elseif ($stats['type'] === 'revenue') {
                                        echo "You've made <strong>₵" . number_format($stats['amount'], 2) . "</strong> in revenue today!";
                                    } elseif ($stats['type'] === 'swaps') {
                                        echo "You've made <strong>" . number_format($stats['value']) . "</strong> " . $stats['label'] . " today!";
                                    } else {
                                        echo "You've made <strong>" . number_format($stats['value']) . "</strong> " . $stats['label'] . " today!";
                                    }
                                } else {
                                    echo 'Wishing you a prosperous year ahead!';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="mb-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Company Dashboard</h2>
                <p class="text-sm sm:text-base text-gray-600">Monitor your company's performance and operations</p>
            </div>
            
            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-4">
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-hidden">
                    <div class="flex items-center">
                        <div class="p-2 sm:p-3 rounded-full bg-green-100 text-green-600 flex-shrink-0">
                            <i class="fas fa-chart-line text-lg sm:text-xl"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Today's Revenue</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900 truncate">₵<?= number_format($today_revenue, 2) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-hidden">
                    <div class="flex items-center">
                        <div class="p-2 sm:p-3 rounded-full bg-blue-100 text-blue-600 flex-shrink-0">
                            <i class="fas fa-shopping-cart text-lg sm:text-xl"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Today's Sales</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($today_sales) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-hidden">
                    <div class="flex items-center">
                        <div class="p-2 sm:p-3 rounded-full bg-emerald-100 text-emerald-600 flex-shrink-0">
                            <i class="fas fa-dollar-sign text-lg sm:text-xl"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Total Profit</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900 truncate" id="total-profit">₵0.00</p>
                            <p class="text-xs text-gray-500 truncate">Realized gains</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-hidden">
                    <div class="flex items-center">
                        <div class="p-2 sm:p-3 rounded-full bg-purple-100 text-purple-600 flex-shrink-0">
                            <i class="fas fa-tools text-lg sm:text-xl"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Active Repairs</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($active_repairs) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-hidden">
                    <div class="flex items-center">
                        <div class="p-2 sm:p-3 rounded-full bg-yellow-100 text-yellow-600 flex-shrink-0">
                            <i class="fas fa-exchange-alt text-lg sm:text-xl"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Pending Swaps</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($pending_swaps) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Status Card - Combined -->
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-8" id="payment-status-card" style="display: none;">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm sm:text-base font-semibold text-gray-800">Payment Status</h3>
                    <i class="fas fa-money-bill-wave text-gray-400"></i>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-check-circle text-green-600 text-sm mr-1"></i>
                            <p class="text-xs text-gray-600">Fully Paid</p>
                        </div>
                        <p class="text-lg sm:text-xl font-bold text-gray-900" id="fully-paid-count">0</p>
                    </div>
                    <div class="text-center">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-exclamation-circle text-yellow-600 text-sm mr-1"></i>
                            <p class="text-xs text-gray-600">Partial</p>
                        </div>
                        <p class="text-lg sm:text-xl font-bold text-gray-900" id="partial-payments-count">0</p>
                    </div>
                    <div class="text-center">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-times-circle text-red-600 text-sm mr-1"></i>
                            <p class="text-xs text-gray-600">Unpaid</p>
                        </div>
                        <p class="text-lg sm:text-xl font-bold text-gray-900" id="unpaid-count">0</p>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Alerts Section -->
            <div id="inventory-alerts-section" class="mb-8 overflow-hidden" style="display: none;">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-3 gap-2">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-bell text-red-600 text-base sm:text-lg"></i>
                        <h2 class="text-base sm:text-lg font-semibold text-gray-800">Inventory Alerts</h2>
                    </div>
                    <span id="inventory-alerts-count" class="bg-red-600 text-white text-xs font-semibold px-2 sm:px-3 py-1 rounded-full whitespace-nowrap">0 Items</span>
                </div>
                
                <!-- Yellow-bordered Alert Container -->
                <div class="border-2 border-yellow-400 rounded-lg bg-white p-3 sm:p-4 overflow-hidden">
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
                        <p class="text-xs sm:text-sm text-gray-600 ml-9 sm:ml-11">Running low on inventory.</p>
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
                        <p class="text-xs sm:text-sm text-gray-600 ml-9 sm:ml-11">Items that need immediate restocking.</p>
                    </div>
                    
                    <!-- Items Grid -->
                    <div id="inventory-items-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                        <!-- Items will be loaded here -->
                    </div>
                    
                    <!-- View All Link -->
                    <div id="view-all-link" class="text-center" style="display: none;">
                        <a href="<?= (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') ?>/dashboard/inventory" class="text-orange-600 hover:text-orange-700 text-sm font-medium inline-flex items-center gap-1">
                            View all <span id="view-all-count">0</span> alert items
                            <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Charts Section (Module Toggle) -->
            <div id="dashboard-charts-section" class="mb-8" style="display: block !important;">
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-800">Performance Trends</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Charts Module</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="charts-module-toggle" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-blue-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        <!-- Bar Chart - Sales Trends -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Sales Performance (Last 7 Days)</h4>
                            <div class="relative" style="height: 300px;">
                                <canvas id="salesTrendsChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Pie Chart - Activity Distribution -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Activity Distribution</h4>
                            <div class="relative" style="height: 300px;">
                                <canvas id="activityDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity and Quick Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
                <!-- Recent Sales -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Recent Sales</h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div id="recent-sales" class="space-y-3">
                            <!-- Recent sales will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <script>
        // Ensure Tailwind processes the content when it loads
        function ensureTailwindStyles() {
            // Wait for Tailwind to be available
            if (window.tailwind) {
                // Force Tailwind to process the DOM
                if (typeof window.tailwind.refresh === 'function') {
                    window.tailwind.refresh();
                }
                return true;
            }
            return false;
        }
        
        // Function to check and apply Tailwind styles with retries
        function applyTailwindStylesWithRetry(retries = 10) {
            if (ensureTailwindStyles()) {
                return;
            }
            if (retries > 0) {
                setTimeout(function() {
                    applyTailwindStylesWithRetry(retries - 1);
                }, 200);
            }
        }
        
        // Try to ensure Tailwind styles are applied
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Wait a bit for Tailwind to load, then retry if needed
                setTimeout(function() {
                    applyTailwindStylesWithRetry();
                }, 100);
            });
        } else {
            // DOM already loaded
            setTimeout(function() {
                applyTailwindStylesWithRetry();
            }, 100);
        }
        
        // Listen for Tailwind loaded event
        window.addEventListener('tailwindLoaded', function() {
            ensureTailwindStyles();
        });
        
        // Also check periodically if Tailwind becomes available
        let tailwindCheckInterval = setInterval(function() {
            if (window.tailwind && !window.tailwindStylesApplied) {
                ensureTailwindStyles();
                window.tailwindStylesApplied = true;
                clearInterval(tailwindCheckInterval);
            }
        }, 500);
        
        // Clear interval after 10 seconds
        setTimeout(function() {
            clearInterval(tailwindCheckInterval);
        }, 10000);
        
        // Utilities
        function getToken() {
            return localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        }
        function getAuthHeaders() {
            const token = getToken();
            return {
                'Content-Type': 'application/json',
                'Authorization': token ? ('Bearer ' + token) : ''
            };
        }

        // Ensure PHP session is synced with localStorage token (prevents auto-logout)
        async function ensureSessionFromLocalToken() {
            const token = getToken();
            if (!token) return false;
            try {
                const res = await fetch(BASE + '/api/auth/validate-local-token', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ token })
                });
                const data = await res.json();
                return !!data.success;
            } catch (e) {
                console.warn('validate-local-token failed', e);
                return false;
            }
        }

        // Ensure page is visible immediately
        function initializeManagerDashboard() {
            if (document.body) {
                document.body.style.display = '';
            }
        }
        
        // Initialize page visibility immediately
        initializeManagerDashboard();
        
        // Load manager dashboard data
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const token = getToken();
                if (!token) {
                    window.location.href = BASE + '/';
                    return;
                }
                // First, make sure session is aligned with token
                await ensureSessionFromLocalToken();

                await loadUserInfo();
                loadCompanyMetrics();
                loadProfitStats(); // Load profit immediately
                loadRecentActivity();
            
            // Set up toggle event listener
            const toggle = document.getElementById('charts-module-toggle');
            if (toggle) {
                toggle.addEventListener('change', function() {
                    window.toggleChartsModule(this.checked);
                });
                // Enable toggle by default
                toggle.checked = true;
            }
            
            // Load charts immediately (don't wait for module check)
            if (typeof Chart !== 'undefined') {
                loadCharts();
            } else {
                // Wait for Chart.js to load
                const checkChartJs = setInterval(() => {
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkChartJs);
                        loadCharts();
                    }
                }, 100);
                
                // Stop checking after 5 seconds
                setTimeout(() => clearInterval(checkChartJs), 5000);
            }
            
            // Check module status (for persistence)
            setTimeout(() => {
                checkChartsModuleStatus();
            }, 1000);

            // Keep session alive to prevent auto-logout
            setInterval(ensureSessionFromLocalToken, 4 * 60 * 1000); // every 4 minutes
        });
        
        // Chart instances
        let salesTrendsChart = null;
        let activityDistributionChart = null;
        
        // Check if charts module is enabled
        async function checkChartsModuleStatus() {
            try {
                const user = await getUserInfo();
                if (!user || !user.company_id) {
                    // If no user, show charts anyway and enable toggle
                    document.getElementById('charts-module-toggle').checked = true;
                    loadCharts();
                    return;
                }
                
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const res = await fetch(baseUrl + '/api/dashboard/check-module?module_key=dashboard_charts', {
                    headers: getAuthHeaders(),
                    credentials: 'same-origin' // Include session cookies
                });
                
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.enabled) {
                        document.getElementById('charts-module-toggle').checked = true;
                        loadCharts();
                    } else {
                        // Module not enabled, but show it anyway (user can enable)
                        document.getElementById('charts-module-toggle').checked = false;
                        loadCharts(); // Load charts anyway
                    }
                } else {
                    // If API fails, show charts anyway
                    document.getElementById('charts-module-toggle').checked = true;
                    loadCharts();
                }
            } catch (error) {
                console.error('Error checking charts module:', error);
                // On error, show charts anyway
                document.getElementById('charts-module-toggle').checked = true;
                loadCharts();
            }
        }
        
        // Toggle charts module - make it global
        window.toggleChartsModule = async function(enabled) {
            try {
                const user = await getUserInfo();
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                
                if (user && user.company_id) {
                    const res = await fetch(baseUrl + '/api/dashboard/toggle-module', {
                        method: 'POST',
                        headers: getAuthHeaders(),
                        credentials: 'same-origin', // Include session cookies
                        body: JSON.stringify({
                            module_key: 'dashboard_charts',
                            enabled: enabled
                        })
                    });
                    
                    const data = await res.json();
                    if (data.success) {
                        if (enabled) {
                            loadCharts();
                        } else {
                            destroyCharts();
                        }
                    } else {
                        showNotification(data.error || 'Failed to toggle module', 'error');
                        document.getElementById('charts-module-toggle').checked = !enabled;
                    }
                } else {
                    // If no user, just toggle charts locally
                    if (enabled) {
                        loadCharts();
                    } else {
                        destroyCharts();
                    }
                }
            } catch (error) {
                console.error('Error toggling charts module:', error);
                // On error, just toggle locally
                if (enabled) {
                    loadCharts();
                } else {
                    destroyCharts();
                }
            }
        }
        
        // Get user info helper
        async function getUserInfo() {
            try {
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const res = await fetch(baseUrl + '/api/auth/validate', {
                    headers: getAuthHeaders()
                });
                const data = await res.json();
                return data.user || null;
            } catch (error) {
                return null;
            }
        }
        
        // Show notification helper
        function showNotification(message, type = 'success') {
            // Simple notification - you can enhance this
            alert(message);
        }
        
        // Load charts data and render
        async function loadCharts() {
            console.log('Loading charts...');
            const salesCtx = document.getElementById('salesTrendsChart');
            const activityCtx = document.getElementById('activityDistributionChart');
            
            if (!salesCtx || !activityCtx) {
                console.error('Chart canvas elements not found!', { salesCtx: !!salesCtx, activityCtx: !!activityCtx });
                return;
            }
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded!');
                return;
            }
            
            try {
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                console.log('Fetching charts data from:', baseUrl + '/api/dashboard/charts-data');
                
                const res = await fetch(baseUrl + '/api/dashboard/charts-data', {
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin' // Include session cookies
                });
                
                console.log('Charts data response status:', res.status);
                
                if (!res.ok) {
                    // Get the error response body
                    const errorText = await res.text();
                    console.error('Failed to load charts data:', res.status);
                    console.error('Error response body:', errorText);
                    
                    // Try to parse as JSON to see debug info
                    try {
                        const errorData = JSON.parse(errorText);
                        console.error('Error details:', errorData);
                        if (errorData.debug) {
                            console.error('Debug info:', errorData.debug);
                            alert(`Charts Error: ${errorData.debug.message}\nFile: ${errorData.debug.file}\nLine: ${errorData.debug.line}`);
                        }
                    } catch (e) {
                        console.error('Could not parse error response');
                    }
                    
                    // Show sample data on error
                    renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                    renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
                    return;
                }
                
                const data = await res.json();
                console.log('Charts data received:', data);
                
                if (data.success && data.data) {
                    renderSalesTrendsChart(data.data.sales_trends || { labels: [], revenue: [] });
                    renderActivityDistributionChart(data.data.activity_distribution || { labels: [], values: [] });
                    console.log('Charts rendered successfully');
                } else {
                    console.error('Invalid chart data:', data);
                    // Show sample data if API fails
                    renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                    renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
                }
            } catch (error) {
                console.error('Error loading charts:', error);
                // Show sample data on error
                renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
            }
        }
        
        // Render Sales Trends Bar Chart
        function renderSalesTrendsChart(data) {
            const ctx = document.getElementById('salesTrendsChart');
            if (!ctx) return;
            
            // Destroy existing chart
            if (salesTrendsChart) {
                salesTrendsChart.destroy();
            }
            
            salesTrendsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Revenue (₵)',
                        data: data.revenue || [],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',   // Blue
                            'rgba(147, 51, 234, 0.8)',   // Purple
                            'rgba(34, 197, 94, 0.8)',    // Green
                            'rgba(245, 158, 11, 0.8)',   // Yellow
                            'rgba(239, 68, 68, 0.8)',    // Red
                            'rgba(236, 72, 153, 0.8)',   // Pink
                            'rgba(14, 165, 233, 0.8)'    // Sky
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(147, 51, 234, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(236, 72, 153, 1)',
                            'rgba(14, 165, 233, 1)'
                        ],
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₵' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toFixed(0);
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // Render Activity Distribution Pie Chart
        function renderActivityDistributionChart(data) {
            const ctx = document.getElementById('activityDistributionChart');
            if (!ctx) return;
            
            // Destroy existing chart
            if (activityDistributionChart) {
                activityDistributionChart.destroy();
            }
            
            activityDistributionChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',   // Blue - Sales
                            'rgba(147, 51, 234, 0.8)',   // Purple - Repairs
                            'rgba(34, 197, 94, 0.8)',    // Green - Swaps
                            'rgba(245, 158, 11, 0.8)',   // Yellow - Customers
                            'rgba(239, 68, 68, 0.8)',    // Red - Products
                            'rgba(236, 72, 153, 0.8)'    // Pink - Technicians
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(147, 51, 234, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(236, 72, 153, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Destroy charts
        function destroyCharts() {
            if (salesTrendsChart) {
                salesTrendsChart.destroy();
                salesTrendsChart = null;
            }
            if (activityDistributionChart) {
                activityDistributionChart.destroy();
                activityDistributionChart = null;
            }
        }
        
        async function loadUserInfo() {
            const token = getToken();
            if (!token) {
                window.location.href = BASE + '/';
                return;
            }
            try {
                const res = await fetch(BASE + '/api/auth/validate', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await res.json();
                if (data.success && data.user) {
                    const userInfoEl = document.getElementById('user-info');
                    const companyInfoEl = document.getElementById('company-info');
                    if (userInfoEl) userInfoEl.textContent = data.user.username;
                    if (companyInfoEl) companyInfoEl.textContent = data.user.company_name || 'Company';
                } else {
                    console.debug('Validate response:', data);
                    localStorage.removeItem('token');
                    localStorage.removeItem('sellapp_token');
                    window.location.href = BASE + '/';
                }
            } catch (error) {
                console.error('Validate error:', error);
                localStorage.removeItem('token');
                localStorage.removeItem('sellapp_token');
                window.location.href = BASE + '/';
            }
        }
        
        function loadCompanyMetrics() {
            // Main KPI metrics are now rendered server-side in PHP
            // This function is kept for payment stats and other dynamic content
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) return;
            
            fetch(BASE + '/api/dashboard/company-metrics', { headers: getAuthHeaders() })
            .then(async response => {
                // Get response text first to handle errors properly
                const responseText = await response.text();
                
                if (!response.ok) {
                    // Try to parse error as JSON, but don't display raw JSON
                    try {
                        const errorData = JSON.parse(responseText);
                        const errorMessage = errorData.error || errorData.message || 'Failed to load metrics';
                        throw new Error(errorMessage);
                    } catch (parseError) {
                        throw new Error('Failed to load company metrics');
                    }
                }
                
                // Parse JSON response
                try {
                    return JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Invalid response from server');
                }
            })
            .then(data => {
                if (data.success) {
                    // Update payment stats if available (still dynamic)
                    if (data.metrics.payment_stats) {
                        const stats = data.metrics.payment_stats;
                        const paymentCard = document.getElementById('payment-status-card');
                        if (paymentCard) {
                            paymentCard.style.display = 'block';
                            document.getElementById('fully-paid-count').textContent = stats.fully_paid || 0;
                            document.getElementById('partial-payments-count').textContent = stats.partial || 0;
                            document.getElementById('unpaid-count').textContent = stats.unpaid || 0;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error loading company metrics:', error.message || error);
                // Don't display raw JSON or error details in UI
            });
            
            // Load profit from stats API
            loadProfitStats();
        }
        
        function loadProfitStats() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) return;
            
            fetch(BASE + '/api/dashboard/stats?t=' + Date.now(), {
                headers: getAuthHeaders()
            })
            .then(async response => {
                const responseText = await response.text();
                
                if (!response.ok) {
                    try {
                        const errorData = JSON.parse(responseText);
                        throw new Error(errorData.error || errorData.message || 'Failed to load stats');
                    } catch (parseError) {
                        throw new Error('Failed to load stats');
                    }
                }
                
                try {
                    return JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Invalid response from server');
                }
            })
            .then(data => {
                if (data.success && data.data) {
                    // Debug logging
                    console.log('Profit Stats API Response:', {
                        total_profit: data.data.total_profit,
                        sales_profit: data.data.sales_profit,
                        swap_profit: data.data.swap_profit,
                        swaps: data.data.swaps
                    });
                    
                    // Update total profit
                    const totalProfit = data.data.total_profit || 0;
                    const profitElement = document.getElementById('total-profit');
                    if (profitElement) {
                        profitElement.textContent = '₵' + parseFloat(totalProfit).toFixed(2);
                        console.log('Updated profit element with: ₵' + parseFloat(totalProfit).toFixed(2));
                    }
                } else {
                    console.error('Profit Stats API Error:', data);
                }
            })
            .catch(error => {
                console.error('Error loading profit stats:', error.message || error);
            });
        }
        

        function loadRecentActivity() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            if (!token) return;
            
            // Load recent sales
            fetch(BASE + '/api/dashboard/recent-sales', { headers: getAuthHeaders() })
            .then(async response => {
                // Get response text first to handle errors properly
                const responseText = await response.text();
                
                if (!response.ok) {
                    // Try to parse error as JSON, but don't display raw JSON
                    try {
                        const errorData = JSON.parse(responseText);
                        const errorMessage = errorData.error || errorData.message || 'Failed to load recent sales';
                        throw new Error(errorMessage);
                    } catch (parseError) {
                        throw new Error('Failed to load recent sales');
                    }
                }
                
                // Parse JSON response
                try {
                    return JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Invalid response from server');
                }
            })
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('recent-sales');
                    container.innerHTML = '';
                    
                    data.sales.forEach(sale => {
                        const saleItem = document.createElement('div');
                        saleItem.className = 'flex justify-between items-center py-2 border-b border-gray-100';
                        saleItem.innerHTML = `
                            <div>
                                <p class="text-sm font-medium text-gray-900">${escapeHtml(sale.customer_name || 'Walk-in')}</p>
                                <p class="text-xs text-gray-500">${escapeHtml(sale.time || '')}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-green-600">₵${escapeHtml(String(sale.amount || 0))}</p>
                                <p class="text-xs text-gray-500">${escapeHtml(String(sale.items || 0))} items</p>
                            </div>
                        `;
                        container.appendChild(saleItem);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading recent sales:', error.message || error);
                // Don't display raw JSON or error details in UI
            });
            
            // Load inventory alerts
            loadInventoryAlerts();
        }
        
        function loadInventoryAlerts() {
            console.log('Loading inventory alerts...');
            fetch(BASE + '/api/dashboard/inventory-alerts', { headers: getAuthHeaders() })
            .then(async response => {
                console.log('Inventory alerts response status:', response.status);
                
                // Get response text first to handle errors properly
                const responseText = await response.text();
                
                if (!response.ok) {
                    // Try to parse error as JSON, but don't display raw JSON
                    try {
                        const errorData = JSON.parse(responseText);
                        const errorMsg = errorData.message || errorData.error || `HTTP error! status: ${response.status}`;
                        throw new Error(errorMsg);
                    } catch (parseError) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }
                
                // Parse JSON response
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Invalid JSON response from inventory alerts API');
                    throw new Error('Invalid response from server');
                }
                
                return data;
            })
            .then(data => {
                console.log('Inventory alerts data:', data);
                if (data.success) {
                    const totalCount = data.total_count || 0;
                    const lowStock = data.low_stock || [];
                    const outOfStock = data.out_of_stock || [];
                    
                    console.log(`Found ${totalCount} alert items: ${outOfStock.length} out of stock, ${lowStock.length} low stock`);
                    
                    const alertsSection = document.getElementById('inventory-alerts-section');
                    if (!alertsSection) {
                        console.error('Inventory alerts section element not found!');
                        return;
                    }
                    
                    if (totalCount > 0) {
                        alertsSection.style.display = 'block';
                        document.getElementById('inventory-alerts-count').textContent = totalCount + ' Items';
                        
                        // Show/hide banners
                        const lowStockBanner = document.getElementById('low-stock-banner');
                        const outOfStockBanner = document.getElementById('out-of-stock-banner');
                        
                        if (lowStock.length > 0) {
                            if (lowStockBanner) {
                                lowStockBanner.style.display = 'block';
                                document.getElementById('low-stock-count').textContent = lowStock.length;
                            }
                        } else {
                            if (lowStockBanner) lowStockBanner.style.display = 'none';
                        }
                        
                        if (outOfStock.length > 0) {
                            if (outOfStockBanner) {
                                outOfStockBanner.style.display = 'block';
                                document.getElementById('out-of-stock-count').textContent = outOfStock.length;
                            }
                        } else {
                            if (outOfStockBanner) outOfStockBanner.style.display = 'none';
                        }
                        
                        // Render items grid
                        const grid = document.getElementById('inventory-items-grid');
                        if (grid) {
                            grid.innerHTML = '';
                            
                            // Combine and limit to 6 items for display
                            const allItems = [...outOfStock, ...lowStock].slice(0, 6);
                            
                            allItems.forEach(item => {
                                const card = document.createElement('div');
                                card.className = 'bg-white rounded-lg border border-gray-200 p-3';
                                const isOutOfStock = item.quantity <= 0;
                                card.innerHTML = `
                                    <div class="font-semibold text-gray-800 text-sm mb-1">${escapeHtml(item.name)}</div>
                                    ${item.brand ? `<div class="text-xs text-gray-500 mb-1">${escapeHtml(item.brand)}</div>` : ''}
                                    ${item.supplier ? `<div class="text-xs text-gray-500 mb-1">${escapeHtml(item.supplier)}</div>` : ''}
                                    <div class="text-sm ${isOutOfStock ? 'text-red-600' : 'text-orange-600'} font-medium">
                                        Stock: ${item.quantity} / Min: ${item.min_quantity}
                                    </div>
                                `;
                                grid.appendChild(card);
                            });
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
                    // Handle API error response without displaying raw JSON
                    const errorMessage = data.error || data.message || 'Unknown error';
                    console.error('API returned error:', errorMessage);
                    const alertsSection = document.getElementById('inventory-alerts-section');
                    if (alertsSection) alertsSection.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error loading inventory alerts:', error.message || error);
                const alertsSection = document.getElementById('inventory-alerts-section');
                if (alertsSection) alertsSection.style.display = 'none';
                // Don't display raw JSON or error details in UI
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-refresh every 5 minutes (metrics only)
        setInterval(loadCompanyMetrics, 300000);
        setInterval(loadProfitStats, 300000); // Refresh profit every 5 minutes
        setInterval(loadInventoryAlerts, 300000);
    </script>
<?php
$content = ob_get_clean();

// Include the dashboard layout
include APP_PATH . '/Views/layouts/dashboard.php';
?>