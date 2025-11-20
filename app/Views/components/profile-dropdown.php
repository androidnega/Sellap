<?php
// Profile Dropdown Component
// Usage: include with $userInfo, $companyInfo, $userRole
?>
<!-- Profile Dropdown -->
<div class="relative ml-auto" x-data="{ open: false }">
    <!-- Profile Button -->
    <button @click="open = !open" class="flex items-center space-x-3 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <div class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                    <span class="text-sm font-medium text-white">
                        <?= strtoupper(substr($userInfo, 0, 1)) ?>
                    </span>
                </div>
            </div>
            <div class="hidden md:block text-left">
                <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($userInfo) ?></p>
                <?php if (isset($companyInfo) && $companyInfo): ?>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($companyInfo) ?></p>
                <?php endif; ?>
            </div>
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </button>

    <!-- Dropdown Menu -->
    <div x-show="open" 
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 ring-1 ring-black ring-opacity-5 focus:outline-none"
         style="display: none;">
        
        <!-- User Info Header -->
        <div class="px-4 py-2 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($userInfo) ?></p>
            <p class="text-xs text-gray-500 capitalize"><?= str_replace('_', ' ', $userRole) ?></p>
            <?php if (isset($companyInfo) && $companyInfo): ?>
            <p class="text-xs text-gray-500"><?= htmlspecialchars($companyInfo) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Menu Items -->
        <a href="<?= BASE_URL_PATH ?>/dashboard/profile" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-user mr-3 text-gray-400"></i>
            Your Profile
        </a>
        
        <?php if ($userRole === 'system_admin'): ?>
        <a href="<?= BASE_URL_PATH ?>/dashboard/system-settings" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-shield-alt mr-3 text-gray-400"></i>
            System Settings
        </a>
        <?php endif; ?>
        
        <div class="border-t border-gray-100"></div>
        
        <button onclick="logout()" class="flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50">
            <i class="fas fa-sign-out-alt mr-3 text-red-400"></i>
            Sign out
        </button>
    </div>
</div>

<script>
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            // Call logout API endpoint to destroy server session
            const response = await fetch(BASE + '/api/auth/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });
            
            // Clear local storage
            localStorage.removeItem('token');
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('sellapp_user');
            
            // Clear session storage
            sessionStorage.clear();
            
            // Redirect to login page
            window.location.href = BASE + '/';
        } catch (error) {
            console.error('Logout error:', error);
            // Even if API call fails, clear local storage and redirect
            localStorage.removeItem('token');
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('sellapp_user');
            sessionStorage.clear();
            window.location.href = BASE + '/';
        }
    }
}
</script>
