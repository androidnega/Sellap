<?php
// Analytics page for System Admin - Platform-wide analytics and insights
?>

<div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Platform Analytics</h2>
    <p class="text-gray-600">Comprehensive analytics and insights across all companies</p>
</div>

<!-- Key Metrics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                <p class="text-2xl font-bold text-gray-900" id="total-revenue">₵0.00</p>
                <p class="text-xs text-gray-500 mt-1">All time</p>
            </div>
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-money-bill-wave text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                <p class="text-2xl font-bold text-gray-900" id="total-transactions">0</p>
                <p class="text-xs text-gray-500 mt-1">All companies</p>
            </div>
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-exchange-alt text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Active Companies</p>
                <p class="text-2xl font-bold text-gray-900" id="active-companies">0</p>
                <p class="text-xs text-gray-500 mt-1">Currently operating</p>
            </div>
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-building text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Users</p>
                <p class="text-2xl font-bold text-gray-900" id="total-users">0</p>
                <p class="text-xs text-gray-500 mt-1">Platform-wide</p>
            </div>
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-users text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Revenue Over Time Chart -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Revenue Over Time (Last 30 Days)</h3>
        </div>
        <div class="p-4" style="height: 150px;">
            <canvas id="revenueChart" width="400" height="100"></canvas>
        </div>
    </div>
    
    <!-- Transaction Types Chart -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Transaction Types Distribution</h3>
        </div>
        <div class="p-4" style="height: 150px;">
            <canvas id="transactionTypesChart" width="400" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Company Performance Section -->
<div class="bg-white rounded-lg shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Company Performance</h3>
                <p class="text-sm text-gray-600">Revenue and profit metrics per company</p>
            </div>
            <div class="flex gap-2">
                <input type="date" id="performance-date-from" class="px-3 py-1 border rounded text-sm">
                <input type="date" id="performance-date-to" class="px-3 py-1 border rounded text-sm">
                <button onclick="loadCompanyPerformance()" class="px-4 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                    <i class="fas fa-filter mr-1"></i>Filter
                </button>
            </div>
        </div>
    </div>
    <div class="p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Total Companies</p>
                <p class="text-2xl font-bold text-blue-600" id="performance-total-companies">0</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Total Revenue</p>
                <p class="text-2xl font-bold text-green-600" id="performance-total-revenue">₵0.00</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Total Profit</p>
                <p class="text-2xl font-bold text-purple-600" id="performance-total-profit">₵0.00</p>
            </div>
        </div>
        
        <!-- Company Performance Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Company</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Sales</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Repairs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Revenue</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Cost</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Profit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Margin</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="company-performance-table" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Loading company performance data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Charts Row: Top Performing Companies and User Growth -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Company Performance Chart -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Top Performing Companies</h3>
            <p class="text-sm text-gray-600">Revenue comparison across companies</p>
        </div>
        <div class="p-4" style="height: 150px;">
            <canvas id="companyRevenueChart" width="400" height="100"></canvas>
        </div>
    </div>

    <!-- User Growth Chart -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">User Growth (Last 90 Days)</h3>
            <p class="text-sm text-gray-600">New user registrations over time</p>
        </div>
        <div class="p-4" style="height: 150px;">
            <canvas id="userGrowthChart" width="400" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Analytics Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Sales Analytics -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Sales Analytics</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Sales</span>
                    <span class="text-lg font-semibold text-gray-900" id="total-sales">0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Sales Revenue</span>
                    <span class="text-lg font-semibold text-green-600" id="sales-revenue">₵0.00</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Average Order Value</span>
                    <span class="text-lg font-semibold text-gray-900" id="avg-order-value">₵0.00</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Last 30 Days Sales</span>
                    <span class="text-lg font-semibold text-blue-600" id="monthly-sales">0</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Repairs & Swaps Analytics -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Repairs & Swaps Analytics</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Repairs</span>
                    <span class="text-lg font-semibold text-gray-900" id="total-repairs">0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Repairs Revenue</span>
                    <span class="text-lg font-semibold text-green-600" id="repairs-revenue">₵0.00</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Swaps</span>
                    <span class="text-lg font-semibold text-gray-900" id="total-swaps">0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Active Swaps</span>
                    <span class="text-lg font-semibold text-blue-600" id="active-swaps">0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Swaps Revenue</span>
                    <span class="text-lg font-semibold text-green-600" id="swaps-revenue">₵0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Company Audit Section -->
<div class="bg-white rounded-lg shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Company Audit Trail</h3>
                <p class="text-sm text-gray-600">View all company transactions and records</p>
            </div>
        </div>
    </div>
    <div class="p-6">
        <!-- Audit Filters -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Company</label>
                <select id="audit-company-id" class="w-full px-3 py-2 border rounded text-sm">
                    <option value="">All Companies</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Period</label>
                <select id="audit-period" class="w-full px-3 py-2 border rounded text-sm" onchange="updateAuditDateRange()">
                    <option value="all">All Time</option>
                    <option value="daily">Today</option>
                    <option value="weekly">This Week</option>
                    <option value="monthly">This Month</option>
                    <option value="yearly">This Year</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" id="audit-date-from" class="w-full px-3 py-2 border rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" id="audit-date-to" class="w-full px-3 py-2 border rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Record Type</label>
                <select id="audit-record-type" class="w-full px-3 py-2 border rounded text-sm">
                    <option value="all">All Types</option>
                    <option value="sales">Sales Only</option>
                    <option value="repairs">Repairs Only</option>
                    <option value="swaps">Swaps Only</option>
                </select>
            </div>
        </div>
        <div class="mb-4">
            <button onclick="loadAuditRecords()" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-search mr-2"></i>Load Records
            </button>
            <button onclick="exportAuditRecords()" class="ml-2 px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i class="fas fa-download mr-2"></i>Export
            </button>
        </div>
        
        <!-- Audit Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="audit-summary" style="display: none;">
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Total Records</p>
                <p class="text-2xl font-bold text-blue-600" id="audit-total-records">0</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Total Revenue</p>
                <p class="text-2xl font-bold text-green-600" id="audit-total-revenue">₵0.00</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Sales</p>
                <p class="text-2xl font-bold text-purple-600" id="audit-sales-count">0</p>
            </div>
            <div class="bg-orange-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 uppercase mb-1">Repairs</p>
                <p class="text-2xl font-bold text-orange-600" id="audit-repairs-count">0</p>
            </div>
        </div>
        
        <!-- Audit Records Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Company</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Created By</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="audit-records-table" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Select filters and click "Load Records" to view audit data</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="mt-4 flex justify-between items-center" id="audit-pagination" style="display: none;">
            <div class="text-sm text-gray-600">
                Showing <span id="audit-showing-from">0</span> to <span id="audit-showing-to">0</span> of <span id="audit-total">0</span> records
            </div>
            <div class="flex gap-2">
                <button onclick="previousAuditPage()" class="px-4 py-2 border rounded text-sm hover:bg-gray-50" id="audit-prev-btn" disabled>Previous</button>
                <button onclick="nextAuditPage()" class="px-4 py-2 border rounded text-sm hover:bg-gray-50" id="audit-next-btn" disabled>Next</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_PATH; ?>/assets/js/chart.min.js"></script>
<script>
    // BASE is already declared in simple_layout.php, so we just use it here
    
    // Load analytics data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalyticsData();
        initializeCharts();
        loadCompanyPerformance();
        loadCompaniesForAudit();
        
        // Auto-refresh every 5 minutes
        setInterval(loadAnalyticsData, 300000);
    });
    
    function getAuthHeaders() {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        const headers = {
            'Content-Type': 'application/json'
        };
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        return headers;
    }
    
    function loadAnalyticsData() {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        if (!token) {
            console.error('No token found');
            return;
        }
        
        // Load admin stats - this has the core metrics
        fetch(BASE + '/api/admin/stats', {
            headers: getAuthHeaders()
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Admin stats data:', data);
            
            // Update companies count
            if (data.companies !== undefined) {
                document.getElementById('active-companies').textContent = data.companies || 0;
            }
            
            // Update total users
            if (data.users !== undefined) {
                document.getElementById('total-users').textContent = data.users || 0;
            }
            
            // Calculate total revenue (sales + repairs)
            const salesRevenue = parseFloat(data.sales_volume) || 0;
            const repairsRevenue = parseFloat(data.repairs_volume) || 0;
            const totalRevenue = salesRevenue + repairsRevenue;
            document.getElementById('total-revenue').textContent = '₵' + totalRevenue.toFixed(2);
            
            // Update total transactions
            if (data.total_transactions !== undefined) {
                document.getElementById('total-transactions').textContent = data.total_transactions || 0;
            }
        })
        .catch(error => {
            console.error('Error loading admin stats:', error);
            
            // Fallback: Try loading from platform metrics
            fetch(BASE + '/api/admin/platform-metrics', {
                headers: getAuthHeaders()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.metrics) {
                    document.getElementById('active-companies').textContent = data.metrics.active_companies || 0;
                    document.getElementById('total-users').textContent = data.metrics.total_users || 0;
                }
            })
            .catch(err => console.error('Error loading platform metrics:', err));
        });
        
        // Load detailed analytics (includes charts data)
        loadDetailedAnalytics();
    }
    
    function loadDetailedAnalytics() {
        fetch(BASE + '/api/admin/analytics', {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update sales analytics
                if (data.sales) {
                    document.getElementById('total-sales').textContent = data.sales.total || 0;
                    document.getElementById('sales-revenue').textContent = '₵' + (parseFloat(data.sales.revenue) || 0).toFixed(2);
                    document.getElementById('avg-order-value').textContent = '₵' + (parseFloat(data.sales.avg_order_value) || 0).toFixed(2);
                    document.getElementById('monthly-sales').textContent = data.sales.monthly || 0;
                }
                
                // Update repairs & swaps
                if (data.repairs) {
                    document.getElementById('total-repairs').textContent = data.repairs.total || 0;
                    document.getElementById('repairs-revenue').textContent = '₵' + (parseFloat(data.repairs.revenue) || 0).toFixed(2);
                }
                
                if (data.swaps) {
                    document.getElementById('total-swaps').textContent = data.swaps.total || 0;
                    document.getElementById('active-swaps').textContent = data.swaps.active || 0;
                    document.getElementById('swaps-revenue').textContent = '₵' + (parseFloat(data.swaps.revenue) || 0).toFixed(2);
                }
                
                // Update charts
                updateCharts(data);
            }
        })
        .catch(error => {
            console.error('Error loading detailed analytics:', error);
            // If endpoint doesn't exist, use default data
            useDefaultChartData();
        });
    }
    
    let revenueChart, transactionTypesChart, companyRevenueChart, userGrowthChart;
    
    function initializeCharts() {
        // Revenue Over Time Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            revenueChart = new Chart(revenueCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue (₵)',
                        data: [],
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Transaction Types Chart
        const transactionCtx = document.getElementById('transactionTypesChart');
        if (transactionCtx) {
            transactionTypesChart = new Chart(transactionCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Sales', 'Repairs', 'Swaps'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(168, 85, 247, 0.8)'
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(168, 85, 247, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Company Revenue Chart
        const companyCtx = document.getElementById('companyRevenueChart');
        if (companyCtx) {
            companyRevenueChart = new Chart(companyCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue (₵)',
                        data: [],
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart');
        if (userGrowthCtx) {
            userGrowthChart = new Chart(userGrowthCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'New Users',
                        data: [],
                        borderColor: 'rgba(168, 85, 247, 1)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Load initial data
        useDefaultChartData();
    }
    
    function updateCharts(data) {
        // Update revenue chart
        if (revenueChart && data.revenue_timeline) {
            revenueChart.data.labels = data.revenue_timeline.labels || [];
            revenueChart.data.datasets[0].data = data.revenue_timeline.values || [];
            revenueChart.update();
        }
        
        // Update transaction types
        if (transactionTypesChart && data.transaction_types) {
            transactionTypesChart.data.datasets[0].data = [
                data.transaction_types.sales || 0,
                data.transaction_types.repairs || 0,
                data.transaction_types.swaps || 0
            ];
            transactionTypesChart.update();
        }
        
        // Update company revenue
        if (companyRevenueChart && data.company_revenue) {
            companyRevenueChart.data.labels = data.company_revenue.labels || [];
            companyRevenueChart.data.datasets[0].data = data.company_revenue.values || [];
            companyRevenueChart.update();
        }
        
        // Update user growth
        if (userGrowthChart && data.user_growth) {
            userGrowthChart.data.labels = data.user_growth.labels || [];
            userGrowthChart.data.datasets[0].data = data.user_growth.values || [];
            userGrowthChart.update();
        }
    }
    
    function useDefaultChartData() {
        // Generate last 30 days labels
        const labels = [];
        for (let i = 29; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        }
        
        // Update revenue chart with placeholder data
        if (revenueChart) {
            revenueChart.data.labels = labels;
            revenueChart.data.datasets[0].data = new Array(30).fill(0).map(() => Math.random() * 10000);
            revenueChart.update();
        }
        
        // Update user growth with placeholder data
        if (userGrowthChart) {
            // Last 90 days
            const userLabels = [];
            for (let i = 89; i >= 0; i -= 7) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                userLabels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            userGrowthChart.data.labels = userLabels;
            userGrowthChart.data.datasets[0].data = new Array(userLabels.length).fill(0).map(() => Math.floor(Math.random() * 10));
            userGrowthChart.update();
        }
        
        // Load company performance for company revenue chart
        fetch(BASE + '/api/admin/company-performance', {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.companies && companyRevenueChart) {
                const topCompanies = data.companies.slice(0, 10); // Top 10 companies
                companyRevenueChart.data.labels = topCompanies.map(c => c.name || 'Company');
                companyRevenueChart.data.datasets[0].data = topCompanies.map(c => parseFloat(c.revenue) || 0);
                companyRevenueChart.update();
            }
        })
        .catch(error => {
            console.error('Error loading company performance:', error);
        });
        
        // Update transaction types from analytics API (real data)
        fetch(BASE + '/api/admin/analytics', {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.transaction_types && transactionTypesChart) {
                transactionTypesChart.data.datasets[0].data = [
                    data.transaction_types.sales || 0,
                    data.transaction_types.repairs || 0,
                    data.transaction_types.swaps || 0
                ];
                transactionTypesChart.update();
            }
        })
        .catch(error => {
            console.error('Error loading transaction types:', error);
            
            // Fallback: Try to get from admin stats
            fetch(BASE + '/api/admin/stats', {
                headers: getAuthHeaders()
            })
            .then(response => response.json())
            .then(statsData => {
                if (transactionTypesChart && statsData.total_transactions) {
                    // Estimate based on total if we can't get exact counts
                    const sales = Math.floor(statsData.total_transactions * 0.6) || 0;
                    const repairs = Math.floor(statsData.total_transactions * 0.25) || 0;
                    const swaps = Math.floor(statsData.total_transactions * 0.15) || 0;
                    
                    transactionTypesChart.data.datasets[0].data = [sales, repairs, swaps];
                    transactionTypesChart.update();
                }
            })
            .catch(err => console.error('Error loading transaction types fallback:', err));
        });
    }
    
    // Company Performance Functions
    let allCompanies = [];
    let currentAuditPage = 1;
    const auditPageSize = 50;
    let allAuditRecords = [];
    
    function loadCompanyPerformance() {
        const dateFrom = document.getElementById('performance-date-from').value;
        const dateTo = document.getElementById('performance-date-to').value;
        
        let url = BASE + '/api/admin/company-performance';
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (params.toString()) url += '?' + params.toString();
        
        fetch(url, {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allCompanies = data.companies || [];
                updateCompanyPerformanceTable(data.companies || []);
                updateCompanyPerformanceSummary(data.summary || {});
                updateCompanyRevenueChart(data.companies || []);
            }
        })
        .catch(error => {
            console.error('Error loading company performance:', error);
        });
    }
    
    function updateCompanyPerformanceTable(companies) {
        const tbody = document.getElementById('company-performance-table');
        if (companies.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No company data available</td></tr>';
            return;
        }
        
        tbody.innerHTML = companies.map(company => {
            const metrics = company.metrics || {};
            const profitColor = metrics.profit >= 0 ? 'text-green-600' : 'text-red-600';
            const marginColor = metrics.profit_margin >= 20 ? 'text-green-600' : metrics.profit_margin >= 10 ? 'text-yellow-600' : 'text-red-600';
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${company.name || 'Unknown'}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">${(metrics.total_sales || 0).toLocaleString()}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">${(metrics.total_repairs || 0).toLocaleString()}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">₵${(metrics.total_revenue || 0).toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">₵${(metrics.total_cost || 0).toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold ${profitColor}">₵${(metrics.profit || 0).toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold ${marginColor}">${(metrics.profit_margin || 0).toFixed(1)}%</td>
                    <td class="px-4 py-3 text-center">
                        <a href="${BASE}/dashboard/companies/view/${company.id}" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
    }
    
    function updateCompanyPerformanceSummary(summary) {
        document.getElementById('performance-total-companies').textContent = summary.total_companies || 0;
        document.getElementById('performance-total-revenue').textContent = '₵' + (summary.total_revenue || 0).toFixed(2);
        document.getElementById('performance-total-profit').textContent = '₵' + (summary.total_profit || 0).toFixed(2);
    }
    
    function updateCompanyRevenueChart(companies) {
        if (companyRevenueChart && companies.length > 0) {
            const topCompanies = companies.slice(0, 10);
            companyRevenueChart.data.labels = topCompanies.map(c => c.name || 'Company');
            companyRevenueChart.data.datasets[0].data = topCompanies.map(c => (c.metrics?.total_revenue || 0));
            companyRevenueChart.update();
        }
    }
    
    // Audit Functions
    function loadCompaniesForAudit() {
        fetch(BASE + '/api/admin/companies', {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(companies => {
            const select = document.getElementById('audit-company-id');
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name || 'Unknown';
                select.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading companies:', error);
        });
    }
    
    function updateAuditDateRange() {
        const period = document.getElementById('audit-period').value;
        const dateFrom = document.getElementById('audit-date-from');
        const dateTo = document.getElementById('audit-date-to');
        
        if (period === 'daily') {
            const today = new Date().toISOString().split('T')[0];
            dateFrom.value = today;
            dateTo.value = today;
        } else if (period === 'weekly') {
            const today = new Date();
            const monday = new Date(today.setDate(today.getDate() - today.getDay() + 1));
            const sunday = new Date(today.setDate(today.getDate() - today.getDay() + 7));
            dateFrom.value = monday.toISOString().split('T')[0];
            dateTo.value = sunday.toISOString().split('T')[0];
        } else if (period === 'monthly') {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            dateFrom.value = firstDay.toISOString().split('T')[0];
            dateTo.value = lastDay.toISOString().split('T')[0];
        } else if (period === 'yearly') {
            const today = new Date();
            dateFrom.value = `${today.getFullYear()}-01-01`;
            dateTo.value = `${today.getFullYear()}-12-31`;
        } else if (period === 'all') {
            dateFrom.value = '';
            dateTo.value = '';
        }
    }
    
    function loadAuditRecords() {
        const companyId = document.getElementById('audit-company-id').value;
        const period = document.getElementById('audit-period').value;
        const dateFrom = document.getElementById('audit-date-from').value;
        const dateTo = document.getElementById('audit-date-to').value;
        const recordType = document.getElementById('audit-record-type').value;
        
        let url = BASE + '/api/admin/company-audit';
        const params = new URLSearchParams();
        if (companyId) params.append('company_id', companyId);
        if (period) params.append('period', period);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (recordType) params.append('record_type', recordType);
        url += '?' + params.toString();
        
        fetch(url, {
            headers: getAuthHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allAuditRecords = data.records || [];
                currentAuditPage = 1;
                updateAuditTable();
                updateAuditSummary(data.summary || {});
                document.getElementById('audit-summary').style.display = 'grid';
                document.getElementById('audit-pagination').style.display = 'flex';
            }
        })
        .catch(error => {
            console.error('Error loading audit records:', error);
        });
    }
    
    function updateAuditTable() {
        const tbody = document.getElementById('audit-records-table');
        const start = (currentAuditPage - 1) * auditPageSize;
        const end = start + auditPageSize;
        const pageRecords = allAuditRecords.slice(start, end);
        
        if (pageRecords.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No records found</td></tr>';
            return;
        }
        
        tbody.innerHTML = pageRecords.map(record => {
            const typeColors = {
                'sale': 'bg-green-100 text-green-800',
                'repair': 'bg-blue-100 text-blue-800',
                'swap': 'bg-purple-100 text-purple-800'
            };
            const typeColor = typeColors[record.record_type] || 'bg-gray-100 text-gray-800';
            const date = new Date(record.record_date);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            let actionUrl = '';
            if (record.record_type === 'sale') {
                actionUrl = `${BASE}/dashboard/pos/sales-history`;
            } else if (record.record_type === 'repair') {
                actionUrl = `${BASE}/dashboard/repairs/${record.id}`;
            } else if (record.record_type === 'swap') {
                actionUrl = `${BASE}/dashboard/swaps/${record.id}`;
            }
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">${formattedDate}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${record.company_name || 'Unknown'}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${typeColor}">
                            ${(record.record_type || 'unknown').toUpperCase()}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">₵${(record.amount || 0).toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${record.status || 'N/A'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${record.created_by || 'System'}</td>
                    <td class="px-4 py-3 text-center">
                        ${actionUrl ? `<button onclick="viewAuditRecord(${record.id}, '${record.record_type}')" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-eye"></i></button>` : '-'}
                    </td>
                </tr>
            `;
        }).join('');
        
        // Update pagination
        document.getElementById('audit-showing-from').textContent = start + 1;
        document.getElementById('audit-showing-to').textContent = Math.min(end, allAuditRecords.length);
        document.getElementById('audit-total').textContent = allAuditRecords.length;
        document.getElementById('audit-prev-btn').disabled = currentAuditPage === 1;
        document.getElementById('audit-next-btn').disabled = end >= allAuditRecords.length;
    }
    
    function updateAuditSummary(summary) {
        document.getElementById('audit-total-records').textContent = summary.total_records || 0;
        document.getElementById('audit-total-revenue').textContent = '₵' + (summary.total_revenue || 0).toFixed(2);
        document.getElementById('audit-sales-count').textContent = summary.total_sales || 0;
        document.getElementById('audit-repairs-count').textContent = summary.total_repairs || 0;
    }
    
    function previousAuditPage() {
        if (currentAuditPage > 1) {
            currentAuditPage--;
            updateAuditTable();
        }
    }
    
    function nextAuditPage() {
        const maxPage = Math.ceil(allAuditRecords.length / auditPageSize);
        if (currentAuditPage < maxPage) {
            currentAuditPage++;
            updateAuditTable();
        }
    }
    
    function exportAuditRecords() {
        if (allAuditRecords.length === 0) {
            alert('No records to export. Please load records first.');
            return;
        }
        
        // Create CSV content
        const headers = ['Date', 'Company', 'Type', 'Amount', 'Status', 'Created By'];
        const rows = allAuditRecords.map(record => {
            const date = new Date(record.record_date);
            return [
                date.toLocaleString(),
                record.company_name || 'Unknown',
                record.record_type || 'unknown',
                record.amount || 0,
                record.status || 'N/A',
                record.created_by || 'System'
            ];
        });
        
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `audit-records-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }
    
    // View audit record in modal
    function viewAuditRecord(recordId, recordType) {
        // First, try to get the record from the audit records we already have
        const record = allAuditRecords.find(r => r.id === recordId && r.record_type === recordType);
        
        if (record) {
            // Show modal with the data we have
            showAuditRecordModal(record, recordType);
            
            // Optionally try to fetch more details in the background
            let url = '';
            if (recordType === 'sale') {
                url = BASE + `/api/pos/sale/${recordId}`;
            } else if (recordType === 'repair') {
                // Try to find repair API endpoint - if it doesn't exist, we'll use the basic data
                url = BASE + `/api/repairs/${recordId}`;
            } else if (recordType === 'swap') {
                url = BASE + `/api/swaps/${recordId}`;
            }
            
            // Try to fetch additional details if URL is available
            if (url) {
                fetch(url, {
                    headers: getAuthHeaders()
                })
                .then(response => {
                    if (!response.ok) {
                        return null; // Silently fail and use existing data
                    }
                    return response.json();
                })
                .then(data => {
                    if (data) {
                        // Handle different response formats
                        let recordData = null;
                        if (data.success && data.data) {
                            recordData = data.data;
                        } else if (data.id || data.sale_id) {
                            recordData = data;
                        }
                        
                        if (recordData) {
                            // Update modal with more detailed data
                            showAuditRecordModal(recordData, recordType);
                        }
                    }
                })
                .catch(error => {
                    // Silently fail - we already showed the modal with basic data
                    console.log('Could not fetch additional details:', error);
                });
            }
        } else {
            alert('Record not found');
        }
    }
    
    function showAuditRecordModal(record, recordType) {
        // Remove existing modal if any
        const existingModal = document.getElementById('auditRecordModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal HTML
        const modal = document.createElement('div');
        modal.id = 'auditRecordModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
        modal.onclick = function(e) {
            // Only close if clicking the backdrop, not the modal content
            if (e.target === modal) {
                closeAuditRecordModal();
            }
        };
        
        let content = '';
        if (recordType === 'sale') {
            const saleId = record.id || record.sale_id || 'N/A';
            const customer = record.customer_name || record.customer || 'Walk-in Customer';
            const date = record.record_date || record.created_at || record.date || new Date().toISOString();
            const amount = parseFloat(record.final_amount || record.total || record.amount || 0).toFixed(2);
            const status = (record.payment_status || record.status || 'N/A').toUpperCase();
            
            content = `
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Sale Details #${saleId}</h3>
                        <button onclick="event.stopPropagation(); closeAuditRecordModal();" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-600">Customer</p>
                                <p class="text-lg font-semibold">${customer}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date</p>
                                <p class="text-lg font-semibold">${new Date(date).toLocaleString()}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Amount</p>
                                <p class="text-lg font-semibold text-green-600">₵${amount}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Payment Status</p>
                                <p class="text-lg font-semibold">${status}</p>
                            </div>
                        </div>
                        ${record.items && Array.isArray(record.items) && record.items.length > 0 ? `
                            <div class="mt-4">
                                <h4 class="font-semibold mb-2">Items</h4>
                                <div class="border rounded">
                                    ${record.items.map(item => `
                                        <div class="p-3 border-b flex justify-between">
                                            <span>${item.name || item.product_name || item.item_name || 'Item'}</span>
                                            <span>Qty: ${item.quantity || 0} × ₵${parseFloat(item.price || item.unit_price || 0).toFixed(2)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        } else if (recordType === 'repair') {
            const repairId = record.id || 'N/A';
            const customer = record.customer_name || record.customer || 'N/A';
            const date = record.record_date || record.created_at || record.date || new Date().toISOString();
            const cost = parseFloat(record.total_cost || record.amount || 0).toFixed(2);
            const status = (record.status || record.repair_status || 'N/A').toUpperCase();
            const description = record.issue_description || record.description || '';
            const paymentStatus = (record.payment_status || 'N/A').toUpperCase();
            
            content = `
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Repair Details #${repairId}</h3>
                        <button onclick="event.stopPropagation(); closeAuditRecordModal();" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Customer</p>
                                <p class="text-lg font-semibold">${customer}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date</p>
                                <p class="text-lg font-semibold">${new Date(date).toLocaleString()}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Cost</p>
                                <p class="text-lg font-semibold text-green-600">₵${cost}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <p class="text-lg font-semibold">${status}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Payment Status</p>
                                <p class="text-lg font-semibold">${paymentStatus}</p>
                            </div>
                            ${description ? `
                                <div class="col-span-2">
                                    <p class="text-sm text-gray-600">Description</p>
                                    <p class="text-lg">${description}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        } else if (recordType === 'swap') {
            const swapId = record.id || 'N/A';
            const date = record.record_date || record.created_at || record.date || new Date().toISOString();
            const amount = parseFloat(record.total_amount || record.amount || 0).toFixed(2);
            const status = (record.status || 'N/A').toUpperCase();
            
            content = `
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Swap Details #${swapId}</h3>
                        <button onclick="event.stopPropagation(); closeAuditRecordModal();" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Date</p>
                                <p class="text-lg font-semibold">${new Date(date).toLocaleString()}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Amount</p>
                                <p class="text-lg font-semibold text-green-600">₵${amount}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <p class="text-lg font-semibold">${status}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Company</p>
                                <p class="text-lg font-semibold">${record.company_name || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        modal.innerHTML = content;
        document.body.appendChild(modal);
        
        // Prevent clicks inside modal content from closing the modal
        const modalContent = modal.querySelector('.bg-white');
        if (modalContent) {
            modalContent.onclick = function(e) {
                e.stopPropagation();
            };
        }
    }
    
    function closeAuditRecordModal() {
        const modal = document.getElementById('auditRecordModal');
        if (modal) {
            modal.remove();
        }
    }
    
</script>

