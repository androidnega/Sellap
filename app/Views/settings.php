<?php
// System Settings View - Content only (uses dashboard layout)
// This file is included within the dashboard layout

// Ensure variables are set
$settings = $settings ?? [];
$cloudinaryConfigured = $cloudinaryConfigured ?? false;
$smsConfigured = $smsConfigured ?? false;
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
        <p class="text-gray-600 mt-2">Configure Cloudinary storage, SMS notifications, and payment gateways</p>
            </div>
            
            <!-- Settings Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6">
                        <button onclick="showTab('cloudinary')" id="cloudinary-tab" class="tab-button active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                            <i class="fas fa-cloud mr-2"></i>Cloudinary Storage
                        </button>
                        <button onclick="showTab('sms')" id="sms-tab" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <i class="fas fa-sms mr-2"></i>SMS Notifications
                        </button>
                        <button onclick="showTab('paystack')" id="paystack-tab" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <i class="fas fa-credit-card mr-2"></i>Paystack Payment
                        </button>
                        <button onclick="showTab('general')" id="general-tab" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            <i class="fas fa-cog mr-2"></i>General Settings
                        </button>
                    </nav>
                </div>
                
                <!-- Cloudinary Settings Tab -->
                <div id="cloudinary-content" class="tab-content p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Cloudinary Configuration</h3>
                        <p class="text-gray-600">Configure cloud storage for image uploads</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cloud Name</label>
                        <input type="text" id="cloudinary-cloud-name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="your-cloud-name" value="<?= htmlspecialchars($settings['cloudinary_cloud_name'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                        <input type="text" id="cloudinary-api-key" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="your-api-key" value="<?= htmlspecialchars($settings['cloudinary_api_key'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Secret</label>
                        <input type="password" id="cloudinary-api-secret" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="your-api-secret" value="<?= htmlspecialchars($settings['cloudinary_api_secret'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">Configuration Status</h4>
                                <div id="cloudinary-status" class="flex items-center">
                            <?php if ($cloudinaryConfigured): ?>
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="text-sm text-green-600">Configured</span>
                            <?php else: ?>
                                    <i class="fas fa-circle text-gray-400 mr-2"></i>
                                    <span class="text-sm text-gray-600">Not configured</span>
                            <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <button onclick="testCloudinary()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                    <i class="fas fa-vial mr-2"></i>Test Configuration
                                </button>
                                <button onclick="saveCloudinarySettings()" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                                    <i class="fas fa-save mr-2"></i>Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Image Upload Test -->
                    <div class="mt-8 border-t pt-6">
                        <h4 class="font-medium text-gray-800 mb-4">Test Image Upload</h4>
                        <div class="flex items-center space-x-4">
                            <input type="file" id="test-image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <button onclick="testImageUpload()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition">
                                <i class="fas fa-upload mr-2"></i>Upload Test
                            </button>
                        </div>
                        <div id="upload-result" class="mt-4 hidden">
                            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                    <div>
                                        <h4 class="text-sm font-medium text-green-800">Upload Successful</h4>
                                        <p class="text-sm text-green-700 mt-1" id="upload-url"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SMS Settings Tab -->
                <div id="sms-content" class="tab-content p-6 hidden">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">SMS Configuration</h3>
                        <p class="text-gray-600">Configure SMS notifications for purchases and other events</p>
                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>SMS Configuration:</strong> The system now supports Arkasel SMS API. 
                                For testing, leave the API key empty to enable simulation mode. 
                                For production, configure your Arkasel API credentials from <a href="https://sms.arkesel.com/user/sms-api/info" target="_blank" class="text-blue-600 underline">sms.arkesel.com</a>.
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Arkasel API Key</label>
                        <input type="text" id="sms-api-key" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="your-arkasel-api-key (leave empty for simulation mode)" value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID</label>
                        <input type="text" id="sms-sender-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="SellApp" value="<?= htmlspecialchars($settings['sms_sender_id'] ?? 'SellApp') ?>">
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">Configuration Status</h4>
                                <div id="sms-status" class="flex items-center">
                            <?php if ($smsConfigured): ?>
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="text-sm text-green-600">Configured</span>
                            <?php else: ?>
                                    <i class="fas fa-circle text-gray-400 mr-2"></i>
                                    <span class="text-sm text-gray-600">Not configured</span>
                            <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <button onclick="testSMS()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                    <i class="fas fa-vial mr-2"></i>Test Configuration
                                </button>
                                <button onclick="saveSMSSettings()" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                                    <i class="fas fa-save mr-2"></i>Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SMS Test -->
                    <div class="mt-8 border-t pt-6">
                        <h4 class="font-medium text-gray-800 mb-4">Test SMS</h4>
                        <div class="flex items-center space-x-4">
                            <input type="tel" id="test-phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="+233XXXXXXXXX">
                            <button onclick="sendTestSMS()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition">
                                <i class="fas fa-paper-plane mr-2"></i>Send Test SMS
                            </button>
                        </div>
                        <div id="sms-result" class="mt-4 hidden">
                            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                    <div>
                                        <h4 class="text-sm font-medium text-green-800">SMS Sent Successfully</h4>
                                        <p class="text-sm text-green-700 mt-1" id="sms-message-id"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paystack Settings Tab -->
                <div id="paystack-content" class="tab-content p-6 hidden">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Paystack Payment Configuration</h3>
                        <p class="text-gray-600">Configure Paystack payment gateway for transactions</p>
                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Paystack Configuration:</strong> Get your API keys from your <a href="https://dashboard.paystack.com/#/settings/developer" target="_blank" class="text-blue-600 underline">Paystack Dashboard</a>. 
                                Use test keys for development and live keys for production.
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                                <input type="password" id="paystack-secret-key" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="sk_test_xxxxxxxxxxxxx" value="<?= htmlspecialchars($settings['paystack_secret_key'] ?? '') ?>">
                                <p class="text-xs text-gray-500 mt-1">Your Paystack secret key (starts with sk_test_ or sk_live_)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Public Key</label>
                                <input type="text" id="paystack-public-key" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="pk_test_xxxxxxxxxxxxx" value="<?= htmlspecialchars($settings['paystack_public_key'] ?? '') ?>">
                                <p class="text-xs text-gray-500 mt-1">Your Paystack public key (starts with pk_test_ or pk_live_)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                                <select id="paystack-mode" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updatePaystackModeDisplay()">
                                    <option value="test" <?= ($settings['paystack_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test Mode</option>
                                    <option value="live" <?= ($settings['paystack_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live Mode</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Use test mode for development, live mode for production</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">Configuration Status</h4>
                                <div id="paystack-status" class="flex items-center">
                                    <?php 
                                    $paystackConfigured = !empty($settings['paystack_secret_key'] ?? '') && !empty($settings['paystack_public_key'] ?? '');
                                    ?>
                                    <?php if ($paystackConfigured): ?>
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        <span class="text-sm text-green-600">Configured</span>
                                    <?php else: ?>
                                        <i class="fas fa-circle text-gray-400 mr-2"></i>
                                        <span class="text-sm text-gray-600">Not configured</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 text-xs text-gray-600">
                                    <p><strong>Current Mode:</strong> <span id="paystack-mode-display"><?= strtoupper($settings['paystack_mode'] ?? 'test') ?></span></p>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <button onclick="testPaystack()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                    <i class="fas fa-vial mr-2"></i>Test Configuration
                                </button>
                                <button onclick="savePaystackSettings()" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                                    <i class="fas fa-save mr-2"></i>Save Settings
                                </button>
                            </div>
                            
                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mt-4">
                                <h4 class="text-sm font-medium text-yellow-800 mb-2">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Security Note
                                </h4>
                                <p class="text-xs text-yellow-700">
                                    Never share your secret keys. Keep them secure and never commit them to version control. 
                                    Live keys should only be used in production environments.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- General Settings Tab -->
                <div id="general-content" class="tab-content p-6 hidden">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">General Settings</h3>
                <p class="text-gray-600">Configure general system preferences</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Image Quality</label>
                            <select id="default-image-quality" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="auto" <?= ($settings['default_image_quality'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Auto</option>
                        <option value="80" <?= ($settings['default_image_quality'] ?? '') === '80' ? 'selected' : '' ?>>80%</option>
                        <option value="90" <?= ($settings['default_image_quality'] ?? '') === '90' ? 'selected' : '' ?>>90%</option>
                        <option value="100" <?= ($settings['default_image_quality'] ?? '') === '100' ? 'selected' : '' ?>>100%</option>
                            </select>
                        </div>
                        
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">SMS for Purchase Notifications</h4>
                            <p class="text-sm text-gray-500">Send SMS notifications when purchases are made</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="sms-purchase-enabled" value="1" <?= ($settings['sms_purchase_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">SMS for Repair Notifications</h4>
                            <p class="text-sm text-gray-500">Send SMS notifications when repairs are completed</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="sms-repair-enabled" value="1" <?= ($settings['sms_repair_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button onclick="saveGeneralSettings()" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                                <i class="fas fa-save mr-2"></i>Save General Settings
                            </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
// Tab switching
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-content').classList.remove('hidden');
    
    // Add active class to selected tab
    const activeButton = document.getElementById(tabName + '-tab');
    activeButton.classList.add('active', 'border-blue-500', 'text-blue-600');
    activeButton.classList.remove('border-transparent', 'text-gray-500');
    
    // Save current tab to localStorage
    localStorage.setItem('settings_active_tab', tabName);
}

// Restore active tab from localStorage
function restoreActiveTab() {
    const savedTab = localStorage.getItem('settings_active_tab');
    if (savedTab && ['cloudinary', 'sms', 'paystack', 'general'].includes(savedTab)) {
        showTab(savedTab);
    }
}

// Load settings on page load
document.addEventListener('DOMContentLoaded', function() {
    // Restore active tab first
    restoreActiveTab();
    // Then load settings
    loadSettings();
});
        
        function loadSettings() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
    
    const headers = {
        'Content-Type': 'application/json'
    };
            
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
                }
    
    fetch(BASE + '/api/system-settings', {
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
                    const settings = data.settings;
                    
            // Update Cloudinary fields
                    if (settings['cloudinary_cloud_name']) {
                        document.getElementById('cloudinary-cloud-name').value = settings['cloudinary_cloud_name'];
                    }
                    if (settings['cloudinary_api_key']) {
                        document.getElementById('cloudinary-api-key').value = settings['cloudinary_api_key'];
                    }
                    if (settings['cloudinary_api_secret']) {
                        document.getElementById('cloudinary-api-secret').value = settings['cloudinary_api_secret'];
                    }
                    
            // Update SMS fields
                    if (settings['sms_api_key']) {
                        document.getElementById('sms-api-key').value = settings['sms_api_key'];
                    }
                    if (settings['sms_sender_id']) {
                        document.getElementById('sms-sender-id').value = settings['sms_sender_id'];
                    }
                    
            // Update Paystack fields
                    if (settings['paystack_secret_key']) {
                        document.getElementById('paystack-secret-key').value = settings['paystack_secret_key'];
                    }
                    if (settings['paystack_public_key']) {
                        document.getElementById('paystack-public-key').value = settings['paystack_public_key'];
                    }
                    if (settings['paystack_mode']) {
                        document.getElementById('paystack-mode').value = settings['paystack_mode'];
                        document.getElementById('paystack-mode-display').textContent = settings['paystack_mode'].toUpperCase();
                    }
                    
            // Update general settings
                    if (settings['default_image_quality']) {
                        document.getElementById('default-image-quality').value = settings['default_image_quality'];
                    }
                    if (settings['sms_purchase_enabled']) {
                        document.getElementById('sms-purchase-enabled').checked = settings['sms_purchase_enabled'] === '1';
                    }
                    if (settings['sms_repair_enabled']) {
                        document.getElementById('sms-repair-enabled').checked = settings['sms_repair_enabled'] === '1';
                    }
                    
                    updateStatusIndicators();
                } else {
                    // Handle API error response
                    if (data.error === 'Unauthorized' && data.redirect) {
                        alert('You need to log in as a system administrator to access settings. Redirecting to login...');
                        window.location.href = data.redirect;
                    } else {
                        alert('Error loading settings: ' + (data.message || data.error || 'Unknown error'));
                    }
                }
            })
            .catch(error => {
                console.error('Error loading settings:', error);
                alert('Error loading settings: ' + error.message);
            });
        }
        
        function updateStatusIndicators() {
    const cloudinaryName = document.getElementById('cloudinary-cloud-name').value;
    const cloudinaryKey = document.getElementById('cloudinary-api-key').value;
    const cloudinarySecret = document.getElementById('cloudinary-api-secret').value;
    
    const smsKey = document.getElementById('sms-api-key').value;
    
    // Update Cloudinary status
            const cloudinaryStatus = document.getElementById('cloudinary-status');
    if (cloudinaryName && cloudinaryKey && cloudinarySecret) {
        cloudinaryStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i><span class="text-sm text-green-600">Configured</span>';
            } else {
                cloudinaryStatus.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i><span class="text-sm text-gray-600">Not configured</span>';
            }
            
    // Update SMS status
            const smsStatus = document.getElementById('sms-status');
    if (smsKey) {
        smsStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i><span class="text-sm text-green-600">Configured</span>';
            } else {
                smsStatus.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i><span class="text-sm text-gray-600">Not configured</span>';
            }
            
    // Update Paystack status
            const paystackSecret = document.getElementById('paystack-secret-key').value;
            const paystackPublic = document.getElementById('paystack-public-key').value;
            const paystackStatus = document.getElementById('paystack-status');
            if (paystackSecret && paystackPublic) {
                paystackStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i><span class="text-sm text-green-600">Configured</span>';
            } else {
                paystackStatus.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i><span class="text-sm text-gray-600">Not configured</span>';
            }
            
            // Update Paystack mode display
            updatePaystackModeDisplay();
        }
        
        function updatePaystackModeDisplay() {
            const paystackMode = document.getElementById('paystack-mode').value;
            const modeDisplay = document.getElementById('paystack-mode-display');
            if (modeDisplay) {
                modeDisplay.textContent = paystackMode.toUpperCase();
            }
        }
        
        function testCloudinary() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {
                    'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
                }
    
    fetch(BASE + '/api/system-settings/test-cloudinary', {
        method: 'POST',
        headers: headers
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cloudinary test successful!');
                } else {
            alert('Cloudinary test failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error testing Cloudinary configuration');
            });
        }
        
        function testSMS() {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {
                    'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
                }
    
    fetch(BASE + '/api/system-settings/test-sms', {
        method: 'POST',
        headers: headers
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('SMS configuration test successful!');
                } else {
            alert('SMS test failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error testing SMS configuration');
            });
        }
        
        function sendTestSMS() {
            const phoneNumber = document.getElementById('test-phone').value;
            if (!phoneNumber) {
                alert('Please enter a phone number');
                return;
            }
            
            // Check if API key is configured
            const apiKey = document.getElementById('sms-api-key').value.trim();
            if (!apiKey) {
                if (!confirm('No SMS API key is configured. You need to enter your Arkasel API key first to send real SMS. Click OK to continue anyway (will use simulation mode), or Cancel to go back and configure it.')) {
                    return;
                }
            }
            
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    // Show loading state - find button by ID or class
    const sendButton = document.querySelector('#test-phone').parentElement.querySelector('button');
    if (sendButton) {
        const originalText = sendButton.innerHTML;
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        
        // Store original HTML for restoration
        window._smsButtonOriginal = originalText;
    }
    
    fetch(BASE + '/api/system-settings/send-test-sms', {
                method: 'POST',
        headers: headers,
                body: JSON.stringify({
                    phone_number: phoneNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                const sendButton = document.querySelector('#test-phone').parentElement.querySelector('button');
                if (sendButton && window._smsButtonOriginal) {
                    sendButton.disabled = false;
                    sendButton.innerHTML = window._smsButtonOriginal;
                }
                
                if (data.success) {
                    if (data.simulated === false) {
                        document.getElementById('sms-result').classList.remove('hidden');
                        document.getElementById('sms-result').innerHTML = `
                            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                    <div>
                                        <h4 class="text-sm font-medium text-green-800">SMS Sent Successfully (Real SMS via Arkasel)</h4>
                                        <p class="text-sm text-green-700 mt-1">Message ID: ${data.message_id}</p>
                                        <p class="text-xs text-green-600 mt-1">Check your phone for the message. Delivery may take a few seconds.</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        alert('SMS sent in simulation mode. Message ID: ' + data.message_id + '\n\nNote: No actual SMS was sent. Please configure your Arkasel API key to send real SMS.');
                    }
                } else {
                    let errorMsg = data.error || 'Unknown error occurred';
                    if (data.http_code) {
                        errorMsg += ' (HTTP ' + data.http_code + ')';
                    }
                    
                    // Log detailed error information to console for debugging
                    console.group('🔴 SMS API Error Details');
                    console.error('Error Message:', data.error);
                    console.error('HTTP Code:', data.http_code);
                    console.error('Full Error Object:', data);
                    if (data.details) {
                        console.error('Error Details:', data.details);
                        if (data.details.response) {
                            try {
                                const responseData = typeof data.details.response === 'string' 
                                    ? JSON.parse(data.details.response) 
                                    : data.details.response;
                                console.error('Parsed API Response:', responseData);
                            } catch (e) {
                                console.error('Raw API Response (string):', data.details.response);
                            }
                        }
                    }
                    if (data.response) {
                        try {
                            const responseData = typeof data.response === 'string' 
                                ? JSON.parse(data.response) 
                                : data.response;
                            console.error('API Response Object:', responseData);
                        } catch (e) {
                            console.error('Raw Response (string):', data.response);
                        }
                    }
                    console.groupEnd();
                    
                    // Parse error details if available for user-friendly message
                    let detailedError = errorMsg;
                    if (data.response) {
                        try {
                            const responseData = typeof data.response === 'string' 
                                ? JSON.parse(data.response) 
                                : data.response;
                            
                            if (responseData.message) {
                                detailedError += '\n\nAPI Message: ' + responseData.message;
                            }
                            if (responseData.error) {
                                detailedError += '\n\nAPI Error: ' + (typeof responseData.error === 'string' ? responseData.error : JSON.stringify(responseData.error));
                            }
                            if (responseData.status) {
                                detailedError += '\n\nStatus: ' + responseData.status;
                            }
                        } catch (e) {
                            detailedError += '\n\nRaw Response: ' + (typeof data.response === 'string' ? data.response.substring(0, 200) : JSON.stringify(data.response).substring(0, 200));
                        }
                    }
                    if (data.details) {
                        if (data.details.response) {
                            try {
                                const responseData = typeof data.details.response === 'string' 
                                    ? JSON.parse(data.details.response) 
                                    : data.details.response;
                                
                                if (responseData.message && !detailedError.includes(responseData.message)) {
                                    detailedError += '\n\nAPI Message: ' + responseData.message;
                                }
                                if (responseData.error && !detailedError.includes('API Error')) {
                                    detailedError += '\n\nAPI Error: ' + (typeof responseData.error === 'string' ? responseData.error : JSON.stringify(responseData.error));
                                }
                            } catch (e) {
                                // Already handled above
                            }
                        }
                        if (data.details.error) {
                            detailedError += '\n\nAdditional Error: ' + data.details.error;
                        }
                    }
                    
                    alert('Failed to send SMS:\n\n' + detailedError + '\n\nPlease check:\n1. Your Arkasel API key is correct\n2. Your phone number format is correct (+233XXXXXXXXX)\n3. Your Arkasel account has sufficient credits\n4. Check browser console (F12) for more details');
                }
            })
            .catch(error => {
                const sendButton = document.querySelector('#test-phone').parentElement.querySelector('button');
                if (sendButton && window._smsButtonOriginal) {
                    sendButton.disabled = false;
                    sendButton.innerHTML = window._smsButtonOriginal;
                }
                console.error('Error:', error);
                alert('Error sending test SMS: ' + error.message);
            });
        }
        
        function testImageUpload() {
            const fileInput = document.getElementById('test-image');
    if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select an image file');
                return;
            }
            
            const formData = new FormData();
            formData.append('image', fileInput.files[0]);
            formData.append('folder', 'sellapp/test');
            
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {};
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    fetch(BASE + '/api/system-settings/upload-image', {
                method: 'POST',
        headers: headers,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('upload-result').classList.remove('hidden');
                    document.getElementById('upload-url').textContent = data.secure_url;
                } else {
            alert('Upload failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading image');
            });
        }
        
        function saveCloudinarySettings() {
            const settings = {
                cloudinary_cloud_name: document.getElementById('cloudinary-cloud-name').value,
                cloudinary_api_key: document.getElementById('cloudinary-api-key').value,
                cloudinary_api_secret: document.getElementById('cloudinary-api-secret').value
            };
            
            saveSettings(settings);
        }
        
        function saveSMSSettings() {
            const settings = {
                sms_api_key: document.getElementById('sms-api-key').value,
                sms_sender_id: document.getElementById('sms-sender-id').value
            };
            
            saveSettings(settings);
        }
        
        function savePaystackSettings() {
            const settings = {
                paystack_secret_key: document.getElementById('paystack-secret-key').value,
                paystack_public_key: document.getElementById('paystack-public-key').value,
                paystack_mode: document.getElementById('paystack-mode').value
            };
            
            saveSettings(settings);
        }
        
        function testPaystack() {
            const secretKey = document.getElementById('paystack-secret-key').value.trim();
            const publicKey = document.getElementById('paystack-public-key').value.trim();
            
            if (!secretKey || !publicKey) {
                alert('Please enter both Secret Key and Public Key before testing');
                return;
            }
            
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {
                    'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
                }
    
    fetch(BASE + '/api/system-settings/test-paystack', {
        method: 'POST',
        headers: headers
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Paystack configuration test successful!');
                } else {
            alert('Paystack test failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error testing Paystack configuration');
            });
        }
        
        function saveGeneralSettings() {
            const settings = {
                default_image_quality: document.getElementById('default-image-quality').value,
                sms_purchase_enabled: document.getElementById('sms-purchase-enabled').checked ? '1' : '0',
        sms_repair_enabled: document.getElementById('sms-repair-enabled').checked ? '1' : '0'
            };
            
            saveSettings(settings);
        }
        
        function saveSettings(settings) {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            
    const headers = {
        'Content-Type': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    fetch(BASE + '/api/system-settings/update', {
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
                    alert('Settings saved successfully!');
                    updateStatusIndicators();
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
    
    <style>
        .tab-button.active {
            border-bottom-color: #3b82f6 !important;
            color: #3b82f6 !important;
        }
    </style>
