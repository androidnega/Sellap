<?php
// Comprehensive Manager Dashboard - Full Stats Overview
$title = 'Manager Dashboard';
$userRole = 'manager';
$currentPage = 'dashboard';
$userInfo = 'Loading...';
$companyInfo = 'Loading...';

ob_start();
?>
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Company Dashboard</h2>
                    <p class="text-sm sm:text-base text-gray-600">Complete overview of your business performance</p>
                </div>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                    <!-- Date Range Filter -->
                    <input type="date" id="date-from" class="px-2 sm:px-3 py-2 border border-gray-300 rounded-md text-xs sm:text-sm flex-1 sm:flex-none" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    <input type="date" id="date-to" class="px-2 sm:px-3 py-2 border border-gray-300 rounded-md text-xs sm:text-sm flex-1 sm:flex-none" value="<?= date('Y-m-d') ?>">
                    <button onclick="loadManagerOverview()" class="px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-xs sm:text-sm whitespace-nowrap">
                        <i class="fas fa-filter mr-1 sm:mr-2"></i><span class="hidden sm:inline">Apply Filter</span><span class="sm:hidden">Filter</span>
                    </button>
                    <button id="export-dashboard-btn" class="px-3 sm:px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-xs sm:text-sm whitespace-nowrap">
                        <i class="fas fa-download mr-1 sm:mr-2"></i><span class="hidden sm:inline">Export</span><span class="sm:hidden">Export</span>
                    </button>
                </div>
            </div>
            
            <!-- Loading Indicator -->
            <div id="dashboard-loading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                <p class="mt-4 text-gray-600">Loading dashboard data...</p>
            </div>
            
            <!-- Dashboard Content -->
            <div id="dashboard-content" class="hidden">
                <!-- 1. OVERVIEW METRICS (Top Summary Cards) -->
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-2 sm:gap-3 md:gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Total Products</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-total-products">0</p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-blue-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-box text-blue-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Total Customers</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-total-customers">0</p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-indigo-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-users text-indigo-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Active Staff</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-active-staff">0</p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-red-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-user-tie text-red-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Today's Repairs</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-today-repairs">0</p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-purple-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-tools text-purple-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Today's Sales</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-today-sales">₵0.00</p>
                                <p class="text-xs mt-1" id="overview-sales-trend">
                                    <span class="text-green-600">▲ 0%</span>
                                </p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-green-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-chart-line text-green-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 uppercase truncate">Today's Swaps</p>
                                <p class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 mt-1" id="overview-today-swaps">0</p>
                            </div>
                            <div class="p-1.5 sm:p-2 md:p-3 bg-yellow-100 rounded-full flex-shrink-0 ml-1 sm:ml-2">
                                <i class="fas fa-exchange-alt text-yellow-600 text-sm sm:text-lg md:text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 2. BUSINESS PERFORMANCE METRICS -->
                <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
                    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-blue-50">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-chart-line text-green-600 mr-2"></i>
                                Business Performance
                            </h3>
                            <span class="text-xs text-gray-500" id="performance-date-range">Period: Last 30 days</span>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <!-- Primary Metrics -->
                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 md:gap-4 lg:gap-6 mb-6">
                            <!-- Total Revenue -->
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-3 sm:p-4 md:p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <i class="fas fa-money-bill-wave text-green-600 text-base sm:text-xl md:text-2xl"></i>
                                    <span class="text-xs text-green-600 font-medium bg-green-200 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">Revenue</span>
                                </div>
                                <p class="text-lg sm:text-2xl md:text-3xl font-bold text-gray-900 mb-1 truncate" id="sales-total-revenue">₵0.00</p>
                                <p class="text-xs text-gray-600">All income sources combined</p>
                            </div>
                            
                            <!-- Total Profit -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 sm:p-4 md:p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <i class="fas fa-chart-line text-blue-600 text-base sm:text-xl md:text-2xl"></i>
                                    <span class="text-xs text-blue-600 font-medium bg-blue-200 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">Profit</span>
                                </div>
                                <p class="text-lg sm:text-2xl md:text-3xl font-bold text-gray-900 mb-1 truncate" id="sales-total-profit">₵0.00</p>
                                <p class="text-xs text-gray-600">Net profit after costs</p>
                            </div>
                            
                            <!-- Total Transactions -->
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 sm:p-4 md:p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <i class="fas fa-shopping-cart text-purple-600 text-base sm:text-xl md:text-2xl"></i>
                                    <span class="text-xs text-purple-600 font-medium bg-purple-200 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">Sales</span>
                                </div>
                                <p class="text-lg sm:text-2xl md:text-3xl font-bold text-gray-900 mb-1" id="sales-total-transactions">0</p>
                                <p class="text-xs text-gray-600">Total transactions</p>
                            </div>
                            
                            <!-- Profit Margin -->
                            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg p-3 sm:p-4 md:p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <i class="fas fa-percent text-yellow-600 text-base sm:text-xl md:text-2xl"></i>
                                    <span class="text-xs text-yellow-600 font-medium bg-yellow-200 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">Margin</span>
                                </div>
                                <p class="text-lg sm:text-2xl md:text-3xl font-bold text-gray-900 mb-1" id="sales-profit-margin">0%</p>
                                <p class="text-xs text-gray-600">Profit percentage</p>
                            </div>
                        </div>
                        
                        <!-- Secondary Metrics Row -->
                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 md:gap-4">
                            <!-- People Owing -->
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-3 sm:p-4 md:p-5 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <div class="p-1.5 sm:p-2 bg-orange-100 rounded-lg">
                                        <i class="fas fa-exclamation-triangle text-orange-600 text-sm sm:text-base md:text-lg"></i>
                                    </div>
                                    <span class="text-xs text-orange-600 font-medium">People Owing</span>
                                </div>
                                <p class="text-lg sm:text-xl md:text-2xl font-bold text-orange-700 truncate" id="people-owing-count">0</p>
                            </div>
                            
                            <!-- Sales Revenue Breakdown -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 sm:p-4 md:p-5 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <div class="p-1.5 sm:p-2 bg-blue-100 rounded-lg">
                                        <i class="fas fa-cash-register text-blue-400 text-sm sm:text-base md:text-lg"></i>
                                    </div>
                                    <span class="text-xs text-blue-500 font-medium">From Sales</span>
                                </div>
                                <p class="text-lg sm:text-xl md:text-2xl font-bold text-blue-600 truncate" id="sales-revenue-breakdown">₵0.00</p>
                            </div>
                            
                            <!-- Swap Profit -->
                            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg p-3 sm:p-4 md:p-5 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <div class="p-1.5 sm:p-2 bg-indigo-100 rounded-lg">
                                        <i class="fas fa-exchange-alt text-indigo-400 text-sm sm:text-base md:text-lg"></i>
                                    </div>
                                    <span class="text-xs text-indigo-500 font-medium">From Swaps</span>
                                </div>
                                <p class="text-lg sm:text-xl md:text-2xl font-bold text-indigo-600 truncate" id="sales-swap-profit-breakdown">₵0.00</p>
                            </div>
                            
                            <!-- Repair Revenue -->
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 sm:p-4 md:p-5 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2 sm:mb-3">
                                    <div class="p-1.5 sm:p-2 bg-purple-100 rounded-lg">
                                        <i class="fas fa-tools text-purple-400 text-sm sm:text-base md:text-lg"></i>
                                    </div>
                                    <span class="text-xs text-purple-500 font-medium">From Repairs</span>
                                </div>
                                <p class="text-lg sm:text-xl md:text-2xl font-bold text-purple-600 truncate" id="sales-repair-revenue-breakdown">₵0.00</p>
                                <p class="text-xs text-purple-500 mt-1"><span id="repair-parts-count">0</span> products sold</p>
                            </div>
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
                
            </div>
        </div>
    </div>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <script>
        // BASE is already declared in the layout file, don't redeclare it
        // const BASE = window.APP_BASE_PATH || ''; // Removed duplicate declaration
        let topBrandsChart = null;
        
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
        
        // Load comprehensive manager overview
        async function loadManagerOverview() {
            const loadingEl = document.getElementById('dashboard-loading');
            const contentEl = document.getElementById('dashboard-content');
            
            loadingEl.classList.remove('hidden');
            contentEl.classList.add('hidden');
            
            const dateFromEl = document.getElementById('date-from');
            const dateToEl = document.getElementById('date-to');
            const dateFrom = dateFromEl ? dateFromEl.value : '';
            const dateTo = dateToEl ? dateToEl.value : '';
            
            // Ensure BASE is defined (it should be from dashboard layout)
            const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
            
            // Build URL safely
            const url = baseUrl + '/api/dashboard/manager-overview?date_from=' + encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);
            
            try {
                const res = await fetch(url, {
                    headers: getAuthHeaders(),
                    credentials: 'same-origin' // Include cookies for session fallback
                });
                
                if (!res.ok) {
                    const errorData = await res.json().catch(() => ({ error: 'Unknown server error', message: `HTTP ${res.status}: ${res.statusText}` }));
                    throw new Error(errorData.message || errorData.error || `HTTP ${res.status}: ${res.statusText}`);
                }
                
                const data = await res.json();
                
                if (data.success && data.data) {
                    const d = data.data;
                    
                    // 1. Overview Metrics
                    updateOverview(d.overview);
                    
                    // 2. Sales & Revenue Metrics
                    updateSalesMetrics(d.financial, d.sales, d.overview, d.date_range);
                    
                    // 3. Load payment stats for people owing
                    loadPeopleOwing();
                    
                    // 4. Load charts
                    setTimeout(() => {
                        if (typeof Chart !== 'undefined') {
                            loadCharts();
                        }
                    }, 500);
                    
                    loadingEl.classList.add('hidden');
                    contentEl.classList.remove('hidden');
                } else {
                    const errorMsg = data.message || data.error || 'Failed to load dashboard';
                    console.error('Dashboard API Error:', data);
                    throw new Error(errorMsg);
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
                loadingEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-red-800 font-bold mb-2">Error Loading Dashboard</h3>
                        <p class="text-red-600">${error.message}</p>
                        <button onclick="loadManagerOverview()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            <i class="fas fa-redo mr-2"></i>Retry
                        </button>
                    </div>
                `;
            }
        }
        
        function updateOverview(overview) {
            document.getElementById('overview-total-products').textContent = overview.total_products || 0;
            document.getElementById('overview-today-sales').textContent = '₵' + (overview.today_sales || 0).toFixed(2);
            document.getElementById('overview-today-swaps').textContent = overview.today_swaps || 0;
            document.getElementById('overview-today-repairs').textContent = overview.today_repairs || 0;
            document.getElementById('overview-total-customers').textContent = overview.total_customers || 0;
            document.getElementById('overview-active-staff').textContent = overview.active_staff || 0;
            
            // Update trends
            const trend = overview.trends?.sales || 0;
            const trendEl = document.getElementById('overview-sales-trend');
            if (trend > 0) {
                trendEl.innerHTML = `<span class="text-green-600">▲ ${Math.abs(trend).toFixed(1)}%</span>`;
            } else if (trend < 0) {
                trendEl.innerHTML = `<span class="text-red-600">▼ ${Math.abs(trend).toFixed(1)}%</span>`;
            } else {
                trendEl.innerHTML = `<span class="text-gray-500">—</span>`;
            }
        }
        
        function updateSalesMetrics(financial, sales, overview, dateRange) {
            // Total Revenue
            const totalRevenue = financial?.total_revenue || 0;
            document.getElementById('sales-total-revenue').textContent = '₵' + totalRevenue.toFixed(2);
            
            // Total Profit - check multiple possible property names
            let totalProfit = 0;
            if (financial) {
                totalProfit = financial.total_profit ?? financial.profit ?? financial.gross_profit ?? 0;
            }
            
            document.getElementById('sales-total-profit').textContent = '₵' + totalProfit.toFixed(2);
            
            // Total Transactions (Sales count) - try multiple sources
            const totalTransactions = sales?.total_transactions || 
                                     sales?.total_sales || 
                                     sales?.sales_count ||
                                     overview?.total_sales || 
                                     0;
            document.getElementById('sales-total-transactions').textContent = totalTransactions;
            
            // Profit Margin
            const profitMargin = financial?.profit_margin || 0;
            document.getElementById('sales-profit-margin').textContent = profitMargin.toFixed(1) + '%';
            
            // People Owing - will be loaded separately via payment stats (see loadPeopleOwing function)
            
            // Breakdown
            document.getElementById('sales-revenue-breakdown').textContent = '₵' + (financial?.sales_revenue || 0).toFixed(2);
            document.getElementById('sales-swap-profit-breakdown').textContent = '₵' + (financial?.swap_profit || 0).toFixed(2);
            document.getElementById('sales-repair-revenue-breakdown').textContent = '₵' + (financial?.repair_revenue || 0).toFixed(2);
            
            // Products sold by repairer count
            const repairPartsCount = financial?.repair_parts_count ?? 0;
            const repairPartsCountEl = document.getElementById('repair-parts-count');
            if (repairPartsCountEl) {
                repairPartsCountEl.textContent = repairPartsCount;
                console.log('Repair parts count updated:', repairPartsCount);
            } else {
                console.error('Element with id "repair-parts-count" not found');
            }
            
            // Date Range
            if (dateRange) {
                document.getElementById('performance-date-range').textContent = `Period: ${dateRange.from} to ${dateRange.to}`;
            }
        }
        
        // Load people owing count (partial + unpaid, excluding fully paid)
        async function loadPeopleOwing() {
            const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
            
            try {
                // Use session-only authentication (no Authorization header)
                const res = await fetch(baseUrl + '/api/dashboard/company-metrics', {
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin' // Include session cookies
                });
                
                if (!res.ok) {
                    console.error('Failed to load payment stats:', res.status, res.statusText);
                    return;
                }
                
                const data = await res.json();
                
                if (data.success && data.metrics && data.metrics.payment_stats) {
                    const stats = data.metrics.payment_stats;
                    // People owing = partial + unpaid (excluding fully paid)
                    const peopleOwing = (stats.partial || 0) + (stats.unpaid || 0);
                    const peopleOwingEl = document.getElementById('people-owing-count');
                    if (peopleOwingEl) {
                        peopleOwingEl.textContent = peopleOwing;
                    }
                }
            } catch (error) {
                console.error('Error loading people owing:', error);
                // Silently fail - this is not critical functionality
            }
        }
        
        // Chart instances for dashboard charts
        let salesTrendsChart = null;
        let activityDistributionChart = null;
        
        // Toggle charts module - make it global
        window.toggleChartsModule = async function(enabled) {
            try {
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                
                // Save module state to backend
                const res = await fetch(baseUrl + '/api/dashboard/toggle-module', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        module_key: 'charts',
                        enabled: enabled
                    })
                });
                
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        if (enabled) {
                            loadCharts();
                        } else {
                            destroyCharts();
                        }
                    }
                } else {
                    console.error('Failed to toggle module:', res.status);
                }
            } catch (error) {
                console.error('Error toggling charts module:', error);
            }
        }
        
        // Load charts data and render
        async function loadCharts() {
            console.log('Loading charts...');
            const salesCtx = document.getElementById('salesTrendsChart');
            const activityCtx = document.getElementById('activityDistributionChart');
            
            if (!salesCtx || !activityCtx) {
                console.error('Chart canvas elements not found!');
                return;
            }
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded!');
                return;
            }
            
            try {
                const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const res = await fetch(baseUrl + '/api/dashboard/charts-data', {
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                
                if (!res.ok) {
                    console.error('Failed to load charts data:', res.status);
                    renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                    renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
                    return;
                }
                
                const data = await res.json();
                if (data.success && data.data) {
                    renderSalesTrendsChart(data.data.sales_trends || { labels: [], revenue: [] });
                    renderActivityDistributionChart(data.data.activity_distribution || { labels: [], values: [] });
                } else {
                    renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                    renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
                }
            } catch (error) {
                console.error('Error loading charts:', error);
                renderSalesTrendsChart({ labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], revenue: [0, 0, 0, 0, 0, 0, 0] });
                renderActivityDistributionChart({ labels: ['Sales', 'Repairs', 'Swaps', 'Customers', 'Products', 'Technicians'], values: [0, 0, 0, 0, 0, 0] });
            }
        }
        
        // Render Sales Trends Bar Chart
        function renderSalesTrendsChart(data) {
            const ctx = document.getElementById('salesTrendsChart');
            if (!ctx) return;
            
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
                            'rgba(59, 130, 246, 0.8)', 'rgba(147, 51, 234, 0.8)', 'rgba(34, 197, 94, 0.8)',
                            'rgba(245, 158, 11, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(236, 72, 153, 0.8)',
                            'rgba(14, 165, 233, 0.8)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)', 'rgba(147, 51, 234, 1)', 'rgba(34, 197, 94, 1)',
                            'rgba(245, 158, 11, 1)', 'rgba(239, 68, 68, 1)', 'rgba(236, 72, 153, 1)',
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
                        legend: { display: false },
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
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        
        // Render Activity Distribution Pie Chart
        function renderActivityDistributionChart(data) {
            const ctx = document.getElementById('activityDistributionChart');
            if (!ctx) return;
            
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
                            'rgba(59, 130, 246, 0.8)', 'rgba(147, 51, 234, 0.8)', 'rgba(34, 197, 94, 0.8)',
                            'rgba(245, 158, 11, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(236, 72, 153, 0.8)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)', 'rgba(147, 51, 234, 1)', 'rgba(34, 197, 94, 1)',
                            'rgba(245, 158, 11, 1)', 'rgba(239, 68, 68, 1)', 'rgba(236, 72, 153, 1)'
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
                                font: { size: 12 }
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
        
        function updateTopBrandsChart(brands) {
            // Find the chart container by looking for the h4 and its next sibling
            const h4 = Array.from(document.querySelectorAll('h4')).find(h => h.textContent.includes('Top Swapped Brands'));
            if (!h4) return;
            
            let container = h4.nextElementSibling;
            if (!container) {
                // Create container if it doesn't exist
                container = document.createElement('div');
                container.style.height = '300px';
                container.style.position = 'relative';
                h4.parentElement.appendChild(container);
            } else {
                // Ensure container has fixed height
                if (!container.style.height) {
                    container.style.height = '300px';
                    container.style.position = 'relative';
                }
            }
            
            // Destroy existing chart
            if (topBrandsChart) {
                topBrandsChart.destroy();
                topBrandsChart = null;
            }
            
            if (brands.length === 0) {
                // Show message in container, maintaining fixed height
                container.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-center text-gray-500">No brand data available</p></div>';
                return;
            }
            
            // Ensure canvas exists in container
            let ctx = document.getElementById('top-brands-chart');
            if (!ctx) {
                container.innerHTML = '<canvas id="top-brands-chart"></canvas>';
                ctx = document.getElementById('top-brands-chart');
            }
            
            if (!ctx) return;
            
            topBrandsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: brands.map(b => b.brand || 'Unknown'),
                    datasets: [{
                        label: 'Number of Swaps',
                        data: brands.map(b => b.count || 0),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(147, 51, 234, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(147, 51, 234, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' swap' + (context.parsed.y !== 1 ? 's' : '');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function getStatusColor(status) {
            const s = (status || '').toLowerCase();
            if (s === 'completed' || s === 'resold') return 'bg-green-100 text-green-800';
            if (s === 'in_progress' || s === 'ongoing') return 'bg-blue-100 text-blue-800';
            if (s === 'pending') return 'bg-yellow-100 text-yellow-800';
            if (s === 'cancelled') return 'bg-red-100 text-red-800';
            return 'bg-gray-100 text-gray-800';
        }
        
        async function exportDashboard() {
            // Get current date range
            const dateFromEl = document.getElementById('date-from');
            const dateToEl = document.getElementById('date-to');
            const dateFrom = dateFromEl ? dateFromEl.value : '';
            const dateTo = dateToEl ? dateToEl.value : '';
            
            // Show format selection dialog
            const format = await showExportFormatDialog();
            if (!format) {
                return; // User cancelled
            }
            
            // Build export URL
            const baseUrl = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
            const params = new URLSearchParams();
            params.append('format', format);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            const url = baseUrl + '/api/dashboard/export?' + params.toString();
            
            try {
                // Show loading indicator
                const exportBtn = document.getElementById('export-dashboard-btn');
                if (exportBtn) {
                    const originalText = exportBtn.innerHTML;
                    exportBtn.disabled = true;
                    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1 sm:mr-2"></i>Exporting...';
                    
                    // Create a hidden link to trigger download
                    const link = document.createElement('a');
                    link.href = url;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Reset button after a delay
                    setTimeout(() => {
                        exportBtn.disabled = false;
                        exportBtn.innerHTML = originalText;
                    }, 2000);
                } else {
                    // Fallback: open in new window
                    window.open(url, '_blank');
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export dashboard. Please try again.');
            }
        }
        
        function showExportFormatDialog() {
            return new Promise((resolve) => {
                // Create modal dialog
                const modal = document.createElement('div');
                modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                modal.innerHTML = `
                    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Export Format</h3>
                        <div class="space-y-3">
                            <button onclick="selectFormat('csv')" class="w-full bg-teal-100 hover:bg-teal-200 text-teal-700 rounded-lg px-4 py-3 text-left transition-colors border border-teal-200 flex items-center">
                                <i class="fas fa-file-csv text-xl mr-3"></i>
                                <div>
                                    <div class="font-medium">CSV Format</div>
                                    <div class="text-xs text-gray-600">Comma-separated values, opens in Excel</div>
                                </div>
                            </button>
                            <button onclick="selectFormat('xlsx')" class="w-full bg-sky-100 hover:bg-sky-200 text-sky-700 rounded-lg px-4 py-3 text-left transition-colors border border-sky-200 flex items-center">
                                <i class="fas fa-file-excel text-xl mr-3"></i>
                                <div>
                                    <div class="font-medium">Excel Format</div>
                                    <div class="text-xs text-gray-600">Microsoft Excel (.xlsx) file</div>
                                </div>
                            </button>
                            <button onclick="selectFormat('pdf')" class="w-full bg-pink-100 hover:bg-pink-200 text-pink-700 rounded-lg px-4 py-3 text-left transition-colors border border-pink-200 flex items-center">
                                <i class="fas fa-file-pdf text-xl mr-3"></i>
                                <div>
                                    <div class="font-medium">PDF Format</div>
                                    <div class="text-xs text-gray-600">Portable Document Format</div>
                                </div>
                            </button>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button onclick="closeFormatDialog()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                                Cancel
                            </button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Store resolve function globally for button handlers
                window._exportFormatResolve = resolve;
                window._exportFormatModal = modal;
            });
        }
        
        window.selectFormat = function(format) {
            if (window._exportFormatResolve) {
                window._exportFormatResolve(format);
                window._exportFormatResolve = null;
            }
            if (window._exportFormatModal) {
                document.body.removeChild(window._exportFormatModal);
                window._exportFormatModal = null;
            }
        };
        
        window.closeFormatDialog = function() {
            if (window._exportFormatResolve) {
                window._exportFormatResolve(null);
                window._exportFormatResolve = null;
            }
            if (window._exportFormatModal) {
                document.body.removeChild(window._exportFormatModal);
                window._exportFormatModal = null;
            }
        };
        
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadManagerOverview();
            
            // Attach export button event listener
            const exportBtn = document.getElementById('export-dashboard-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportDashboard);
            }
            
            // Set up charts module toggle
            const toggle = document.getElementById('charts-module-toggle');
            if (toggle) {
                toggle.addEventListener('change', function() {
                    window.toggleChartsModule(this.checked);
                });
                // Enable toggle by default
                toggle.checked = true;
            }
            
            // Load charts after a short delay to ensure Chart.js is loaded
            setTimeout(() => {
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
                    setTimeout(() => clearInterval(checkChartJs), 5000);
                }
            }, 1000);
            
            // Auto-refresh every 5 minutes
            setInterval(loadManagerOverview, 300000);
        });
    </script>
<?php
$content = ob_get_clean();

// Include the dashboard layout
include APP_PATH . '/Views/layouts/dashboard.php';
?>

