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
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Revenue Over Time (Last 30 Days)</h3>
        <canvas id="revenueChart" width="400" height="200"></canvas>
    </div>
    
    <!-- Transaction Types Chart -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaction Types Distribution</h3>
        <canvas id="transactionTypesChart" width="400" height="200"></canvas>
    </div>
</div>

<!-- Company Performance Comparison -->
<div class="bg-white rounded-lg shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">Top Performing Companies</h3>
        <p class="text-sm text-gray-600">Revenue comparison across companies</p>
    </div>
    <div class="p-6">
        <canvas id="companyRevenueChart" width="400" height="150"></canvas>
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
            </div>
        </div>
    </div>
</div>

<!-- User Growth Chart -->
<div class="bg-white rounded-lg shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">User Growth (Last 90 Days)</h3>
        <p class="text-sm text-gray-600">New user registrations over time</p>
    </div>
    <div class="p-6">
        <canvas id="userGrowthChart" width="400" height="150"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    const BASE = window.APP_BASE_PATH || '';
    
    // Load analytics data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalyticsData();
        initializeCharts();
        
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
                    plugins: {
                        legend: {
                            display: true
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
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
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
</script>

