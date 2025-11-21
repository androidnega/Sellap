<div class="p-6">
  <div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">SMS & Company Configuration</h2>
    <p class="text-gray-600">Manage SMS credits, branding, settings, logs, and payments for all companies</p>
  </div>

  <!-- Quick Stats Summary -->
  <div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">System SMS Summary</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <?php
        $totalAllocated = 0;
        $totalUsed = 0;
        $totalRemaining = 0;
        $lowBalanceCount = 0;
        
        foreach ($companiesWithSMS as $item) {
          if ($item['sms']['success']) {
            $totalAllocated += ($item['sms']['total_sms'] ?? 0);
            $totalUsed += ($item['sms']['sms_used'] ?? 0);
            $totalRemaining += ($item['sms']['sms_remaining'] ?? 0);
            $usage = ($item['sms']['total_sms'] ?? 0) > 0 ? (($item['sms']['sms_used'] ?? 0) / ($item['sms']['total_sms'] ?? 1)) * 100 : 0;
            $remaining = $item['sms']['sms_remaining'] ?? 0;
            if ($usage >= 90 || ($remaining <= 10 && $remaining > 0)) {
              $lowBalanceCount++;
            }
          }
        }
      ?>
      <div class="text-center p-4 bg-blue-50 rounded-lg">
        <p class="text-2xl font-bold text-blue-600"><?= count($companiesWithSMS) ?></p>
        <p class="text-xs text-gray-600 mt-1">Total Companies</p>
      </div>
      <div class="text-center p-4 bg-green-50 rounded-lg">
        <p class="text-2xl font-bold text-green-600"><?= number_format($totalAllocated) ?></p>
        <p class="text-xs text-gray-600 mt-1">Total Allocated</p>
      </div>
      <div class="text-center p-4 bg-yellow-50 rounded-lg">
        <p class="text-2xl font-bold text-yellow-600"><?= number_format($totalUsed) ?></p>
        <p class="text-xs text-gray-600 mt-1">Total Used</p>
      </div>
      <div class="text-center p-4 <?= $lowBalanceCount > 0 ? 'bg-red-50' : 'bg-gray-50' ?> rounded-lg">
        <p class="text-2xl font-bold <?= $lowBalanceCount > 0 ? 'text-red-600' : 'text-gray-600' ?>"><?= $lowBalanceCount ?></p>
        <p class="text-xs text-gray-600 mt-1">Low Balance</p>
      </div>
    </div>
  </div>

  <!-- Companies SMS Management Sections -->
  <div class="space-y-6">
    <?php foreach ($companiesWithSMS as $item): 
      $company = $item['company'];
      $sms = $item['sms'];
      $totalSMS = $sms['success'] ? ($sms['total_sms'] ?? 0) : 0;
      $usedSMS = $sms['success'] ? ($sms['sms_used'] ?? 0) : 0;
      $remainingSMS = $sms['success'] ? ($sms['sms_remaining'] ?? 0) : 0;
      $usagePercent = $totalSMS > 0 ? round(($usedSMS / $totalSMS) * 100) : 0;
      $isLowBalance = $usagePercent >= 90 || ($remainingSMS <= 10 && $remainingSMS > 0);
      $customEnabled = $sms['success'] ? ($sms['custom_sms_enabled'] ?? false) : false;
      $senderName = $sms['success'] ? ($sms['sms_sender_name'] ?? '') : '';
    ?>
      <div class="bg-white rounded-lg shadow-lg border <?= $isLowBalance ? 'border-red-300' : 'border-gray-200' ?>" id="company-<?= $company['id'] ?>">
        <!-- Company Header (Collapsible) -->
        <div class="p-5 cursor-pointer hover:bg-gray-50 transition" onclick="toggleCompanyDetails(<?= $company['id'] ?>)">
          <div class="flex items-center justify-between">
            <div class="flex items-center flex-1">
              <i class="fas fa-chevron-right text-gray-400 mr-3 transition transform" id="chevron-<?= $company['id'] ?>"></i>
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($company['name']) ?></h3>
                <p class="text-xs text-gray-500">ID: #<?= $company['id'] ?></p>
              </div>
              <?php if ($isLowBalance): ?>
                <span class="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-full mr-3">
                  <i class="fas fa-exclamation-triangle"></i> Low Balance
                </span>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-4">
              <?php if ($sms['success']): ?>
                <div class="text-right">
                  <p class="text-sm text-gray-600">SMS Credits</p>
                  <p class="text-xl font-bold <?= $isLowBalance ? 'text-red-600' : 'text-green-600' ?>">
                    <?= number_format($remainingSMS) ?> / <?= number_format($totalSMS) ?>
                  </p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Expanded Details Section -->
        <div id="details-<?= $company['id'] ?>" class="hidden border-t border-gray-200">
          <?php if ($sms['success']): ?>
            <!-- SMS Management Tab Content -->
            <div class="p-6">
              <!-- Tabs -->
              <div class="border-b border-gray-200 mb-6">
                <nav class="flex space-x-8">
                  <button onclick="showTab(<?= $company['id'] ?>, 'management')" class="company-tab tab-management-<?= $company['id'] ?> py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                    <i class="fas fa-cog mr-2"></i>SMS Management
                  </button>
                  <button onclick="showTab(<?= $company['id'] ?>, 'logs')" class="company-tab tab-logs-<?= $company['id'] ?> py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-list-alt mr-2"></i>SMS Logs
                  </button>
                  <button onclick="showTab(<?= $company['id'] ?>, 'payments')" class="company-tab tab-payments-<?= $company['id'] ?> py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-credit-card mr-2"></i>Payment History
                  </button>
                </nav>
              </div>

              <!-- Management Tab -->
              <div id="tab-management-<?= $company['id'] ?>" class="tab-content">
                <!-- SMS Balance Display -->
                <div class="mb-6">
                  <div class="bg-gradient-to-r from-teal-50 to-blue-50 rounded-lg p-6 border border-teal-200">
                    <div class="flex items-center justify-between mb-4">
                      <div>
                        <p class="text-sm font-medium text-gray-600">SMS Credits Balance</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">
                          <?= number_format($remainingSMS) ?> SMS
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                          <?= number_format($usedSMS) ?> used of <?= number_format($totalSMS) ?> total
                        </p>
                      </div>
                      <div class="p-4 bg-white rounded-full shadow">
                        <i class="fas fa-sms text-4xl text-teal-600"></i>
                      </div>
                    </div>
                    
                    <!-- Usage Progress Bar -->
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                      <div 
                        class="h-3 rounded-full transition-all duration-300 <?= $usagePercent < 50 ? 'bg-green-500' : ($usagePercent < 80 ? 'bg-yellow-500' : 'bg-red-500') ?>" 
                        style="width: <?= min(100, $usagePercent) ?>%"
                      ></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                      <span><?= number_format($usagePercent, 1) ?>% used</span>
                      <span class="font-medium <?= ($sms['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ucfirst($sms['status'] ?? 'unknown') ?>
                      </span>
                    </div>
                    
                    <?php if ($usagePercent >= 90 || $remainingSMS <= 10): ?>
                      <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">
                          <i class="fas fa-exclamation-triangle mr-2"></i>
                          <strong>Warning:</strong> SMS credits running low (<?= number_format($usagePercent, 1) ?>% used)
                        </p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Custom SMS Settings -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                  <div class="flex items-center justify-between mb-4">
                    <div>
                      <label class="text-sm font-medium text-gray-700">Enable Company-Branded SMS</label>
                      <p class="text-xs text-gray-500">Use company name as sender ID instead of "SellApp"</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input 
                        type="checkbox" 
                        class="sr-only peer custom-sms-toggle" 
                        data-company-id="<?= $company['id'] ?>"
                        data-company-name="<?= htmlspecialchars($company['name'], ENT_QUOTES) ?>"
                        <?= $customEnabled ? 'checked' : '' ?>
                      >
                      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
                  
                  <div class="sender-name-section-<?= $company['id'] ?>" style="display: <?= $customEnabled ? 'block' : 'none' ?>;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sender Name (max 11 characters - Arkassel API requirement)</label>
                    <div class="flex gap-2">
                      <input 
                        type="text" 
                        class="sender-name-input flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        data-company-id="<?= $company['id'] ?>"
                        value="<?= htmlspecialchars($senderName) ?>" 
                        maxlength="11" 
                        placeholder="Company Name"
                      >
                      <button 
                        class="save-sender-name-btn px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
                        data-company-id="<?= $company['id'] ?>"
                      >
                        Save
                      </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">This will appear as the sender name for SMS messages</p>
                  </div>
                </div>

                <!-- SMS Management Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                      <div class="bg-green-100 rounded-lg p-2 mr-3">
                        <i class="fas fa-plus-circle text-green-600 text-sm"></i>
                      </div>
                      <h4 class="text-sm font-semibold text-gray-800">Add SMS Credits</h4>
                    </div>
                    <p class="text-xs text-gray-600 mb-3">Add more credits to current balance</p>
                    <div class="flex gap-2">
                      <input 
                        type="number" 
                        class="topup-amount flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"
                        data-company-id="<?= $company['id'] ?>"
                        min="1" 
                        placeholder="Amount"
                      >
                      <button 
                        class="topup-sms-btn px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition text-sm font-medium whitespace-nowrap"
                        data-company-id="<?= $company['id'] ?>"
                      >
                        <i class="fas fa-plus mr-1"></i>Add
                      </button>
                    </div>
                  </div>
                  
                  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                      <div class="bg-blue-100 rounded-lg p-2 mr-3">
                        <i class="fas fa-sync-alt text-blue-600 text-sm"></i>
                      </div>
                      <h4 class="text-sm font-semibold text-gray-800">Reset Total SMS</h4>
                    </div>
                    <p class="text-xs text-gray-600 mb-3">
                      <span class="font-medium text-orange-600">Warning:</span> Resets usage counter
                    </p>
                    <div class="flex gap-2">
                      <input 
                        type="number" 
                        class="set-total-amount flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                        data-company-id="<?= $company['id'] ?>"
                        min="0" 
                        placeholder="New total"
                      >
                      <button 
                        class="set-total-sms-btn px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm font-medium whitespace-nowrap"
                        data-company-id="<?= $company['id'] ?>"
                      >
                        <i class="fas fa-sync-alt mr-1"></i>Reset
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Logs Tab -->
              <div id="tab-logs-<?= $company['id'] ?>" class="tab-content hidden">
                <div id="sms-logs-container-<?= $company['id'] ?>" class="sms-logs-container">
                  <p class="text-gray-500 text-center py-8">Loading SMS logs...</p>
                </div>
              </div>

              <!-- Payments Tab -->
              <div id="tab-payments-<?= $company['id'] ?>" class="tab-content hidden">
                <div id="payment-history-container-<?= $company['id'] ?>" class="payment-history-container">
                  <p class="text-gray-500 text-center py-8">Loading payment history...</p>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="p-6">
              <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
                <p class="text-sm text-yellow-800 mb-2">
                  <i class="fas fa-exclamation-circle"></i> SMS account not initialized
                </p>
                <p class="text-xs text-gray-600">This company's SMS account needs to be initialized before management options become available.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const baseUrl = '<?= BASE_URL_PATH ?>';

// Get auth token
function getAuthToken() {
  return localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '';
}

// Make API request
async function apiRequest(url, method = 'GET', data = null) {
  const token = getAuthToken();
  const options = {
    method: method,
    headers: {
      'Content-Type': 'application/json'
    },
    credentials: 'same-origin' // Include cookies for session-based auth
  };
  
  // Add Authorization header only if token exists
  if (token) {
    options.headers['Authorization'] = `Bearer ${token}`;
  }
  
  if (data) {
    options.body = JSON.stringify(data);
  }
  
  try {
    const response = await fetch(url, options);
    
    // Handle 401 Unauthorized - might need session refresh
    if (response.status === 401) {
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        const errorData = await response.json();
        return { success: false, error: errorData.error || errorData.message || 'Authentication required. Please refresh the page.' };
      }
      return { success: false, error: 'Authentication required. Please refresh the page and try again.' };
    }
    
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('Non-JSON response:', text.substring(0, 200));
      return { success: false, error: 'Server returned non-JSON response' };
    }
    return await response.json();
  } catch (error) {
    console.error('API Error:', error);
    return { success: false, error: error.message };
  }
}

// Show notification
function showNotification(message, type = 'success') {
  const bgColor = type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 ${bgColor}`;
  notification.innerHTML = `
    <div class="flex items-center">
      <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
      <span>${message}</span>
    </div>
  `;
  document.body.appendChild(notification);
  setTimeout(() => notification.remove(), 3000);
}

// Toggle company details
function toggleCompanyDetails(companyId) {
  const details = document.getElementById(`details-${companyId}`);
  const chevron = document.getElementById(`chevron-${companyId}`);
  const isHidden = details.classList.contains('hidden');
  
  if (isHidden) {
    details.classList.remove('hidden');
    chevron.classList.remove('fa-chevron-right');
    chevron.classList.add('fa-chevron-down');
    // Load logs and payments when first opened
    loadSMSLogs(companyId);
    loadPaymentHistory(companyId);
  } else {
    details.classList.add('hidden');
    chevron.classList.remove('fa-chevron-down');
    chevron.classList.add('fa-chevron-right');
  }
}

// Show tab
function showTab(companyId, tabName) {
  // Hide all tabs
  document.querySelectorAll(`#company-${companyId} .tab-content`).forEach(tab => {
    tab.classList.add('hidden');
  });
  
  // Remove active class from all tabs
  document.querySelectorAll(`#company-${companyId} .company-tab`).forEach(btn => {
    btn.classList.remove('border-blue-500', 'text-blue-600');
    btn.classList.add('border-transparent', 'text-gray-500');
  });
  
  // Show selected tab
  document.getElementById(`tab-${tabName}-${companyId}`).classList.remove('hidden');
  
  // Add active class to button
  const btn = document.querySelector(`.tab-${tabName}-${companyId}`);
  btn.classList.remove('border-transparent', 'text-gray-500');
  btn.classList.add('border-blue-500', 'text-blue-600');
  
  // Load data for tabs if needed
  if (tabName === 'logs') {
    loadSMSLogs(companyId);
  } else if (tabName === 'payments') {
    loadPaymentHistory(companyId);
  }
}

// Load SMS Logs
async function loadSMSLogs(companyId) {
  const container = document.getElementById(`sms-logs-container-${companyId}`);
  if (!container) return;
  
  try {
    const result = await apiRequest(`${baseUrl}/api/admin/company/${companyId}/sms/logs`);
    if (result.success) {
      if (result.logs && result.logs.length > 0) {
        container.innerHTML = `
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                ${result.logs.slice(0, 50).map(log => {
                  const date = new Date(log.sent_at || log.created_at);
                  const statusColor = log.status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                  const messageTypeLabel = (log.message_type || 'system').charAt(0).toUpperCase() + (log.message_type || 'system').slice(1);
                  return `
                    <tr class="hover:bg-gray-50">
                      <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">
                        ${date.toLocaleDateString()} ${date.toLocaleTimeString()}
                      </td>
                      <td class="px-3 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">${messageTypeLabel}</span>
                      </td>
                      <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 font-mono">${log.recipient || 'N/A'}</td>
                      <td class="px-3 py-3 text-xs text-gray-600 max-w-xs truncate">${(log.message || 'N/A').substring(0, 50)}${(log.message || '').length > 50 ? '...' : ''}</td>
                      <td class="px-3 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full ${statusColor}">${log.status || 'unknown'}</span>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        `;
      } else {
        container.innerHTML = `<p class="text-gray-500 text-center py-8">${result.message || 'No SMS logs found'}</p>`;
      }
    } else {
      container.innerHTML = `<p class="text-yellow-500 text-center py-8">${result.error || 'Failed to load SMS logs'}</p>`;
    }
  } catch (error) {
    console.error('SMS logs error:', error);
    container.innerHTML = '<p class="text-red-500 text-center py-8">Failed to load SMS logs. Please check console.</p>';
  }
}

// Load Payment History
async function loadPaymentHistory(companyId) {
  const container = document.getElementById(`payment-history-container-${companyId}`);
  if (!container) return;
  
  try {
    const result = await apiRequest(`${baseUrl}/api/payments/sms/history?company_id=${companyId}`);
    if (result.success) {
      if (result.payments && result.payments.length > 0) {
        container.innerHTML = `
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment ID</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">SMS Credits</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                ${result.payments.map(payment => {
                  const date = new Date(payment.created_at);
                  const statusColors = {
                    'completed': 'bg-green-100 text-green-800',
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'failed': 'bg-red-100 text-red-800',
                    'cancelled': 'bg-gray-100 text-gray-800'
                  };
                  return `
                    <tr class="hover:bg-gray-50">
                      <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-600">${date.toLocaleDateString()}</td>
                      <td class="px-3 py-3 whitespace-nowrap text-xs font-mono text-gray-900">${payment.payment_id || 'N/A'}</td>
                      <td class="px-3 py-3 whitespace-nowrap text-right text-xs font-semibold text-gray-900">${payment.currency || 'GHS'} ${parseFloat(payment.amount || 0).toFixed(2)}</td>
                      <td class="px-3 py-3 whitespace-nowrap text-right text-xs text-gray-600">${parseInt(payment.sms_credits || 0).toLocaleString()}</td>
                      <td class="px-3 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full ${statusColors[payment.status] || 'bg-gray-100 text-gray-800'}">${payment.status || 'unknown'}</span>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        `;
      } else {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No payment history found</p>';
      }
    } else {
      container.innerHTML = `<p class="text-yellow-500 text-center py-8">${result.error || 'Failed to load payment history'}</p>`;
    }
  } catch (error) {
    console.error('Payment history error:', error);
    container.innerHTML = '<p class="text-red-500 text-center py-8">Failed to load payment history. Please check console.</p>';
  }
}

// Initialize event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  // Custom SMS Toggle
  document.querySelectorAll('.custom-sms-toggle').forEach(toggle => {
    toggle.addEventListener('change', async function(e) {
      const companyId = this.getAttribute('data-company-id');
      const companyName = this.getAttribute('data-company-name');
      const enabled = e.target.checked;
      const senderNameSection = document.querySelector(`.sender-name-section-${companyId}`);
      const senderNameInput = document.querySelector(`.sender-name-input[data-company-id="${companyId}"]`);
      
      if (enabled) {
        senderNameSection.style.display = 'block';
        
        // Always auto-populate with company name when enabling (truncated to 11 characters)
        const truncatedName = companyName.substring(0, 11);
        senderNameInput.value = truncatedName;
        
        // Automatically save the sender name when enabling
        const saveResult = await apiRequest(
          `${baseUrl}/api/admin/company/${companyId}/sms/sender-name`,
          'POST',
          { sender_name: truncatedName }
        );
        
        if (!saveResult.success) {
          console.warn('Failed to auto-save sender name:', saveResult.error);
          showNotification('Failed to auto-save sender name: ' + (saveResult.error || 'Unknown error'), 'error');
        }
      } else {
        senderNameSection.style.display = 'none';
      }
      
      const result = await apiRequest(
        `${baseUrl}/api/admin/company/${companyId}/sms/toggle`,
        'POST',
        { enabled: enabled }
      );
      
      if (result.success) {
        if (enabled) {
          showNotification('Company-branded SMS enabled. Sender name automatically set to company name.');
        } else {
          showNotification('Company-branded SMS disabled. Using default "SellApp" sender name.');
        }
      } else {
        showNotification(result.error || 'Failed to update setting', 'error');
        e.target.checked = !enabled;
        senderNameSection.style.display = enabled ? 'none' : 'block';
      }
    });
  });

  // Save Sender Name
  document.querySelectorAll('.save-sender-name-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const companyId = this.getAttribute('data-company-id');
      const senderName = document.querySelector(`.sender-name-input[data-company-id="${companyId}"]`).value.trim() || '';
      
      if (!senderName) {
        showNotification('Please enter a sender name', 'error');
        return;
      }
      
      if (senderName.length > 11) {
        showNotification('Sender name must be 11 characters or less (Arkassel API requirement)', 'error');
        return;
      }
      
      const result = await apiRequest(
        `${baseUrl}/api/admin/company/${companyId}/sms/sender-name`,
        'POST',
        { sender_name: senderName }
      );
      
      if (result.success) {
        showNotification('Sender name updated successfully');
      } else {
        showNotification(result.error || 'Failed to update sender name', 'error');
      }
    });
  });

  // Top-up SMS
  document.querySelectorAll('.topup-sms-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const companyId = this.getAttribute('data-company-id');
      const amount = parseInt(document.querySelector(`.topup-amount[data-company-id="${companyId}"]`).value || '0');
      
      if (amount <= 0) {
        showNotification('Please enter a valid amount greater than 0', 'error');
        return;
      }
      
      if (!confirm(`Add ${amount} SMS credits to this company?`)) {
        return;
      }
      
      const result = await apiRequest(
        `${baseUrl}/api/admin/company/${companyId}/sms/topup`,
        'POST',
        { amount: amount }
      );
      
      if (result.success) {
        let notificationMessage = result.message || 'SMS credits added successfully';
        
        // Check if SMS notification was sent
        if (result.sms_notification) {
          if (!result.sms_notification.sent) {
            notificationMessage += '\n\n⚠️ SMS notification could not be sent: ' + (result.sms_notification.error || 'Unknown error');
            if (result.sms_notification.phone) {
              notificationMessage += '\nPhone: ' + result.sms_notification.phone;
            }
          } else {
            notificationMessage += '\n\n✓ SMS notification sent to manager';
          }
        }
        
        showNotification(notificationMessage, result.sms_notification && !result.sms_notification.sent ? 'warning' : 'success');
        document.querySelector(`.topup-amount[data-company-id="${companyId}"]`).value = '';
        
        // Trigger refresh of manager dashboard and balance indicators
        // Dispatch custom event to refresh balance across all open pages
        if (window.parent && window.parent !== window) {
            // If in iframe, trigger refresh in parent
            window.parent.postMessage({ type: 'refreshSMSBalance' }, '*');
        }
        window.dispatchEvent(new CustomEvent('refreshSMSBalance'));
        
        // Reload page after short delay to show updated balance
        setTimeout(() => location.reload(), 1000);
      } else {
        showNotification(result.error || 'Failed to add SMS credits', 'error');
      }
    });
  });

  // Set Total SMS
  document.querySelectorAll('.set-total-sms-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const companyId = this.getAttribute('data-company-id');
      const totalSMS = parseInt(document.querySelector(`.set-total-amount[data-company-id="${companyId}"]`).value || '0');
      
      if (totalSMS < 0) {
        showNotification('Please enter a valid amount (0 or greater)', 'error');
        return;
      }
      
      if (!confirm(`Set total SMS credits to ${totalSMS}? This will reset the usage count.`)) {
        return;
      }
      
      const result = await apiRequest(
        `${baseUrl}/api/admin/company/${companyId}/sms/set-total`,
        'POST',
        { total_sms: totalSMS }
      );
      
      if (result.success) {
        showNotification(result.message || 'Total SMS credits updated successfully');
        document.querySelector(`.set-total-amount[data-company-id="${companyId}"]`).value = '';
        setTimeout(() => location.reload(), 500);
      } else {
        showNotification(result.error || 'Failed to update SMS credits', 'error');
      }
    });
  });
});
</script>
