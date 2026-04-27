<?php
// Dynamic Sidebar Component based on User Role
// Usage: include with $userRole, $currentPage, $userInfo, $companyInfo

// Load CompanyModule model for module access checks
require_once __DIR__ . '/../../Models/CompanyModule.php';
require_once __DIR__ . '/../../Models/CompanyFeature.php';
use App\Models\CompanyModule;
use App\Models\CompanyFeature;

// Get company_id from session
$companyId = $_SESSION['user']['company_id'] ?? null;

// Get additional user information from database if not already available
$userFullName = $_SESSION['user']['full_name'] ?? null;
$userEmail = $_SESSION['user']['email'] ?? null;
$userCreatedAt = $_SESSION['user']['created_at'] ?? null;
$userUpdatedAt = $_SESSION['user']['updated_at'] ?? null;

// If we don't have full user data, fetch it from database
if (!$userFullName || !$userCreatedAt) {
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        $db = \Database::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'] ?? null;
        
        if ($userId) {
            $userQuery = $db->prepare("
                SELECT u.full_name, u.email, u.created_at, u.updated_at, c.name as company_name
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $userQuery->execute([$userId]);
            $userData = $userQuery->fetch(\PDO::FETCH_ASSOC);
            
            if ($userData) {
                $userFullName = $userData['full_name'] ?? $userInfo;
                $userEmail = $userData['email'] ?? null;
                $userCreatedAt = $userData['created_at'] ?? null;
                $userUpdatedAt = $userData['updated_at'] ?? null;
                if (!$companyInfo && $userData['company_name']) {
                    $companyInfo = $userData['company_name'];
                }
            }
        }
    } catch (\Exception $e) {
        // Silently fail - use session data only
        error_log("Sidebar: Error fetching user data: " . $e->getMessage());
    }
}

// Module name to module key mapping (from SYSTEM_MODULE_AUDIT.json)
$moduleKeyMap = [
    'Products & Inventory' => 'products_inventory',
    'POS / Sales' => 'pos_sales',
    'Swap' => 'swap',
    'Repairs' => 'repairs',
    'Customers' => 'customers',
    'Staff Management' => 'staff_management',
    'Reports & Analytics' => 'reports_analytics',
    'Notifications & SMS' => 'notifications_sms',
    'Suppliers' => 'suppliers',
    'Purchase Orders' => 'purchase_orders',
    'Inventory travel sessions' => 'inventory_travel_sessions'
];

// Helper function to check if module is enabled
function isModuleEnabled($moduleKey, $companyId, $userRole) {
    // System admins bypass module checks (they have access to all modules)
    if ($userRole === 'system_admin' || !$companyId) {
        return true;
    }
    
    return CompanyModule::isEnabled($companyId, $moduleKey);
}

// Define role-specific configurations
$roleConfig = [
    'system_admin' => [
        'title' => 'SellApp Platform',
        'subtitle' => 'System Administration',
        'icon' => 'fas fa-shield-alt',
        'userIcon' => 'fas fa-user-shield',
        'color' => '#1f2937'
    ],
    'manager' => [
        'title' => 'SellApp Manager',
        'subtitle' => 'Company Management',
        'icon' => 'fas fa-chart-line',
        'userIcon' => 'fas fa-user-tie',
        'color' => '#059669'
    ],
    'admin' => [
        'title' => 'SellApp Manager',
        'subtitle' => 'Company Management',
        'icon' => 'fas fa-chart-line',
        'userIcon' => 'fas fa-user-tie',
        'color' => '#059669'
    ],
    'salesperson' => [
        'title' => 'SellApp Sales',
        'subtitle' => 'Sales Dashboard',
        'icon' => 'fas fa-cash-register',
        'userIcon' => 'fas fa-user',
        'color' => '#3b82f6'
    ],
    'technician' => [
        'title' => 'SellApp Tech',
        'subtitle' => 'Technical Services',
        'icon' => 'fas fa-tools',
        'userIcon' => 'fas fa-user-cog',
        'color' => '#7c3aed'
    ]
];

// Get configuration for current role
$config = $roleConfig[$userRole] ?? $roleConfig['salesperson'];

// Helper function to generate sidebar link
function sidebarLink($href, $icon, $text, $currentPage, $pageName, $extraClasses = '') {
    $isActive = $currentPage === $pageName;
    $activeClasses = $isActive ? 'sidebar-item-active' : '';
    $iconClasses = $isActive ? 'sidebar-icon-active' : 'sidebar-text';
    $textClasses = $isActive ? 'sidebar-label-active' : 'sidebar-text';
    
    return sprintf(
        '<a href="%s" class="sidebar-item flex items-center px-3 py-2 rounded-md text-sm %s %s" onclick="expandSidebarIfCollapsed(event)">
            <i class="%s mr-3 text-xs %s flex-shrink-0" style="width: 1rem; min-width: 1rem;"></i>
            <span class="%s">%s</span>
        </a>',
        $href,
        $activeClasses,
        $extraClasses,
        $icon,
        $iconClasses,
        $textClasses,
        $text
    );
}

function sidebarSection($label) {
    return sprintf(
        '<div class="sidebar-section-label">%s</div>',
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
    );
}

function sidebarCollapsibleGroup($label, $icon, $linksHtml, $isOpen = false) {
    $openAttr = $isOpen ? ' open' : '';
    return sprintf(
        '<details class="sidebar-collapsible"%s>
            <summary class="sidebar-collapsible-summary">
                <span class="sidebar-collapsible-label"><i class="%s mr-2 text-xs"></i>%s</span>
                <span class="sidebar-collapsible-plus" aria-hidden="true"><i class="fas fa-chevron-right text-xs"></i></span>
            </summary>
            <div class="sidebar-collapsible-content">%s</div>
        </details>',
        $openAttr,
        htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $linksHtml
    );
}
?>
<!-- Sidebar Toggle Button for Mobile Only -->
<button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-[1001] bg-gray-800 text-white p-2.5 rounded-lg shadow-lg hover:bg-gray-700 transition-colors" onclick="toggleSidebar()" aria-label="Toggle sidebar">
    <i id="sidebarToggleIcon" class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar Overlay (for mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<?php $isNeutralSidebar = in_array($userRole, ['manager', 'admin'], true); ?>
<div id="sidebar" class="sidebar loading h-screen min-h-0 p-4 relative flex flex-col overflow-hidden <?= $isNeutralSidebar ? 'sidebar-neutral' : '' ?>" style="background: <?= $isNeutralSidebar ? '#f8f8f8' : $config['color'] ?>;">
    <div class="flex items-center mb-6 flex-shrink-0 sidebar-brand-wrap">
        <i class="<?= $config['icon'] ?> text-lg mr-3 sidebar-text flex-shrink-0" style="width: 1.25rem; min-width: 1.25rem;"></i>
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-semibold sidebar-text truncate"><?= $config['title'] ?></h1>
            <p class="text-xs sidebar-text opacity-75 truncate"><?= $companyInfo ? htmlspecialchars($companyInfo) : $config['subtitle'] ?></p>
        </div>
    </div>
    
    <!-- Inner scroll: scrollbar hidden via CSS (outer #sidebar stays overflow:hidden so no gutter shows) -->
    <div class="sidebar-nav-scroll flex-1 min-h-0 overflow-y-auto overscroll-y-contain">
    <nav class="space-y-1">
        <?php if ($userRole === 'system_admin'): ?>
            <!-- System Admin Navigation -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard', 'fas fa-tachometer-alt', 'Platform Overview', $currentPage, 'dashboard') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/companies', 'fas fa-building', 'Companies', $currentPage, 'companies') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/companies/sms-config', 'fas fa-sms', 'SMS & Company Config', $currentPage, 'sms-config') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/companies/modules', 'fas fa-puzzle-piece', 'Company Modules', $currentPage, 'company-modules') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/analytics', 'fas fa-chart-line', 'Analytics', $currentPage, 'analytics') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/backup', 'fas fa-database', 'Backups', $currentPage, 'backup') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/email-logs', 'fas fa-envelope', 'Email Logs', $currentPage, 'email-logs') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/admin/inventory-logs', 'fas fa-clipboard-list', 'Inventory & order logs', $currentPage, 'admin-inventory-logs') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/tools', 'fas fa-tools', 'Tools', $currentPage, 'tools') ?>
            <div class="border-t border-white border-opacity-20 my-2"></div>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/reset/history', 'fas fa-history', 'Reset History', $currentPage, 'reset-history') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/reset', 'fas fa-skull', 'System Reset', $currentPage, 'system-reset') ?>
        <?php elseif ($userRole === 'manager' || $userRole === 'admin'): ?>
            <!-- Manager/Admin Navigation -->
            <?= sidebarSection('Main') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard', 'fas fa-tachometer-alt', 'Dashboard', $currentPage, 'dashboard') ?>
            
            <?php if (isModuleEnabled('products_inventory', $companyId, $userRole)): ?>
                <?= sidebarSection('Products & Inventory') ?>
                <?php
                    $inventoryGroupOpen = in_array($currentPage, ['inventory', 'restock', 'categories', 'subcategories', 'brands', 'travel-sessions'], true);
                    $travelLink = isModuleEnabled('inventory_travel_sessions', $companyId, $userRole)
                        ? sidebarLink(BASE_URL_PATH . '/dashboard/inventory/travel-sessions', 'fas fa-route', 'Travel sessions', $currentPage, 'travel-sessions', 'sidebar-subitem')
                        : '';
                    echo sidebarCollapsibleGroup(
                        'Inventory',
                        'fas fa-boxes',
                        sidebarLink(BASE_URL_PATH . '/dashboard/inventory', 'fas fa-boxes', 'Inventory', $currentPage, 'inventory', 'sidebar-subitem') .
                        $travelLink .
                        sidebarLink(BASE_URL_PATH . '/dashboard/restock', 'fas fa-truck-loading', 'Restock', $currentPage, 'restock', 'sidebar-subitem') .
                        sidebarLink(BASE_URL_PATH . '/dashboard/categories', 'fas fa-tags', 'Categories', $currentPage, 'categories', 'sidebar-subitem') .
                        sidebarLink(BASE_URL_PATH . '/dashboard/subcategories', 'fas fa-layer-group', 'Subcategories', $currentPage, 'subcategories', 'sidebar-subitem') .
                        sidebarLink(BASE_URL_PATH . '/dashboard/brands', 'fas fa-star', 'Brands', $currentPage, 'brands', 'sidebar-subitem'),
                        $inventoryGroupOpen
                    );
                ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('suppliers', $companyId, $userRole) || isModuleEnabled('purchase_orders', $companyId, $userRole)): ?>
                <?= sidebarSection('Procurement') ?>
                <?php
                    $procurementLinks = '';
                    if (isModuleEnabled('suppliers', $companyId, $userRole)) {
                        $procurementLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/suppliers', 'fas fa-truck', 'Suppliers', $currentPage, 'suppliers', 'sidebar-subitem');
                    }
                    if (isModuleEnabled('purchase_orders', $companyId, $userRole)) {
                        $procurementLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/purchase-orders', 'fas fa-shopping-cart', 'Purchase Orders', $currentPage, 'purchase_orders', 'sidebar-subitem');
                    }
                    $procurementOpen = in_array($currentPage, ['suppliers', 'purchase_orders'], true);
                    echo sidebarCollapsibleGroup('Procurement', 'fas fa-dolly', $procurementLinks, $procurementOpen);
                ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('staff_management', $companyId, $userRole)): ?>
                <?= sidebarSection('Users') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/staff', 'fas fa-users', 'Staff', $currentPage, 'staff') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('pos_sales', $companyId, $userRole)): ?>
                <?= sidebarSection('POS & Orders') ?>
                <?php
                // Check if manager can sell - if not, show Audit Trail instead of POS
                $canSell = CompanyModule::isEnabled($companyId, 'manager_can_sell');
                $posLinks = '';
                if ($canSell || $userRole === 'system_admin') {
                    $posLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/pos', 'fas fa-cash-register', 'POS', $currentPage, 'pos', 'sidebar-subitem');
                }
                $posLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/pos/sales-history', 'fas fa-history', 'Sales History', $currentPage, 'sales-history', 'sidebar-subitem');
                if ($companyId && CompanyFeature::tableExists() && (new CompanyFeature())->isEnabled((int)$companyId, 'returns_enabled')) {
                    if ($userRole === 'manager' || in_array($userRole, ['salesperson', 'sales'], true)) {
                        $posLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/returns', 'fas fa-undo-alt', 'Returns', $currentPage, 'returns', 'sidebar-subitem');
                    }
                }
                if (CompanyModule::isEnabled($companyId, 'partial_payments')) {
                    $posLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/pos/partial-payments', 'fas fa-money-bill-wave', 'Partial Payments', $currentPage, 'partial-payments', 'sidebar-subitem');
                }
                // Allow all roles to access notifications
                $allowedRoles = ['system_admin', 'admin', 'manager', 'salesperson', 'repairer'];
                if (in_array($userRole, $allowedRoles, true)) {
                    $posLinks .= sidebarLink(BASE_URL_PATH . '/dashboard/notifications', 'fas fa-bell', 'Notifications', $currentPage, 'notifications', 'sidebar-subitem');
                }
                $posOpen = in_array($currentPage, ['pos', 'sales-history', 'partial-payments', 'notifications', 'returns'], true);
                echo sidebarCollapsibleGroup('POS & Orders', 'fas fa-cash-register', $posLinks, $posOpen);
                ?>
            <?php endif; ?>
            
            <?= sidebarSection('Operations') ?>
            <?php if (isModuleEnabled('repairs', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/repairs', 'fas fa-tools', 'Repairs', $currentPage, 'repairs') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('swap', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/swaps', 'fas fa-exchange-alt', 'Swaps', $currentPage, 'swaps') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('customers', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/customers', 'fas fa-user-friends', 'Customers', $currentPage, 'customers') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('reports_analytics', $companyId, $userRole)): ?>
                <?= sidebarSection('Reports') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/audit-trail', 'fas fa-chart-bar', 'Audit Trail', $currentPage, 'audit-trail') ?>
            <?php endif; ?>
            
            <?= sidebarSection('Settings') ?>
            <!-- SMS Settings - Available for managers to view balance and purchase credits -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/sms-settings', 'fas fa-sms', 'SMS Settings', $currentPage, 'sms-settings') ?>
            <?php if (isModuleEnabled('customers', $companyId, $userRole) && in_array($userRole, ['manager', 'admin'], true)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/sms-broadcast', 'fas fa-gifts', 'Holiday & SMS broadcast', $currentPage, 'sms-broadcast') ?>
            <?php endif; ?>
            
            <!-- Company Settings - Available for managers to configure SMS notification preferences -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/company-settings', 'fas fa-cog', 'Company Settings', $currentPage, 'company-settings') ?>

            <?php if ($userRole === 'admin'): ?>
                <?= sidebarSection('Administration') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/admin/inventory-logs', 'fas fa-clipboard-list', 'Inventory & order logs', $currentPage, 'admin-inventory-logs') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/admin/company-features', 'fas fa-toggle-on', 'Company features', $currentPage, 'admin-inventory-logs') ?>
            <?php endif; ?>
        <?php elseif ($userRole === 'technician'): ?>
            <!-- Technician Navigation -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard', 'fas fa-tachometer-alt', 'Dashboard', $currentPage, 'dashboard') ?>
            
            <?php if (isModuleEnabled('repairs', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/booking', 'fas fa-plus-circle', 'New Booking', $currentPage, 'booking') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/repairs', 'fas fa-tools', 'My Repairs', $currentPage, 'repairs') ?>
            <?php endif; ?>
            
            <!-- Sales History - Always visible for technicians (they sell spare parts during repairs) -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/sales-history', 'fas fa-history', 'Sales History', $currentPage, 'sales-history') ?>
            
            <?php if (isModuleEnabled('customers', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/customers', 'fas fa-user-friends', 'Customers', $currentPage, 'customers') ?>
            <?php endif; ?>
        <?php else: ?>
            <!-- Salesperson Navigation -->
            <?= sidebarLink(BASE_URL_PATH . '/dashboard', 'fas fa-tachometer-alt', 'Dashboard', $currentPage, 'dashboard') ?>
            
            <?php if (isModuleEnabled('pos_sales', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/pos', 'fas fa-cash-register', 'Point of Sale', $currentPage, 'pos') ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/pos/sales-history', 'fas fa-history', 'Sales History', $currentPage, 'sales-history') ?>
                <?php if ($companyId && CompanyFeature::tableExists() && (new CompanyFeature())->isEnabled((int)$companyId, 'returns_enabled')): ?>
                    <?= sidebarLink(BASE_URL_PATH . '/dashboard/returns', 'fas fa-undo-alt', 'Returns', $currentPage, 'returns') ?>
                <?php endif; ?>
                <?php if (CompanyModule::isEnabled($companyId, 'partial_payments')): ?>
                    <?= sidebarLink(BASE_URL_PATH . '/dashboard/pos/partial-payments', 'fas fa-money-bill-wave', 'Partial Payments', $currentPage, 'partial-payments') ?>
                <?php endif; ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/reports', 'fas fa-chart-line', 'Reports', $currentPage, 'reports') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('customers', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/customers', 'fas fa-user-friends', 'Customers', $currentPage, 'customers') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('products_inventory', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/products', 'fas fa-boxes', 'Products', $currentPage, 'products') ?>
            <?php endif; ?>
            
            <?php if (isModuleEnabled('swap', $companyId, $userRole)): ?>
                <?= sidebarLink(BASE_URL_PATH . '/dashboard/swaps', 'fas fa-exchange-alt', 'Swaps', $currentPage, 'swaps') ?>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Profile and Settings Links -->
        <div class="border-t border-white border-opacity-20 my-4"></div>
        
        <?= sidebarLink(BASE_URL_PATH . '/dashboard/profile', 'fas fa-user', 'Profile', $currentPage, 'profile') ?>
        
        <?php if ($userRole === 'system_admin'): ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/user-logs', 'fas fa-user-clock', 'User Activity Logs', $currentPage, 'user-logs') ?>
            <?= sidebarLink(BASE_URL_PATH . '/dashboard/system-settings', 'fas fa-cog', 'System Settings', $currentPage, 'settings') ?>
        <?php endif; ?>
        <!-- Managers and Admins should NOT have access to settings - removed for security -->
        
        <a href="#" onclick="expandSidebarIfCollapsed(event); logout(); return false;" class="sidebar-item flex items-center px-3 py-2 rounded-md text-sm">
            <i class="fas fa-sign-out-alt mr-3 text-xs sidebar-text flex-shrink-0" style="width: 1rem; min-width: 1rem;"></i>
            <span class="sidebar-text">Logout</span>
        </a>
    </nav>
    </div>
    
    <!-- Close button for mobile -->
    <button class="md:hidden absolute top-4 right-4 text-white hover:text-gray-200 transition-colors z-10" onclick="closeSidebar()" aria-label="Close sidebar">
        <i class="fas fa-times text-xl"></i>
    </button>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<style>
    /* Scroll lives on .sidebar-nav-scroll only; scrollbar visually hidden (Chrome/Firefox/Safari) */
    #sidebar .sidebar-nav-scroll,
    .sidebar-nav-scroll {
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important;
        scrollbar-color: transparent transparent;
    }
    #sidebar .sidebar-nav-scroll::-webkit-scrollbar,
    .sidebar-nav-scroll::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        max-width: 0 !important;
        height: 0 !important;
        background: transparent !important;
    }
    #sidebar .sidebar-nav-scroll::-webkit-scrollbar-thumb,
    .sidebar-nav-scroll::-webkit-scrollbar-thumb {
        background: transparent !important;
    }
    .sidebar-text {
        color: rgba(255, 255, 255, 0.8);
        transition: color 0.2s ease;
    }
    
    .sidebar-item {
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    
    .sidebar-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .sidebar-item:hover .sidebar-text {
        color: white;
    }
    
    .sidebar-item.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .sidebar-section-label {
        font-size: 0.72rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.58);
        margin-top: 0.9rem;
        margin-bottom: 0.4rem;
        padding-left: 0.25rem;
    }
    .sidebar-item-active {
        background: #fef2f2;
        color: #dc2626;
        font-weight: 600;
    }
    .sidebar-label-active,
    .sidebar-icon-active {
        color: #dc2626;
    }

    .sidebar-brand-wrap {
        border-bottom: 1px solid rgba(156, 163, 175, 0.25);
        padding-bottom: 0.75rem;
    }

    .sidebar.sidebar-neutral .sidebar-text {
        color: #4b5563;
    }
    .sidebar.sidebar-neutral .sidebar-section-label {
        color: #9ca3af;
    }
    .sidebar.sidebar-neutral .sidebar-item {
        color: #374151;
        border-radius: 0.65rem;
        padding-top: 0.56rem;
        padding-bottom: 0.56rem;
    }
    .sidebar.sidebar-neutral .sidebar-item:hover {
        background: #eceef1;
        color: #374151;
    }
    .sidebar.sidebar-neutral .sidebar-item:hover .sidebar-text {
        color: #374151;
    }
    .sidebar.sidebar-neutral .sidebar-item-active {
        background: #fee2e2;
        color: #dc2626;
    }
    .sidebar.sidebar-neutral {
        border-right: 1px solid #e5e7eb;
    }
    .sidebar-collapsible {
        margin-top: 0.35rem;
        margin-bottom: 0.35rem;
    }
    .sidebar-collapsible-summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.72rem 0.78rem;
        border-radius: 0.75rem;
        color: #4b5563;
        font-size: 1.02rem;
        font-weight: 500;
        background: #eceef1;
    }
    .sidebar-collapsible-summary::-webkit-details-marker {
        display: none;
    }
    .sidebar-collapsible[open] .sidebar-collapsible-plus {
        transform: rotate(90deg);
    }
    .sidebar-collapsible-plus {
        transition: transform 0.2s ease;
        color: #6b7280;
    }
    .sidebar-collapsible-content {
        margin-top: 0.4rem;
        padding-left: 0.4rem;
    }
    .sidebar-subitem {
        position: relative;
        margin-left: 0.25rem;
        padding-left: 1.35rem !important;
        background: transparent !important;
        color: #4b5563;
    }
    .sidebar-subitem i {
        display: none;
    }
    .sidebar-subitem::before {
        content: "";
        position: absolute;
        left: 0.58rem;
        top: 50%;
        transform: translateY(-50%);
        width: 0.35rem;
        height: 0.35rem;
        border-radius: 9999px;
        background: #9ca3af;
    }
    .sidebar-subitem.sidebar-item-active::before {
        background: #dc2626;
    }
    
    /* Prevent transitions on initial page load */
    .sidebar.loading {
        transition: none !important;
    }
    
    .sidebar.loading * {
        transition: none !important;
    }
    
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1000;
        background: #667eea;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        backdrop-filter: blur(2px);
        transition: opacity 0.3s ease-in-out;
    }
    
    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            height: 100vh;
            z-index: 1000;
            width: 16rem;
            max-width: 80vw;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar:not(.loading) {
            transition: left 0.3s ease-in-out;
        }
        
        .sidebar.open {
            left: 0;
        }
        
        .sidebar-toggle {
            display: block;
        }
        
        .main-content {
            margin-left: 0;
        }
        
        /* Prevent body scroll when sidebar is open */
        body.sidebar-open {
            overflow: hidden;
        }
    }
</style>

<script>
    // Remove loading class after page load to enable transitions
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            // Small delay to ensure page is fully rendered
            setTimeout(function() {
                sidebar.classList.remove('loading');
            }, 50);
        }
    });
    
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const body = document.body;
        
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            if (sidebar.classList.contains('open')) {
                body.classList.add('sidebar-open');
            } else {
                body.classList.remove('sidebar-open');
            }
        }
    }
    
    function closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const body = document.body;
        
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('[onclick="toggleSidebar()"]');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (window.innerWidth <= 768 && 
            sidebar && 
            overlay &&
            !sidebar.contains(event.target) && 
            toggle && 
            !toggle.contains(event.target) &&
            sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Handle window resize - close sidebar if window becomes larger
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
    
    // Close sidebar when pressing Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
    
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