<?php
// Company Settings View - Content only (uses dashboard layout)
// This file allows managers to configure which services use company SMS credits

$userRole = $userRole ?? ($_SESSION['user']['role'] ?? 'manager');
$companyId = $_SESSION['user']['company_id'] ?? null;
$settings = $settings ?? [];
?>

<div class="w-full">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Company SMS Settings</h1>
        <p class="text-sm text-gray-600 mt-1">Configure which services will use your company's SMS credits for notifications</p>
        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Note:</strong> When enabled, these services will use your company's SMS credits. 
                Only administrative/system SMS uses SellApp's system SMS balance.
            </p>
        </div>
    </div>
    
    <!-- SMS Notification Settings -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-800 mb-4">SMS Notification Preferences</h3>
        
        <div class="space-y-3">
            <!-- Purchase Notifications -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h4 class="text-xs font-medium text-gray-900">SMS for Purchase Notifications</h4>
                    <p class="text-xs text-gray-500 mt-1">Send SMS notifications when purchases are made (uses company SMS credits)</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-4">
                    <input type="checkbox" id="company-sms-purchase-enabled" value="1" 
                           <?= ($settings['sms_purchase_enabled'] ?? 1) ? 'checked' : '' ?> 
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            
            <!-- Repair Notifications -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h4 class="text-xs font-medium text-gray-900">SMS for Repair Notifications</h4>
                    <p class="text-xs text-gray-500 mt-1">Send SMS notifications when repairs are completed (uses company SMS credits)</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-4">
                    <input type="checkbox" id="company-sms-repair-enabled" value="1" 
                           <?= ($settings['sms_repair_enabled'] ?? 1) ? 'checked' : '' ?> 
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            
            <!-- Swap Notifications -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h4 class="text-xs font-medium text-gray-900">SMS for Swap Notifications</h4>
                    <p class="text-xs text-gray-500 mt-1">Send SMS notifications when swaps are completed (uses company SMS credits)</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-4">
                    <input type="checkbox" id="company-sms-swap-enabled" value="1" 
                           <?= ($settings['sms_swap_enabled'] ?? 1) ? 'checked' : '' ?> 
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            
            <!-- Payment Notifications -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h4 class="text-xs font-medium text-gray-900">SMS for Payment Notifications</h4>
                    <p class="text-xs text-gray-500 mt-1">Send SMS notifications when partial payments are made or completed (uses company SMS credits)</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-4">
                    <input type="checkbox" id="company-sms-payment-enabled" value="1" 
                           <?= ($settings['sms_payment_enabled'] ?? 1) ? 'checked' : '' ?> 
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button onclick="saveCompanySettings()" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
    
    <!-- SMS Balance Info -->
    <div class="mt-4 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-800 mb-3">SMS Balance Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-lg font-bold text-blue-600" id="company-sms-total">0</p>
                <p class="text-xs text-gray-600 mt-1">Total SMS Credits</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-lg font-bold text-yellow-600" id="company-sms-used">0</p>
                <p class="text-xs text-gray-600 mt-1">SMS Used</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-lg font-bold text-green-600" id="company-sms-remaining">0</p>
                <p class="text-xs text-gray-600 mt-1">SMS Remaining</p>
            </div>
        </div>
    </div>
</div>

<script>
// Load company settings on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCompanySettings();
    loadCompanySMSBalance();
});

function loadCompanySettings() {
    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    fetch(BASE + '/api/company/settings', {
        headers: headers
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const settings = data.settings || {};
            
            // Update toggle states
            const purchaseToggle = document.getElementById('company-sms-purchase-enabled');
            const repairToggle = document.getElementById('company-sms-repair-enabled');
            const swapToggle = document.getElementById('company-sms-swap-enabled');
            const paymentToggle = document.getElementById('company-sms-payment-enabled');
            
            if (purchaseToggle) {
                const purchaseValue = settings['sms_purchase_enabled'] ?? 1;
                purchaseToggle.checked = purchaseValue === 1 || purchaseValue === true || purchaseValue === '1';
            }
            
            if (repairToggle) {
                const repairValue = settings['sms_repair_enabled'] ?? 1;
                repairToggle.checked = repairValue === 1 || repairValue === true || repairValue === '1';
            }
            
            if (swapToggle) {
                const swapValue = settings['sms_swap_enabled'] ?? 1;
                swapToggle.checked = swapValue === 1 || swapValue === true || swapValue === '1';
            }
            
            if (paymentToggle) {
                const paymentValue = settings['sms_payment_enabled'] ?? 1;
                paymentToggle.checked = paymentValue === 1 || paymentValue === true || paymentValue === '1';
            }
        } else {
            console.error('Error loading company settings:', data.error);
        }
    })
    .catch(error => {
        console.error('Error loading company settings:', error);
    });
}

function loadCompanySMSBalance() {
    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    fetch(BASE + '/api/company/sms-balance', {
        headers: headers
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.balance) {
            document.getElementById('company-sms-total').textContent = (data.balance.total_sms || 0).toLocaleString();
            document.getElementById('company-sms-used').textContent = (data.balance.sms_used || 0).toLocaleString();
            document.getElementById('company-sms-remaining').textContent = (data.balance.sms_remaining || 0).toLocaleString();
        }
    })
    .catch(error => {
        console.error('Error loading SMS balance:', error);
    });
}

function saveCompanySettings() {
    const purchaseToggle = document.getElementById('company-sms-purchase-enabled');
    const repairToggle = document.getElementById('company-sms-repair-enabled');
    const swapToggle = document.getElementById('company-sms-swap-enabled');
    const paymentToggle = document.getElementById('company-sms-payment-enabled');
    
    const settings = {
        sms_purchase_enabled: purchaseToggle && purchaseToggle.checked ? 1 : 0,
        sms_repair_enabled: repairToggle && repairToggle.checked ? 1 : 0,
        sms_swap_enabled: swapToggle && swapToggle.checked ? 1 : 0,
        sms_payment_enabled: paymentToggle && paymentToggle.checked ? 1 : 0
    };
    
    console.log('Saving company settings:', settings);
    
    const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    fetch(BASE + '/api/company/settings/update', {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(settings)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Company settings saved successfully!');
            loadCompanySettings();
        } else {
            alert('Failed to save settings: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving settings: ' + error.message);
    });
}
</script>

