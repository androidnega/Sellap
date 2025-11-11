<?php
// SMS Settings View - Content only (uses dashboard layout)
// This file is included within the dashboard layout

$userRole = $userRole ?? ($_SESSION['user']['role'] ?? 'manager');
$companyId = $_SESSION['user']['company_id'] ?? null;
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SMS Settings & Management</h1>
        <p class="text-gray-600 mt-2">Manage your SMS credits, view usage, and purchase additional credits</p>
    </div>
    
    <!-- SMS Balance Alert (if low) -->
    <div id="sms-low-balance-alert" class="hidden mb-6">
        <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-semibold text-red-800">SMS Credits Running Low</h3>
                    <p class="mt-1 text-sm text-red-700">
                        Your SMS balance is below 10%. Please top up your credits to continue sending SMS notifications.
                        <span class="font-semibold" id="sms-alert-balance">0 SMS remaining</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SMS Usage & Balance Section -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-teal-50">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-sms text-teal-600 mr-2"></i>
                SMS Usage & Balance
            </h3>
        </div>
        <div class="p-6">
            <!-- SMS Balance Card -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-teal-50 to-blue-50 rounded-lg p-6 border border-teal-200">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-medium text-gray-600">SMS Credits Balance</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1" id="sms-balance-display">
                                <span id="sms-remaining-count">0</span> SMS
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span id="sms-used-count">0</span> used of <span id="sms-total-count">0</span> total
                            </p>
                        </div>
                        <div class="p-4 bg-white rounded-full shadow">
                            <i class="fas fa-sms text-4xl text-teal-600"></i>
                        </div>
                    </div>
                    
                    <!-- Usage Progress Bar -->
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                        <div 
                            id="sms-usage-bar" 
                            class="h-3 rounded-full transition-all duration-300"
                            style="width: 0%; background: linear-gradient(90deg, #10b981, #059669);"
                        ></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span id="sms-usage-percent">0% used</span>
                        <span id="sms-status-text" class="font-medium">Active</span>
                    </div>
                    
                    <!-- Buy SMS Credits Button -->
                    <div class="mt-4 pt-4 border-t border-teal-200">
                        <a 
                            href="<?= defined('BASE_URL_PATH') ? BASE_URL_PATH : '' ?>/dashboard/sms/purchase"
                            class="w-full bg-gradient-to-r from-blue-600 to-teal-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-blue-700 hover:to-teal-700 transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center block text-center"
                        >
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Buy SMS Credits
                        </a>
                    </div>
                </div>
            </div>
            <!-- Recent SMS Logs -->
            <div>
                <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-list-alt text-teal-600 mr-2"></i>
                    Recent SMS Activity (Last 20)
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Message Preview</th>
                            </tr>
                        </thead>
                        <tbody id="sms-logs-table" class="bg-white divide-y divide-gray-200">
                            <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Loading SMS logs...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Paystack Inline JS -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
// BASE is already declared in the dashboard layout as a const
// Just reference it directly - no need to redeclare

// Load SMS data - expose globally so payment-success page can call it
window.loadSMSData = async function loadSMSData() {
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        if (!token) {
            console.error('No authentication token found');
            return;
        }
        
        const response = await fetch(BASE + '/api/dashboard/manager-overview', {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.data && data.data.sms) {
            updateSMSData(data.data.sms);
        } else {
            console.error('Failed to load SMS data:', data);
            const logsTable = document.getElementById('sms-logs-table');
            if (logsTable) {
                logsTable.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Failed to load SMS data</td></tr>';
            }
        }
    } catch (error) {
        console.error('Error loading SMS data:', error);
        const logsTable = document.getElementById('sms-logs-table');
        if (logsTable) {
            logsTable.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-red-500">Error loading SMS data</td></tr>';
        }
    }
}

function updateSMSData(sms) {
    if (!sms) {
        console.warn('No SMS data provided');
        return;
    }
    
    // Update SMS balance display
    const remaining = sms.sms_remaining || 0;
    const total = sms.total_sms || 0;
    const used = sms.sms_used || 0;
    const usagePercent = sms.usage_percent || 0;
    
    document.getElementById('sms-remaining-count').textContent = remaining.toLocaleString();
    document.getElementById('sms-used-count').textContent = used.toLocaleString();
    document.getElementById('sms-total-count').textContent = total.toLocaleString();
    document.getElementById('sms-usage-percent').textContent = usagePercent.toFixed(1) + '% used';
    
    // Update progress bar
    const usageBar = document.getElementById('sms-usage-bar');
    let barColor = 'linear-gradient(90deg, #10b981, #059669)'; // green
    if (usagePercent >= 80) {
        barColor = 'linear-gradient(90deg, #ef4444, #dc2626)'; // red
    } else if (usagePercent >= 50) {
        barColor = 'linear-gradient(90deg, #f59e0b, #d97706)'; // yellow
    }
    usageBar.style.width = Math.min(100, usagePercent) + '%';
    usageBar.style.background = barColor;
    
    // Update status text
    const statusText = document.getElementById('sms-status-text');
    if (sms.status === 'active') {
        statusText.textContent = 'Active';
        statusText.className = 'font-medium text-green-600';
    } else {
        statusText.textContent = sms.status || 'Unknown';
        statusText.className = 'font-medium text-red-600';
    }
    
    // Show/hide low balance alert
    const alertEl = document.getElementById('sms-low-balance-alert');
    if (usagePercent >= 90 || remaining <= 10) {
        alertEl.classList.remove('hidden');
        document.getElementById('sms-alert-balance').textContent = remaining.toLocaleString() + ' SMS remaining';
    } else {
        alertEl.classList.add('hidden');
    }
    
    // Update SMS logs table
    updateSMSLogs(sms.recent_logs || []);
}

function updateSMSLogs(logs) {
    const tableBody = document.getElementById('sms-logs-table');
    
    if (!logs || logs.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">No SMS activity found</td></tr>';
        return;
    }
    
    tableBody.innerHTML = logs.map(log => {
        const date = new Date(log.sent_at || log.created_at);
        const formattedDate = date.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric' 
        });
        const formattedTime = date.toLocaleTimeString('en-GB', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const statusColor = log.status === 'sent' 
            ? 'bg-green-100 text-green-800' 
            : 'bg-red-100 text-red-800';
        const statusIcon = log.status === 'sent' 
            ? '<i class="fas fa-check-circle mr-1"></i>' 
            : '<i class="fas fa-times-circle mr-1"></i>';
        
        const messagePreview = (log.message || '').substring(0, 50) + ((log.message || '').length > 50 ? '...' : '');
        const messageTypeLabel = (log.message_type || 'system').charAt(0).toUpperCase() + (log.message_type || 'system').slice(1);
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                    ${formattedDate}<br>
                    <span class="text-gray-400">${formattedTime}</span>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">${messageTypeLabel}</span>
                </td>
                <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 font-mono">${log.recipient || 'N/A'}</td>
                <td class="px-3 py-3 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs rounded-full ${statusColor}">
                        ${statusIcon}${log.status || 'unknown'}
                    </span>
                </td>
                <td class="px-3 py-3 text-xs text-gray-600">${messagePreview || 'N/A'}</td>
            </tr>
        `;
    }).join('');
}

// Load SMS data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSMSData();
    
    // Check if we need to refresh after returning from payment
    if (sessionStorage.getItem('refreshSMSData') === 'true') {
        sessionStorage.removeItem('refreshSMSData');
        // Force immediate refresh
        setTimeout(loadSMSData, 500);
    }
    
    // Refresh every 2 minutes
    setInterval(loadSMSData, 120000);
});
</script>

