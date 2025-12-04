<?php
// Repair list view for managers and technicians
?>

<div class="w-full px-2 sm:px-4 md:px-6 lg:px-8 py-3 sm:py-4 md:py-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
        <div class="flex items-center flex-wrap gap-3">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Repairs</h1>
            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full whitespace-nowrap" id="total-count">
                <?= $GLOBALS['total_repairs'] ?? count($repairs) ?> Total
            </span>
        </div>
        <?php
        // Only show action buttons for technicians, not managers
        $userRole = $GLOBALS['user_role'] ?? '';
        if ($userRole === 'technician' || $userRole === 'system_admin'):
        ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/reports" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Reports
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Repair
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search Section -->
    <div class="mb-4 sm:mb-6 bg-white rounded-lg shadow-sm border p-3 sm:p-4">
        <div class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1">
                <label for="search-input" class="block text-sm font-medium text-gray-700 mb-2">Search Repairs</label>
                <div class="relative">
                    <input type="text" 
                           id="search-input" 
                           placeholder="Search by customer name, contact, tracking code, issue, or device..."
                           value="<?= htmlspecialchars($GLOBALS['search'] ?? '') ?>"
                           class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <div id="search-loading" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                        <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <button type="button" 
                    id="clear-search" 
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors whitespace-nowrap <?= empty($GLOBALS['search'] ?? '') ? 'hidden' : '' ?>">
                Clear
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="mb-4 sm:mb-6 bg-white rounded-lg shadow-sm border p-3 sm:p-4 md:p-6">
        <!-- Date Range Filter -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
            <form method="GET" action="<?= BASE_URL_PATH ?>/dashboard/repairs" class="flex flex-wrap gap-3 items-end" id="date-filter-form">
                <!-- Preserve status and search filters if set -->
                <?php if (isset($_GET['status'])): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']) ?>">
                <?php endif; ?>
                <?php if (!empty($GLOBALS['search'] ?? '')): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($GLOBALS['search']) ?>">
                <?php endif; ?>
                
                <div class="flex-1 min-w-[150px]">
                    <label for="date_from" class="block text-xs text-gray-600 mb-1">From Date</label>
                    <input type="date" 
                           id="date_from" 
                           name="date_from" 
                           value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex-1 min-w-[150px]">
                    <label for="date_to" class="block text-xs text-gray-600 mb-1">To Date</label>
                    <input type="date" 
                           id="date_to" 
                           name="date_to" 
                           value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap">
                        <i class="fas fa-filter mr-2"></i>Apply Filter
                    </button>
                    <?php if (isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
                        <a href="<?= BASE_URL_PATH ?>/dashboard/repairs<?php 
                            $params = [];
                            if (isset($_GET['status'])) $params[] = 'status=' . urlencode($_GET['status']);
                            if (!empty($GLOBALS['search'] ?? '')) $params[] = 'search=' . urlencode($GLOBALS['search']);
                            echo !empty($params) ? '?' . implode('&', $params) : '';
                        ?>" 
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors whitespace-nowrap">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <div class="flex flex-wrap gap-2">
                <?php
                // Build base URL with date and search filters preserved
                $baseUrl = BASE_URL_PATH . '/dashboard/repairs';
                $queryParams = [];
                if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                    $queryParams[] = 'date_from=' . urlencode($_GET['date_from']);
                }
                if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                    $queryParams[] = 'date_to=' . urlencode($_GET['date_to']);
                }
                if (!empty($GLOBALS['search'] ?? '')) {
                    $queryParams[] = 'search=' . urlencode($GLOBALS['search']);
                }
                $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';
                ?>
                <a href="<?= $baseUrl . $queryString ?>" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= !isset($_GET['status']) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    All
                </a>
                <a href="<?= $baseUrl . ($queryString ? $queryString . '&' : '?') ?>status=pending" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Pending
                </a>
                <a href="<?= $baseUrl . ($queryString ? $queryString . '&' : '?') ?>status=in_progress" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'in_progress' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    In Progress
                </a>
                <a href="<?= $baseUrl . ($queryString ? $queryString . '&' : '?') ?>status=completed" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Completed
                </a>
                <a href="<?= $baseUrl . ($queryString ? $queryString . '&' : '?') ?>status=delivered" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'delivered' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Delivered
                </a>
                <a href="<?= $baseUrl . ($queryString ? $queryString . '&' : '?') ?>status=failed" 
                   class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Failed
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        // Calculate statistics from all repairs (not just filtered)
        $totalRepairs = count($repairs);
        $pendingCount = count(array_filter($repairs, fn($r) => strtolower($r['status'] ?? '') === 'pending'));
        $inProgressCount = count(array_filter($repairs, fn($r) => strtolower($r['status'] ?? '') === 'in_progress'));
        $completedCount = count(array_filter($repairs, fn($r) => strtolower($r['status'] ?? '') === 'completed'));
        
        // Get stats for managers (from controller)
        $userRole = $GLOBALS['user_role'] ?? 'technician';
        $totalWorkmanshipFee = $GLOBALS['total_workmanship_fee'] ?? 0;
        $totalTechnicianSales = $GLOBALS['total_technician_sales'] ?? 0;
        $technicianSalesCount = $GLOBALS['technician_sales_count'] ?? 0;
        ?>
        
        <!-- Total Repairs Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Repairs</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalRepairs ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Pending Repairs Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Pending</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $pendingCount ?></p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- In Progress Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">In Progress</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $inProgressCount ?></p>
                </div>
                <div class="p-3 bg-orange-100 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Completed Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Completed</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $completedCount ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'manager' || $userRole === 'system_admin'): ?>
    <!-- Manager Stats Cards -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Total Workmanship Fee Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Workmanship Fee</p>
                    <p class="text-2xl font-bold text-gray-900">₵<?= number_format($totalWorkmanshipFee, 2) ?></p>
                    <p class="text-xs text-gray-500 mt-1">All repairs</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Total Technician Sales Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Technician Sales</p>
                    <p class="text-2xl font-bold text-gray-900">₵<?= number_format($totalTechnicianSales, 2) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Parts & accessories</p>
                </div>
                <div class="p-3 bg-indigo-100 rounded-lg">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Number of Sales by Technician Card -->
        <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Technician Sales Count</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $technicianSalesCount ?></p>
                    <p class="text-xs text-gray-500 mt-1">Repairs with sales</p>
                </div>
                <div class="p-3 bg-teal-100 rounded-lg">
                    <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Repairs Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden" id="repairs-table-container">
        <?php if (empty($repairs)): ?>
            <div class="p-6 sm:p-8 text-center">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No repairs found</h3>
                <p class="text-gray-500 mb-4">Get started by creating your first repair record.</p>
                <?php
                // Only show create button for technicians, not managers
                $userRole = $GLOBALS['user_role'] ?? '';
                if ($userRole === 'technician' || $userRole === 'system_admin'):
                ?>
                <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    Create Repair
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Mobile Card View (hidden on larger screens) -->
            <div class="block sm:hidden space-y-4">
                <?php foreach ($repairs as $repair): ?>
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    <?= !empty($repair['customer_name']) ? htmlspecialchars($repair['customer_name']) : '<span class="text-gray-400 italic">Unknown Customer</span>' ?>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= !empty($repair['customer_contact']) ? htmlspecialchars($repair['customer_contact']) : '<span class="text-gray-400 italic">No contact</span>' ?>
                                </p>
                            </div>
                            <?php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-orange-100 text-orange-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'delivered' => 'bg-blue-100 text-blue-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                'failed' => 'bg-red-100 text-red-800'
                            ];
                            $statusColor = $statusColors[$repair['status'] ?? 'pending'] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColor ?> ml-2">
                                <?= ucfirst(str_replace('_', ' ', $repair['status'] ?? 'pending')) ?>
                            </span>
                        </div>
                        
                        <div class="space-y-2 text-xs text-gray-600 mb-3">
                            <div>
                                <span class="font-medium">Device:</span> 
                                <?php if ($repair['product_name']): ?>
                                    <?= htmlspecialchars($repair['product_name']) ?>
                                <?php else: ?>
                                    <span class="text-gray-500">Customer's device</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="font-medium">Issue:</span> 
                                <?= !empty($repair['issue_description']) ? htmlspecialchars($repair['issue_description']) : '<span class="text-gray-400 italic">No description</span>' ?>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                <div>
                                    <span class="font-medium text-gray-700">Cost:</span>
                                    <?php
                                    $displayCost = floatval($repair['total_cost'] ?? 0);
                                    if ($displayCost == 0) {
                                        $repairCost = floatval($repair['repair_cost'] ?? 0);
                                        $partsCost = floatval($repair['parts_cost'] ?? 0);
                                        $accessoryCost = floatval($repair['accessory_cost'] ?? 0);
                                        $displayCost = $repairCost + $partsCost + $accessoryCost;
                                    }
                                    ?>
                                    <span class="text-sm font-semibold text-gray-900">₵<?= number_format($displayCost, 2) ?></span>
                                </div>
                                <div class="text-gray-500">
                                    <?= date('M j, Y', strtotime($repair['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="flex-1 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium text-center hover:bg-blue-100 transition-colors">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <?php 
                            $userRole = $GLOBALS['user_role'] ?? '';
                            if ($userRole === 'technician'): 
                                if ($repair['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="flex-1" onsubmit="return confirm('Start this repair?');">
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="w-full px-3 py-2 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition-colors">
                                            <i class="fas fa-play-circle mr-1"></i> Start
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled' && $repair['status'] !== 'failed'): ?>
                                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>/edit" class="flex-1 px-3 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium text-center hover:bg-indigo-100 transition-colors">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php 
                            if ($userRole === 'manager' || $userRole === 'system_admin'): 
                            ?>
                                <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/delete/<?= $repair['id'] ?>" class="flex-1" onsubmit="return confirm('Delete this repair?');">
                                    <button type="submit" class="w-full px-3 py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Desktop Table View (hidden on mobile) -->
            <div class="hidden sm:block overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                    Device
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                    Issue
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                    Payment
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cost
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                    Date
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($repairs as $repair): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= !empty($repair['customer_name']) ? htmlspecialchars($repair['customer_name']) : '<span class="text-gray-400 italic">Unknown Customer</span>' ?>
                                            </div>
                                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                                <?= !empty($repair['customer_contact']) ? htmlspecialchars($repair['customer_contact']) : '<span class="text-gray-400 italic">No contact</span>' ?>
                                            </div>
                                            <!-- Mobile: Show device and issue info -->
                                            <div class="sm:hidden mt-2 space-y-1">
                                                <div class="text-xs text-gray-600">
                                                    <span class="font-medium">Device:</span> 
                                                    <?php if ($repair['product_name']): ?>
                                                        <?= htmlspecialchars($repair['product_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">Customer's device</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-600 truncate">
                                                    <span class="font-medium">Issue:</span> <?= !empty($repair['issue_description']) ? htmlspecialchars($repair['issue_description']) : '<span class="text-gray-400 italic">No description</span>' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                        <div class="text-sm text-gray-900">
                                            <?php if ($repair['product_name']): ?>
                                                <?= htmlspecialchars($repair['product_name']) ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">Customer's device</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 hidden md:table-cell">
                                        <div class="text-sm text-gray-900 max-w-xs truncate">
                                            <?= !empty($repair['issue_description']) ? htmlspecialchars($repair['issue_description']) : '<span class="text-gray-400 italic">No description</span>' ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'in_progress' => 'bg-orange-100 text-orange-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'delivered' => 'bg-blue-100 text-blue-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'failed' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusColor = $statusColors[$repair['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColor ?>">
                                            <?= ucfirst(str_replace('_', ' ', $repair['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <?php
                                        // All repairs are considered paid at booking time (payment received when service is booked)
                                        // Normalize payment_status (handle both 'paid' and 'PAID', 'unpaid' and 'UNPAID')
                                        $paymentStatus = strtolower(trim($repair['payment_status'] ?? 'paid'));
                                        
                                        // If payment_status is empty, null, or 'unpaid', default to 'paid' (all bookings are paid)
                                        if (empty($paymentStatus) || $paymentStatus === 'null' || $paymentStatus === 'unpaid') {
                                            $paymentStatus = 'paid';
                                        }
                                        
                                        $paymentColors = [
                                            'unpaid' => 'bg-red-100 text-red-800',
                                            'partial' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800'
                                        ];
                                        $paymentColor = $paymentColors[$paymentStatus] ?? 'bg-green-100 text-green-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $paymentColor ?>">
                                            <?= ucfirst($paymentStatus) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        <?php
                                        // Calculate total_cost if it's 0 or missing
                                        $displayCost = floatval($repair['total_cost'] ?? 0);
                                        if ($displayCost == 0) {
                                            $repairCost = floatval($repair['repair_cost'] ?? 0);
                                            $partsCost = floatval($repair['parts_cost'] ?? 0);
                                            $accessoryCost = floatval($repair['accessory_cost'] ?? 0);
                                            $displayCost = $repairCost + $partsCost + $accessoryCost;
                                        }
                                        ?>
                                        ₵<?= number_format($displayCost, 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                        <?= date('M j, Y', strtotime($repair['created_at'])) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex flex-col sm:flex-row gap-1 sm:gap-2 items-start sm:items-center">
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="text-blue-600 hover:text-blue-900 flex items-center gap-1" title="View Details">
                                                <i class="fas fa-eye text-sm"></i>
                                                <span class="hidden sm:inline">View</span>
                                            </a>
                                            <?php 
                                            // Only show start/edit buttons for technicians, not managers
                                            $userRole = $GLOBALS['user_role'] ?? '';
                                            if ($userRole === 'technician'): 
                                            ?>
                                                <?php if ($repair['status'] === 'pending'): ?>
                                                    <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline" onsubmit="return confirm('Start this repair? Status will change to In Progress.');">
                                                        <input type="hidden" name="status" value="in_progress">
                                                        <button type="submit" class="text-green-600 hover:text-green-900 flex items-center gap-1" title="Start Repair">
                                                            <i class="fas fa-play-circle text-sm"></i>
                                                            <span class="hidden sm:inline">Start</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled' && $repair['status'] !== 'failed'): ?>
                                                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>/edit" class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1" title="Edit Repair">
                                                        <i class="fas fa-edit text-sm"></i>
                                                        <span class="hidden sm:inline">Edit</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php 
                                            // Only show delete button for managers and system admins
                                            $userRole = $GLOBALS['user_role'] ?? '';
                                            if ($userRole === 'manager' || $userRole === 'system_admin'): 
                                            ?>
                                                <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/delete/<?= $repair['id'] ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this repair? This action cannot be undone.');">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 flex items-center gap-1" title="Delete Repair">
                                                        <i class="fas fa-trash text-sm"></i>
                                                        <span class="hidden sm:inline">Delete</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php
    $currentPage = $GLOBALS['current_page'] ?? 1;
    $totalPages = $GLOBALS['total_pages'] ?? 1;
    $totalRepairs = $GLOBALS['total_repairs'] ?? 0;
    $limit = $GLOBALS['limit'] ?? 20;
    
    if ($totalPages > 1):
    ?>
    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-700">
            Showing <span class="font-medium"><?= (($currentPage - 1) * $limit) + 1 ?></span> to 
            <span class="font-medium"><?= min($currentPage * $limit, $totalRepairs) ?></span> of 
            <span class="font-medium"><?= $totalRepairs ?></span> results
        </div>
        <div class="flex items-center gap-2">
            <?php
            // Build pagination URL with all filters preserved
            $paginationParams = [];
            if (isset($_GET['status'])) $paginationParams[] = 'status=' . urlencode($_GET['status']);
            if (isset($_GET['date_from'])) $paginationParams[] = 'date_from=' . urlencode($_GET['date_from']);
            if (isset($_GET['date_to'])) $paginationParams[] = 'date_to=' . urlencode($_GET['date_to']);
            if (!empty($GLOBALS['search'] ?? '')) $paginationParams[] = 'search=' . urlencode($GLOBALS['search']);
            $paginationBase = BASE_URL_PATH . '/dashboard/repairs' . (!empty($paginationParams) ? '?' . implode('&', $paginationParams) : '');
            $paginationSeparator = !empty($paginationParams) ? '&' : '?';
            ?>
            
            <!-- Previous Button -->
            <?php if ($currentPage > 1): ?>
                <a href="<?= $paginationBase . $paginationSeparator ?>page=<?= $currentPage - 1 ?>" 
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Previous
                </a>
            <?php else: ?>
                <span class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                    Previous
                </span>
            <?php endif; ?>
            
            <!-- Page Numbers -->
            <div class="flex items-center gap-1">
                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                if ($startPage > 1): ?>
                    <a href="<?= $paginationBase . $paginationSeparator ?>page=1" 
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        1
                    </a>
                    <?php if ($startPage > 2): ?>
                        <span class="px-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $currentPage): ?>
                        <span class="px-3 py-2 border border-blue-500 rounded-lg text-sm font-medium text-white bg-blue-600">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $paginationBase . $paginationSeparator ?>page=<?= $i ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="px-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="<?= $paginationBase . $paginationSeparator ?>page=<?= $totalPages ?>" 
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Next Button -->
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $paginationBase . $paginationSeparator ?>page=<?= $currentPage + 1 ?>" 
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Next
                </a>
            <?php else: ?>
                <span class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                    Next
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const searchInput = document.getElementById('search-input');
    const clearSearchBtn = document.getElementById('clear-search');
    const searchLoading = document.getElementById('search-loading');
    const repairsTableContainer = document.getElementById('repairs-table-container');
    const totalCountSpan = document.getElementById('total-count');
    
    let searchTimeout;
    let isSearching = false;
    
    // Get current URL parameters
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            status: params.get('status') || '',
            date_from: params.get('date_from') || '',
            date_to: params.get('date_to') || '',
            page: params.get('page') || '1'
        };
    }
    
    // Build search URL
    function buildSearchUrl(search, page = 1) {
        const params = getUrlParams();
        const queryParams = [];
        
        if (search) queryParams.push('search=' + encodeURIComponent(search));
        if (params.status) queryParams.push('status=' + encodeURIComponent(params.status));
        if (params.date_from) queryParams.push('date_from=' + encodeURIComponent(params.date_from));
        if (params.date_to) queryParams.push('date_to=' + encodeURIComponent(params.date_to));
        if (page > 1) queryParams.push('page=' + page);
        
        return '<?= BASE_URL_PATH ?>/api/repairs/search' + (queryParams.length ? '?' + queryParams.join('&') : '');
    }
    
    // Perform live search
    function performSearch(search, page = 1) {
        if (isSearching) return;
        
        isSearching = true;
        searchLoading.classList.remove('hidden');
        
        fetch(buildSearchUrl(search, page))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.repairs, data.pagination);
                } else {
                    console.error('Search failed:', data.error);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            })
            .finally(() => {
                isSearching = false;
                searchLoading.classList.add('hidden');
            });
    }
    
    // Update table with search results
    function updateTable(repairs, pagination) {
        // Update total count
        if (totalCountSpan) {
            totalCountSpan.textContent = pagination.total_items + ' Total';
        }
        
        // Build table HTML
        let tableHtml = '';
        
        if (repairs.length === 0) {
            tableHtml = `
                <div class="p-6 sm:p-8 text-center">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No repairs found</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filters.</p>
                </div>
            `;
        } else {
            // Mobile Card View
            let mobileCardsHtml = '<div class="block sm:hidden space-y-4">';
            
            // Desktop Table View
            let desktopTableHtml = `
                <div class="hidden sm:block overflow-x-auto -mx-4 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Device</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Issue</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Payment</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Date</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            repairs.forEach(repair => {
                const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'in_progress': 'bg-orange-100 text-orange-800',
                    'completed': 'bg-green-100 text-green-800',
                    'delivered': 'bg-blue-100 text-blue-800',
                    'cancelled': 'bg-red-100 text-red-800',
                    'failed': 'bg-red-100 text-red-800'
                };
                const statusColor = statusColors[repair.status] || 'bg-gray-100 text-gray-800';
                const statusText = repair.status ? repair.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown';
                
                const paymentStatus = (repair.payment_status || 'paid').toLowerCase();
                const paymentColors = {
                    'unpaid': 'bg-red-100 text-red-800',
                    'partial': 'bg-yellow-100 text-yellow-800',
                    'paid': 'bg-green-100 text-green-800'
                };
                const paymentColor = paymentColors[paymentStatus] || 'bg-green-100 text-green-800';
                
                const displayCost = parseFloat(repair.total_cost || 0);
                const customerName = repair.customer_name || 'Unknown Customer';
                const customerContact = repair.customer_contact || 'No contact';
                const productName = repair.product_name || '';
                const issueDescription = repair.issue_description || 'No description';
                const createdDate = repair.created_at ? new Date(repair.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                
                // Mobile card HTML
                mobileCardsHtml += `
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-gray-900">${escapeHtml(customerName)}</h3>
                                <p class="text-xs text-gray-500 mt-1">${escapeHtml(customerContact)}</p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${statusColor} ml-2">${statusText}</span>
                        </div>
                        <div class="space-y-2 text-xs text-gray-600 mb-3">
                            <div><span class="font-medium">Device:</span> ${productName ? escapeHtml(productName) : '<span class="text-gray-500">Customer\'s device</span>'}</div>
                            <div><span class="font-medium">Issue:</span> ${escapeHtml(issueDescription)}</div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                <div><span class="font-medium text-gray-700">Cost:</span> <span class="text-sm font-semibold text-gray-900">₵${displayCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</span></div>
                                <div class="text-gray-500">${createdDate}</div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/${repair.id}" class="flex-1 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium text-center hover:bg-blue-100 transition-colors">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                        </div>
                    </div>
                `;
                
                // Desktop table row HTML
                desktopTableHtml += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 sm:px-6 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">${escapeHtml(customerName)}</div>
                                <div class="text-xs sm:text-sm text-gray-500 mt-1">${escapeHtml(customerContact)}</div>
                            </div>
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                            <div class="text-sm text-gray-900">${productName ? escapeHtml(productName) : '<span class="text-gray-500">Customer\'s device</span>'}</div>
                        </td>
                        <td class="px-3 sm:px-6 py-4 hidden md:table-cell">
                            <div class="text-sm text-gray-900 max-w-xs truncate">${escapeHtml(issueDescription)}</div>
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${statusColor}">${statusText}</span>
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${paymentColor}">${paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1)}</span>
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            ₵${displayCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                            ${createdDate}
                        </td>
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex flex-col sm:flex-row gap-1 sm:gap-2 items-start sm:items-center">
                                <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/${repair.id}" class="text-blue-600 hover:text-blue-900 flex items-center gap-1" title="View Details">
                                    <i class="fas fa-eye text-sm"></i>
                                    <span class="hidden sm:inline">View</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            mobileCardsHtml += '</div>';
            desktopTableHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            tableHtml = mobileCardsHtml + desktopTableHtml;
        }
        
        repairsTableContainer.innerHTML = tableHtml;
        
        // Update pagination if needed
        updatePagination(pagination);
    }
    
    // Update pagination controls
    function updatePagination(pagination) {
        // This would require more complex DOM manipulation
        // For now, we'll just reload the page with the new page number
        // A full implementation would update the pagination HTML dynamically
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Search input handler with debounce
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const search = e.target.value.trim();
            
            // Show/hide clear button
            if (clearSearchBtn) {
                if (search) {
                    clearSearchBtn.classList.remove('hidden');
                } else {
                    clearSearchBtn.classList.add('hidden');
                }
            }
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Debounce search (wait 500ms after user stops typing)
            searchTimeout = setTimeout(() => {
                if (search.length >= 2 || search.length === 0) {
                    // Update URL without reload
                    const params = getUrlParams();
                    const queryParams = [];
                    if (search) queryParams.push('search=' + encodeURIComponent(search));
                    if (params.status) queryParams.push('status=' + encodeURIComponent(params.status));
                    if (params.date_from) queryParams.push('date_from=' + encodeURIComponent(params.date_from));
                    if (params.date_to) queryParams.push('date_to=' + encodeURIComponent(params.date_to));
                    
                    const newUrl = '<?= BASE_URL_PATH ?>/dashboard/repairs' + (queryParams.length ? '?' + queryParams.join('&') : '');
                    window.history.pushState({}, '', newUrl);
                    
                    // Perform search
                    performSearch(search, 1);
                }
            }, 500);
        });
    }
    
    // Clear search handler
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearchBtn.classList.add('hidden');
            
            // Update URL and reload
            const params = getUrlParams();
            const queryParams = [];
            if (params.status) queryParams.push('status=' + encodeURIComponent(params.status));
            if (params.date_from) queryParams.push('date_from=' + encodeURIComponent(params.date_from));
            if (params.date_to) queryParams.push('date_to=' + encodeURIComponent(params.date_to));
            
            const newUrl = '<?= BASE_URL_PATH ?>/dashboard/repairs' + (queryParams.length ? '?' + queryParams.join('&') : '');
            window.location.href = newUrl;
        });
    }
})();
</script>
