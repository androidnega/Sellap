<?php
// Top Navigation Bar Component
// Usage: include with $userInfo, $companyInfo, $userRole
?>
<!-- Top Navigation Bar -->
<nav class="bg-white px-4 py-3 w-full">
    <div class="flex items-center justify-between w-full">
        <!-- Left side - Mobile menu button, breadcrumb (when collapsed), and title -->
        <div class="flex items-center">
            <!-- Mobile menu button -->
            <button class="md:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Page title -->
            <div class="ml-4 md:ml-0">
                <h1 class="text-xl font-semibold text-gray-900" id="page-title">Dashboard</h1>
            </div>
        </div>
        
        <!-- Right side - Balance indicators and Profile dropdown -->
        <div class="flex items-center space-x-3 md:space-x-4">
            <!-- Balance Indicators (Manager/Admin only) -->
            <?php if (in_array($userRole, ['manager', 'admin'])): ?>
            <div class="hidden lg:flex items-center space-x-2">
                <!-- GHS Balance -->
                <div id="balance-indicator-ghs" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg flex items-center shadow-sm">
                    <span class="text-xs font-medium text-gray-700">Balance :</span>
                    <span id="balance-ghs-amount" class="ml-1.5 text-xs font-semibold text-gray-900">GHS 0.00</span>
                </div>
                
                <!-- SMS Balance -->
                <div id="balance-indicator-sms" class="px-3 py-1.5 bg-green-600 rounded-lg flex items-center shadow-sm">
                    <span class="text-xs font-medium text-white">SMS Balance :</span>
                    <span id="balance-sms-amount" class="ml-1.5 text-xs font-semibold text-white">0</span>
                </div>
            </div>
            
            <!-- Mobile: Stacked balance indicators (smaller) -->
            <div class="lg:hidden flex flex-col space-y-1">
                <div id="balance-indicator-ghs-mobile" class="px-2 py-1 bg-white border border-gray-200 rounded flex items-center">
                    <span class="text-xs font-medium text-gray-700">Balance:</span>
                    <span id="balance-ghs-amount-mobile" class="ml-1 text-xs font-semibold text-gray-900">₵0.00</span>
                </div>
                <div id="balance-indicator-sms-mobile" class="px-2 py-1 bg-green-600 rounded flex items-center">
                    <span class="text-xs font-medium text-white">SMS:</span>
                    <span id="balance-sms-amount-mobile" class="ml-1 text-xs font-semibold text-white">0</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications -->
            <div class="relative">
                <button id="notificationBell" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                </button>
                
                <!-- Notification Dropdown -->
                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-[calc(100vw-2rem)] sm:w-80 max-w-sm bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 sm:p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Notifications</h3>
                            <button id="markAllRead" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap">Mark all read</button>
                        </div>
                    </div>
                    <div id="notificationList" class="max-h-[60vh] sm:max-h-96 overflow-y-auto">
                        <!-- Notifications will be loaded here -->
                    </div>
                    <div id="noNotifications" class="p-4 text-center text-gray-500 hidden">
                        <i class="fas fa-bell-slash text-xl sm:text-2xl mb-2"></i>
                        <p class="text-sm">No notifications</p>
                    </div>
                </div>
            </div>
            
            <!-- Profile Dropdown -->
            <?php include __DIR__ . '/profile-dropdown.php'; ?>
        </div>
    </div>
</nav>

<script>
// Update page title and breadcrumb based on current page
function updatePageTitle() {
    const path = window.location.pathname;
    const titleElement = document.getElementById('page-title');
    const breadcrumbElement = document.getElementById('breadcrumb-current');
    let pageTitle = 'Dashboard';
    let breadcrumbText = 'Dashboard';
    
    if (path.includes('/pos')) {
        pageTitle = 'Point of Sale';
        breadcrumbText = 'Point of Sale';
    } else if (path.includes('/customers')) {
        pageTitle = 'Customers';
        breadcrumbText = 'Customers';
    } else if (path.includes('/products')) {
        pageTitle = 'Products';
        breadcrumbText = 'Products';
    } else if (path.includes('/swaps')) {
        pageTitle = 'Swaps';
        breadcrumbText = 'Swaps';
    } else if (path.includes('/repairs')) {
        pageTitle = 'Repairs';
        breadcrumbText = 'Repairs';
    } else if (path.includes('/inventory')) {
        pageTitle = 'Inventory';
        breadcrumbText = 'Inventory';
    } else if (path.includes('/staff')) {
        pageTitle = 'Staff';
        breadcrumbText = 'Staff';
    } else if (path.includes('/companies')) {
        pageTitle = 'Companies';
        breadcrumbText = 'Companies';
    } else if (path.includes('/analytics')) {
        pageTitle = 'Analytics';
        breadcrumbText = 'Analytics';
    } else if (path.includes('/settings')) {
        pageTitle = 'Settings';
        breadcrumbText = 'Settings';
    } else if (path.includes('/sales-history')) {
        pageTitle = 'Sales History';
        breadcrumbText = 'Sales History';
    } else if (path.includes('/booking')) {
        pageTitle = 'New Booking';
        breadcrumbText = 'New Booking';
    }
    
    if (titleElement) {
        titleElement.textContent = pageTitle;
    }
    if (breadcrumbElement) {
        breadcrumbElement.textContent = breadcrumbText;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePageTitle();
    
    // Load balance indicators for managers/admins
    <?php if (in_array($userRole, ['manager', 'admin'])): ?>
    loadBalanceIndicators();
    <?php endif; ?>
    
});

// Load balance indicators (GHS and SMS)
async function loadBalanceIndicators() {
    try {
        const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        if (!token) return;
        
        // Load manager overview to get balance data
        const response = await fetch(BASE + '/api/dashboard/manager-overview', {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            // Update SMS Balance
            if (data.data.sms) {
                const smsRemaining = data.data.sms.sms_remaining || 0;
                updateSMSBalance(smsRemaining);
                
                // Calculate GHS Balance based on remaining SMS credits
                // Balance = Remaining SMS * Rate per SMS
                const smsCreditRate = data.data.sms.sms_credit_rate || 0.05891; // Default ₵0.05891 per SMS (38/645)
                const ghsBalance = smsRemaining * smsCreditRate;
                
                updateGHSBalance(ghsBalance);
            } else {
                // Fallback if SMS data not available
                updateGHSBalance(0);
                updateSMSBalance(0);
            }
        }
    } catch (error) {
        console.error('Error loading balance indicators:', error);
        // Set defaults on error
        updateGHSBalance(0);
        updateSMSBalance(0);
    }
}

function updateGHSBalance(amount) {
    const formatted = 'GHS ' + parseFloat(amount).toFixed(2);
    
    // Desktop
    const desktopEl = document.getElementById('balance-ghs-amount');
    if (desktopEl) desktopEl.textContent = formatted;
    
    // Mobile
    const mobileEl = document.getElementById('balance-ghs-amount-mobile');
    if (mobileEl) mobileEl.textContent = '₵' + parseFloat(amount).toFixed(2);
}

function updateSMSBalance(count) {
    const formatted = count.toLocaleString();
    
    // Desktop
    const desktopEl = document.getElementById('balance-sms-amount');
    if (desktopEl) desktopEl.textContent = formatted;
    
    // Mobile
    const mobileEl = document.getElementById('balance-sms-amount-mobile');
    if (mobileEl) mobileEl.textContent = formatted;
}

// Refresh balance indicators every 2 minutes
<?php if (in_array($userRole, ['manager', 'admin'])): ?>
setInterval(loadBalanceIndicators, 120000);
<?php endif; ?>
</script>
