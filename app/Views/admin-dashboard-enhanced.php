<?php
// System Admin Dashboard - Platform Administration & Oversight
$title = 'Platform Administration Dashboard';
$userRole = 'system_admin';
$currentPage = 'dashboard';
$userInfo = 'Loading...';
$companyInfo = null;

ob_start();
?>
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-800">Platform Administration</h2>
                <p class="text-gray-600">Monitor platform health, system growth, and company oversight</p>
            </div>
            
            <!-- Summary Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Companies -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-building text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Companies</p>
                            <p class="text-2xl font-bold text-gray-900" id="companies-count">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Total Managers -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-user-tie text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Managers</p>
                            <p class="text-2xl font-bold text-gray-900" id="managers-count">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Total Users -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900" id="users-count">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Active Companies -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Companies</p>
                            <p class="text-2xl font-bold text-gray-900" id="active-companies-count">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Inactive Companies -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-pause-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Inactive Companies</p>
                            <p class="text-2xl font-bold text-gray-900" id="inactive-companies-count">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- New Companies This Month -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-calendar-plus text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">New This Month</p>
                            <p class="text-2xl font-bold text-gray-900" id="new-companies-month">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- API Requests Today -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                            <i class="fas fa-network-wired text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">API Calls Today</p>
                            <p class="text-2xl font-bold text-gray-900" id="api-requests-today">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Total SMS Sent -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-teal-100 text-teal-600">
                            <i class="fas fa-sms text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total SMS Sent</p>
                            <p class="text-2xl font-bold text-gray-900" id="sms-sent-total">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- SMS Balance -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">SMS Balance</p>
                            <p class="text-2xl font-bold text-gray-900" id="sms-balance">
                                <span id="sms-balance-amount">Loading...</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1" id="sms-balance-status"></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Company Growth Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Company Growth (Last 12 Months)</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="companyGrowthChart"></canvas>
                    </div>
                </div>
                
                <!-- User Growth Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">User Growth (Last 12 Months)</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
                
                <!-- SMS Usage Trend -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">SMS Usage Trend (Last 30 Days)</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="smsUsageChart"></canvas>
            </div>
                </div>
                
                <!-- Activity Heatmap -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Company Activity (Last 30 Days)</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="activityHeatmapChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- System Information & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- System Health & Alerts -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">System Health</h3>
                    <div class="space-y-3" id="system-health">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Platform Status</span>
                            <span class="text-sm font-medium text-green-600" id="platform-status">Operational</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Database</span>
                            <span class="text-sm font-medium text-green-600" id="db-status">Connected</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">PHP Version</span>
                            <span class="text-sm font-medium text-gray-900" id="php-version">-</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Recent Errors (24h)</span>
                            <span class="text-sm font-medium" id="recent-errors">
                                <span class="text-green-600">0</span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- System Alerts Widget -->
                    <div class="mt-4 pt-4 border-t border-gray-200" id="system-alerts">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">System Alerts</h4>
                        <div id="alerts-container">
                            <p class="text-xs text-gray-500">No alerts</p>
                        </div>
                    </div>
                </div>
                
                <!-- Version & Backup Info -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">System Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Current Version</span>
                            <span class="text-sm font-medium text-gray-900" id="app-version">2.0.0</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Last Update Check</span>
                            <span class="text-sm font-medium text-gray-900" id="update-check">-</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Backup Status</span>
                            <span class="text-sm font-medium text-gray-500" id="backup-status">Not Configured</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Storage Used</span>
                            <span class="text-sm font-medium text-gray-900" id="storage-used">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="<?= BASE_URL_PATH ?>/dashboard/companies/create" class="block w-full bg-blue-600 text-white text-center px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-plus mr-2"></i>Add New Company
                        </a>
                        <a href="<?= BASE_URL_PATH ?>/dashboard/companies" class="block w-full bg-indigo-600 text-white text-center px-4 py-2 rounded-md hover:bg-indigo-700 transition">
                            <i class="fas fa-building mr-2"></i>Manage Companies
                        </a>
                        <a href="<?= BASE_URL_PATH ?>/dashboard/users" class="block w-full bg-purple-600 text-white text-center px-4 py-2 rounded-md hover:bg-purple-700 transition">
                            <i class="fas fa-users mr-2"></i>Manage Users
                        </a>
                        <a href="<?= BASE_URL_PATH ?>/dashboard/settings" class="block w-full bg-green-600 text-white text-center px-4 py-2 rounded-md hover:bg-green-700 transition">
                            <i class="fas fa-cog mr-2"></i>System Configuration
                        </a>
                        <button onclick="refreshDashboard()" class="block w-full bg-gray-600 text-white text-center px-4 py-2 rounded-md hover:bg-gray-700 transition">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh Data
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Transaction Summary & Top Companies -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- System Transaction Summary -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">System Transaction Summary</h3>
                    <p class="text-sm text-gray-600 mb-4">Aggregated counts across all companies</p>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Total Sales</span>
                            <span class="text-lg font-bold text-gray-900" id="total-sales">0</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Total Repairs</span>
                            <span class="text-lg font-bold text-gray-900" id="total-repairs">0</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Total Swaps</span>
                            <span class="text-lg font-bold text-gray-900" id="total-swaps">0</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded border border-blue-200">
                            <span class="text-sm font-bold text-gray-800">Grand Total</span>
                            <span class="text-xl font-bold text-blue-600" id="grand-total">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Top Companies -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 3 Most Active Companies</h3>
                    <p class="text-sm text-gray-600 mb-4">By transaction volume</p>
                    <div class="space-y-3" id="top-companies">
                        <p class="text-sm text-gray-500">Loading...</p>
                    </div>
                </div>
            </div>
            
            <!-- Company SMS Summary Section -->
            <div class="bg-white rounded-lg shadow p-6 mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-sms text-teal-600 mr-2"></i>
                    Company SMS Summary
                </h3>
                <p class="text-sm text-gray-600 mb-6">Overview of SMS usage across all companies</p>
                
                <!-- SMS Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-teal-50 rounded-lg p-4 border border-teal-200">
                        <p class="text-xs text-gray-600 uppercase mb-1">Total SMS Used</p>
                        <p class="text-2xl font-bold text-teal-600" id="admin-total-sms-used">0</p>
                        <p class="text-xs text-gray-500 mt-1">Across all companies</p>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                        <p class="text-xs text-gray-600 uppercase mb-1">Companies with Low Balance</p>
                        <p class="text-2xl font-bold text-yellow-600" id="admin-low-balance-count">0</p>
                        <p class="text-xs text-gray-500 mt-1">&lt; 10% remaining</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-xs text-gray-600 uppercase mb-1">Most Active SMS Sender</p>
                        <p class="text-lg font-bold text-blue-600" id="admin-top-sms-sender">-</p>
                        <p class="text-xs text-gray-500 mt-1" id="admin-top-sender-count">0 SMS sent</p>
                    </div>
                </div>
                
                <!-- Companies with Low SMS Balance -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        Companies with Low SMS Balance (&lt; 10%)
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Company</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">SMS Remaining</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Total</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Usage</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody id="admin-low-balance-companies" class="bg-white divide-y divide-gray-200">
                                <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Most Active SMS Senders -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-chart-bar text-blue-500 mr-2"></i>
                        Most Active SMS Senders (Top 5)
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-blue-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Company</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">SMS Sent</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">Remaining</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody id="admin-top-sms-senders" class="bg-white divide-y divide-gray-200">
                                <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let companyGrowthChart, userGrowthChart, smsUsageChart, activityHeatmapChart;
        
        // Load dashboard data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            // Auto-refresh every 30 seconds for real-time API calls counter
            setInterval(loadDashboardData, 30000);
        });
        
        function loadDashboardData() {
            // Get token from localStorage (if available)
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            const BASE = window.BASE || window.APP_BASE_PATH || '<?= defined("BASE_URL_PATH") ? BASE_URL_PATH : "" ?>' || '';
            
            console.log('Loading dashboard data from:', BASE + '/api/admin/dashboard');
            
            // Prepare headers
            const headers = {
                'Content-Type': 'application/json'
            };
            
            // Add Authorization header if token exists
            if (token) {
                headers['Authorization'] = 'Bearer ' + token;
            }
            
            // Make request with both JWT (if available) and session cookies
            fetch(BASE + '/api/admin/dashboard', {
                method: 'GET',
                credentials: 'same-origin',
                headers: headers
            })
            .then(response => {
                console.log('Response status:', response.status, response.statusText);
                if (!response.ok) {
                    // Log the response text for debugging
                    return response.text().then(text => {
                        console.error('Error response:', text);
                        try {
                            const errorData = JSON.parse(text);
                            throw new Error(errorData.error || errorData.message || 'Failed to load dashboard data: ' + response.status);
                        } catch (e) {
                            if (e instanceof SyntaxError) {
                                throw new Error('Failed to load dashboard data: ' + response.status + ' - ' + text.substring(0, 100));
                            }
                            throw e;
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Dashboard data received:', data);
                if (data && data.success) {
                    updateDashboard(data);
                    loadCharts(data);
                } else {
                    console.error('Dashboard error:', data?.error || 'Unknown error', data);
                    // Still try to update with partial data if available
                    if (data) {
                        updateDashboard(data);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading dashboard:', error);
                // Show user-friendly error message
                const balanceEl = document.getElementById('sms-balance-amount');
                const statusEl = document.getElementById('sms-balance-status');
                if (balanceEl) balanceEl.textContent = 'Error';
                if (statusEl) {
                    statusEl.textContent = 'Unable to load dashboard data: ' + (error.message || 'Unknown error');
                    statusEl.className = 'text-xs text-red-600 mt-1';
                }
            });
        }
        
        function updateDashboard(data) {
            // Update summary cards
            document.getElementById('companies-count').textContent = data.companies || 0;
            document.getElementById('active-companies-count').textContent = data.active_companies || 0;
            document.getElementById('inactive-companies-count').textContent = data.inactive_companies || 0;
            document.getElementById('new-companies-month').textContent = data.new_companies_this_month || 0;
            document.getElementById('managers-count').textContent = data.managers || 0;
            document.getElementById('users-count').textContent = data.users || 0;
            document.getElementById('api-requests-today').textContent = data.api_requests_today || 0;
            document.getElementById('sms-sent-total').textContent = data.sms_sent_total || 0;
            
            // Update SMS Balance (shows SMS credits, not money)
            const balanceEl = document.getElementById('sms-balance-amount');
            const statusEl = document.getElementById('sms-balance-status');
            
            if (data.sms_balance) {
                if (data.sms_balance.status === 'active' && data.sms_balance.credits !== null) {
                    // Display as SMS credits (e.g., "476 SMS")
                    if (balanceEl) balanceEl.textContent = data.sms_balance.formatted || ((data.sms_balance.credits || 0).toLocaleString() + ' SMS');
                    if (statusEl) {
                        statusEl.textContent = 'Credits Available';
                        statusEl.className = 'text-xs text-green-600 mt-1';
                    }
                } else if (data.sms_balance.status === 'not_configured') {
                    if (balanceEl) balanceEl.textContent = 'Not Configured';
                    if (statusEl) {
                        statusEl.textContent = 'Configure API key in Settings';
                        statusEl.className = 'text-xs text-yellow-600 mt-1';
                    }
                } else {
                    // Show error status
                    const errorMsg = data.sms_balance.error || 'Unable to fetch';
                    if (balanceEl) {
                        // Show shortened error message in balance field
                        if (errorMsg.includes('timeout') || errorMsg.includes('Connection timeout')) {
                            balanceEl.textContent = 'Timeout';
                        } else if (errorMsg.includes('DNS') || errorMsg.includes('Network error')) {
                            balanceEl.textContent = 'Network Error';
                        } else {
                            balanceEl.textContent = 'Error';
                        }
                    }
                    if (statusEl) {
                        statusEl.textContent = errorMsg;
                        statusEl.className = 'text-xs text-red-600 mt-1';
                    }
                }
            } else {
                // Fallback if SMS balance data is missing
                if (balanceEl) balanceEl.textContent = 'Loading...';
                if (statusEl) {
                    statusEl.textContent = 'Data not available';
                    statusEl.className = 'text-xs text-gray-500 mt-1';
                }
            }
            
            // Update transaction summary
            const summary = data.transaction_summary || {};
            document.getElementById('total-sales').textContent = summary.sales || 0;
            document.getElementById('total-repairs').textContent = summary.repairs || 0;
            document.getElementById('total-swaps').textContent = summary.swaps || 0;
            document.getElementById('grand-total').textContent = summary.total || 0;
            
            // Update system health
            if (data.system_health) {
                document.getElementById('platform-status').textContent = data.system_health.status || 'Operational';
                document.getElementById('db-status').textContent = data.system_health.database || 'Connected';
                document.getElementById('php-version').textContent = data.system_health.php_version || '-';
            }
            
            // Update system alerts
            if (data.system_alerts) {
                const recentErrors = data.system_alerts.recent_errors || 0;
                const errorsEl = document.getElementById('recent-errors');
                if (recentErrors > 0) {
                    errorsEl.innerHTML = '<span class="text-red-600 font-bold">' + recentErrors + '</span>';
                } else {
                    errorsEl.innerHTML = '<span class="text-green-600">0</span>';
                }
                
                // Show alerts if any
                const alertsContainer = document.getElementById('alerts-container');
                let alertsHtml = [];
                
                // Add error alerts
                if (data.system_alerts.has_alerts && recentErrors > 0) {
                    alertsHtml.push('<p class="text-xs text-red-600 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>' + recentErrors + ' errors in last 24 hours</p>');
                }
                
                // Add SMS low balance alerts
                if (data.company_sms_summary && data.company_sms_summary.companies_with_low_balance > 0) {
                    alertsHtml.push('<p class="text-xs text-yellow-600 mb-2"><i class="fas fa-sms mr-1"></i>' + data.company_sms_summary.companies_with_low_balance + ' companies with low SMS balance (&lt; 10%)</p>');
                }
                
                if (alertsHtml.length > 0) {
                    alertsContainer.innerHTML = alertsHtml.join('');
                } else {
                    alertsContainer.innerHTML = '<p class="text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>All systems operational</p>';
                }
            }
            
            // Update version info
            if (data.version_info) {
                document.getElementById('app-version').textContent = data.version_info.current_version || '2.0.0';
                document.getElementById('update-check').textContent = data.version_info.last_update_check || '-';
            }
            
            // Update backup status
            if (data.backup_status) {
                const backupEl = document.getElementById('backup-status');
                if (data.backup_status.last_backup) {
                    backupEl.textContent = data.backup_status.last_backup;
                } else {
                    backupEl.textContent = 'Not Configured';
                    backupEl.className = 'text-sm font-medium text-gray-500';
                }
            }
            
            // Update storage
            document.getElementById('storage-used').textContent = (data.storage_used_mb || 0) + ' MB';
            
            // Update top companies
            updateTopCompanies(data.top_companies || []);
            
            // Update Company SMS Summary
            if (data.company_sms_summary) {
                updateCompanySMSSummary(data.company_sms_summary);
            } else {
                // Initialize with empty data if not available
                updateCompanySMSSummary({
                    total_sms_used: 0,
                    companies_with_low_balance: 0,
                    low_balance_companies: [],
                    top_senders: [],
                    top_sender: null
                });
            }
        }
        
        function updateCompanySMSSummary(summary) {
            // Update summary stats
            document.getElementById('admin-total-sms-used').textContent = (summary.total_sms_used || 0).toLocaleString();
            document.getElementById('admin-low-balance-count').textContent = (summary.companies_with_low_balance || 0);
            
            // Update top sender
            if (summary.top_sender) {
                document.getElementById('admin-top-sms-sender').textContent = summary.top_sender.company_name || '-';
                document.getElementById('admin-top-sender-count').textContent = (summary.top_sender.sms_sent || 0).toLocaleString() + ' SMS sent';
            } else {
                document.getElementById('admin-top-sms-sender').textContent = '-';
                document.getElementById('admin-top-sender-count').textContent = '0 SMS sent';
            }
            
            // Update low balance companies table
            const lowBalanceBody = document.getElementById('admin-low-balance-companies');
            if (summary.low_balance_companies && summary.low_balance_companies.length > 0) {
                lowBalanceBody.innerHTML = summary.low_balance_companies.map(company => {
                    const usagePercent = company.usage_percent || 0;
                    const usageColor = usagePercent >= 95 ? 'text-red-600' : 'text-yellow-600';
                    
                    return `
                        <tr class="hover:bg-red-50">
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${company.company_name || 'Unknown'}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-red-600 font-semibold">${(company.sms_remaining || 0).toLocaleString()}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-600">${(company.total_sms || 0).toLocaleString()}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm ${usageColor} font-semibold">${usagePercent.toFixed(1)}%</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm">
                                <a href="${BASE}/dashboard/companies/view/${company.company_id}" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                lowBalanceBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">All companies have sufficient SMS balance</td></tr>';
            }
            
            // Update top SMS senders table
            const topSendersBody = document.getElementById('admin-top-sms-senders');
            if (summary.top_senders && summary.top_senders.length > 0) {
                topSendersBody.innerHTML = summary.top_senders.map((company, index) => {
                    const statusColor = company.status === 'active' ? 'text-green-600' : 'text-red-600';
                    const statusBadge = company.status === 'active' 
                        ? '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>'
                        : '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">' + (company.status || 'Unknown') + '</span>';
                    
                    return `
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <span class="text-gray-400 mr-2">#${index + 1}</span>
                                ${company.company_name || 'Unknown'}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right font-semibold text-blue-600">${(company.sms_sent || 0).toLocaleString()}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-600">${(company.sms_remaining || 0).toLocaleString()}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm">${statusBadge}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                topSendersBody.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No SMS activity found</td></tr>';
            }
        }
        
        function updateTopCompanies(companies) {
            const container = document.getElementById('top-companies');
            if (companies.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-500">No data available</p>';
                return;
            }
            
            container.innerHTML = companies.map((company, index) => {
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : '🥉';
                return `
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <div>
                            <span class="text-lg mr-2">${medal}</span>
                            <span class="text-sm font-medium text-gray-900">${company.name}</span>
                                        </div>
                        <span class="text-sm font-bold text-blue-600">${company.transaction_count} transactions</span>
                                </div>
                `;
            }).join('');
        }
        
        function loadCharts(data) {
            // Company Growth Chart
            const companyGrowthCtx = document.getElementById('companyGrowthChart');
            if (!companyGrowthCtx) return;
            
            if (companyGrowthChart) companyGrowthChart.destroy();
            companyGrowthChart = new Chart(companyGrowthCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.growth_chart?.labels || [],
                    datasets: [{
                        label: 'Companies Added',
                        data: data.growth_chart?.values || [],
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
            
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart');
            if (!userGrowthCtx) return;
            
            if (userGrowthChart) userGrowthChart.destroy();
            userGrowthChart = new Chart(userGrowthCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.user_chart?.labels || [],
                    datasets: [{
                        label: 'Users Registered',
                        data: data.user_chart?.values || [],
                        borderColor: 'rgba(34, 197, 94, 1)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
            
            // SMS Usage Chart
            const smsUsageCtx = document.getElementById('smsUsageChart');
            if (!smsUsageCtx) return;
            
            if (smsUsageChart) smsUsageChart.destroy();
            smsUsageChart = new Chart(smsUsageCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.sms_chart?.labels || [],
                    datasets: [{
                        label: 'SMS Sent',
                        data: data.sms_chart?.values || [],
                        borderColor: 'rgba(20, 184, 166, 1)',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
            
            // Activity Heatmap Chart
            const activityCtx = document.getElementById('activityHeatmapChart');
            if (!activityCtx) return;
            
            if (activityHeatmapChart) activityHeatmapChart.destroy();
            activityHeatmapChart = new Chart(activityCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.activity_heatmap?.labels || [],
                    datasets: [{
                        label: 'Active Companies',
                        data: data.activity_heatmap?.values || [],
                        backgroundColor: 'rgba(168, 85, 247, 0.5)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
        }
        
        function refreshDashboard() {
            loadDashboardData();
            // Show feedback
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
            btn.disabled = true;
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        }
    </script>
<?php
$content = ob_get_clean();

// Include the dashboard layout
include APP_PATH . '/Views/layouts/dashboard.php';
?>

?>