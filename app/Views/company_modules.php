<?php
/**
 * Company Modules Management Page
 * System Admin only - Full module administration interface
 */
?>

<div class="p-6">
  <!-- Header -->
  <div class="mb-6 flex items-center justify-between">
    <div>
      <div class="flex items-center mb-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/companies/view/<?= $company['id'] ?>" class="text-gray-500 hover:text-gray-700 mr-3">
          <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-3xl font-bold text-gray-800">Company Modules</h2>
      </div>
      <p class="text-gray-600">Manage enabled modules for <?= htmlspecialchars($company['name']) ?></p>
    </div>
    <a href="<?= BASE_URL_PATH ?>/dashboard/companies" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm font-medium">
      <i class="fas fa-arrow-left mr-2"></i>Back to Companies
    </a>
  </div>

  <!-- Company Info Card -->
  <div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center">
      <div class="bg-blue-100 rounded-lg p-4 mr-4">
        <i class="fas fa-building text-blue-600 text-2xl"></i>
      </div>
      <div>
        <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($company['name']) ?></h3>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($company['email'] ?? 'No email') ?></p>
        <p class="text-xs text-gray-500">Company ID: <?= $company['id'] ?></p>
      </div>
    </div>
  </div>

  <!-- Summary Stats -->
  <div id="modules-summary" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-gray-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-gray-800" id="total-modules">0</div>
        <div class="text-sm text-gray-600">Total Modules</div>
      </div>
      <div class="bg-gray-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-green-600" id="enabled-modules">0</div>
        <div class="text-sm text-gray-600">Enabled</div>
      </div>
      <div class="bg-gray-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-gray-600" id="disabled-modules">0</div>
        <div class="text-sm text-gray-600">Disabled</div>
      </div>
    </div>
  </div>

  <!-- Info Alert -->
  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <div class="flex items-start">
      <i class="fas fa-info-circle text-blue-600 mr-3 mt-1"></i>
      <div class="text-sm text-blue-800">
        <p class="font-medium mb-1">Module Management Tips</p>
        <ul class="list-disc list-inside space-y-1 text-blue-700">
          <li>Disabling a module will hide it from all company users' navigation and dashboards</li>
          <li>Existing data for disabled modules will be preserved</li>
          <li>API endpoints for disabled modules will return 403 Forbidden errors</li>
          <li>System Admins can always access all modules regardless of these settings</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Module Management Section -->
  <div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center">
        <i class="fas fa-puzzle-piece text-purple-600 mr-2"></i>
        Module Configuration
      </h3>
      <p class="text-sm text-gray-600 mt-1">
        Enable or disable modules for this company. Changes take effect immediately and affect all users in this company.
      </p>
    </div>

    <!-- Loading State -->
    <div id="modules-loading" class="p-8 text-center">
      <i class="fas fa-spinner fa-spin text-gray-400 text-3xl mb-3"></i>
      <p class="text-gray-500">Loading modules...</p>
    </div>

    <!-- Error State -->
    <div id="modules-error" class="p-8 text-center hidden">
      <i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-3"></i>
      <p class="text-red-600 font-medium">Failed to load modules</p>
      <p class="text-sm text-gray-500 mt-1" id="error-message"></p>
      <button onclick="loadModules()" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-redo mr-2"></i>Retry
      </button>
    </div>

    <!-- Modules Container -->
    <div id="modules-container" class="p-6 space-y-4 hidden">
      <!-- Modules will be loaded here -->
    </div>
  </div>
</div>

<script>
(function() {
  const companyId = <?= isset($company['id']) && is_numeric($company['id']) ? (int)$company['id'] : 'null' ?>;
  const baseUrl = '<?= BASE_URL_PATH ?>';
  
  // Get JWT token from localStorage or session
  function getAuthToken() {
    return localStorage.getItem('auth_token') || localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '';
  }
  
  // Make API request
  async function apiRequest(url, method = 'GET', data = null) {
    const options = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getAuthToken()}`
      },
      credentials: 'same-origin'
    };
    
    if (data) {
      options.body = JSON.stringify(data);
    }
    
    try {
      const response = await fetch(url, options);
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Non-JSON response received:', text.substring(0, 200));
        return { 
          success: false, 
          error: 'Server returned non-JSON response.' 
        };
      }
      
      const result = await response.json();
      return result;
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
  
  // Get module icon
  function getModuleIcon(moduleKey) {
    const icons = {
      'products_inventory': 'boxes',
      'pos_sales': 'cash-register',
      'swap': 'exchange-alt',
      'repairs': 'tools',
      'customers': 'users',
      'staff_management': 'user-tie',
      'reports_analytics': 'chart-line',
      'notifications_sms': 'sms',
      'manager_delete_sales': 'trash',
      'manager_bulk_delete_sales': 'trash-alt',
      'manager_can_sell': 'shopping-cart',
      'manager_create_contact': 'user-plus',
      'manager_delete_contact': 'user-times',
      'charts': 'chart-bar'
    };
    return icons[moduleKey] || 'puzzle-piece';
  }
  
  // Get module name from key
  function getModuleName(moduleKey) {
    const names = {
      'products_inventory': 'Products & Inventory',
      'pos_sales': 'POS / Sales',
      'swap': 'Swap',
      'repairs': 'Repairs',
      'customers': 'Customers',
      'staff_management': 'Staff Management',
      'reports_analytics': 'Reports & Analytics',
      'notifications_sms': 'Notifications & SMS',
      'manager_delete_sales': 'Manager Delete Sales',
      'manager_bulk_delete_sales': 'Manager Bulk Delete Sales',
      'manager_can_sell': 'Manager Can Sell',
      'manager_create_contact': 'Manager Create Contact',
      'manager_delete_contact': 'Manager Delete Contact',
      'charts': 'Dashboard Charts'
    };
    return names[moduleKey] || moduleKey;
  }
  
  // Get module description
  function getModuleDescription(moduleKey) {
    const descriptions = {
      'products_inventory': 'Manage products, inventory, categories, and stock levels',
      'pos_sales': 'Point of Sale system for processing sales transactions',
      'swap': 'Device swapping system allowing customers to exchange devices',
      'repairs': 'Repair service management system for tracking device repairs',
      'customers': 'Customer management system for tracking customer information',
      'staff_management': 'Staff and user management for companies',
      'reports_analytics': 'Business intelligence and reporting system',
      'notifications_sms': 'SMS notification system and company SMS management',
      'manager_delete_sales': 'Allow managers to delete individual sales records',
      'manager_bulk_delete_sales': 'Allow managers to bulk delete sales records',
      'manager_can_sell': 'Allow managers to process sales transactions',
      'manager_create_contact': 'Allow managers to create customer contacts',
      'manager_delete_contact': 'Allow managers to delete customer contacts',
      'charts': 'Interactive charts and graphs for dashboard analytics and performance trends'
    };
    return descriptions[moduleKey] || 'Module functionality';
  }
  
  // Load company modules
  window.loadModules = async function() {
    const container = document.getElementById('modules-container');
    const loading = document.getElementById('modules-loading');
    const error = document.getElementById('modules-error');
    const summary = document.getElementById('modules-summary');
    
    // Show loading, hide error and container
    loading.classList.remove('hidden');
    error.classList.add('hidden');
    container.classList.add('hidden');
    summary.classList.add('hidden');
    
    if (!companyId || isNaN(companyId)) {
      loading.classList.add('hidden');
      error.classList.remove('hidden');
      document.getElementById('error-message').textContent = 'Invalid company ID';
      return;
    }
    
    try {
      const result = await apiRequest(`${baseUrl}/api/admin/company/${companyId}/modules`);
      
      if (result.success && result.modules) {
        loading.classList.add('hidden');
        container.classList.remove('hidden');
        summary.classList.remove('hidden');
        
        // Count enabled/disabled
        let enabledCount = 0;
        let disabledCount = 0;
        
        const modulesHtml = result.modules.map(module => {
          const enabled = module.enabled;
          if (enabled) enabledCount++;
          else disabledCount++;
          
          return `
            <div class="flex items-center justify-between p-5 border border-gray-200 rounded-lg hover:bg-gray-50 transition ${enabled ? 'bg-green-50 border-green-200' : ''}">
              <div class="flex items-center flex-1">
                <div class="flex-shrink-0 mr-4">
                  <div class="bg-${enabled ? 'green' : 'gray'}-100 rounded-lg p-3">
                    <i class="fas fa-${getModuleIcon(module.key)} text-${enabled ? 'green' : 'gray'}-600 text-xl"></i>
                  </div>
                </div>
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-900 flex items-center">
                    ${getModuleName(module.key)}
                    ${enabled ? '<span class="ml-2 px-2 py-1 bg-green-100 text-green-700 text-xs rounded">Enabled</span>' : '<span class="ml-2 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">Disabled</span>'}
                  </h4>
                  <p class="text-sm text-gray-600 mt-1">${getModuleDescription(module.key)}</p>
                  <p class="text-xs text-gray-500 mt-1">Module Key: <code class="bg-gray-100 px-1 rounded">${module.key}</code></p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer ml-4">
                <input 
                  type="checkbox" 
                  class="sr-only peer module-toggle" 
                  data-module-key="${module.key}"
                  data-module-name="${getModuleName(module.key)}"
                  ${enabled ? 'checked' : ''}
                >
                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-600"></div>
              </label>
            </div>
          `;
        }).join('');
        
        container.innerHTML = modulesHtml;
        
        // Update summary
        document.getElementById('total-modules').textContent = result.modules.length;
        document.getElementById('enabled-modules').textContent = enabledCount;
        document.getElementById('disabled-modules').textContent = disabledCount;
        
        // Attach event listeners to toggle switches
        document.querySelectorAll('.module-toggle').forEach(toggle => {
          toggle.addEventListener('change', async function(e) {
            const moduleKey = this.getAttribute('data-module-key');
            const moduleName = this.getAttribute('data-module-name');
            const enabled = this.checked;
            
            // Disable toggle while processing
            this.disabled = true;
            
            const result = await apiRequest(
              `${baseUrl}/api/admin/company/${companyId}/modules/toggle`,
              'POST',
              { module_key: moduleKey, enabled: enabled }
            );
            
            // Re-enable toggle
            this.disabled = false;
            
            if (result.success) {
              showNotification(`${result.data.enabled ? 'Enabled' : 'Disabled'} ${moduleName} module successfully`);
              // Reload modules to update UI
              setTimeout(() => loadModules(), 500);
            } else {
              // Revert toggle on error
              this.checked = !enabled;
              showNotification(result.error || 'Failed to update module status', 'error');
            }
          });
        });
      } else {
        loading.classList.add('hidden');
        error.classList.remove('hidden');
        document.getElementById('error-message').textContent = result.error || 'Failed to load modules';
      }
    } catch (error) {
      console.error('Modules loading error:', error);
      loading.classList.add('hidden');
      error.classList.remove('hidden');
      document.getElementById('error-message').textContent = error.message || 'An unexpected error occurred';
    }
  };
  
  // Load modules on page load
  document.addEventListener('DOMContentLoaded', function() {
    loadModules();
  });
})();
</script>

