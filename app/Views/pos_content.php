<?php
/**
 * POS Content View - Standard Design
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = $_SESSION['user']['role'] ?? '';
// Only managers, admins, and system_admins have read-only access
// Salespersons can complete sales
$nonSellingRoles = ['manager','admin','system_admin'];
$isReadOnly = in_array($role, $nonSellingRoles, true) ? 'true' : 'false';

// Debug: Log role for troubleshooting
error_log("POS View: User role = {$role}, isReadOnly = {$isReadOnly}");
?>

<div class="p-2 sm:p-4 pb-4 overflow-x-hidden max-w-full" data-server-rendered="true">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800">Point of Sale</h2>
        <p class="text-sm sm:text-base text-gray-600">Process sales and manage transactions</p>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- POS Stats Cards -->
    <div id="posStatsCards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-3 sm:p-4 md:p-5 border border-blue-200 shadow-sm min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-sm font-medium text-blue-700 mb-1 truncate">Total Items</p>
                    <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-blue-900 break-words overflow-hidden" id="posTotalItems">0</p>
                </div>
                <div class="p-2 sm:p-3 bg-blue-200 rounded-lg flex-shrink-0">
                    <i class="fas fa-boxes text-lg sm:text-xl md:text-2xl text-blue-700"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-3 sm:p-4 md:p-5 border border-green-200 shadow-sm min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-sm font-medium text-green-700 mb-1 truncate">Sales Today</p>
                    <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-green-900 break-words overflow-hidden" id="posSalesToday">0</p>
                </div>
                <div class="p-2 sm:p-3 bg-green-200 rounded-lg flex-shrink-0">
                    <i class="fas fa-shopping-cart text-lg sm:text-xl md:text-2xl text-green-700"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-3 sm:p-4 md:p-5 border border-purple-200 shadow-sm min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-sm font-medium text-purple-700 mb-1 truncate">Revenue Today</p>
                    <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-purple-900 break-words overflow-hidden" id="posRevenueToday">₵0.00</p>
                </div>
                <div class="p-2 sm:p-3 bg-purple-200 rounded-lg flex-shrink-0">
                    <i class="fas fa-money-bill-wave text-lg sm:text-xl md:text-2xl text-purple-700"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-3 sm:p-4 md:p-5 border border-orange-200 shadow-sm min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-sm font-medium text-orange-700 mb-1 truncate">Swaps Today</p>
                    <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-orange-900 break-words overflow-hidden" id="posSwapsToday">0</p>
                </div>
                <div class="p-2 sm:p-3 bg-orange-200 rounded-lg flex-shrink-0">
                    <i class="fas fa-exchange-alt text-lg sm:text-xl md:text-2xl text-orange-700"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-3 sm:p-4 md:p-5 border border-teal-200 shadow-sm min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-sm font-medium text-teal-700 mb-1 truncate">Swap Revenue</p>
                    <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-teal-900 break-words overflow-hidden" id="posSwapRevenueToday">₵0.00</p>
                </div>
                <div class="p-2 sm:p-3 bg-teal-200 rounded-lg flex-shrink-0">
                    <i class="fas fa-hand-holding-usd text-lg sm:text-xl md:text-2xl text-teal-700"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Manager POS Metrics (shown when POS_READ_ONLY) -->
    <div id="posManagerMetrics" class="pb-4<?php echo (isset($isReadOnly) && $isReadOnly === 'true') ? '' : ' hidden'; ?>">
        <div class="mb-4">
            <h3 class="text-2xl font-bold text-gray-800">POS Overview</h3>
            <p class="text-gray-600">Manager view: sales metrics and inventory stats</p>
        </div>

        <!-- Date range controls and export -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-end">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">From</label>
                    <input type="date" id="posDateFrom" class="border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">To</label>
                    <input type="date" id="posDateTo" class="border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button id="btnToday" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm">Today</button>
                    <button id="btnThisWeek" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm">This Week</button>
                    <button id="btnApplyRange" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-3 py-2 text-sm">View Stats</button>
                    <button id="btnExportPdf" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded px-3 py-2 text-sm">Export PDF</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4">
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-green-100 text-green-600 flex-shrink-0">
                        <i class="fas fa-sack-dollar text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Selected Revenue</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-today-revenue">₵0.00</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-blue-100 text-blue-600 flex-shrink-0">
                        <i class="fas fa-calendar-day text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Selected Sales</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-today-sales">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-purple-100 text-purple-600 flex-shrink-0">
                        <i class="fas fa-calendar-week text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Monthly Sales</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-month-sales">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-amber-100 text-amber-600 flex-shrink-0">
                        <i class="fas fa-coins text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Selected Total</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-month-revenue">₵0.00</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                        <i class="fas fa-boxes text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Total Products</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-total-products">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-green-100 text-green-600 flex-shrink-0">
                        <i class="fas fa-check-circle text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">In Stock</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-in-stock">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-yellow-100 text-yellow-600 flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Low Stock</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-low-stock">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 sm:p-6 min-w-0 overflow-hidden">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-2 sm:p-3 rounded-full bg-red-100 text-red-600 flex-shrink-0">
                        <i class="fas fa-times-circle text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Out of Stock</p>
                        <p class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 break-words overflow-hidden" id="pos-out-of-stock">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comprehensive Audit Dashboard -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <!-- Recent Sales -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 bg-blue-50">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-shopping-cart text-blue-600 mr-2"></i>
                        Recent Sales
                    </h4>
                </div>
                <div class="p-4">
                    <div id="recent-sales-list" class="space-y-3 max-h-64 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center py-4">Loading recent sales...</p>
                    </div>
                </div>
            </div>

            <!-- Recent Repairs -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-tools text-purple-600 mr-2"></i>
                        Recent Repairs
                    </h4>
                </div>
                <div class="p-4">
                    <div id="recent-repairs-list" class="space-y-3 max-h-64 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center py-4">Loading recent repairs...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <!-- Recent Swaps -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 bg-orange-50">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-exchange-alt text-orange-600 mr-2"></i>
                        Recent Swaps
                    </h4>
                </div>
                <div class="p-4">
                    <div id="recent-swaps-list" class="space-y-3 max-h-64 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center py-4">Loading recent swaps...</p>
                    </div>
                </div>
            </div>

            <!-- Top Selling Products -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 bg-green-50">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-chart-line text-green-600 mr-2"></i>
                        Top Products (Last 30 Days)
                    </h4>
                </div>
                <div class="p-4">
                    <div id="top-products-list" class="space-y-3 max-h-64 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center py-4">Loading top products...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Summary -->
        <div class="bg-white rounded-lg shadow p-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-clipboard-list text-gray-600 mr-2"></i>
                System Activity Summary
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 md:gap-4 mb-4">
                <div class="text-center p-3 sm:p-4 bg-blue-50 rounded-lg min-w-0 overflow-hidden">
                    <p class="text-lg sm:text-xl md:text-2xl font-bold text-blue-600 break-words overflow-hidden" id="audit-today-sales">0</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1 truncate">Sales Today</p>
                </div>
                <div class="text-center p-3 sm:p-4 bg-purple-50 rounded-lg min-w-0 overflow-hidden">
                    <p class="text-lg sm:text-xl md:text-2xl font-bold text-purple-600 break-words overflow-hidden" id="audit-today-repairs">0</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1 truncate">Repairs Today</p>
                </div>
                <div class="text-center p-3 sm:p-4 bg-orange-50 rounded-lg min-w-0 overflow-hidden">
                    <p class="text-lg sm:text-xl md:text-2xl font-bold text-orange-600 break-words overflow-hidden" id="audit-today-swaps">0</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1 truncate">Swaps Today</p>
                </div>
                <div class="text-center p-3 sm:p-4 bg-green-50 rounded-lg min-w-0 overflow-hidden">
                    <p class="text-lg sm:text-xl md:text-2xl font-bold text-green-600 break-words overflow-hidden" id="audit-month-revenue">₵0.00</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1 truncate">Month Revenue</p>
                </div>
            </div>
            
            <!-- Payment Status Card - Combined -->
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-4 border border-gray-200" id="audit-payment-status-card" style="display: none;">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-money-bill-wave text-gray-600 mr-2"></i>
                        Payment Status
                    </h4>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:gap-3 md:gap-4">
                    <div class="text-center min-w-0 overflow-hidden">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-check-circle text-green-600 text-xs sm:text-sm mr-1"></i>
                            <p class="text-xs text-gray-600 truncate">Fully Paid</p>
                        </div>
                        <p class="text-base sm:text-lg md:text-xl font-bold text-gray-900 break-words overflow-hidden" id="audit-fully-paid">0</p>
                    </div>
                    <div class="text-center min-w-0 overflow-hidden">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-exclamation-circle text-yellow-600 text-xs sm:text-sm mr-1"></i>
                            <p class="text-xs text-gray-600 truncate">Partial</p>
                        </div>
                        <p class="text-base sm:text-lg md:text-xl font-bold text-gray-900 break-words overflow-hidden" id="audit-partial">0</p>
                    </div>
                    <div class="text-center min-w-0 overflow-hidden">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-times-circle text-red-600 text-xs sm:text-sm mr-1"></i>
                            <p class="text-xs text-gray-600 truncate">Unpaid</p>
                        </div>
                        <p class="text-base sm:text-lg md:text-xl font-bold text-gray-900 break-words overflow-hidden" id="audit-unpaid">0</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex flex-col lg:flex-row gap-6 overflow-x-hidden">
        <!-- Product Selection (Left Side) -->
        <div class="w-full lg:w-2/3 min-w-0" id="productsSection">
            <div class="bg-white rounded shadow overflow-x-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Products</h3>
                </div>
                
                <!-- Search and Filter -->
                <div class="p-4 sm:p-6 border-b border-gray-200 overflow-x-hidden">
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 flex-wrap">
                        <div class="relative flex-grow min-w-0 w-full sm:w-auto sm:flex-1">
                            <input type="text" id="productSearch" placeholder="Search products by name or brand..."
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent min-w-0">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select id="categoryFilter" class="w-full sm:w-auto sm:min-w-[150px] border border-gray-300 rounded-lg py-3 px-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-shrink-0">
                            <option value="">All Categories</option>
                            <!-- Categories will be loaded here -->
                        </select>
                        <select id="brandFilter" class="w-full sm:w-auto sm:min-w-[150px] border border-gray-300 rounded-lg py-3 px-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-shrink-0">
                            <option value="">All Brands</option>
                            <!-- Brands will be loaded here -->
                        </select>
                        <select id="sortFilter" class="w-full sm:w-auto sm:min-w-[150px] border border-gray-300 rounded-lg py-3 px-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-shrink-0">
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="price_asc">Price Low-High</option>
                            <option value="price_desc">Price High-Low</option>
                            <option value="stock_asc">Stock Low-High</option>
                            <option value="stock_desc">Stock High-Low</option>
                        </select>
                    </div>
                </div>

                
                <!-- Product Grid -->
                <div class="p-6">
                    <div id="productCount" class="text-sm text-gray-600 mb-4 font-medium">
                        <i class="fas fa-boxes mr-2"></i>
                        <span id="productCountText">Loading products...</span>
                    </div>
                    <div id="productsGrid" class="space-y-4 max-h-[calc(100vh-20rem)] overflow-y-auto products-scroll" style="scrollbar-width: none; -ms-overflow-style: none;">
                        <!-- Products will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart and Checkout (Right Side) - Sticky -->
        <div class="w-full lg:w-1/3<?php echo (isset($isReadOnly) && $isReadOnly === 'true') ? ' hidden' : ''; ?>" id="cartSection">
            <div class="sticky top-2 sm:top-4 self-start max-h-[calc(100vh-2rem)] overflow-y-auto overflow-x-hidden" style="position: -webkit-sticky; position: sticky; scrollbar-width: none; -ms-overflow-style: none;">
            <div class="bg-white rounded-lg shadow-lg min-w-0 overflow-x-hidden" style="scrollbar-width: none; -ms-overflow-style: none;">
                <div class="p-3 sm:p-4 md:p-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-shopping-cart mr-1.5 sm:mr-2 text-blue-600 text-sm sm:text-base"></i>
                        <span>Cart (<span id="cartItemCount">0</span>)</span>
                    </h3>
                </div>

                <!-- Cart Items -->
                <div class="p-3 sm:p-4 md:p-6">
                    <div id="cartItems" class="space-y-2 sm:space-y-3 md:space-y-4 max-h-80 overflow-y-auto cart-scroll min-w-0" style="max-height: 320px; scrollbar-width: none; -ms-overflow-style: none; overflow-y: auto;">
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-shopping-cart text-3xl sm:text-4xl text-gray-300 mb-2"></i>
                            <p class="font-medium text-sm sm:text-base">Cart is empty</p>
                            <p class="text-xs sm:text-sm mt-1">Add items to get started</p>
                        </div>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div id="cartSummarySection" class="p-3 sm:p-4 md:p-6 border-t border-gray-200 hidden">
                    <div class="space-y-2 sm:space-y-3">
                        <div class="flex justify-between text-sm gap-2">
                            <span class="text-gray-600 flex-shrink-0">Subtotal:</span>
                            <span id="subtotal" class="font-medium break-words overflow-hidden text-right min-w-0">₵0.00</span>
                        </div>
                        
                        <!-- Discount -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Discount:</span>
                            <div class="flex items-center space-x-2">
                                <input type="number" id="discountAmount" step="0.01" min="0" 
                                       class="w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="0.00">
                                <select id="discountType" class="text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    <option value="amount">₵</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Tax -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Tax:</span>
                            <div class="flex items-center space-x-2">
                                <input type="number" id="taxAmount" step="0.01" min="0" 
                                       class="w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="0.00">
                                <select id="taxType" class="text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    <option value="amount">₵</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-between text-sm gap-2">
                            <span class="text-gray-600 flex-shrink-0">Discount:</span>
                            <span id="discount" class="font-medium text-red-600 break-words overflow-hidden text-right min-w-0">-₵0.00</span>
                        </div>
                        <div class="flex justify-between text-sm gap-2">
                            <span class="text-gray-600 flex-shrink-0">Tax:</span>
                            <span id="tax" class="font-medium break-words overflow-hidden text-right min-w-0">₵0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-base sm:text-lg font-bold border-t border-gray-200 pt-3 gap-2">
                            <span class="flex-shrink-0">TOTAL:</span>
                            <span id="total" class="text-green-600 font-bold break-words overflow-hidden text-right min-w-0">₵0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div id="paymentMethodSection" class="p-3 sm:p-4 md:p-6 border-t border-gray-200 hidden">
                    <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Payment Method</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <button class="payment-method-btn bg-green-100 text-green-700 border-2 border-green-300 py-2 px-3 rounded-lg text-sm font-medium flex items-center justify-center transition-colors" data-method="cash">
                            <i class="fas fa-money-bill-wave mr-1"></i>Cash
                        </button>
                        <button class="payment-method-btn bg-gray-100 text-gray-700 border-2 border-gray-300 py-2 px-3 rounded-lg text-sm font-medium flex items-center justify-center transition-colors" data-method="momo">
                            <i class="fas fa-mobile-alt mr-1"></i>MoMo
                        </button>
                    </div>
                    <input type="hidden" id="selectedPaymentMethod" name="payment_method" value="cash">
                    
                    <!-- Partial Payment Section (Only shown if module is enabled) -->
                    <div id="partialPaymentSection" class="mt-4" style="display: none;">
                        <label for="amountReceived" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>
                            Amount Received <span class="text-xs text-gray-500">(Leave empty for full payment)</span>
                        </label>
                        <div class="relative">
                            <input type="number" id="amountReceived" step="0.01" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                   placeholder="Enter amount received">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm">
                                <span id="remainingAmountDisplay" class="hidden text-orange-600 font-medium"></span>
                            </div>
                        </div>
                        <p id="partialPaymentHint" class="text-xs text-gray-500 mt-1 hidden">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="partialPaymentHintText"></span>
                        </p>
                    </div>
                </div>

                <!-- Customer Type -->
                <div id="customerSection" class="p-3 sm:p-4 md:p-6 border-t border-gray-200 hidden">
                    <h4 class="text-xs font-semibold text-gray-700 mb-2 sm:mb-3">Customer</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <button class="customer-type-btn bg-gray-100 text-gray-700 border-2 border-gray-300 py-1.5 px-2 rounded-lg text-xs font-medium flex items-center justify-center transition-colors" data-type="walkin">
                            <i class="fas fa-walking mr-1 text-xs"></i>Walk-in
                        </button>
                        <button class="customer-type-btn bg-blue-100 text-blue-700 border-2 border-blue-300 py-1.5 px-2 rounded-lg text-xs font-medium flex items-center justify-center transition-colors" data-type="existing">
                            <i class="fas fa-user mr-1 text-xs"></i>Existing
                        </button>
                        <button class="customer-type-btn bg-gray-100 text-gray-700 border-2 border-gray-300 py-1.5 px-2 rounded-lg text-xs font-medium flex items-center justify-center transition-colors" data-type="new" onclick="openNewCustomerModal(false)">
                            <i class="fas fa-user-plus mr-1 text-xs"></i>New
                        </button>
                    </div>
                    
                    <!-- Existing Customer Dropdown -->
                    <div id="existingCustomerDropdown" class="mt-3" style="display: block;">
                        <label for="existingCustomerSearch" class="block text-xs font-medium text-gray-600 mb-1">Search Customer</label>
                        <div class="relative">
                            <input type="text" id="existingCustomerSearch" 
                                   class="w-full px-3 py-2 pr-8 text-xs border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="Click to search customers..."
                                   autocomplete="off">
                            <i class="fas fa-chevron-down absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            <div id="existingCustomerDropdownList" class="absolute z-50 w-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg hidden">
                                <!-- Search results will appear here -->
                            </div>
                        </div>
                        <select id="existingCustomerSelect" class="w-full px-3 py-2 mt-2 text-xs border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500" style="display: none;">
                            <option value="">Choose a customer...</option>
                            <!-- Customers will be loaded here -->
                        </select>
                    </div>
                </div>

                <!-- Quick Contact Creation -->
                <div class="p-6 border-t border-gray-200" id="quickContactSection" style="display: none;">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-plus mr-2 text-green-600"></i>
                        Quick Add Contact
                    </h4>
                    <form id="quickContactForm" class="space-y-3">
                        <div>
                            <label for="contactName" class="block text-xs font-medium text-gray-600 mb-1">Full Name *</label>
                            <input type="text" id="contactName" name="full_name" required
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter customer name">
                        </div>
                        <div>
                            <label for="contactPhone" class="block text-xs font-medium text-gray-600 mb-1">Phone Number *</label>
                            <input type="tel" id="contactPhone" name="phone_number" required
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter phone number">
                        </div>
                        <div>
                            <label for="contactEmail" class="block text-xs font-medium text-gray-600 mb-1">Email (Optional)</label>
                            <input type="email" id="contactEmail" name="email"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter email address">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors">
                                <i class="fas fa-save mr-1"></i>Save Contact
                            </button>
                            <button type="button" id="cancelContactBtn" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notes -->
                <div id="notesSection" class="p-3 sm:p-4 md:p-6 border-t border-gray-200 hidden">
                    <h4 class="text-xs sm:text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Notes (optional)</h4>
                    <textarea id="saleNotes" placeholder="Add notes..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm resize-none" 
                              rows="2"></textarea>
                </div>

                <!-- Action Buttons -->
                <div id="actionButtonsSection" class="p-3 sm:p-4 md:p-6 border-t border-gray-200 space-y-2 sm:space-y-3 hidden">
                    <div class="flex gap-2 flex-nowrap">
                        <button id="clearCartBtn" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-2 sm:px-3 rounded-lg transition-colors flex items-center justify-center whitespace-nowrap text-xs sm:text-sm min-w-0">
                            <i class="fas fa-trash text-xs sm:text-sm"></i><span class="ml-1 sm:ml-1.5">Clear</span>
                        </button>
                        <button id="processSaleBtn" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-2 sm:px-3 rounded-lg transition-colors flex items-center justify-center whitespace-nowrap text-xs sm:text-sm min-w-0">
                            <i class="fas fa-check text-xs sm:text-sm"></i><span class="ml-1 sm:ml-1.5">Complete Sale</span>
                        </button>
                    </div>
                    <button id="printLastReceiptBtn" class="w-full bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-4 rounded-lg transition-colors text-sm sm:text-base whitespace-nowrap" disabled>
                        <i class="fas fa-receipt mr-2"></i>
                        Print Last Receipt
                    </button>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>


<!-- Notification Toast -->
<div id="notification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-xl hidden z-50 max-w-sm">
    <div class="flex items-center justify-between">
        <span id="notificationMessage">Product added to cart</span>
        <button id="closeNotification" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- POS JavaScript -->
<script>
// Format currency with K/M notation for large numbers - improved for hundreds of thousands
function formatCurrency(amount) {
    if (amount >= 1000000) {
        const millions = amount / 1000000;
        return millions >= 10 ? millions.toFixed(1) + 'M' : millions.toFixed(2) + 'M';
    } else if (amount >= 100000) {
        const hundredsK = amount / 1000;
        return hundredsK >= 100 ? hundredsK.toFixed(0) + 'K' : hundredsK.toFixed(1) + 'K';
    } else if (amount >= 1000) {
        return (amount / 1000).toFixed(1) + 'K';
    } else {
        return amount.toFixed(2);
    }
}

// Determine if current user is manager/admin/system_admin (view-only)
const POS_READ_ONLY = <?= $isReadOnly ?>;
document.addEventListener('DOMContentLoaded', async function() {
    console.log('POS page loaded');
    
    // Check if partial payments module is enabled (with delay to ensure DOM is ready)
    setTimeout(async () => {
        console.log('Checking partial payments module...');
        await checkPartialPaymentsModule();
    }, 500);
    
    // Add event listener for amount received input
    const amountReceivedInput = document.getElementById('amountReceived');
    if (amountReceivedInput) {
        amountReceivedInput.addEventListener('input', updatePartialPaymentDisplay);
        amountReceivedInput.addEventListener('blur', function() {
            const total = parseFloat(document.getElementById('total')?.textContent.replace('₵', '').replace(',', '') || 0);
            const value = parseFloat(this.value) || 0;
            if (value > total) {
                this.value = total.toFixed(2);
                updatePartialPaymentDisplay();
            }
        });
    }
    
    // Clear cart on page load/refresh
    clearCartOnLoad();
    
    // Check authentication (non-blocking - session cookies will handle auth)
    const token = getAuthToken();
    if (!token) {
        console.warn('No authentication token found in localStorage - will use session cookies for authentication');
    } else {
        console.log('POS page loaded with authentication token');
    }
    
    // Load quick stats
    loadPOSQuickStats();
    
    // Refresh stats every 30 seconds
    setInterval(loadPOSQuickStats, 30000);
    
    // Load products/customers OR show metrics for manager
    if (POS_READ_ONLY) {
        const prod = document.getElementById('productsSection');
        const cart = document.getElementById('cartSection');
        const metrics = document.getElementById('posManagerMetrics');
        if (prod) prod.classList.add('hidden');
        if (cart) cart.classList.add('hidden');
        if (metrics) metrics.classList.remove('hidden');
        loadPOSManagerMetrics();
        initPOSReportControls();
        loadInventoryStats();
        loadAuditData();
        // Refresh audit data every 30 seconds
        setInterval(loadAuditData, 30000);
    } else {
        loadProducts();
        loadCustomers();
        loadCart();
    }
    
    // Setup event listeners
    setupEventListeners();
    
    // Setup customer dropdowns with delay to ensure DOM is ready (skip for manager)
    if (!POS_READ_ONLY) {
        setTimeout(() => {
            console.log('Setting up customer dropdowns...');
            setupPOSCustomerDropdown();
            console.log('Customer dropdowns setup complete');
        }, 100);
    }
    
    // Initialize sticky cart behavior
    initializeStickyCart();

    // Manager view banner removed per request
});

// Initialize sticky cart functionality
function initializeStickyCart() {
    const cartContainer = document.querySelector('.lg\\:sticky');
    if (cartContainer) {
        console.log('Sticky cart container found:', cartContainer);
        
        // Add scroll event listener to handle cart scrolling
        cartContainer.addEventListener('scroll', function(e) {
            e.stopPropagation();
        });
        
        // Prevent cart from scrolling when mouse is not over it
        cartContainer.addEventListener('mouseenter', function() {
            this.style.overflowY = 'auto';
        });
        
        cartContainer.addEventListener('mouseleave', function() {
            // Keep scrollable but prevent interference with page scroll
            this.style.overflowY = 'auto';
        });
    } else {
        console.warn('Sticky cart container not found');
    }
}

// Global variables
let products = [];
let customers = [];
let cart = [];
let lastSaleId = null;

// Get auth token from localStorage or cookies
function getAuthToken() {
    // Try localStorage first
    let token = localStorage.getItem('sellapp_token') || localStorage.getItem('token');
    
    // If not in localStorage, try to get from cookies (non-HttpOnly cookies only)
    if (!token) {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'sellapp_token' || name === 'token') {
                token = decodeURIComponent(value);
                break;
            }
        }
    }
    
    return token || null;
}

async function clearCartAfterSale() {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(basePath + '/pos/cart/clear', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await response.json();
        if (data.success) {
            console.log('Cart cleared after successful sale');
        }
    } catch (error) {
        console.log('Cart clear after sale failed:', error);
    }
}

// Get auth headers (works with or without token - session cookies will handle auth)
function getAuthHeaders() {
    const token = getAuthToken();
    const headers = {
        'Content-Type': 'application/json'
    };
    
    // Only add Authorization header if token exists
    // If no token, session cookies will be used via credentials: 'same-origin'
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    return headers;
}

// Show notification toast
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const messageEl = document.getElementById('notificationMessage');
    
    messageEl.textContent = message;
    notification.className = `fixed bottom-5 right-5 px-6 py-3 rounded shadow-xl z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
    notification.classList.remove('hidden');
    
    setTimeout(() => {
        notification.classList.add('hidden');
    }, 3000);
}

// Load POS quick stats
async function loadPOSQuickStats() {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/api/pos/quick-stats', {
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            // Update stats display
            const totalItemsEl = document.getElementById('posTotalItems');
            const salesTodayEl = document.getElementById('posSalesToday');
            const revenueTodayEl = document.getElementById('posRevenueToday');
            const swapsTodayEl = document.getElementById('posSwapsToday');
            const swapRevenueTodayEl = document.getElementById('posSwapRevenueToday');
            
            if (totalItemsEl) {
                totalItemsEl.textContent = data.data.total_items || 0;
            }
            if (salesTodayEl) {
                salesTodayEl.textContent = data.data.sales_today || 0;
            }
            if (revenueTodayEl) {
                revenueTodayEl.textContent = '₵' + (parseFloat(data.data.revenue_today || 0).toFixed(2));
            }
            if (swapsTodayEl) {
                swapsTodayEl.textContent = data.data.swap_count_today || 0;
            }
            if (swapRevenueTodayEl) {
                swapRevenueTodayEl.textContent = '₵' + (parseFloat(data.data.swap_revenue_today || 0).toFixed(2));
            }
        }
    } catch (error) {
        console.error('Error loading POS quick stats:', error);
        // Don't show error notification for stats, just log it
    }
}

// Load products
async function loadProducts() {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/api/pos/products', {
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        
        if (!responseText || responseText.trim() === '') {
            throw new Error('Empty response from server');
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', responseText.substring(0, 500));
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            products = data.data || [];
            console.log('Products loaded:', products.length, 'items');
            renderProducts(products);
            updateProductCount();
            initializeFilters(products);
        } else {
            console.error('Failed to load products:', data.error);
            showNotification('Failed to load products: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showNotification('Error loading products: ' + error.message, 'error');
    }
}

// Initialize and wire up category/brand/sort filters
// Use a flag to prevent duplicate event listeners
let filtersInitialized = false;

function initializeFilters(productsData) {
    const categorySelect = document.getElementById('categoryFilter');
    const brandSelect = document.getElementById('brandFilter');
    const sortSelect = document.getElementById('sortFilter');
    const searchInput = document.getElementById('productSearch');

    if (!categorySelect || !brandSelect || !sortSelect) return;

    // Build unique category and brand lists from current products
    const categories = Array.from(new Set((productsData || []).map(p => (p.category_name || '').trim()).filter(Boolean))).sort();
    const brands = Array.from(new Set((productsData || []).map(p => (p.brand_name || '').trim()).filter(Boolean))).sort();

    // Store currently selected values to preserve them if they still exist
    const currentCategory = categorySelect.value;
    const currentBrand = brandSelect.value;

    // Populate category select
    categorySelect.innerHTML = '<option value="">All Categories</option>' +
        categories.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

    // Restore category selection if it still exists
    if (currentCategory && categories.includes(currentCategory)) {
        categorySelect.value = currentCategory;
    }

    // Populate brand select based on current category filter
    const selectedCategory = categorySelect.value;
    const filteredForBrands = selectedCategory
        ? (productsData || []).filter(p => (p.category_name || '') === selectedCategory)
        : (productsData || []);
    const brandsForCategory = Array.from(new Set(filteredForBrands.map(p => (p.brand_name || '').trim()).filter(Boolean))).sort();
    
    brandSelect.innerHTML = '<option value="">All Brands</option>' +
        brandsForCategory.map(b => `<option value="${escapeHtml(b)}">${escapeHtml(b)}</option>`).join('');

    // Restore brand selection if it still exists in the filtered brands
    if (currentBrand && brandsForCategory.includes(currentBrand)) {
        brandSelect.value = currentBrand;
    }

    // Only add event listeners once to prevent duplicates
    if (!filtersInitialized) {
        // Update brands when category changes and reset brand filter
        categorySelect.addEventListener('change', () => {
            const selectedCategory = categorySelect.value;
            const filteredForBrands = selectedCategory
                ? (products || []).filter(p => (p.category_name || '') === selectedCategory)
                : (products || []);
            const brandsForCategory = Array.from(new Set(filteredForBrands.map(p => (p.brand_name || '').trim()).filter(Boolean))).sort();
            
            // Reset brand filter when category changes
            const previousBrand = brandSelect.value;
            brandSelect.innerHTML = '<option value="">All Brands</option>' +
                brandsForCategory.map(b => `<option value="${escapeHtml(b)}">${escapeHtml(b)}</option>`).join('');
            
            // Restore brand if it exists in the new category
            if (previousBrand && brandsForCategory.includes(previousBrand)) {
                brandSelect.value = previousBrand;
            }
            
            applyFiltersAndRender();
        });

        brandSelect.addEventListener('change', applyFiltersAndRender);
        sortSelect.addEventListener('change', applyFiltersAndRender);
        
        // Search input with debounce - respects all filters
        if (searchInput) {
            searchInput.addEventListener('input', debounce(applyFiltersAndRender, 150));
        }
        
        filtersInitialized = true;
    }
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"]+/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
}

function debounce(fn, ms) {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
}

function applyFiltersAndRender() {
    const categorySelect = document.getElementById('categoryFilter');
    const brandSelect = document.getElementById('brandFilter');
    const sortSelect = document.getElementById('sortFilter');
    const searchInput = document.getElementById('productSearch');

    let list = [...(products || [])];

    // Text search
    const q = (searchInput?.value || '').toLowerCase();
    if (q) {
        list = list.filter(p => (p.name || '').toLowerCase().includes(q) || (p.brand_name || '').toLowerCase().includes(q));
    }

    // Category filter
    const cat = categorySelect?.value || '';
    if (cat) list = list.filter(p => (p.category_name || '') === cat);

    // Brand filter
    const brand = brandSelect?.value || '';
    if (brand) list = list.filter(p => (p.brand_name || '') === brand);

    // Sorting
    switch ((sortSelect?.value) || 'name_asc') {
        case 'name_asc':
            list.sort((a,b) => (a.name||'').localeCompare(b.name||''));
            break;
        case 'name_desc':
            list.sort((a,b) => (b.name||'').localeCompare(a.name||''));
            break;
        case 'price_asc':
            list.sort((a,b) => (+a.price||0) - (+b.price||0));
            break;
        case 'price_desc':
            list.sort((a,b) => (+b.price||0) - (+a.price||0));
            break;
        case 'stock_asc':
            list.sort((a,b) => (+a.quantity||0) - (+b.quantity||0));
            break;
        case 'stock_desc':
            list.sort((a,b) => (+b.quantity||0) - (+a.quantity||0));
            break;
    }

    renderProducts(list);
    // Update count label to reflect filtered list
    const productCountText = document.getElementById('productCountText');
    if (productCountText) productCountText.textContent = `${list.length} products`;
}

// Load customers
async function loadCustomers() {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/api/customers', {
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        
        // Check if response is OK
        if (!response.ok) {
            if (response.status === 404) {
                throw new Error('Route not found');
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        
        if (!responseText || responseText.trim() === '') {
            throw new Error('Empty response from server');
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', responseText.substring(0, 500));
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            customers = data.data || [];
            console.log('Customers loaded:', customers.length, 'items');
            renderCustomers();
        } else {
            console.error('Failed to load customers:', data.error);
            showNotification('Failed to load customers: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showNotification('Error loading customers: ' + error.message, 'error');
    }
}

// Load cart
async function loadCart() {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart', {
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await response.json();
        
        if (data.success) {
            cart = data.cart || [];
            console.log('Cart loaded:', cart);
            updateCartDisplay();
        }
    } catch (error) {
        console.error('Error loading cart:', error);
    }
}

// Render products
// Get category-based icon for products
function getCategoryIcon(category, productName) {
    const categoryLower = category.toLowerCase();
    const nameLower = productName.toLowerCase();
    
    // Phone and smartphone related
    if (categoryLower.includes('phone') || categoryLower.includes('smartphone') || 
        nameLower.includes('phone') || nameLower.includes('smartphone') || 
        nameLower.includes('iphone') || nameLower.includes('samsung') || 
        nameLower.includes('huawei') || nameLower.includes('xiaomi')) {
        return '<i class="fas fa-mobile-alt text-2xl text-blue-500"></i>';
    }
    
    // Charger and power related
    if (categoryLower.includes('charger') || categoryLower.includes('power') || 
        nameLower.includes('charger') || nameLower.includes('power bank') || 
        nameLower.includes('cable') || nameLower.includes('usb') || 
        nameLower.includes('adapter') || nameLower.includes('wireless')) {
        return '<i class="fas fa-bolt text-2xl text-yellow-500"></i>';
    }
    
    // Earbuds and audio related
    if (categoryLower.includes('earbud') || categoryLower.includes('headphone') || 
        categoryLower.includes('audio') || categoryLower.includes('sound') || 
        nameLower.includes('earbud') || nameLower.includes('headphone') || 
        nameLower.includes('airpods') || nameLower.includes('speaker') || 
        nameLower.includes('bluetooth')) {
        return '<i class="fas fa-headphones text-2xl text-purple-500"></i>';
    }
    
    // Laptop and computer related
    if (categoryLower.includes('laptop') || categoryLower.includes('computer') || 
        categoryLower.includes('pc') || nameLower.includes('laptop') || 
        nameLower.includes('macbook') || nameLower.includes('dell') || 
        nameLower.includes('hp') || nameLower.includes('lenovo')) {
        return '<i class="fas fa-laptop text-2xl text-indigo-500"></i>';
    }
    
    // Tablet related
    if (categoryLower.includes('tablet') || categoryLower.includes('ipad') || 
        nameLower.includes('tablet') || nameLower.includes('ipad')) {
        return '<i class="fas fa-tablet-alt text-2xl text-green-500"></i>';
    }
    
    // Watch and wearable
    if (categoryLower.includes('watch') || categoryLower.includes('wearable') || 
        nameLower.includes('watch') || nameLower.includes('apple watch') || 
        nameLower.includes('smartwatch')) {
        return '<i class="fas fa-clock text-2xl text-orange-500"></i>';
    }
    
    // Camera related
    if (categoryLower.includes('camera') || categoryLower.includes('photo') || 
        nameLower.includes('camera') || nameLower.includes('dslr') || 
        nameLower.includes('canon') || nameLower.includes('nikon')) {
        return '<i class="fas fa-camera text-2xl text-red-500"></i>';
    }
    
    // Gaming related
    if (categoryLower.includes('gaming') || categoryLower.includes('game') || 
        nameLower.includes('gaming') || nameLower.includes('controller') || 
        nameLower.includes('ps4') || nameLower.includes('xbox')) {
        return '<i class="fas fa-gamepad text-2xl text-pink-500"></i>';
    }
    
    // Accessories
    if (categoryLower.includes('accessory') || categoryLower.includes('case') || 
        nameLower.includes('case') || nameLower.includes('cover') || 
        nameLower.includes('protector') || nameLower.includes('screen')) {
        return '<i class="fas fa-shield-alt text-2xl text-teal-500"></i>';
    }
    
    // Memory and storage
    if (categoryLower.includes('memory') || categoryLower.includes('storage') || 
        nameLower.includes('memory') || nameLower.includes('sd card') || 
        nameLower.includes('usb drive') || nameLower.includes('hard drive')) {
        return '<i class="fas fa-hdd text-2xl text-gray-500"></i>';
    }
    
    // Default icon for unknown categories
    return '<i class="fas fa-box text-2xl text-gray-400"></i>';
}

function renderProducts(productsToRender) {
    const productsGrid = document.getElementById('productsGrid');
    
    if (!productsToRender || productsToRender.length === 0) {
        productsGrid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">No products found</div>';
        return;
    }
    
    productsGrid.innerHTML = productsToRender.map(product => {
        const productId = product.id || product.product_id;
        
        // Check if item is swapped - handle multiple formats
        // Check is_swapped_item flag
        const hasIsSwappedFlag = product.is_swapped_item != null && (
            product.is_swapped_item == 1 || 
            product.is_swapped_item === '1' || 
            product.is_swapped_item === true ||
            parseInt(product.is_swapped_item || 0) > 0
        );
        
        // Check swap_ref_id
        const hasSwapRef = product.swap_ref_id != null && 
                          product.swap_ref_id !== null && 
                          product.swap_ref_id !== 'NULL' && 
                          product.swap_ref_id !== '' &&
                          parseInt(product.swap_ref_id || 0) > 0;
        
        // Check is_swapped_for_resale flag
        const hasSwappedForResale = product.is_swapped_for_resale === true || 
                                    product.is_swapped_for_resale == 1 ||
                                    product.is_swapped_for_resale === '1';
        
        const isSwappedItem = hasIsSwappedFlag || hasSwapRef || hasSwappedForResale;
        
        // Debug logging for swapped items (remove after testing)
        if (isSwappedItem) {
            console.log('🔴 Swapped item detected:', {
                name: product.name,
                id: productId,
                is_swapped_item: product.is_swapped_item,
                swap_ref_id: product.swap_ref_id,
                is_swapped_for_resale: product.is_swapped_for_resale,
                hasIsSwappedFlag: hasIsSwappedFlag,
                hasSwapRef: hasSwapRef,
                hasSwappedForResale: hasSwappedForResale
            });
        }
        
        // Debug: Log all product data for first product to see what's being returned
        if (productsToRender.indexOf(product) === 0) {
            console.log('Sample product data from API:', {
                name: product.name,
                id: productId,
                is_swapped_item: product.is_swapped_item,
                swap_ref_id: product.swap_ref_id,
                is_swapped_for_resale: product.is_swapped_for_resale,
                allKeys: Object.keys(product)
            });
        }
        // Swapped items are always available for resale, regardless of quantity
        const isAvailable = (product.quantity > 0 || isSwappedItem) && productId;
        const cartQuantity = cart[productId] ? cart[productId].quantity : 0;
        const remainingStock = isSwappedItem ? 1 : (product.quantity - cartQuantity);
        const isSelected = cartQuantity > 0;
        const isLowStock = !isSwappedItem && remainingStock <= 5 && remainingStock > 0;
        const isOutOfStock = !isSwappedItem && remainingStock <= 0;
        
        // Handle product image with category-based icons
        let imageHtml = '';
        if (product.image_url && product.image_url.trim() !== '') {
            // Construct proper image URL with base path
            const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
            const imageUrl = product.image_url.startsWith('http') ? product.image_url : basePath + '/' + product.image_url;
            // Use actual product image
            imageHtml = `<img src="${imageUrl}" alt="${product.name || 'Product'}" class="w-16 h-16 object-cover rounded-lg" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center" style="display: none;">
                             ${getCategoryIcon(product.category_name || product.category || '', product.name || '')}
                         </div>`;
        } else {
            // Use category-based icon
            imageHtml = `<div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                             ${getCategoryIcon(product.category_name || product.category || '', product.name || '')}
                         </div>`;
        }
        
        // Stock warning badge - only for regular products
        let stockBadge = '';
        if (!isSwappedItem) {
            if (isLowStock && !isOutOfStock) {
                stockBadge = `<div class="stock-warning">Low Stock</div>`;
            } else if (isOutOfStock) {
                stockBadge = `<div class="stock-warning stock-danger">Out of Stock</div>`;
            }
        }
        
        // Swap button for available_for_swap products (Add to Cart serves as Sell Normally)
        let swapButton = '';
        if (product.available_for_swap && isAvailable) {
            swapButton = `
                <button class="open-swap-btn bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium py-1.5 px-3 rounded-lg transition-colors inline-flex items-center" 
                        data-product-id="${productId}" 
                        data-product-name="${product.name || 'Product'}" 
                        data-product-price="${product.price || 0}">
                    <i class="fas fa-exchange-alt mr-1"></i>Swap
                </button>
            `;
        }
        
        // Create tags array
        const tags = [];
        if (product.category_name) tags.push(product.category_name);
        if (product.subcategory_name) tags.push(product.subcategory_name);
        if (product.imei) tags.push(`IMEI: ${product.imei}`);
        if (product.condition) tags.push(product.condition);
        if (product.specs) {
            try {
                const specs = typeof product.specs === 'string' ? JSON.parse(product.specs) : product.specs;
                if (specs.storage) tags.push(specs.storage);
                if (specs.ram) tags.push(specs.ram);
                if (specs.color) tags.push(specs.color);
            } catch (e) {
                // Ignore JSON parse errors
            }
        }
        if (product.available_for_swap) tags.push('Swappable');
        if (isSwappedItem) tags.push('Swapped Item');
        
        // Simple class building - swapped items get distinct dark red background
        let cardClasses = 'product-card rounded-lg p-3 border-2 transition-all ';
        let cardStyle = '';
        
        if (isSwappedItem) {
            // Swapped items: purple/lavender theme for better visibility - responsive
            cardClasses += 'swapped-item-card ';
            cardStyle = 'background-color: #f3e8ff !important; border-color: #a78bfa !important; color: #6b21a8 !important;';
        } else {
            // Regular products: white background with standard border
            cardClasses += 'bg-white border-gray-300 ';
        }
        
        cardClasses += isAvailable ? 'cursor-pointer hover:bg-gray-50 ' : 'cursor-not-allowed opacity-50 ';
        // Removed ring/border for selected products
        
        return `
            <div class="${cardClasses.trim()}" data-product-id="${productId}" data-is-swapped="${isSwappedItem ? 'true' : 'false'}" ${cardStyle ? `style="${cardStyle}"` : ''} ${isSwappedItem ? `onmouseover="this.style.backgroundColor='#e9d5ff'; this.style.borderColor='#8b5cf6';" onmouseout="this.style.backgroundColor='#f3e8ff'; this.style.borderColor='#a78bfa';"` : ''}>
                <div class="flex items-start sm:items-center space-x-2 sm:space-x-3 py-1 sm:py-2">
                    <!-- Product Image -->
                    <div class="flex-shrink-0 flex flex-col items-center">
                        <div class="relative w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14">
                            ${imageHtml}
                        </div>
                        ${stockBadge ? `<div class="mt-0.5 sm:mt-1 text-xs">${stockBadge}</div>` : ''}
                    </div>
                    
                    <!-- Product Details -->
                    <div class="flex-1 min-w-0">
                        <!-- Product Name -->
                        <h3 class="font-semibold ${isSwappedItem ? 'text-purple-900' : 'text-gray-800'} text-xs sm:text-sm md:text-base leading-tight mb-0.5 sm:mb-1 line-clamp-2" style="${isSwappedItem ? 'color: #6b21a8 !important;' : ''}">${product.name || 'Unnamed Product'}</h3>
                        
                        <!-- Category Badge -->
                        ${product.category_name ? `
                            <div class="mb-1 sm:mb-1.5">
                                <span class="inline-block ${isSwappedItem ? 'bg-white text-purple-800' : 'bg-blue-100 text-blue-800'} text-xs font-medium px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full" style="${isSwappedItem ? 'background-color: #c4b5fd !important; color: #6b21a8 !important;' : ''}">
                                    <i class="fas fa-tag mr-0.5 sm:mr-1 text-xs" style="${isSwappedItem ? 'color: #6b21a8 !important;' : ''}"></i><span class="text-xs">${product.category_name}</span>
                                </span>
                            </div>
                        ` : ''}
                        
                        <!-- Swapped Item Badge -->
                        ${isSwappedItem ? `
                            <div class="mb-1 sm:mb-1.5">
                                <span class="inline-block bg-white text-purple-800 text-xs font-bold px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full border" style="background-color: #c4b5fd !important; color: #6b21a8 !important; border-color: #8b5cf6 !important;">
                                    <i class="fas fa-exchange-alt mr-0.5 sm:mr-1 text-xs" style="color: #6b21a8 !important;"></i><span class="text-xs">Swapped Item - For Resale</span>
                                </span>
                            </div>
                        ` : ''}
                        
                        <!-- Additional Tags -->
                        <div class="flex flex-wrap gap-0.5 sm:gap-1 mb-1 sm:mb-1.5">
                            ${tags.filter(tag => tag !== product.category_name && tag !== 'Swapped Item').slice(0, 2).map(tag => `
                                <span class="inline-block ${isSwappedItem ? 'bg-white text-purple-800' : 'bg-gray-100 text-gray-700'} text-xs px-1.5 sm:px-2 py-0.5 rounded-full" style="${isSwappedItem ? 'background-color: #c4b5fd !important; color: #6b21a8 !important;' : ''}">${tag}</span>
                            `).join('')}
                            ${tags.filter(tag => tag !== product.category_name && tag !== 'Swapped Item').length > 2 ? `<span class="inline-block ${isSwappedItem ? 'bg-white text-purple-800' : 'bg-gray-100 text-gray-700'} text-xs px-1.5 sm:px-2 py-0.5 rounded-full" style="${isSwappedItem ? 'background-color: #c4b5fd !important; color: #6b21a8 !important;' : ''}">+${tags.filter(tag => tag !== product.category_name && tag !== 'Swapped Item').length - 2}</span>` : ''}
                        </div>
                        
                        <!-- Stock Info -->
                        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-1 sm:mb-1.5">
                            ${isSwappedItem ? `
                                <span class="text-xs font-medium whitespace-nowrap" style="color: #6b21a8 !important;">Status: <span class="font-bold stock-display" style="color: #6b21a8 !important;">For Resale</span></span>
                                <span class="text-xs font-medium px-1.5 sm:px-2 py-0.5 rounded-full whitespace-nowrap" style="background-color: #c4b5fd !important; color: #6b21a8 !important;">
                                    <i class="fas fa-exchange-alt mr-0.5 sm:mr-1 text-xs" style="color: #6b21a8 !important;"></i>Swapped Item
                                </span>
                            ` : `
                                <span class="text-xs text-gray-600 whitespace-nowrap">Stock: <span class="font-medium text-gray-800 stock-display">${remainingStock}</span></span>
                            `}
                            ${isSelected ? `<span class="text-xs ${isSwappedItem ? '' : 'text-blue-600 bg-blue-100'} font-medium px-1.5 sm:px-2 py-0.5 rounded-full whitespace-nowrap" style="${isSwappedItem ? 'background-color: #c4b5fd !important; color: #6b21a8 !important;' : ''}">In Cart: ${cartQuantity}</span>` : ''}
                        </div>
                        
                        ${!productId ? '<p class="text-xs text-red-500 mt-1">No ID</p>' : ''}
                    </div>
                    
                    <!-- Price and Add Button -->
                    <div class="flex-shrink-0 text-right min-w-0">
                        ${isSwappedItem ? `
                            <div class="text-xs font-medium mb-0.5 sm:mb-1 whitespace-nowrap" style="color: #6b21a8 !important;">For Resale</div>
                            <div class="text-base sm:text-lg md:text-xl font-bold mb-1.5 sm:mb-2 break-words overflow-hidden" style="color: #6b21a8 !important;">₵${formatCurrency(parseFloat(product.price || product.resell_price || 0))}</div>
                            ${parseFloat(product.price || product.resell_price || 0) === 0 ? `
                                <div class="text-xs mb-1 sm:mb-2" style="color: #d97706 !important;">⚠ Price not set</div>
                            ` : ''}
                        ` : `
                            <div class="text-base sm:text-lg md:text-xl font-bold text-green-600 mb-1.5 sm:mb-2 break-words overflow-hidden">₵${formatCurrency(parseFloat(product.price || 0))}</div>
                        `}
        ${isAvailable ? `
                            ${POS_READ_ONLY ? `
                                <button disabled class="bg-gray-400 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-not-allowed">
                                    <i class=\"fas fa-ban mr-1\"></i>View Only
                                </button>
                            ` : product.available_for_swap ? `
                                <div class="flex gap-2">
                                    ${swapButton}
                                    <button onclick=\"event.stopPropagation(); addToCart(${productId})\" 
                                            class=\"bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium flex items-center transition-colors\">
                                        <i class=\"fas fa-cart-plus mr-1\"></i>Sell Normally
                                    </button>
                                </div>
                            ` : `
                                <button onclick=\"event.stopPropagation(); addToCart(${productId})\" 
                                        class=\"${isSwappedItem ? 'bg-white text-purple-800 hover:bg-purple-50' : 'bg-blue-600 hover:bg-blue-700 text-white'} px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs font-medium flex items-center justify-center transition-colors whitespace-nowrap\" style=\"${isSwappedItem ? 'background-color: #c4b5fd !important; color: #6b21a8 !important;' : ''}\">
                                    <i class=\"${isSwappedItem ? 'fas fa-tag' : 'fas fa-cart-plus'} mr-1\" style=\"${isSwappedItem ? 'color: #6b21a8 !important;' : ''}\"></i>
                                    <span class="hidden sm:inline">${isSwappedItem ? 'Resale' : 'Add to Cart'}</span><span class="sm:hidden">${isSwappedItem ? 'Resale' : 'Add'}</span>
                                </button>
                            `}
                        ` : `
                            <button disabled class="bg-gray-400 text-white px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs font-medium cursor-not-allowed whitespace-nowrap">
                                <i class="fas fa-times mr-1"></i>
                                <span class="hidden sm:inline">Out of Stock</span><span class="sm:hidden">Out</span>
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Render customers
function renderCustomers() {
    const customerSelect = document.getElementById('customerSelect');
    const existingCustomerSelect = document.getElementById('existingCustomerSelect');
    const swapCustomerSelect = document.getElementById('swapCustomerSelect');
    
    // If it's still a select element, populate it
    if (customerSelect && customerSelect.tagName === 'SELECT') {
        let customerOptions = '';
        customers.forEach(customer => {
            customerOptions += `<option value="${customer.id}">${customer.full_name} (${customer.phone_number})</option>`;
        });
        customerSelect.innerHTML = '<option value="">Walk-in Customer</option>' + customerOptions;
    }
    
    if (existingCustomerSelect) {
        let customerOptions = '';
        customers.forEach(customer => {
            customerOptions += `<option value="${customer.id}">${customer.full_name} (${customer.phone_number})</option>`;
        });
        existingCustomerSelect.innerHTML = '<option value="">Choose a customer...</option>' + customerOptions;
    }
    
    // Populate swap modal customer dropdown
    if (swapCustomerSelect) {
        let customerOptions = '';
        customers.forEach(customer => {
            customerOptions += `<option value="${customer.id}">${customer.full_name} (${customer.phone_number})</option>`;
        });
        swapCustomerSelect.innerHTML = '<option value="">Choose a customer...</option>' + customerOptions;
    }
}

// Populate swap customer dropdown
function populateSwapCustomerDropdown() {
    const inputEl = document.getElementById('swapCustomerCombo');
    const dropdownEl = document.getElementById('swapCustomerDropdown');
    const hiddenSelect = document.getElementById('swapCustomerSelect');
    if (!inputEl || !dropdownEl || !hiddenSelect) return;

    function renderList(list) {
        if (!list || list.length === 0) {
            dropdownEl.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
            return;
        }
        dropdownEl.innerHTML = list.map(customer => `
            <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                 data-id="${customer.id}" data-name="${customer.full_name}" data-phone="${customer.phone_number || ''}">
                <div class="font-medium text-gray-800">${customer.full_name}</div>
                <div class="text-sm text-gray-600">${customer.phone_number || ''}</div>
                ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
            </div>
        `).join('');
    }

    // Initial render
    renderList(customers);

    // Open dropdown on focus/click
    inputEl.addEventListener('focus', () => dropdownEl.classList.remove('hidden'));
    inputEl.addEventListener('click', () => dropdownEl.classList.remove('hidden'));

    // Filter as user types
    inputEl.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const filtered = !q ? customers : customers.filter(c =>
            (c.full_name || '').toLowerCase().includes(q) ||
            (c.phone_number || '').toLowerCase().includes(q)
        );
        renderList(filtered);
    });

    // Select customer from dropdown
    dropdownEl.addEventListener('click', function(e) {
        const item = e.target.closest('[data-id]');
        if (!item) return;
        const id = item.getAttribute('data-id');
        const name = item.getAttribute('data-name');
        const phone = item.getAttribute('data-phone');
        hiddenSelect.value = id;
        inputEl.value = `${name} (${phone})`;
        dropdownEl.classList.add('hidden');

        // Show selected customer info
        const infoBox = document.getElementById('swapSelectedCustomerInfo');
        if (infoBox) {
            document.getElementById('swapSelectedCustomerName').textContent = name || '';
            document.getElementById('swapSelectedCustomerPhone').textContent = phone || '';
            infoBox.classList.remove('hidden');
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!inputEl.contains(e.target) && !dropdownEl.contains(e.target)) {
            dropdownEl.classList.add('hidden');
        }
    });

    // Clear button support
    const clearBtn = document.getElementById('swapClearCustomerBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            inputEl.value = '';
            hiddenSelect.value = '';
            dropdownEl.classList.add('hidden');
            const infoBox = document.getElementById('swapSelectedCustomerInfo');
            if (infoBox) infoBox.classList.add('hidden');
        });
    }
}

// Display customer search results for swap modal
function displaySwapCustomerResults(filteredCustomers) {
    const dropdown = document.getElementById('swapCustomerDropdown');
    
    if (filteredCustomers.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
    } else {
        dropdown.innerHTML = filteredCustomers.map(customer => `
            <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                 onclick="selectSwapCustomer(${customer.id}, '${customer.full_name}', '${customer.phone_number}')">
                <div class="font-medium text-gray-800">${customer.full_name}</div>
                <div class="text-sm text-gray-600">${customer.phone_number}</div>
                ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
            </div>
        `).join('');
    }
    
    dropdown.classList.remove('hidden');
}

// Select customer from swap modal dropdown
function selectSwapCustomer(customerId, customerName, customerPhone) {
    const hiddenSelect = document.getElementById('swapCustomerSelect');
    const combo = document.getElementById('swapCustomerCombo');
    const dropdown = document.getElementById('swapCustomerDropdown');
    if (hiddenSelect) hiddenSelect.value = customerId;
    if (combo) combo.value = `${customerName} (${customerPhone})`;
    if (dropdown) dropdown.classList.add('hidden');
    document.getElementById('swapSelectedCustomerName').textContent = customerName;
    document.getElementById('swapSelectedCustomerPhone').textContent = customerPhone;
    document.getElementById('swapSelectedCustomerInfo').classList.remove('hidden');
}

// Toggle product selection (add/remove from cart)
async function toggleProductSelection(productId) {
    if (!productId || productId === 'undefined' || productId === 'null') {
        console.error('Invalid product ID:', productId);
        showNotification('Invalid product ID', 'error');
        return;
    }
    
    const cartQuantity = cart[productId] ? cart[productId].quantity : 0;
    const productCard = document.querySelector(`[data-product-id="${productId}"]`);
    const stockDisplay = productCard ? productCard.querySelector('.stock-display') : null;
    const currentStock = stockDisplay ? parseInt(stockDisplay.textContent) : 0;
    
    // If product is already in cart, remove it
    if (cartQuantity > 0) {
        showNotification('Product removed from cart', 'info');
        await removeFromCart(productId);
        return;
    }
    
    // Check if adding would exceed stock
    if (currentStock <= 0) {
        showNotification('Product is out of stock!', 'error');
        return;
    }
    
    // If product is not in cart, add it
    showNotification('Product added to cart', 'success');
    await addToCart(productId);
}

// Add to cart
async function addToCart(productId) {
    if (POS_READ_ONLY) { showNotification('Managers cannot sell (view-only).', 'error'); return; }
    if (!productId || productId === 'undefined' || productId === 'null') {
        console.error('Invalid product ID:', productId);
        showNotification('Invalid product ID', 'error');
        return;
    }
    
    // Check if product is already in cart and if we can add more
    const cartQuantity = cart[productId] ? cart[productId].quantity : 0;
    const productCard = document.querySelector(`[data-product-id="${productId}"]`);
    const stockDisplay = productCard ? productCard.querySelector('.stock-display') : null;
    const currentStock = stockDisplay ? parseInt(stockDisplay.textContent) : 0;
    
    // Check if adding would exceed stock
    if (currentStock <= 0) {
        showNotification('Product is out of stock!', 'error');
        return;
    }
    
    // Add visual feedback
    if (productCard) {
        productCard.classList.add('adding');
        setTimeout(() => {
            productCard.classList.remove('adding');
        }, 1000);
    }
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart/add', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin', // Include session cookies for authentication
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart immediately
            cart = data.cart || [];
            updateCartDisplay();
            // Re-render products to update stock display and selection state
            renderProducts(products);
            // Check and show partial payment section after adding item (with delay to ensure DOM updated)
            setTimeout(() => {
                updatePartialPaymentSectionVisibility();
            }, 200);
        } else {
            showNotification(data.error || 'Failed to add product to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('Error adding product to cart', 'error');
    }
}

// Update cart display
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const subtotal = document.getElementById('subtotal');
    const total = document.getElementById('total');
    
    if (!cart || Object.keys(cart).length === 0) {
        cartItems.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-2"></i>
                <p class="font-medium">Cart is empty</p>
                <p class="text-sm mt-1">Add items to get started</p>
            </div>
        `;
        document.getElementById('cartItemCount').textContent = '0';
        subtotal.textContent = '₵0.00';
        total.textContent = '₵0.00';
        
        // Hide all sections when cart is empty
        document.getElementById('cartSummarySection')?.classList.add('hidden');
        document.getElementById('paymentMethodSection')?.classList.add('hidden');
        document.getElementById('customerSection')?.classList.add('hidden');
        document.getElementById('notesSection')?.classList.add('hidden');
        document.getElementById('actionButtonsSection')?.classList.add('hidden');
        
        // Hide partial payment section when cart is empty
        updatePartialPaymentSectionVisibility();
        
        updateTotals();
        return;
    }
    
    let totalAmount = 0;
    
    // Update cart item count
    const cartItemCount = Object.values(cart).reduce((sum, item) => sum + item.quantity, 0);
    document.getElementById('cartItemCount').textContent = cartItemCount;
    
    cartItems.innerHTML = Object.values(cart).map(item => {
        const itemTotal = parseFloat(item.total || 0);
        totalAmount += itemTotal;
        
        // Create tags for cart item
        const tags = [];
        if (item.category_name) tags.push(item.category_name);
        if (item.imei) tags.push(`IMEI: ${item.imei}`);
        if (item.condition) tags.push(item.condition);
        
        return `
            <div class="bg-gray-50 rounded-lg p-3 sm:p-4 border border-gray-200 min-w-0 overflow-hidden">
                <div class="flex items-start justify-between mb-2 sm:mb-3 gap-2">
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-gray-800 text-xs sm:text-sm mb-1 truncate">${item.name || 'Unknown Product'}</h4>
                        <div class="flex flex-wrap gap-1 mb-2">
                            ${tags.map(tag => `
                                <span class="inline-block bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full whitespace-nowrap">${tag}</span>
                            `).join('')}
                        </div>
                    </div>
                    <button onclick="removeFromCart(${item.product_id})" class="text-red-500 hover:text-red-700 p-1 flex-shrink-0">
                        <i class="fas fa-trash text-xs sm:text-sm"></i>
                    </button>
                </div>
                
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center space-x-1 sm:space-x-2">
                        <button onclick="updateCartQuantity(${item.product_id}, ${item.quantity - 1})" class="w-7 h-7 sm:w-8 sm:h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-xs sm:text-sm">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="text-xs sm:text-sm font-bold w-6 sm:w-8 text-center">${item.quantity}</span>
                        <button onclick="updateCartQuantity(${item.product_id}, ${item.quantity + 1})" class="w-7 h-7 sm:w-8 sm:h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-xs sm:text-sm">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="text-right min-w-0">
                        <div class="text-xs sm:text-sm text-gray-600 break-words">Price: ₵${formatCurrency(parseFloat(item.price || 0))}</div>
                        <div class="text-xs sm:text-sm text-gray-600">Discount: 0</div>
                        <div class="text-base sm:text-lg font-bold text-gray-800 break-words">₵${formatCurrency(itemTotal)}</div>
                    </div>
                </div>
                ${item.quantity >= item.available_stock ? '<div class="text-xs text-red-500 mt-2">⚠️ Exceeds available stock!</div>' : ''}
            </div>
        `;
    }).join('');
    
    subtotal.textContent = `₵${totalAmount.toFixed(2)}`;
    
    // Show all sections when cart has items
    document.getElementById('cartSummarySection')?.classList.remove('hidden');
    document.getElementById('paymentMethodSection')?.classList.remove('hidden');
    document.getElementById('customerSection')?.classList.remove('hidden');
    document.getElementById('notesSection')?.classList.remove('hidden');
    document.getElementById('actionButtonsSection')?.classList.remove('hidden');
    
    // Update partial payment section visibility (will show if module enabled and payment section is visible)
    updatePartialPaymentSectionVisibility();
    
    updateTotals();
    updatePartialPaymentDisplay();
}

// Check if partial payments module is enabled
let partialPaymentsEnabled = false;

// Helper function to show/hide partial payment section based on module status and payment section visibility
function updatePartialPaymentSectionVisibility() {
    const partialSection = document.getElementById('partialPaymentSection');
    const paymentSection = document.getElementById('paymentMethodSection');
    
    console.log('updatePartialPaymentSectionVisibility called:', {
        partialSectionExists: !!partialSection,
        paymentSectionExists: !!paymentSection,
        partialPaymentsEnabled: partialPaymentsEnabled,
        paymentSectionVisible: paymentSection ? !paymentSection.classList.contains('hidden') : false
    });
    
    if (partialSection && paymentSection) {
        // Show only if module is enabled AND payment section is visible
        const paymentSectionVisible = !paymentSection.classList.contains('hidden') && paymentSection.style.display !== 'none';
        
        if (partialPaymentsEnabled && paymentSectionVisible) {
            partialSection.style.display = 'block';
            partialSection.classList.remove('hidden');
            console.log('✅ Partial payment section SHOWN (module enabled, payment section visible)');
        } else {
            partialSection.style.display = 'none';
            partialSection.classList.add('hidden');
            console.log('❌ Partial payment section HIDDEN', {
                moduleEnabled: partialPaymentsEnabled,
                paymentSectionVisible: paymentSectionVisible
            });
        }
    } else {
        console.warn('⚠️ Partial payment section or payment section not found in DOM', {
            partialSection: !!partialSection,
            paymentSection: !!paymentSection
        });
    }
}

async function checkPartialPaymentsModule() {
    try {
        const companyId = <?= isset($_SESSION['user']['company_id']) ? (int)$_SESSION['user']['company_id'] : 'null' ?>;
        console.log('Checking partial payments module for company:', companyId);
        
        if (!companyId) {
            console.warn('No company ID found, partial payments disabled');
            partialPaymentsEnabled = false;
            updatePartialPaymentSectionVisibility();
            return false;
        }
        
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        console.log('Fetching modules from:', `${basePath}/api/admin/company/${companyId}/modules`);
        
        const response = await fetch(`${basePath}/api/admin/company/${companyId}/modules`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        console.log('Modules API response status:', response.status);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Modules API response data:', data);
            
            if (data.success && data.modules) {
                const module = data.modules.find(m => m.key === 'partial_payments');
                partialPaymentsEnabled = module ? module.enabled : false;
                
                console.log('Partial payments module status:', {
                    enabled: partialPaymentsEnabled,
                    companyId: companyId,
                    moduleFound: !!module,
                    allModules: data.modules.map(m => ({ key: m.key, enabled: m.enabled }))
                });
                
                // Update partial payment section visibility
                updatePartialPaymentSectionVisibility();
                
                return partialPaymentsEnabled;
            } else {
                console.warn('Modules API response not successful or no modules:', data);
            }
        } else {
            const errorText = await response.text();
            console.error('Modules API error:', response.status, errorText);
        }
    } catch (error) {
        console.error('Error checking partial payments module:', error);
        console.error('Error stack:', error.stack);
    }
    
    partialPaymentsEnabled = false;
    updatePartialPaymentSectionVisibility();
    return false;
}

// Update totals with discount and tax
function updateTotals() {
    const subtotalEl = document.getElementById('subtotal');
    const discountEl = document.getElementById('discount');
    const taxEl = document.getElementById('tax');
    const totalEl = document.getElementById('total');
    
    const subtotal = parseFloat(subtotalEl.textContent.replace('₵', '')) || 0;
    const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const taxAmount = parseFloat(document.getElementById('taxAmount').value) || 0;
    const taxType = document.getElementById('taxType').value;
    
    // Calculate discount
    let discount = 0;
    if (discountType === 'percent') {
        discount = (subtotal * discountAmount) / 100;
    } else {
        discount = discountAmount;
    }
    
    // Calculate tax
    let tax = 0;
    if (taxType === 'percent') {
        tax = ((subtotal - discount) * taxAmount) / 100;
    } else {
        tax = taxAmount;
    }
    
    const total = subtotal - discount + tax;
    
    discountEl.textContent = `-₵${discount.toFixed(2)}`;
    taxEl.textContent = `₵${tax.toFixed(2)}`;
    totalEl.textContent = `₵${total.toFixed(2)}`;
    
    // Update partial payment display if enabled
    if (partialPaymentsEnabled) {
        updatePartialPaymentDisplay();
    }
}

// Update partial payment display
function updatePartialPaymentDisplay() {
    if (!partialPaymentsEnabled) return;
    
    const totalEl = document.getElementById('total');
    const amountReceivedInput = document.getElementById('amountReceived');
    const remainingDisplay = document.getElementById('remainingAmountDisplay');
    const hintEl = document.getElementById('partialPaymentHint');
    const hintTextEl = document.getElementById('partialPaymentHintText');
    
    if (!totalEl || !amountReceivedInput || !remainingDisplay) return;
    
    const total = parseFloat(totalEl.textContent.replace('₵', '').replace(',', '')) || 0;
    const amountReceived = parseFloat(amountReceivedInput.value) || 0;
    
    if (amountReceived > 0 && amountReceived < total) {
        const remaining = total - amountReceived;
        remainingDisplay.textContent = `Remaining: ₵${remaining.toFixed(2)}`;
        remainingDisplay.classList.remove('hidden');
        remainingDisplay.classList.remove('text-green-600');
        remainingDisplay.classList.add('text-orange-600');
        
        if (hintEl && hintTextEl) {
            hintTextEl.textContent = `Partial payment: ₵${amountReceived.toFixed(2)} received, ₵${remaining.toFixed(2)} remaining`;
            hintEl.classList.remove('hidden');
        }
    } else if (amountReceived >= total && amountReceived > 0) {
        remainingDisplay.textContent = 'Paid in full';
        remainingDisplay.classList.remove('hidden');
        remainingDisplay.classList.remove('text-orange-600');
        remainingDisplay.classList.add('text-green-600');
        
        if (hintEl) {
            hintEl.classList.add('hidden');
        }
    } else {
        remainingDisplay.classList.add('hidden');
        if (hintEl) {
            hintEl.classList.add('hidden');
        }
    }
    
    // Set max value to total
    amountReceivedInput.max = total;
}

// Update product count
function updateProductCount() {
    const productCountText = document.getElementById('productCountText');
    if (productCountText) {
        productCountText.textContent = `${products.length} products`;
    }
}

// Setup event listeners
function setupEventListeners() {
    // Payment method buttons
    console.log('Setting up payment method buttons...');
    const paymentButtons = document.querySelectorAll('.payment-method-btn');
    console.log('Found payment buttons:', paymentButtons.length);
    
    paymentButtons.forEach((btn, index) => {
        console.log(`Button ${index}:`, btn.getAttribute('data-method'));
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Payment button clicked:', this.getAttribute('data-method'));
            
            // Remove active state from all payment method buttons
            document.querySelectorAll('.payment-method-btn').forEach(b => {
                b.classList.remove('bg-green-100', 'text-green-700', 'border-green-300');
                b.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300');
            });
            
            // Add active state to clicked button
            this.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300');
            this.classList.add('bg-green-100', 'text-green-700', 'border-green-300');
            
            // Store selected payment method
            const selectedMethod = this.getAttribute('data-method');
            const hiddenInput = document.getElementById('selectedPaymentMethod');
            if (hiddenInput) {
                hiddenInput.value = selectedMethod;
                console.log('Selected payment method stored:', selectedMethod);
            } else {
                console.error('Hidden input not found!');
            }
        });
    });

    // Customer type buttons (excluding "new" which opens modal)
    document.querySelectorAll('.customer-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const customerType = this.getAttribute('data-type');
            
            // Skip if "new" button (handled by modal)
            if (customerType === 'new') {
                return;
            }
            
            // Remove active state from all buttons
            document.querySelectorAll('.customer-type-btn').forEach(b => {
                b.classList.remove('bg-blue-100', 'text-blue-700', 'border-blue-300');
                b.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300');
            });
            
            // Add active state to clicked button
            this.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300');
            this.classList.add('bg-blue-100', 'text-blue-700', 'border-blue-300');
            
            // Show/hide appropriate sections
            const existingDropdown = document.getElementById('existingCustomerDropdown');
            const dropdownList = document.getElementById('existingCustomerDropdownList');
            const existingCustomerSelect = document.getElementById('existingCustomerSelect');
            
            if (customerType === 'existing') {
                existingDropdown.style.display = 'block';
                // Ensure customers are loaded
                if (!customers || customers.length === 0) {
                    loadCustomers().then(() => {
                        // Dropdown will show when user focuses on input
                    });
                }
            } else if (customerType === 'walkin') {
                // Hide existing customer dropdown and open walk-in modal
                existingDropdown.style.display = 'none';
                dropdownList.classList.add('hidden');
                window.selectedCustomer = null;
                openNewCustomerModal(true); // Open in walk-in SMS mode
            } else {
                existingDropdown.style.display = 'none';
                dropdownList.classList.add('hidden');
                window.selectedCustomer = null;
            }
        });
    });
    
    // Setup existing customer search
    const existingCustomerSearch = document.getElementById('existingCustomerSearch');
    if (existingCustomerSearch) {
        const dropdownList = document.getElementById('existingCustomerDropdownList');
        
        // Show dropdown immediately when input is focused/clicked
        existingCustomerSearch.addEventListener('focus', function() {
            // Ensure customers are loaded
            if (!customers || customers.length === 0) {
                loadCustomers().then(() => {
                    if (customers && customers.length > 0) {
                        const query = existingCustomerSearch.value.trim();
                        if (query.length > 0) {
                            filterExistingCustomers(query);
                        } else {
                            renderExistingCustomerList(customers);
                        }
                        dropdownList.classList.remove('hidden');
                    }
                });
            } else {
                const query = this.value.trim();
                if (query.length > 0) {
                    filterExistingCustomers(query);
                } else {
                    renderExistingCustomerList(customers);
                }
                dropdownList.classList.remove('hidden');
            }
        });
        
        // Live search as user types (no delay)
        existingCustomerSearch.addEventListener('input', function() {
            const query = this.value.trim();
            const dropdownList = document.getElementById('existingCustomerDropdownList');
            
            if (query.length > 0) {
                // Filter immediately (live search)
                filterExistingCustomers(query);
            } else {
                // Show all customers when search is cleared
                if (customers && customers.length > 0) {
                    renderExistingCustomerList(customers);
                }
            }
            // Keep dropdown visible
            dropdownList.classList.remove('hidden');
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const searchContainer = existingCustomerSearch.closest('.relative');
            if (searchContainer && !searchContainer.contains(e.target)) {
                dropdownList.classList.add('hidden');
            }
        });
    }

    // Clear cart button
    document.getElementById('clearCartBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to clear the cart?')) {
            clearCart();
        }
    });

    // Process sale button (opens modal) - keep this for "Process Sale" if it exists
    // Complete sale button - processes sale directly
    const processSaleBtn = document.getElementById('processSaleBtn');
    if (processSaleBtn) {
        processSaleBtn.addEventListener('click', async function() {
            if (POS_READ_ONLY) { showNotification('Managers cannot process sales (view-only).', 'error'); return; }
            if (Object.keys(cart).length === 0) {
                showNotification('Cart is empty. Add products before processing sale.', 'error');
                return;
            }
            
            // Disable button and show loading state immediately
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            this.classList.add('opacity-75', 'cursor-not-allowed');
            
            try {
                // Process sale directly with default values
                const paymentMethod = 'cash'; // Default payment method
                const subtotal = Object.values(cart).reduce((sum, item) => sum + parseFloat(item.total), 0);
                
                // Get discount and tax values from form (if they exist)
                const discountAmount = parseFloat(document.getElementById('discountAmount')?.value || 0);
                const discountType = document.getElementById('discountType')?.value || 'amount';
                const taxAmount = parseFloat(document.getElementById('taxAmount')?.value || 0);
                const taxType = document.getElementById('taxType')?.value || 'amount';
                
                // Calculate discount
                let discount = 0;
                if (discountType === 'percent') {
                    discount = (subtotal * discountAmount) / 100;
                } else {
                    discount = discountAmount;
                }
                
                // Calculate tax
                let tax = 0;
                if (taxType === 'percent') {
                    tax = ((subtotal - discount) * taxAmount) / 100;
                } else {
                    tax = taxAmount;
                }
                
                const total = subtotal - discount + tax;
                
                // Get amount received from input (if partial payments enabled) or default to total
                let amountReceived = total;
                const amountReceivedInput = document.getElementById('amountReceived');
                if (amountReceivedInput && !amountReceivedInput.classList.contains('hidden')) {
                    const inputValue = parseFloat(amountReceivedInput.value);
                    if (!isNaN(inputValue) && inputValue > 0) {
                        amountReceived = Math.min(inputValue, total); // Don't allow overpayment
                    }
                }
                
                const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
                const response = await fetch(basePath + '/api/pos', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    credentials: 'same-origin', // Include session cookies for authentication fallback
                    body: JSON.stringify({
                        items: Object.values(cart).map(item => ({
                            product_id: item.product_id,
                            name: item.name,
                            quantity: item.quantity,
                            unit_price: item.price,
                            total_price: item.total
                        })),
                        payment_method: paymentMethod,
                        amount_received: amountReceived,
                        discount: discount,
                        tax: tax,
                        notes: '',
                        customer_id: window.selectedCustomer ? window.selectedCustomer.id : null,
                        customer_name: window.selectedCustomer ? window.selectedCustomer.name : null,
                        customer_contact: window.selectedCustomer ? window.selectedCustomer.phone : null
                    })
                });
                
                // Check if response is ok
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP error:', response.status, errorText);
                    console.error('Error response headers:', [...response.headers.entries()]);
                    
                    // Try to parse error as JSON
                    let errorData = null;
                    try {
                        errorData = JSON.parse(errorText);
                        console.error('Error data:', errorData);
                    } catch (e) {
                        console.error('Error response is not JSON:', errorText.substring(0, 500));
                    }
                    
                    throw new Error(`HTTP error! status: ${response.status} - ${errorData?.message || errorData?.error || errorText.substring(0, 100)}`);
                }
                
                // Read response as text first
                const responseText = await response.text();
                
                // Check if response is empty
                if (!responseText || responseText.trim() === '') {
                    console.error('Empty response from server');
                    throw new Error('Empty response from server');
                }
                
                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data && data.success) {
                    showNotification('Sale completed successfully!', 'success');
                    
                    // Store the last sale ID for receipt printing
                    lastSaleId = data.sale_id || data.data?.sale_id;
                    
                    // Enable print receipt button
                    const printBtn = document.getElementById('printLastReceiptBtn');
                    if (printBtn && lastSaleId) {
                        printBtn.disabled = false;
                        printBtn.classList.remove('bg-purple-100', 'text-purple-700');
                        printBtn.classList.add('bg-purple-600', 'text-white');
                    }
                    
                    // Show receipt modal with sale data
                    const saleData = {
                        sale_id: data.unique_id || data.data?.unique_id || lastSaleId,
                        total_amount: total,
                        payment_method: paymentMethod,
                        items: Object.values(cart).map(item => ({
                            name: item.name,
                            quantity: item.quantity,
                            total_price: item.total
                        })),
                        company_name: 'SellApp'
                    };
                    showReceiptModal(saleData);
                    
                    // Clear cart after successful sale
                    await clearCartAfterSale();
                    
                    cart = [];
                    updateCartDisplay();
                    
                    // Reload products to reflect updated inventory
                    await loadProducts();
                    
                    // Reset discount and tax fields
                    if (document.getElementById('discountAmount')) {
                        document.getElementById('discountAmount').value = '';
                        document.getElementById('discountType').value = 'amount';
                        document.getElementById('taxAmount').value = '';
                        document.getElementById('taxType').value = 'amount';
                    }
                    
                    // Refresh quick stats after successful sale
                    loadPOSQuickStats();
                } else {
                    showNotification(data.message || 'Failed to complete sale', 'error');
                }
            } catch (error) {
                console.error('Error completing sale:', error);
                showNotification('Error completing sale: ' + error.message, 'error');
            } finally {
                // Re-enable button and restore original text
                processSaleBtn.disabled = false;
                processSaleBtn.innerHTML = originalText;
                processSaleBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        });
    }
    
    // Notification close
    document.getElementById('closeNotification').addEventListener('click', () => {
        document.getElementById('notification').classList.add('hidden');
    });
    
    // Discount and tax inputs
    document.getElementById('discountAmount').addEventListener('input', updateTotals);
    document.getElementById('discountType').addEventListener('change', updateTotals);
    document.getElementById('taxAmount').addEventListener('input', updateTotals);
    document.getElementById('taxType').addEventListener('change', updateTotals);
    
    // Product search is handled by initializeFilters() to respect all filters (category, brand, sort)
    // No need for duplicate listener here
    
    // Print last receipt button
    document.getElementById('printLastReceiptBtn').addEventListener('click', () => {
        if (lastSaleId) {
            printReceipt(lastSaleId);
        } else {
            showNotification('No recent sale to print receipt for', 'error');
        }
    });

    // New Customer Form handling
    const newCustomerForm = document.getElementById('newCustomerForm');
    if (newCustomerForm) {
        newCustomerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Check if this is walk-in mode (name field is hidden)
            const nameField = document.getElementById('newCustomerName');
            const isWalkIn = nameField.closest('div').style.display === 'none';
            
            const formData = {
                phone_number: document.getElementById('newCustomerPhone').value.trim(),
                is_walk_in: isWalkIn
            };
            
            // Only add name and email if not walk-in
            if (!isWalkIn) {
                formData.full_name = document.getElementById('newCustomerName').value.trim();
                formData.email = document.getElementById('newCustomerEmail').value.trim() || null;
                
                if (!formData.full_name || !formData.phone_number) {
                    showNotification('Name and phone number are required', 'error');
                    return;
                }
            } else {
                // For walk-in, set default name
                formData.full_name = 'Walk-in Customer';
                
                if (!formData.phone_number) {
                    showNotification('Phone number is required for SMS', 'error');
                    return;
                }
            }
            
            try {
                const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
                const response = await fetch(basePath + '/api/customers', {
                    method: 'POST',
                    headers: {
                        ...getAuthHeaders(),
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin', // Include session cookies for authentication
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success && data.data) {
                    const newCustomer = data.data;
                    
                    // Add to customers array
                    customers.push(newCustomer);
                    
                    // Select the new customer
                    window.selectedCustomer = {
                        id: newCustomer.id,
                        name: newCustomer.full_name,
                        phone: newCustomer.phone_number
                    };
                    
                    // Update button to show selected
                    document.querySelectorAll('.customer-type-btn[data-type="new"]').forEach(btn => {
                        btn.className = btn.className.replace('bg-gray-100 text-gray-700 border-gray-300', 'bg-blue-100 text-blue-700 border-blue-300');
                    });
                    
                    // Close modal
                    closeNewCustomerModal();
                    
                    showNotification('Customer added and selected successfully!', 'success');
                } else {
                    showNotification(data.error || 'Failed to add customer', 'error');
                }
            } catch (error) {
                console.error('Error adding customer:', error);
                showNotification('Error adding customer: ' + error.message, 'error');
            }
        });
    }
    
    // Contact form handling
    const quickContactForm = document.getElementById('quickContactForm');
    if (quickContactForm) {
        quickContactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(quickContactForm);
            const contactData = Object.fromEntries(formData.entries());
            
            try {
                const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
                const response = await fetch(basePath + '/api/customers', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    credentials: 'same-origin', // Include session cookies for authentication
                    body: JSON.stringify(contactData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Contact created successfully!', 'success');
                    quickContactForm.reset();
                    document.getElementById('quickContactSection').style.display = 'none';
                    
                    // Reset customer type selection
                    document.querySelectorAll('.customer-type-btn').forEach(btn => {
                        btn.className = btn.className.replace('bg-blue-100 text-blue-700 border-blue-300', 'bg-gray-100 text-gray-700 border-gray-300');
                    });
                    document.querySelector('[data-type="walkin"]').className = document.querySelector('[data-type="walkin"]').className.replace('bg-gray-100 text-gray-700 border-gray-300', 'bg-blue-100 text-blue-700 border-blue-300');
                    
                    // Reload customers list
                    loadCustomers();
                } else {
                    showNotification(data.error || 'Failed to create contact', 'error');
                }
            } catch (error) {
                console.error('Error creating contact:', error);
                showNotification('Error creating contact', 'error');
            }
        });
    }

    // Cancel contact button
    const cancelContactBtn = document.getElementById('cancelContactBtn');
    if (cancelContactBtn) {
        cancelContactBtn.addEventListener('click', () => {
            document.getElementById('quickContactSection').style.display = 'none';
            quickContactForm.reset();
            
            // Reset to walk-in customer
            document.querySelectorAll('.customer-type-btn').forEach(btn => {
                btn.className = btn.className.replace('bg-blue-100 text-blue-700 border-blue-300', 'bg-gray-100 text-gray-700 border-gray-300');
            });
            document.querySelector('[data-type="walkin"]').className = document.querySelector('[data-type="walkin"]').className.replace('bg-gray-100 text-gray-700 border-gray-300', 'bg-blue-100 text-blue-700 border-blue-300');
        });
    }
}

// Print receipt function
function printReceipt(saleId) {
    // Open receipt in new window for printing
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
    const receiptUrl = `${basePath}/pos/receipt/${saleId}`;
    window.open(receiptUrl, '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
}

// Clear cart function
async function clearCart() {
    if (POS_READ_ONLY) { showNotification('Managers cannot clear cart (view-only).', 'error'); return; }
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart/clear', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await response.json();
        if (data.success) {
            cart = {};
            renderCart();
            updateCartSummary();
            showNotification('Cart cleared successfully', 'success');
        }
    } catch (error) {
        console.error('Error clearing cart:', error);
        showNotification('Error clearing cart', 'error');
    }
}

// Clear cart on page load/refresh
async function clearCartOnLoad() {
    try {
        if (POS_READ_ONLY) { return; }
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart/clear', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        
        const data = await response.json();
        if (data.success) {
            console.log('Cart cleared on page load');
            cart = {};
            updateCartDisplay();
        }
    } catch (error) {
        console.error('Error clearing cart on load:', error);
    }
}

// Global functions for onclick handlers
window.updateCartQuantity = async function(productId, quantity) {
    if (POS_READ_ONLY) { showNotification('Managers cannot change cart (view-only).', 'error'); return; }
    if (quantity < 0) return;
    
    // Check stock availability before updating
    const product = products.find(p => (p.id || p.product_id) == productId);
    if (product && quantity > product.quantity) {
        showNotification(`Cannot add more than ${product.quantity} items. Only ${product.quantity} in stock!`, 'error');
        return;
    }
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart/update', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin', // Include session cookies for authentication
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Cart updated', 'success');
            cart = data.cart || [];
            updateCartDisplay();
            // Re-render products to update stock display and selection state
            renderProducts(products);
        } else {
            showNotification(data.error || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Error updating cart quantity:', error);
        showNotification('Error updating cart quantity', 'error');
    }
};

window.removeFromCart = async function(productId) {
    if (POS_READ_ONLY) { showNotification('Managers cannot change cart (view-only).', 'error'); return; }
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(basePath + '/pos/cart/remove', {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin', // Include session cookies for authentication
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cart = data.cart || [];
            updateCartDisplay();
            // Re-render products to update stock display and selection state
            renderProducts(products);
        } else {
            showNotification(data.error || 'Failed to remove product', 'error');
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
        showNotification('Error removing product from cart', 'error');
    }
};

// Add CSS for product selection and cart scrolling
const style = document.createElement('style');
style.textContent = `
    /* Swapped items - distinct purple/lavender theme for better visibility - Responsive */
    .swapped-item-card {
        background-color: #f3e8ff !important;
        border: 2px solid #a78bfa !important;
        color: #6b21a8 !important;
        min-height: auto !important;
        padding: 0.5rem !important;
    }
    @media (min-width: 640px) {
        .swapped-item-card {
            padding: 0.75rem !important;
        }
    }
    @media (min-width: 768px) {
        .swapped-item-card {
            padding: 1rem !important;
        }
    }
    .swapped-item-card:hover {
        background-color: #e9d5ff !important;
        border-color: #8b5cf6 !important;
    }
    .swapped-item-card h3,
    .swapped-item-card .text-gray-800,
    .swapped-item-card .text-gray-700,
    .swapped-item-card .text-gray-600 {
        color: #6b21a8 !important;
    }
    .swapped-item-card .fas,
    .swapped-item-card i {
        color: #6b21a8 !important;
    }
    
    /* Reduce product card height - responsive */
    .product-card {
        min-height: auto !important;
        padding: 0.5rem !important;
    }
    @media (min-width: 640px) {
        .product-card {
            padding: 0.75rem !important;
        }
    }
    @media (min-width: 1024px) {
        .product-card {
            padding: 1.25rem !important;
        }
    }
    
    /* Stock warnings - only for regular products */
    .stock-warning {
        position: relative;
        display: inline-block;
        margin-top: 4px;
        background: rgba(245, 158, 11, 0.9);
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 6px;
        font-weight: bold;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .stock-danger {
        background: rgba(239, 68, 68, 0.9) !important;
    }
    
    /* Cart scrolling behavior - Hide scrollbar completely */
    .cart-scroll {
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important; /* Internet Explorer 10+ */
        overflow-y: auto !important;
    }
    
    /* Hide scrollbar for cart section wrapper */
    #cartSection > div {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    
    #cartSection > div::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    
    /* Hide scrollbar for cart content */
    #cartSection .bg-white {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    
    #cartSection .bg-white::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    
    /* Hide scrollbar for products grid */
    .products-scroll {
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important; /* Internet Explorer 10+ */
        overflow-y: auto !important;
    }
    
    .products-scroll::-webkit-scrollbar {
        display: none !important; /* WebKit (Chrome, Safari, Edge) */
        width: 0 !important;
        height: 0 !important;
    }
    
    .products-scroll::-webkit-scrollbar-track {
        display: none !important;
    }
    
    .products-scroll::-webkit-scrollbar-thumb {
        display: none !important;
    }
    
    .products-scroll::-webkit-scrollbar-corner {
        display: none !important;
    }
    
    /* Apply scrollbar hiding to all cart containers */
    .cart-scroll,
    .lg\\:sticky.cart-scroll {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    
    .cart-scroll::-webkit-scrollbar {
        display: none !important; /* WebKit (Chrome, Safari, Edge) */
        width: 0 !important;
        height: 0 !important;
    }
    
    .cart-scroll::-webkit-scrollbar-track {
        display: none !important;
    }
    
    .cart-scroll::-webkit-scrollbar-thumb {
        display: none !important;
    }
    
    /* Additional scrollbar hiding for all browsers */
    .cart-scroll {
        scrollbar-gutter: stable;
        scroll-behavior: smooth;
    }
    
    /* Ensure no scrollbar appears in any scenario */
    .cart-scroll::-webkit-scrollbar-corner {
        display: none !important;
    }
    
    /* Specific targeting for sticky cart container */
    .lg\\:sticky.cart-scroll::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    
    .lg\\:sticky.cart-scroll::-webkit-scrollbar-track {
        display: none !important;
    }
    
    .lg\\:sticky.cart-scroll::-webkit-scrollbar-thumb {
        display: none !important;
    }
    
    .lg\\:sticky.cart-scroll::-webkit-scrollbar-corner {
        display: none !important;
    }
    
    /* Sticky cart styling */
    @media (min-width: 1024px) {
        .lg\\:sticky {
            position: sticky;
            top: 1.5rem;
            z-index: 10;
        }
        
        .lg\\:max-h-screen {
            max-height: calc(100vh - 3rem);
        }
        
        .lg\\:overflow-y-auto {
            overflow-y: auto;
        }
    }
    
    /* Contact form styling */
    #quickContactSection {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-left: 4px solid #10b981;
    }
    
    #quickContactSection input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 1px #10b981;
    }
`;
document.head.appendChild(style);

// Load metrics for manager POS view
async function loadPOSManagerMetrics() {
    try {
        // Use dashboard company-metrics endpoint for basic POS KPIs
        const res = await fetch(BASE + '/api/dashboard/company-metrics', { 
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await res.json();
        if (data && data.success) {
            const m = data.metrics || {};
            const el = (id, val) => { const d = document.getElementById(id); if (d) d.textContent = val; };
            // Total products is not in metrics; approximate using products array if loaded or leave 0
            el('pos-today-revenue', '₵' + (parseFloat(m.today_revenue || 0).toFixed(2)));
            el('pos-month-sales', (m.monthly_sales || 0));
            el('pos-month-revenue', '₵' + (parseFloat(m.monthly_revenue || 0).toFixed(2)));
        }
    } catch (e) {
        console.error('Failed loading POS manager metrics', e);
    }
}

// Initialize date controls and export actions for manager
function initPOSReportControls() {
    const fromEl = document.getElementById('posDateFrom');
    const toEl = document.getElementById('posDateTo');
    const todayBtn = document.getElementById('btnToday');
    const weekBtn = document.getElementById('btnThisWeek');
    const applyBtn = document.getElementById('btnApplyRange');
    const exportBtn = document.getElementById('btnExportPdf');

    const fmt = (d) => d.toISOString().slice(0,10);
    const setToday = () => {
        const t = new Date();
        fromEl.value = fmt(t);
        toEl.value = fmt(t);
    };
    const setThisWeek = () => {
        const t = new Date();
        const day = t.getDay(); // 0=Sun
        const diff = (day === 0 ? -6 : 1) - day; // Monday as start
        const start = new Date(t);
        start.setDate(t.getDate() + diff);
        fromEl.value = fmt(start);
        toEl.value = fmt(t);
    };

    // Default to today on first load
    if (fromEl && toEl && !fromEl.value && !toEl.value) setToday();

    if (todayBtn) todayBtn.addEventListener('click', (e) => { e.preventDefault(); setToday(); refreshStatsForRange(); });
    if (weekBtn) weekBtn.addEventListener('click', (e) => { e.preventDefault(); setThisWeek(); refreshStatsForRange(); });
    if (applyBtn) applyBtn.addEventListener('click', (e) => { e.preventDefault(); refreshStatsForRange(); });
    if (exportBtn) exportBtn.addEventListener('click', (e) => { e.preventDefault(); exportPdfForRange(); });
}

async function refreshStatsForRange() {
    const from = document.getElementById('posDateFrom')?.value;
    const to = document.getElementById('posDateTo')?.value || from;
    if (!from) return;
    try {
        const url = new URL(BASE + '/api/pos/stats', window.location.origin);
        if (from) url.searchParams.set('date_from', from);
        if (to) url.searchParams.set('date_to', to);
        const res = await fetch(url.toString(), { 
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await res.json();
        if (data && data.success && (data.data || data.metrics)) {
            const s = data.data || data.metrics;
            const el = (id, val) => { const d = document.getElementById(id); if (d) d.textContent = val; };
            el('pos-today-revenue', '₵' + (parseFloat((s.today_revenue ?? s.total_revenue ?? 0)).toFixed(2)));
            el('pos-today-sales', (s.today_sales ?? s.total_sales ?? 0));
            el('pos-month-sales', (s.total_sales ?? s.monthly_sales ?? 0));
            el('pos-month-revenue', '₵' + (parseFloat((s.total_revenue ?? s.monthly_revenue ?? 0)).toFixed(2)));
        }
    } catch (e) {
        console.error('Failed loading stats for range', e);
    }
}

function exportPdfForRange() {
    const from = document.getElementById('posDateFrom')?.value;
    const to = document.getElementById('posDateTo')?.value || from;
    if (!from) { showNotification('Select a date or range first', 'error'); return; }
    const url = new URL(BASE + '/pos/report/print', window.location.origin);
    url.searchParams.set('date_from', from);
    url.searchParams.set('date_to', to);
    window.open(url.toString(), '_blank');
}

// Load inventory stats
async function loadInventoryStats() {
    try {
        const res = await fetch(BASE + '/api/pos/inventory-stats', { 
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await res.json();
        if (data && data.success && data.data) {
            const d = data.data;
            const el = (id, val) => { const x = document.getElementById(id); if (x) x.textContent = val; };
            el('pos-total-products', d.total_products ?? 0);
            el('pos-in-stock', d.in_stock_products ?? 0);
            el('pos-low-stock', d.low_stock_products ?? 0);
            el('pos-out-of-stock', d.out_of_stock_products ?? 0);
        }
    } catch (e) {
        console.error('Failed loading inventory stats', e);
    }
}

// Load comprehensive audit data
async function loadAuditData() {
    try {
        const res = await fetch(BASE + '/api/pos/audit-data?limit=10', { 
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        const data = await res.json();
        if (data && data.success && data.data) {
            const d = data.data;
            
            // Render Recent Sales
            const salesList = document.getElementById('recent-sales-list');
            if (salesList) {
                if (d.recent_sales && d.recent_sales.length > 0) {
                    salesList.innerHTML = d.recent_sales.map(sale => `
                        <div class="border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Sale #${sale.sale_id || sale.unique_id || sale.id || 'N/A'}</p>
                                    <p class="text-xs text-gray-500">${sale.created_at ? new Date(sale.created_at).toLocaleString() : 'N/A'}</p>
                                    <p class="text-xs text-gray-600 mt-1">${sale.item_count || 0} items • Cashier: ${sale.cashier_name || sale.cashier || 'N/A'}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-green-600">₵${parseFloat(sale.final_amount || 0).toFixed(2)}</p>
                                    <span class="inline-block px-2 py-1 text-xs rounded ${sale.payment_method === 'cash' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">${sale.payment_method || 'cash'}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    salesList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No recent sales</p>';
                }
            }

            // Render Recent Repairs
            const repairsList = document.getElementById('recent-repairs-list');
            if (repairsList) {
                if (d.recent_repairs && d.recent_repairs.length > 0) {
                    repairsList.innerHTML = d.recent_repairs.map(repair => {
                        const deviceInfo = repair.device_info || repair.device_brand || repair.phone_description || 'Device';
                        const deviceModel = repair.device_model || '';
                        const deviceDisplay = deviceModel ? `${deviceInfo} ${deviceModel}`.trim() : deviceInfo;
                        const issue = repair.issue || repair.issue_description || 'No description';
                        const status = repair.status || repair.repair_status || 'pending';
                        const cost = repair.cost || repair.total_cost || repair.repair_cost || 0;
                        
                        return `
                        <div class="border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">${deviceDisplay}</p>
                                    <p class="text-xs text-gray-500">${repair.customer_name || 'Guest'} • ${repair.customer_phone || 'N/A'}</p>
                                    <p class="text-xs text-gray-600 mt-1">${issue}</p>
                                    <p class="text-xs text-gray-500 mt-1">${new Date(repair.created_at).toLocaleString()}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-1 text-xs rounded ${
                                        status.toLowerCase() === 'completed' || status === 'COMPLETED' ? 'bg-green-100 text-green-800' :
                                        status.toLowerCase() === 'in_progress' || status === 'IN_PROGRESS' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-gray-100 text-gray-800'
                                    }">${status}</span>
                                    ${cost ? `<p class="text-sm font-bold text-purple-600 mt-1">₵${parseFloat(cost).toFixed(2)}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    }).join('');
                } else {
                    repairsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No recent repairs</p>';
                }
            }

            // Render Recent Swaps
            const swapsList = document.getElementById('recent-swaps-list');
            if (swapsList) {
                if (d.recent_swaps && d.recent_swaps.length > 0) {
                    swapsList.innerHTML = d.recent_swaps.map(swap => `
                        <div class="border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">${swap.product_name || 'Product Swap'}</p>
                                    <p class="text-xs text-gray-500">${swap.customer_name || 'Guest'}</p>
                                    <p class="text-xs text-gray-600 mt-1">${new Date(swap.created_at).toLocaleString()}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-1 text-xs rounded ${
                                        (swap.status === 'completed' || swap.swap_status === 'COMPLETED') ? 'bg-green-100 text-green-800' :
                                        (swap.status === 'pending' || swap.swap_status === 'PENDING') ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-gray-100 text-gray-800'
                                    }">${swap.status || swap.swap_status || 'pending'}</span>
                                    ${swap.total_value ? `<p class="text-sm font-bold text-orange-600 mt-1">₵${parseFloat(swap.total_value).toFixed(2)}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    swapsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No recent swaps</p>';
                }
            }

            // Render Top Products
            const topProductsList = document.getElementById('top-products-list');
            if (topProductsList) {
                if (d.top_products && d.top_products.length > 0) {
                    topProductsList.innerHTML = d.top_products.map((product, index) => `
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                            <div class="flex items-center flex-1">
                                <span class="text-lg font-bold text-gray-400 mr-3 w-6">${index + 1}</span>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">${product.product_name}</p>
                                    <p class="text-xs text-gray-500">${product.total_sold || 0} units sold</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-green-600">₵${parseFloat(product.total_revenue || 0).toFixed(2)}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    topProductsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No sales data available</p>';
                }
            }

            // Update Activity Summary
            const fs = d.financial_summary || {};
            const el = (id, val) => { const x = document.getElementById(id); if (x) x.textContent = val; };
            el('audit-today-sales', fs.today_sales_count || 0);
            el('audit-today-repairs', fs.today_repairs_count || 0);
            el('audit-today-swaps', fs.today_swaps_count || 0);
            el('audit-month-revenue', '₵' + (parseFloat(fs.month_revenue || 0).toFixed(2)));
            
            // Update payment stats if available
            if (fs.payment_stats) {
                const paymentCard = document.getElementById('audit-payment-status-card');
                if (paymentCard) {
                    paymentCard.style.display = 'block';
                    el('audit-fully-paid', fs.payment_stats.fully_paid || 0);
                    el('audit-partial', fs.payment_stats.partial || 0);
                    el('audit-unpaid', fs.payment_stats.unpaid || 0);
                }
            }
        }
    } catch (e) {
        console.error('Failed loading audit data', e);
    }
}

// Receipt modal functions
function showReceiptModal(saleData) {
    const modal = document.getElementById('receiptModal');
    const content = document.getElementById('receiptContent');
    
    // Format date similar to receipt page
    const receiptDate = new Date(saleData.created_at || new Date()).toLocaleString('en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    // Build payment info HTML if available
    let paymentInfoHtml = '';
    if (saleData.payment_info || (saleData.total_paid !== undefined && saleData.remaining !== undefined)) {
        const totalPaid = parseFloat(saleData.total_paid || saleData.payment_info?.total_paid || 0);
        const remaining = parseFloat(saleData.remaining || saleData.payment_info?.remaining || 0);
        const status = (saleData.payment_status || saleData.payment_info?.payment_status || 'PAID').toUpperCase();
        
        paymentInfoHtml = `
            <div style="border-top: 1px dashed #333; padding-top: 10px; margin-top: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; margin-bottom: 5px;">PAYMENT INFORMATION:</div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Total Amount:</span>
                    <span>₵${parseFloat(saleData.total_amount || saleData.final_amount || 0).toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Total Paid:</span>
                    <span style="color: #10b981; font-weight: bold;">₵${totalPaid.toFixed(2)}</span>
                </div>
                ${remaining > 0 ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Remaining:</span>
                    <span style="color: #f59e0b; font-weight: bold;">₵${remaining.toFixed(2)}</span>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    const receiptHtml = `
        <div style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; max-width: 300px; margin: 0 auto; border: 1px solid #ccc; padding: 15px; background: white;">
            <div style="text-align: center; border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 15px;">
                <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">${saleData.company_name || 'SellApp'}</div>
                <div style="font-size: 10px; color: #666;">
                    ${saleData.company_address || ''}<br>
                    Tel: ${saleData.company_phone || ''}
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Receipt #:</span>
                    <span>${saleData.sale_id || 'N/A'}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Date:</span>
                    <span>${receiptDate}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Cashier:</span>
                    <span>${localStorage.getItem('username') || 'Staff'}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Customer:</span>
                    <span>${saleData.customer_name || 'Walk-in Customer'}</span>
                </div>
            </div>
            
            <div style="border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 15px;">
                <div style="font-weight: bold; margin-bottom: 8px;">ITEMS:</div>
                ${saleData.items ? saleData.items.map(item => `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <div style="flex: 1;">
                            <div>${item.name || item.item_description || 'Item'}</div>
                            <div style="font-size: 10px; color: #666; margin-left: 10px;">
                                ${item.quantity} × ₵${parseFloat(item.unit_price || 0).toFixed(2)}
                            </div>
                        </div>
                        <div>₵${parseFloat(item.total_price || 0).toFixed(2)}</div>
                    </div>
                `).join('') : ''}
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Subtotal:</span>
                    <span>₵${parseFloat(saleData.subtotal || saleData.total_amount || 0).toFixed(2)}</span>
                </div>
                ${parseFloat(saleData.discount || 0) > 0 ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Discount:</span>
                    <span>-₵${parseFloat(saleData.discount || 0).toFixed(2)}</span>
                </div>
                ` : ''}
                ${parseFloat(saleData.tax || 0) > 0 ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Tax:</span>
                    <span>₵${parseFloat(saleData.tax || 0).toFixed(2)}</span>
                </div>
                ` : ''}
                <div style="font-weight: bold; border-top: 1px solid #333; padding-top: 5px; margin-top: 5px; display: flex; justify-content: space-between;">
                    <span>TOTAL:</span>
                    <span>₵${parseFloat(saleData.total_amount || saleData.final_amount || 0).toFixed(2)}</span>
                </div>
            </div>
            
            ${paymentInfoHtml}
            
            <div style="text-align: center; font-size: 10px; color: #666; border-top: 1px dashed #333; padding-top: 10px;">
                <div>Payment Method: ${(saleData.payment_method || 'CASH').toUpperCase()}</div>
                <div>Payment Status: ${(saleData.payment_status || 'PAID').toUpperCase()}</div>
                <div>Thank you for your business!</div>
                <div>Visit us again soon</div>
            </div>
        </div>
    `;
    
    content.innerHTML = receiptHtml;
    modal.classList.remove('hidden');
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.classList.add('hidden');
    // Restore body scrolling when modal is closed
    document.body.style.overflow = '';
}

function ensureModalInBody(modal) {
    if (modal && modal.parentNode !== document.body) {
        console.log(`Moving modal #${modal.id} to document.body to avoid clipping issues`);
        document.body.appendChild(modal);
    }
}

// Swap Modal Functions
function openSwapModal(productId, productName, productPrice) {
    console.log('Opening swap modal:', { productId, productName, productPrice });
    
    // Check if modal exists
    const modal = document.getElementById('swapModal');
    if (!modal) {
        console.error('Swap modal not found in DOM');
        showNotification('Swap modal not found. Please refresh the page.', 'error');
        return;
    }
    ensureModalInBody(modal);
    
    // Hide any previous errors
    hideSwapModalError();
    
    // Set product details - check if elements exist
    const swapProductId = document.getElementById('swapProductId');
    const swapProductName = document.getElementById('swapProductName');
    const swapProductPrice = document.getElementById('swapProductPrice');
    
    if (swapProductId) swapProductId.value = productId;
    if (swapProductName) swapProductName.value = productName;
    if (swapProductPrice) swapProductPrice.value = productPrice;
    
    // Display product info - check if elements exist
    const displayProductName = document.getElementById('displayProductName');
    const displayProductPrice = document.getElementById('displayProductPrice');
    const swapCompanyPrice = document.getElementById('swapCompanyPrice');
    
    if (displayProductName) displayProductName.textContent = productName;
    if (displayProductPrice) displayProductPrice.textContent = '₵' + parseFloat(productPrice).toFixed(2);
    if (swapCompanyPrice) swapCompanyPrice.textContent = '₵' + parseFloat(productPrice).toFixed(2);
    
    // Reset form
    const swapForm = document.getElementById('swapForm');
    if (swapForm) {
        swapForm.reset();
        if (swapProductId) swapProductId.value = productId;
        if (swapProductName) swapProductName.value = productName;
        if (swapProductPrice) swapProductPrice.value = productPrice;
    }
    
    // Reset brand dropdown and specs
    const brandSelect = document.getElementById('swapCustomerBrandId');
    const brandNameInput = document.getElementById('swapCustomerBrand');
    if (brandSelect) {
        brandSelect.value = '';
        // Reset loaded flag to allow reloading if needed
        brandSelect.dataset.loaded = 'false';
    }
    if (brandNameInput) {
        brandNameInput.value = '';
    }
    
    const swapCustomerSpecsContainer = document.getElementById('swapCustomerSpecsContainer');
    const swapCustomerDynamicSpecs = document.getElementById('swapCustomerDynamicSpecs');
    if (swapCustomerSpecsContainer) swapCustomerSpecsContainer.classList.add('hidden');
    if (swapCustomerDynamicSpecs) swapCustomerDynamicSpecs.innerHTML = '';
    
    // Ensure brands are loaded (only if not already populated)
    if (brandSelect && (brandSelect.options.length <= 1 || brandSelect.dataset.loaded !== 'true')) {
        loadSwapBrands();
    }
    
    // Update balance calculation
    if (typeof updateSwapBalance === 'function') {
        updateSwapBalance();
    }
    
    // Populate customer dropdown
    if (typeof populateSwapCustomerDropdown === 'function') {
        populateSwapCustomerDropdown();
    }
    
    // Show modal
    console.log('Removing hidden class from modal');
    modal.classList.remove('hidden');
    
    // Force display style to ensure visibility (use block since modal is a fixed container)
    modal.style.display = 'block';
    
    // Verify modal is visible
    const isHidden = modal.classList.contains('hidden');
    const computedStyle = window.getComputedStyle(modal);
    console.log('Modal visibility check:', {
        hasHiddenClass: isHidden,
        display: computedStyle.display,
        visibility: computedStyle.visibility,
        zIndex: computedStyle.zIndex,
        opacity: computedStyle.opacity
    });
    
    if (isHidden) {
        console.error('Modal still has hidden class after removal attempt');
        // Force remove hidden class again
        modal.classList.remove('hidden');
        modal.style.display = 'block';
    }
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Scroll the modal content to top (the white box with overflow-y-auto)
    const modalContent = modal.querySelector('.overflow-y-auto');
    if (modalContent) {
        modalContent.scrollTop = 0;
    }
    
    console.log('Swap modal should now be visible');
}

// New Customer Modal Functions
function openNewCustomerModal(isWalkIn = false) {
    const modal = document.getElementById('newCustomerModal');
    const nameField = document.getElementById('newCustomerName');
    const emailField = document.getElementById('newCustomerEmail');
    const nameDiv = nameField.closest('div');
    const emailDiv = emailField.closest('div');
    const modalTitle = document.querySelector('#newCustomerModal h3');
    
    modal.classList.remove('hidden');
    
    if (isWalkIn) {
        // For walk-in customers, hide name and email fields, only show phone
        nameDiv.style.display = 'none';
        emailDiv.style.display = 'none';
        nameField.removeAttribute('required');
        emailField.removeAttribute('required');
        document.getElementById('newCustomerPhone').focus();
        if (modalTitle) modalTitle.textContent = 'Add Walk-in Customer (SMS Only)';
    } else {
        // For regular customers, show all fields
        nameDiv.style.display = 'block';
        emailDiv.style.display = 'block';
        nameField.setAttribute('required', 'required');
        document.getElementById('newCustomerName').focus();
        if (modalTitle) modalTitle.textContent = 'Add New Customer';
    }
}

function closeNewCustomerModal() {
    const modal = document.getElementById('newCustomerModal');
    const form = document.getElementById('newCustomerForm');
    const nameDiv = document.getElementById('newCustomerName').closest('div');
    const emailDiv = document.getElementById('newCustomerEmail').closest('div');
    
    modal.classList.add('hidden');
    form.reset();
    
    // Reset form visibility
    nameDiv.style.display = 'block';
    emailDiv.style.display = 'block';
    document.getElementById('newCustomerName').setAttribute('required', 'required');
}

// Render existing customer list (show all customers)
function renderExistingCustomerList(customerList) {
    const dropdownList = document.getElementById('existingCustomerDropdownList');
    if (!dropdownList) return;
    
    if (!customerList || customerList.length === 0) {
        dropdownList.innerHTML = '<div class="p-3 text-gray-500 text-center text-xs">No customers found</div>';
        return;
    }
    
    dropdownList.innerHTML = customerList.map(customer => `
        <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
             data-id="${customer.id}" data-name="${customer.full_name}" data-phone="${customer.phone_number || ''}">
            <div class="font-medium text-gray-800 text-sm">${customer.full_name}</div>
            <div class="text-xs text-gray-600">${customer.phone_number || ''}</div>
            ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
        </div>
    `).join('');
    
    // Add click handlers
    dropdownList.querySelectorAll('div[data-id]').forEach(item => {
        item.addEventListener('click', function() {
            const customerId = this.getAttribute('data-id');
            const customerName = this.getAttribute('data-name');
            const customerPhone = this.getAttribute('data-phone');
            
            window.selectedCustomer = {
                id: parseInt(customerId),
                name: customerName,
                phone: customerPhone
            };
            
            // Update button to show selected
            document.querySelectorAll('.customer-type-btn[data-type="existing"]').forEach(btn => {
                btn.className = btn.className.replace('bg-gray-100 text-gray-700 border-gray-300', 'bg-blue-100 text-blue-700 border-blue-300');
            });
            
            // Hide dropdown
            dropdownList.classList.add('hidden');
            
            showNotification(`Customer selected: ${customerName}`, 'success');
        });
    });
}

// Filter existing customers
function filterExistingCustomers(query) {
    const dropdownList = document.getElementById('existingCustomerDropdownList');
    const queryLower = query.toLowerCase();
    
    const filtered = customers.filter(c => 
        (c.full_name && c.full_name.toLowerCase().includes(queryLower)) ||
        (c.phone_number && c.phone_number.includes(query)) ||
        (c.email && c.email.toLowerCase().includes(queryLower))
    );
    
    if (filtered.length === 0) {
        dropdownList.innerHTML = '<div class="p-3 text-gray-500 text-center text-xs">No customers found</div>';
    } else {
        dropdownList.innerHTML = filtered.map(customer => `
            <div class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                 onclick="selectExistingCustomer(${customer.id}, '${(customer.full_name || '').replace(/'/g, "\\'")}', '${(customer.phone_number || '').replace(/'/g, "\\'")}')">
                <div class="font-medium text-gray-800 text-xs">${customer.full_name || 'Unknown'}</div>
                <div class="text-xs text-gray-600">${customer.phone_number || ''}</div>
                ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
            </div>
        `).join('');
    }
    
    dropdownList.classList.remove('hidden');
}

// Select existing customer
function selectExistingCustomer(customerId, customerName, customerPhone) {
    window.selectedCustomer = {
        id: customerId,
        name: customerName,
        phone: customerPhone
    };
    
    document.getElementById('existingCustomerSearch').value = `${customerName} (${customerPhone})`;
    document.getElementById('existingCustomerDropdownList').classList.add('hidden');
    
    // Update button to show selected
    document.querySelectorAll('.customer-type-btn[data-type="existing"]').forEach(btn => {
        btn.className = btn.className.replace('bg-gray-100 text-gray-700 border-gray-300', 'bg-blue-100 text-blue-700 border-blue-300');
    });
    
    showNotification('Customer selected: ' + customerName, 'success');
}

function closeSwapModal() {
    const modal = document.getElementById('swapModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
    hideSwapModalError();
    // Restore body scrolling when modal is closed
    document.body.style.overflow = '';
}

function showSwapModalError(message) {
    const errorDiv = document.getElementById('swapModalError');
    const errorText = document.getElementById('swapModalErrorText');
    if (errorDiv && errorText) {
        errorText.textContent = message;
        errorDiv.classList.remove('hidden');
        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function hideSwapModalError() {
    const errorDiv = document.getElementById('swapModalError');
    if (errorDiv) {
        errorDiv.classList.add('hidden');
    }
}

function updateSwapBalance() {
    const companyPrice = parseFloat(document.getElementById('swapProductPrice').value) || 0;
    const customerValue = parseFloat(document.getElementById('swapEstimatedValue').value) || 0;
    const topup = parseFloat(document.getElementById('swapTopup').value) || 0;
    
    const totalCustomerValue = customerValue + topup;
    const balance = totalCustomerValue - companyPrice;
    
    // Update display values
    document.getElementById('swapCustomerValue').textContent = '₵' + customerValue.toFixed(2);
    document.getElementById('swapTopupValue').textContent = '₵' + topup.toFixed(2);
    
    // Update balance with color coding
    // Balance = (Customer Device Value + Cash Top-up) - Company Product Price
    // If balance is 0: Perfect match
    // If balance > 0: Customer is giving more (overpayment)
    // If balance < 0: Customer needs to pay more
    const balanceElement = document.getElementById('swapBalance');
    if (Math.abs(balance) < 0.01) {
        // Balanced (within 0.01 rounding tolerance)
        balanceElement.textContent = '₵0.00 ✓ Balanced';
        balanceElement.className = 'text-lg text-green-600 font-semibold';
    } else if (balance > 0) {
        // Customer is giving more than product value
        balanceElement.textContent = '₵' + balance.toFixed(2) + ' (Overpayment - adjust values)';
        balanceElement.className = 'text-lg text-orange-600';
    } else {
        // Customer is giving less than product value
        balanceElement.textContent = '₵' + Math.abs(balance).toFixed(2) + ' (Increase customer value or top-up)';
        balanceElement.className = 'text-lg text-red-600';
    }
}

// Add event listeners for swap modal
document.addEventListener('DOMContentLoaded', function() {
    // Ensure modals live directly under body to prevent stacking/overflow issues
    ['receiptModal', 'swapModal', 'newCustomerModal'].forEach(modalId => {
        const modalEl = document.getElementById(modalId);
        ensureModalInBody(modalEl);
    });
    
    // Swap button click handlers - use event delegation to handle clicks on button or child elements
    // Use a flag to prevent multiple rapid clicks
    let swapModalOpening = false;
    
    document.addEventListener('click', function(e) {
        // Check if the clicked element or its parent has the open-swap-btn class
        const swapButton = e.target.closest('.open-swap-btn');
        if (swapButton) {
            // Prevent multiple rapid clicks
            if (swapModalOpening) {
                console.log('Swap modal already opening, ignoring click');
                return;
            }
            
            e.preventDefault();
            e.stopPropagation(); // Prevent product selection
            const productId = swapButton.getAttribute('data-product-id');
            const productName = swapButton.getAttribute('data-product-name');
            const productPrice = swapButton.getAttribute('data-product-price');
            
            // Debug logging
            console.log('Swap button clicked:', { productId, productName, productPrice });
            
            if (productId && productName && productPrice) {
                swapModalOpening = true;
                openSwapModal(productId, productName, productPrice);
                // Reset flag after a short delay
                setTimeout(() => {
                    swapModalOpening = false;
                }, 500);
            } else {
                console.error('Missing swap button data:', { productId, productName, productPrice });
            }
        }
    });
    
    // Swap form calculation listeners
    const swapEstimatedValue = document.getElementById('swapEstimatedValue');
    const swapTopup = document.getElementById('swapTopup');
    
    if (swapEstimatedValue) {
        swapEstimatedValue.addEventListener('input', updateSwapBalance);
    }
    if (swapTopup) {
        swapTopup.addEventListener('input', updateSwapBalance);
    }
    
    // Swap form submission
    const swapForm = document.getElementById('swapForm');
    if (swapForm) {
        swapForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (POS_READ_ONLY) { showNotification('Managers cannot process swaps (view-only).', 'error'); return; }
            processSwap();
        });
    }
    
    // Initialize POS customer dropdown (skip for manager)
    if (!POS_READ_ONLY) {
        setupPOSCustomerDropdown();
    }
});

// Load brands for swap modal (phone category ID = 1)
function loadSwapBrands() {
    const brandSelect = document.getElementById('swapCustomerBrandId');
    const brandNameInput = document.getElementById('swapCustomerBrand');
    
    if (!brandSelect) return;
    
    // Track if brands have been loaded to prevent duplicate loading
    if (brandSelect.dataset.loaded === 'true') {
        return; // Already loaded, skip
    }
    
    // Helper function to deduplicate brands by both ID and name
    function deduplicateBrands(brands) {
        const seenById = new Map();
        const seenByName = new Set();
        return brands.filter(brand => {
            if (!brand || !brand.id || !brand.name) return false;
            
            // Normalize brand name (trim and lowercase for comparison)
            const normalizedName = brand.name.trim().toLowerCase();
            
            // Check for duplicate ID
            if (seenById.has(brand.id)) return false;
            
            // Check for duplicate name (case-insensitive)
            if (seenByName.has(normalizedName)) return false;
            
            // Add to seen sets
            seenById.set(brand.id, true);
            seenByName.add(normalizedName);
            return true;
        });
    }
    
    // Helper function to populate select
    function populateBrandSelect(brands) {
        const uniqueBrands = deduplicateBrands(brands);
        if (uniqueBrands.length > 0) {
            // Clear existing options first
            brandSelect.innerHTML = '<option value="">Select Brand</option>';
            
            // Sort brands alphabetically by name
            uniqueBrands.sort((a, b) => {
                const nameA = (a.name || '').trim().toLowerCase();
                const nameB = (b.name || '').trim().toLowerCase();
                return nameA.localeCompare(nameB);
            });
            
            uniqueBrands.forEach(brand => {
                // Check if option already exists (prevent duplicates)
                const existingOption = Array.from(brandSelect.options).find(
                    opt => opt.value == brand.id || opt.textContent.trim().toLowerCase() === brand.name.trim().toLowerCase()
                );
                
                if (!existingOption) {
                    const opt = document.createElement('option');
                    opt.value = brand.id;
                    opt.textContent = brand.name.trim(); // Trim whitespace
                    opt.dataset.brandName = brand.name.trim();
                    brandSelect.appendChild(opt);
                }
            });
            
            // Mark as loaded
            brandSelect.dataset.loaded = 'true';
            return true;
        }
        return false;
    }
    
    // Load brands for phone category
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
    fetch(basePath + '/api/brands/by-category/1', {
        credentials: 'same-origin' // Include session cookies for authentication
    })
        .then(response => response.json())
        .then(result => {
            // Handle both formats: direct array or {success: true, data: [...]}
            let brands = Array.isArray(result) ? result : (result.data || []);
            if (!populateBrandSelect(brands)) {
                // Fallback: try alternative API endpoint
                fetch(basePath + '/api/products/brands/1', {
                    credentials: 'same-origin' // Include session cookies for authentication
                })
                    .then(response => response.json())
                    .then(data => {
                        let brands = data.success && data.data ? data.data : (Array.isArray(data) ? data : []);
                        populateBrandSelect(brands);
                    })
                    .catch(error => console.error('Error loading brands:', error));
            }
        })
        .catch(error => {
            console.error('Error loading brands:', error);
            // Fallback: try alternative API endpoint
                        fetch(basePath + '/api/products/brands/1', {
                    credentials: 'same-origin' // Include session cookies for authentication
                })
                .then(response => response.json())
                .then(data => {
                    let brands = data.success && data.data ? data.data : (Array.isArray(data) ? data : []);
                    populateBrandSelect(brands);
                })
                .catch(err => console.error('Error loading brands:', err));
        });
}

// Handle brand selection - load specs (separate function to avoid duplicate listeners)
document.addEventListener('DOMContentLoaded', function() {
    const brandSelect = document.getElementById('swapCustomerBrandId');
    const brandNameInput = document.getElementById('swapCustomerBrand');
    
    if (brandSelect) {
        // Remove any existing listeners by cloning and replacing
        const newSelect = brandSelect.cloneNode(true);
        brandSelect.parentNode.replaceChild(newSelect, brandSelect);
        
        // Add single event listener
        newSelect.addEventListener('change', function() {
            const brandId = this.value;
            const selectedOption = this.options[this.selectedIndex];
            const brandName = selectedOption ? selectedOption.dataset.brandName : '';
            
            // Store brand name for form submission
            const hiddenBrandInput = document.getElementById('swapCustomerBrand');
            if (hiddenBrandInput) {
                hiddenBrandInput.value = brandName || '';
            }
            
            // Load brand specs
            if (brandId) {
                loadSwapCustomerSpecs(brandId);
            } else {
                document.getElementById('swapCustomerSpecsContainer').classList.add('hidden');
                document.getElementById('swapCustomerDynamicSpecs').innerHTML = '';
            }
        });
    }
});

// Handle brand selection for dynamic specs
document.addEventListener('DOMContentLoaded', function() {
    // Load brands on page load
    loadSwapBrands();
    
    // Close modals when clicking outside (on backdrop)
    const receiptModal = document.getElementById('receiptModal');
    const swapModal = document.getElementById('swapModal');
    
    if (receiptModal) {
        receiptModal.addEventListener('click', function(e) {
            // Close if clicking directly on the modal backdrop (not on the content)
            if (e.target === receiptModal) {
                closeReceiptModal();
            }
        });
    }
    
    if (swapModal) {
        swapModal.addEventListener('click', function(e) {
            // Close if clicking directly on the modal backdrop (not on the content)
            if (e.target === swapModal) {
                closeSwapModal();
            }
        });
    }
    
    // Close modals with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (receiptModal && !receiptModal.classList.contains('hidden')) {
                closeReceiptModal();
            }
            if (swapModal && !swapModal.classList.contains('hidden')) {
                closeSwapModal();
            }
        }
    });
});

function loadSwapCustomerSpecs(brandId) {
    if (!brandId) {
        document.getElementById('swapCustomerSpecsContainer').classList.add('hidden');
        return;
    }
    
    // Fetch brand specs from API using brand ID
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
    fetch(basePath + '/api/brands/specs/' + encodeURIComponent(brandId), {
        credentials: 'same-origin' // Include session cookies for authentication
    })
        .then(response => response.json())
        .then(data => {
            const specsContainer = document.getElementById('swapCustomerSpecsContainer');
            const dynamicSpecs = document.getElementById('swapCustomerDynamicSpecs');
            
            if (data && data.length > 0) {
                specsContainer.classList.remove('hidden');
                dynamicSpecs.innerHTML = '';
                
                data.forEach(spec => {
                    if (spec.name === 'model' || spec.name === 'imei') return; // Skip these as they're already in the form
                    
                    const div = document.createElement('div');
                    const label = document.createElement('label');
                    label.className = 'block text-sm font-medium text-gray-700 mb-1';
                    label.textContent = spec.label + (spec.required ? ' *' : '');
                    label.setAttribute('for', 'swapSpec_' + spec.name);
                    
                    let input;
                    if (spec.type === 'select' && spec.options) {
                        input = document.createElement('select');
                        input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = 'Select ' + spec.label;
                        input.appendChild(defaultOption);
                        
                        spec.options.forEach(option => {
                            const opt = document.createElement('option');
                            opt.value = option;
                            opt.textContent = option;
                            input.appendChild(opt);
                        });
                    } else {
                        input = document.createElement('input');
                        input.type = spec.type || 'text';
                        input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
                        input.placeholder = spec.placeholder || '';
                    }
                    
                    input.id = 'swapSpec_' + spec.name;
                    input.name = 'customer_spec_' + spec.name;
                    if (spec.required) input.required = true;
                    
                    div.appendChild(label);
                    div.appendChild(input);
                    dynamicSpecs.appendChild(div);
                });
            } else {
                // Use default specs
                showDefaultSwapSpecs();
            }
        })
        .catch(error => {
            console.error('Error loading specs:', error);
            showDefaultSwapSpecs();
        });
}

function showDefaultSwapSpecs() {
    const specsContainer = document.getElementById('swapCustomerSpecsContainer');
    const dynamicSpecs = document.getElementById('swapCustomerDynamicSpecs');
    
    specsContainer.classList.remove('hidden');
    dynamicSpecs.innerHTML = '';
    
    const defaultSpecs = [
        { name: 'storage', label: 'Storage', type: 'select', options: ['32GB', '64GB', '128GB', '256GB', '512GB', '1TB'] },
        { name: 'ram', label: 'RAM', type: 'select', options: ['2GB', '3GB', '4GB', '6GB', '8GB', '12GB', '16GB'] },
        { name: 'color', label: 'Color', type: 'text', placeholder: 'e.g., Black, Blue, Gold' }
    ];
    
    defaultSpecs.forEach(spec => {
        const div = document.createElement('div');
        const label = document.createElement('label');
        label.className = 'block text-sm font-medium text-gray-700 mb-1';
        label.textContent = spec.label;
        label.setAttribute('for', 'swapSpec_' + spec.name);
        
        let input;
        if (spec.type === 'select' && spec.options) {
            input = document.createElement('select');
            input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select ' + spec.label;
            input.appendChild(defaultOption);
            
            spec.options.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option;
                opt.textContent = option;
                input.appendChild(opt);
            });
        } else {
            input = document.createElement('input');
            input.type = spec.type || 'text';
            input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
            input.placeholder = spec.placeholder || '';
        }
        
        input.id = 'swapSpec_' + spec.name;
        input.name = 'customer_spec_' + spec.name;
        
        div.appendChild(label);
        div.appendChild(input);
        dynamicSpecs.appendChild(div);
    });
}

function processSwap() {
    const submitBtn = document.getElementById('swapSubmitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    submitBtn.disabled = true;
    
    // Validate customer selection before proceeding
    const customerSelect = document.getElementById('swapCustomerSelect');
    const selectedCustomerId = customerSelect ? customerSelect.value : '';
    
    if (!selectedCustomerId) {
        showSwapModalError('Please select a customer before processing the swap');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Get form data
    const formData = new FormData(document.getElementById('swapForm'));
    const data = Object.fromEntries(formData.entries());
    
    // Collect specs into JSON
    const specs = {};
    const specInputs = document.querySelectorAll('[name^="customer_spec_"]');
    specInputs.forEach(input => {
        const specName = input.name.replace('customer_spec_', '');
        if (input.value && input.value.trim() !== '') {
            specs[specName] = input.value.trim();
        }
    });
    
    // Add specs to notes as JSON if any specs were collected
    if (Object.keys(specs).length > 0) {
        data.customer_specs = JSON.stringify(specs);
    }
    
    // Get customer information from hidden select
    if (selectedCustomerId) {
        // Find the selected customer in the customers array
        const selectedCustomer = customers.find(c => c.id == selectedCustomerId);
        if (selectedCustomer) {
            data.customer_id = selectedCustomer.id;
            data.customer_name = selectedCustomer.full_name;
            data.customer_phone = selectedCustomer.phone_number;
        } else {
            showSwapModalError('Selected customer not found. Please select again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            return;
        }
    }
    
    // Convert numeric fields
    data.customer_estimated_value = parseFloat(data.customer_estimated_value) || 0;
    data.customer_topup = parseFloat(data.customer_topup) || 0;
    
    // Send swap request
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
    fetch(basePath + '/dashboard/pos/swap/checkout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin', // Include session cookies for authentication
        body: JSON.stringify(data)
    })
    .then(async response => {
        // Get response text first
        const responseText = await response.text().catch(() => '');
        
        // Check if response is ok
        if (!response.ok) {
            let errorMessage = `Server error (${response.status})`;
            let errorDetails = null;
            
            // Try to parse as JSON
            if (responseText) {
                try {
                    const errorJson = JSON.parse(responseText);
                    errorMessage = errorJson.message || errorJson.error || errorMessage;
                    errorDetails = errorJson;
                    console.error('Swap error details:', errorJson);
                } catch (e) {
                    // Not JSON, use raw text
                    if (responseText) {
                        errorMessage = responseText.substring(0, 300);
                        console.error('Swap error (non-JSON):', responseText);
                    }
                }
            }
            
            // Create error with details
            const error = new Error(errorMessage);
            error.response = errorDetails || { raw: responseText };
            throw error;
        }
        
        // Response is OK, try to parse JSON
        if (!responseText || responseText.trim() === '') {
            throw new Error('Server returned empty response. Please try again.');
        }
        
        try {
            return JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e, 'Response text:', responseText.substring(0, 200));
            throw new Error('Server returned invalid JSON: ' + responseText.substring(0, 100));
        }
    })
    .then(result => {
        if (result.success) {
            // Show success message
            showNotification('Swap processed successfully! Transaction: ' + (result.transaction_code || ''), 'success');
            
            // Close modal
            closeSwapModal();
            
            // Redirect to receipt
            if (result.sale_id) {
                setTimeout(() => {
                    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
                    window.location.href = basePath + '/pos/receipt/' + result.sale_id;
                }, 1500);
            }
        } else {
            const errorMsg = result.message || result.error || 'Unknown error occurred';
            showSwapModalError('Swap failed: ' + errorMsg);
            console.error('Swap failed:', result);
        }
    })
    .catch(error => {
        console.error('Swap error:', error);
        console.error('Swap error details:', error.response);
        
        // Show detailed error in modal
        let errorMsg = error.message || 'Network error. Please check your connection and try again.';
        
        if (error.response) {
            if (error.response.error) {
                errorMsg = error.response.error;
            } else if (error.response.message) {
                errorMsg = error.response.message;
            } else if (error.response.raw) {
                errorMsg = 'Server error: ' + error.response.raw.substring(0, 200);
            }
        }
        
        showSwapModalError(errorMsg);
    })
    .finally(() => {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}




// Setup customer dropdown for existing POS cart
function setupPOSCustomerDropdown() {
    console.log('Setting up POS customer dropdown...');
    const customerSelect = document.getElementById('customerSelect');
    if (!customerSelect) {
        console.log('Customer select element not found');
        return;
    }
    
    // Check if already converted
    if (customerSelect.tagName !== 'SELECT') {
        console.log('Customer select already converted or not a select element');
        return;
    }
    
    console.log('Converting customer select to searchable dropdown...');
    
    // Convert existing select to searchable dropdown
    const searchContainer = document.createElement('div');
    searchContainer.className = 'relative';
    
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search customers...';
    searchInput.className = 'shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline';
    searchInput.id = 'posCustomerSearch';
    
    const dropdown = document.createElement('div');
    dropdown.className = 'absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
    dropdown.id = 'posCustomerDropdown';
    
    searchContainer.appendChild(searchInput);
    searchContainer.appendChild(dropdown);
    
    // Replace the select with search container
    customerSelect.parentNode.replaceChild(searchContainer, customerSelect);
    
    let searchTimeout;
    
    // Search customers as user types
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => searchPOSCustomers(query), 300);
        } else {
            dropdown.classList.add('hidden');
        }
    });
    
    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// Search customers for POS
async function searchPOSCustomers(query) {
    console.log('Searching POS customers with query:', query);
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        const response = await fetch(`${basePath}/pos/swap/customers?q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: getAuthHeaders(),
            credentials: 'same-origin' // Include session cookies for authentication
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayPOSCustomerResults(result.data);
        } else {
            console.error('Error searching customers:', result.message || result.error);
            if (result.error === 'Authentication required') {
                showNotification('Please login to search customers', 'error');
            }
        }
    } catch (error) {
        console.error('Error searching customers:', error);
    }
}

// Display customer results for POS
function displayPOSCustomerResults(customers) {
    const dropdown = document.getElementById('posCustomerDropdown');
    
    if (customers.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
    } else {
        dropdown.innerHTML = customers.map(customer => `
            <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                 onclick="selectPOSCustomer(${customer.id}, '${customer.full_name}', '${customer.phone_number}')">
                <div class="font-medium text-gray-800">${customer.full_name}</div>
                <div class="text-sm text-gray-600">${customer.phone_number}</div>
                ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
            </div>
        `).join('');
    }
    
    dropdown.classList.remove('hidden');
}

// Select customer for POS
function selectPOSCustomer(customerId, customerName, customerPhone) {
    // Update the search input
    const searchInput = document.getElementById('posCustomerSearch');
    searchInput.value = customerName;
    
    // Set customer data for form submission
    window.selectedCustomer = {
        id: customerId,
        name: customerName,
        phone: customerPhone
    };
    
    // Hide dropdown
    document.getElementById('posCustomerDropdown').classList.add('hidden');
}

function printReceipt() {
    const receiptContent = document.getElementById('receiptContent');
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .receipt { max-width: 300px; margin: 0 auto; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .item { display: flex; justify-content: space-between; margin: 5px 0; }
                    .total { border-top: 1px solid #000; padding-top: 10px; margin-top: 10px; }
                    .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="receipt">
                    ${receiptContent.innerHTML}
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<style>
/* Custom scrollbar for modals */
#receiptModal > div::-webkit-scrollbar,
#swapModal > div::-webkit-scrollbar {
    width: 8px;
}

#receiptModal > div::-webkit-scrollbar-track,
#swapModal > div::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#receiptModal > div::-webkit-scrollbar-thumb,
#swapModal > div::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#receiptModal > div::-webkit-scrollbar-thumb:hover,
#swapModal > div::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* For Firefox */
#receiptModal > div,
#swapModal > div {
    scrollbar-width: thin;
    scrollbar-color: #888 #f1f1f1;
}
</style>

<!-- Receipt Modal -->
<div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-hidden">
    <div class="h-full w-full flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-semibold text-gray-800">Receipt</h3>
                    <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="receiptContent" class="space-y-3">
                    <!-- Receipt content will be populated here -->
                </div>
                
                <div class="flex gap-2 mt-4">
                    <button onclick="printReceipt()" class="flex-1 bg-blue-600 text-white py-1.5 px-3 rounded text-sm hover:bg-blue-700 transition">
                        <i class="fas fa-print mr-1.5"></i>Print
                    </button>
                    <button onclick="closeReceiptModal()" class="flex-1 bg-gray-500 text-white py-1.5 px-3 rounded text-sm hover:bg-gray-600 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
</div>

<!-- New Customer Modal -->
<div id="newCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-hidden">
    <div class="h-full w-full flex items-center justify-center p-4">
        <div class="my-auto">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Add New Customer</h3>
                    <button onclick="closeNewCustomerModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="newCustomerForm" class="space-y-4">
                    <div>
                        <label for="newCustomerName" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" id="newCustomerName" name="full_name" required
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter customer name">
                    </div>
                    <div>
                        <label for="newCustomerPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                        <input type="tel" id="newCustomerPhone" name="phone_number" required
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter phone number">
                    </div>
                    <div>
                        <label for="newCustomerEmail" class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                        <input type="email" id="newCustomerEmail" name="email"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter email address">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors">
                            <i class="fas fa-save mr-1"></i>Save & Select
                        </button>
                        <button type="button" onclick="closeNewCustomerModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Swap Modal -->
<div id="swapModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-hidden" style="z-index: 9999;">
    <div class="h-full w-full flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto" style="scroll-behavior: smooth;">
            <div class="p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Process Swap</h3>
                    <button onclick="closeSwapModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Error Display Area -->
                <div id="swapModalError" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-600 mt-0.5 mr-3"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-red-800 mb-1">Error</h4>
                            <p id="swapModalErrorText" class="text-sm text-red-700"></p>
                        </div>
                        <button type="button" onclick="hideSwapModalError()" class="text-red-400 hover:text-red-600 ml-2">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <form id="swapForm">
                    <input type="hidden" id="swapProductId" name="company_product_id">
                    <input type="hidden" id="swapProductName" name="product_name">
                    <input type="hidden" id="swapProductPrice" name="product_price">
                    
                    <!-- Company Product Info -->
                    <div class="bg-blue-50 rounded-lg p-3 mb-4">
                        <h4 class="font-semibold text-blue-800 mb-1.5 text-sm">Company Product</h4>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-gray-600">Product:</span>
                                <span id="displayProductName" class="font-medium"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Price:</span>
                                <span id="displayProductPrice" class="font-medium text-green-600"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-800 mb-3 text-sm">Customer Information</h4>
                        
                        <!-- Customer Selection (single searchable dropdown) -->
                        <div class="mb-3">
                            <label for="swapCustomerCombo" class="block text-xs font-medium text-gray-700 mb-1.5">Select Customer *</label>
                            <div class="relative">
                                <input type="text" id="swapCustomerCombo" placeholder="Search customers..."
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" autocomplete="off">
                                <div id="swapCustomerDropdown" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto"></div>
                            </div>
                            <!-- Hidden select for form submission -->
                            <select id="swapCustomerSelect" name="customer_id" class="hidden">
                                <option value="">Choose a customer...</option>
                            </select>
                        </div>
                        
                        <!-- Selected Customer Display -->
                        <div id="swapSelectedCustomerInfo" class="hidden mb-3 p-2.5 bg-blue-50 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-blue-800" id="swapSelectedCustomerName">Customer Name</div>
                                    <div class="text-sm text-blue-600" id="swapSelectedCustomerPhone">Phone Number</div>
                                </div>
                                <button type="button" id="swapClearCustomerBtn" 
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer's Device -->
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-800 mb-3 text-sm">Customer's Device</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="swapCustomerBrandId" class="block text-xs font-medium text-gray-700 mb-1">Brand <span class="text-red-500">*</span></label>
                                <select id="swapCustomerBrandId" name="customer_brand_id" required
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Brand</option>
                                    <!-- Brands will be loaded dynamically -->
                                </select>
                                <!-- Hidden input to store brand name for form submission -->
                                <input type="hidden" id="swapCustomerBrand" name="customer_brand">
                            </div>
                            <div>
                                <label for="swapCustomerModel" class="block text-xs font-medium text-gray-700 mb-1">Model</label>
                                <input type="text" id="swapCustomerModel" name="customer_model" 
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="e.g. iPhone 12, Galaxy S21">
                            </div>
                            <div>
                                <label for="swapCustomerCondition" class="block text-xs font-medium text-gray-700 mb-1">Condition</label>
                                <select id="swapCustomerCondition" name="customer_condition" 
                                        class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="used">Used</option>
                                    <option value="new">New</option>
                                    <option value="faulty">Faulty</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label for="swapCustomerImei" class="block text-xs font-medium text-gray-700 mb-1">IMEI (Optional)</label>
                                <input type="text" id="swapCustomerImei" name="customer_imei" 
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Enter device IMEI number">
                            </div>
                        </div>
                        
                        <!-- Dynamic Spec Fields Container -->
                        <div id="swapCustomerSpecsContainer" class="mt-3 hidden">
                            <h5 class="text-xs font-semibold text-gray-700 mb-2">Device Specifications</h5>
                            <div id="swapCustomerDynamicSpecs" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <!-- Dynamic fields will be inserted here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Swap Calculation -->
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-800 mb-3 text-sm">Swap Calculation</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="swapEstimatedValue" class="block text-xs font-medium text-gray-700 mb-1">Estimated Value (₵)</label>
                                <input type="number" id="swapEstimatedValue" name="customer_estimated_value" step="0.01" min="0" 
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="0.00">
                            </div>
                            <div>
                                <label for="swapTopup" class="block text-xs font-medium text-gray-700 mb-1">Cash Top-up (₵)</label>
                                <input type="number" id="swapTopup" name="customer_topup" step="0.01" min="0" 
                                       class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="0.00">
                            </div>
                            <div class="flex items-end">
                                <div class="w-full p-2.5 bg-gray-50 rounded-md border border-gray-200">
                                    <div class="text-xs text-gray-600 mb-0.5">Balance</div>
                                    <div id="swapBalance" class="text-base font-bold text-gray-900">₵0.00</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Balance Display -->
                        <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Company Product Price:</span>
                                <span id="swapCompanyPrice" class="font-medium">₵0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Customer Device Value:</span>
                                <span id="swapCustomerValue" class="font-medium">₵0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Cash Top-up:</span>
                                <span id="swapTopupValue" class="font-medium">₵0.00</span>
                            </div>
                            <hr class="my-1.5">
                            <div class="flex justify-between items-center font-bold text-sm">
                                <span>Balance:</span>
                                <span id="swapBalance" class="text-base"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-4">
                        <label for="swapNotes" class="block text-xs font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea id="swapNotes" name="notes" rows="2" 
                                  class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Any additional notes about the swap..."></textarea>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeSwapModal()" 
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="swapSubmitBtn" 
                                class="px-4 py-1.5 text-sm bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                            <i class="fas fa-exchange-alt mr-1.5"></i>Process Swap
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>