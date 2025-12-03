<?php
/**
 * Manager Analytics / Audit Trail View
 * Modern analytics hub for managers with full operational visibility
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
$companyId = $user['company_id'] ?? null;
$userRole = $user['role'] ?? 'manager';
?>

<div class="p-3 sm:p-4 pb-4 max-w-full" data-server-rendered="true">
    <!-- Header -->
    <div class="mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800">Audit Trail - Manager Analytics</h2>
        <p class="text-sm sm:text-base text-gray-600 mt-1">Complete operational visibility, filtering, traceability, and export capabilities</p>
    </div>

    <!-- Flash Messages -->
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

    <!-- Filters and Export Controls -->
    <div class="bg-white rounded-lg shadow p-3 sm:p-4 mb-4 sm:mb-6">
        <div class="flex flex-col gap-4">
            <!-- Filter Inputs Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">From Date</label>
                    <input type="date" id="filterDateFrom" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">To Date</label>
                    <input type="date" id="filterDateTo" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Staff Member</label>
                    <select id="filterStaff" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Staff</option>
                    </select>
                </div>
            </div>
            <!-- Quick Filters and Actions Row -->
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button id="btnToday" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm transition-colors" data-range="today">Today</button>
                    <button id="btnThisWeek" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm transition-colors" data-range="this_week">This Week</button>
                    <button id="btnThisMonth" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm active transition-colors" data-range="this_month">This Month</button>
                    <button id="btnThisYear" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm transition-colors" data-range="this_year">This Year</button>
                    <div class="flex items-center gap-2">
                        <label for="monthSelector" class="text-xs sm:text-sm text-gray-600 font-medium whitespace-nowrap">Select Month:</label>
                        <input type="month" id="monthSelector" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="">
                    </div>
                </div>
                <button id="btnApplyFilters" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-4 py-2 text-sm font-medium transition-colors whitespace-nowrap">
                    <i class="fas fa-filter mr-1"></i> Apply Filters
                </button>
            </div>
        </div>
        
        <!-- Export Buttons -->
        <div class="mt-4 pt-4 border-t border-gray-200">
            <div class="flex flex-wrap gap-2">
                <div class="flex gap-1 items-center">
                    <button onclick="exportData('sales', 'csv')" class="bg-teal-100 hover:bg-teal-200 text-teal-700 rounded-lg px-3 py-2 text-sm transition-colors border border-teal-200" title="Export Sales as CSV">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button onclick="exportData('sales', 'xlsx')" class="bg-sky-100 hover:bg-sky-200 text-sky-700 rounded-lg px-3 py-2 text-sm transition-colors border border-sky-200" title="Export Sales as Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button onclick="exportData('sales', 'pdf')" class="bg-pink-100 hover:bg-pink-200 text-pink-700 rounded-lg px-3 py-2 text-sm transition-colors border border-pink-200" title="Export Sales as PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <span class="px-2 py-2 text-sm text-gray-600 font-medium">Sales</span>
                </div>
                <div class="flex gap-1 items-center">
                    <button onclick="exportData('repairs', 'csv')" class="bg-teal-100 hover:bg-teal-200 text-teal-700 rounded-lg px-3 py-2 text-sm transition-colors border border-teal-200" title="Export Repairs as CSV">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button onclick="exportData('repairs', 'xlsx')" class="bg-sky-100 hover:bg-sky-200 text-sky-700 rounded-lg px-3 py-2 text-sm transition-colors border border-sky-200" title="Export Repairs as Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button onclick="exportData('repairs', 'pdf')" class="bg-pink-100 hover:bg-pink-200 text-pink-700 rounded-lg px-3 py-2 text-sm transition-colors border border-pink-200" title="Export Repairs as PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <span class="px-2 py-2 text-sm text-gray-600 font-medium">Repairs</span>
                </div>
                <div class="flex gap-1 items-center">
                    <button onclick="exportData('swaps', 'csv')" class="bg-teal-100 hover:bg-teal-200 text-teal-700 rounded-lg px-3 py-2 text-sm transition-colors border border-teal-200" title="Export Swaps as CSV">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button onclick="exportData('swaps', 'xlsx')" class="bg-sky-100 hover:bg-sky-200 text-sky-700 rounded-lg px-3 py-2 text-sm transition-colors border border-sky-200" title="Export Swaps as Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button onclick="exportData('swaps', 'pdf')" class="bg-pink-100 hover:bg-pink-200 text-pink-700 rounded-lg px-3 py-2 text-sm transition-colors border border-pink-200" title="Export Swaps as PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <span class="px-2 py-2 text-sm text-gray-600 font-medium">Swaps</span>
                </div>
                <div class="flex gap-1 items-center">
                    <button onclick="exportData('inventory', 'csv')" class="bg-teal-100 hover:bg-teal-200 text-teal-700 rounded-lg px-3 py-2 text-sm transition-colors border border-teal-200" title="Export Inventory as CSV">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button onclick="exportData('inventory', 'xlsx')" class="bg-sky-100 hover:bg-sky-200 text-sky-700 rounded-lg px-3 py-2 text-sm transition-colors border border-sky-200" title="Export Inventory as Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button onclick="exportData('inventory', 'pdf')" class="bg-pink-100 hover:bg-pink-200 text-pink-700 rounded-lg px-3 py-2 text-sm transition-colors border border-pink-200" title="Export Inventory as PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <span class="px-2 py-2 text-sm text-gray-600 font-medium">Inventory</span>
                </div>
            </div>
        </div>
    </div>


    <!-- Consolidated Key Metrics -->
    <div id="keyMetricsSection" class="mb-4 sm:mb-6">
        <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">Key Metrics</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
            <!-- Sales & Revenue -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow p-4 sm:p-6 border border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 rounded-lg bg-blue-200">
                        <i class="fas fa-dollar-sign text-blue-700 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-600">Period: <span id="salesPeriodLabel">This Month</span></span>
                </div>
                <p class="text-sm font-medium text-gray-600 mb-1">Sales & Revenue</p>
                <p class="text-2xl font-bold text-gray-900" id="metric-monthly-revenue">₵0.00</p>
                <div class="mt-2 flex items-center text-xs text-gray-600">
                    <span id="metric-monthly-sales">0</span> <span class="ml-1">sales</span>
                    <span class="mx-2">•</span>
                    <span id="metric-today-revenue">₵0.00</span> <span class="ml-1">today</span>
                </div>
            </div>
            
            <!-- Profit & Loss -->
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-lg shadow p-4 sm:p-6 border border-emerald-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 rounded-lg bg-emerald-200">
                        <i class="fas fa-chart-line text-emerald-700 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-600" id="profitMarginLabel">20.00%</span>
                </div>
                <p class="text-sm font-medium text-gray-600 mb-1">Net Profit</p>
                <p class="text-2xl font-bold text-gray-900" id="metric-profit-profit">₵0.00</p>
                <div class="mt-2 flex items-center text-xs text-gray-600">
                    <span>Revenue: <span id="metric-profit-revenue">₵0.00</span></span>
                    <span class="mx-2">•</span>
                    <span>Cost: <span id="metric-profit-cost">₵0.00</span></span>
                </div>
            </div>
            
            <!-- Services (Repairs & Swaps) -->
            <div id="repairerStatsSection" class="bg-gradient-to-br from-indigo-50 to-purple-100 rounded-lg shadow p-4 sm:p-6 border border-indigo-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 rounded-lg bg-indigo-200">
                        <i class="fas fa-wrench text-indigo-700 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-600">Repairs: <span id="repairerStatsRepairsCount">0</span></span>
                </div>
                <p class="text-sm font-medium text-gray-600 mb-1">Repairer Stats</p>
                <p class="text-2xl font-bold text-gray-900" id="repairerStatsTotalRevenue">₵0.00</p>
                <div class="mt-2 flex flex-wrap items-center text-xs text-gray-600">
                    <span>Parts Sales: <span id="repairerStatsPartsRevenue">₵0.00</span></span>
                    <span class="mx-2">•</span>
                    <span>Products Sold: <span id="repairerStatsPartsCount">0</span></span>
                    <span class="mx-2">•</span>
                    <span>Profit: <span id="repairerStatsTotalProfit">₵0.00</span></span>
                </div>
            </div>
            
            <!-- Swapping Stats -->
            <div class="bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg shadow p-4 sm:p-6 border border-amber-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 rounded-lg bg-amber-200">
                        <i class="fas fa-exchange-alt text-amber-700 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-600">Total: <span id="metric-swap-total">0</span></span>
                </div>
                <p class="text-sm font-medium text-gray-600 mb-1">Swapping Stats</p>
                <p class="text-2xl font-bold text-gray-900" id="swapTotalRevenue">₵0.00</p>
                <div class="mt-2 flex items-center text-xs text-gray-600">
                    <span class="text-yellow-600">Pending: <span id="metric-swap-pending">0</span></span>
                    <span class="mx-2">•</span>
                    <span>Revenue: <span id="metric-swap-revenue">₵0.00</span></span>
                    <span class="mx-2">•</span>
                    <span class="text-green-600">Profit: <span id="metric-swap-profit">₵0.00</span></span>
                </div>
            </div>
        </div>
        
        <!-- Payment Status Card - Combined -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-3 sm:p-4 border border-gray-200" id="audit-payment-status-card" style="display: none;">
            <div class="flex items-center justify-between mb-2 sm:mb-3">
                <h4 class="text-xs sm:text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-money-bill-wave text-gray-600 mr-2"></i>
                    Payment Status
                </h4>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <div class="text-center">
                    <div class="flex items-center justify-center mb-1">
                        <i class="fas fa-check-circle text-green-600 text-sm mr-1"></i>
                        <p class="text-xs text-gray-600">Fully Paid</p>
                    </div>
                    <p class="text-xl font-bold text-gray-900" id="audit-fully-paid">0</p>
                </div>
                <div class="text-center">
                    <div class="flex items-center justify-center mb-1">
                        <i class="fas fa-exclamation-circle text-yellow-600 text-sm mr-1"></i>
                        <p class="text-xs text-gray-600">Partial</p>
                    </div>
                    <p class="text-xl font-bold text-gray-900" id="audit-partial">0</p>
                </div>
                <div class="text-center">
                    <div class="flex items-center justify-center mb-1">
                        <i class="fas fa-times-circle text-red-600 text-sm mr-1"></i>
                        <p class="text-xs text-gray-600">Unpaid</p>
                    </div>
                    <p class="text-xl font-bold text-gray-900" id="audit-unpaid">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Detail View (shown when staff is selected) -->
    <div id="staffDetailSection" class="mb-4 sm:mb-6 hidden">
        <div class="bg-white rounded-lg shadow p-3 sm:p-6 mb-4 sm:mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 sm:mb-4 gap-3">
                <div>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-800" id="staffDetailName">Staff Member Details</h3>
                    <p class="text-sm sm:text-base text-gray-600" id="staffDetailRole">Role</p>
                </div>
                <button onclick="clearStaffSelection()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 rounded px-3 sm:px-4 py-2 text-xs sm:text-sm whitespace-nowrap">
                    <i class="fas fa-times mr-2"></i> View All Staff
                </button>
            </div>
            
            <!-- Staff Performance Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                <div class="bg-blue-50 rounded-lg p-3 sm:p-4 border border-blue-200">
                    <p class="text-xs sm:text-sm font-medium text-gray-600">Total Sales</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffTotalSales">0</p>
                </div>
                <div class="bg-green-50 rounded-lg p-3 sm:p-4 border border-green-200">
                    <p class="text-xs sm:text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffTotalRevenue">₵0.00</p>
                </div>
                <div class="bg-emerald-50 rounded-lg p-3 sm:p-4 border border-emerald-200">
                    <p class="text-xs sm:text-sm font-medium text-gray-600">Total Profit</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffTotalProfit">₵0.00</p>
                </div>
                <div class="bg-red-50 rounded-lg p-3 sm:p-4 border border-red-200">
                    <p class="text-xs sm:text-sm font-medium text-gray-600">Total Losses</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffTotalLosses">₵0.00</p>
                </div>
            </div>
            
            <!-- Technician-Specific Breakdown (shown only for technicians) -->
            <div id="technicianBreakdownSection" class="hidden mb-4 sm:mb-6">
                <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-3 sm:mb-4">Repair Breakdown</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-purple-50 rounded-lg p-3 sm:p-4 border border-purple-200">
                        <p class="text-xs sm:text-sm font-medium text-gray-600">Workmanship Revenue</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffWorkmanshipRevenue">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Repair charges</p>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-3 sm:p-4 border border-indigo-200">
                        <p class="text-xs sm:text-sm font-medium text-gray-600">Parts & Accessories Revenue</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffPartsRevenue">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Spare parts sold</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 sm:p-4 border border-blue-200">
                        <p class="text-xs sm:text-sm font-medium text-gray-600">Products Sold</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffPartsCount">0</p>
                        <p class="text-xs text-gray-500 mt-1">Spare parts count</p>
                    </div>
                    <div class="bg-teal-50 rounded-lg p-3 sm:p-4 border border-teal-200">
                        <p class="text-xs sm:text-sm font-medium text-gray-600">Parts Profit</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900" id="staffPartsProfit">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Parts revenue - cost</p>
                    </div>
                </div>
            </div>
            
            <!-- Staff Breakdown by Period -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
                <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3">Today</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Sales:</span>
                            <span class="text-sm font-medium" id="staffTodaySales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Revenue:</span>
                            <span class="text-sm font-medium" id="staffTodayRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit:</span>
                            <span class="text-sm font-medium text-green-600" id="staffTodayProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">This Week</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Sales:</span>
                            <span class="text-sm font-medium" id="staffWeekSales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Revenue:</span>
                            <span class="text-sm font-medium" id="staffWeekRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit:</span>
                            <span class="text-sm font-medium text-green-600" id="staffWeekProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">This Month</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Sales:</span>
                            <span class="text-sm font-medium" id="staffMonthSales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Revenue:</span>
                            <span class="text-sm font-medium" id="staffMonthRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit:</span>
                            <span class="text-sm font-medium text-green-600" id="staffMonthProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Staff Transaction History -->
            <div class="mt-4 sm:mt-6">
                <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-3 sm:mb-4">Transaction History</h4>
                <div class="w-full -mx-3 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="w-full text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Reference</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Profit</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody id="staffTransactionHistory" class="bg-white divide-y divide-gray-200">
                                <tr><td colspan="6" class="text-center py-4 text-gray-500">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- All Staff Summary Section (shown when no staff is selected) -->
    <div id="allStaffSummarySection" class="mb-4 sm:mb-6">
        <div class="bg-white rounded-lg shadow p-3 sm:p-6">
            <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">All Staff Summary</h3>
            
            <!-- Staff Breakdown by Period -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3">Today</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Sales:</span>
                            <span class="text-sm font-medium" id="allStaffTodaySales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Revenue:</span>
                            <span class="text-sm font-medium" id="allStaffTodayRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit:</span>
                            <span class="text-sm font-medium text-green-600" id="allStaffTodayProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3">This Week</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs sm:text-sm text-gray-600">Sales:</span>
                            <span class="text-xs sm:text-sm font-medium" id="allStaffWeekSales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs sm:text-sm text-gray-600">Revenue:</span>
                            <span class="text-xs sm:text-sm font-medium" id="allStaffWeekRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs sm:text-sm text-gray-600">Profit:</span>
                            <span class="text-xs sm:text-sm font-medium text-green-600" id="allStaffWeekProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3">This Month</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Sales:</span>
                            <span class="text-sm font-medium" id="allStaffMonthSales">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Revenue:</span>
                            <span class="text-sm font-medium" id="allStaffMonthRevenue">₵0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit:</span>
                            <span class="text-sm font-medium text-green-600" id="allStaffMonthProfit">₵0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 sm:mb-4">Revenue Trend</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 sm:mb-4">Profit Breakdown</h3>
            <canvas id="profitChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 sm:mb-4">Top Products</h3>
            <canvas id="topProductsChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 sm:mb-4">Top Customers</h3>
            <canvas id="topCustomersChart" height="200"></canvas>
        </div>
    </div>

    <!-- Items Sold by Repairers Section -->
    <div id="repairerPartsSection" class="bg-white rounded-lg shadow p-3 sm:p-6 mb-4 sm:mb-6 hidden">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 sm:mb-4 gap-3">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">Items Sold by Repairers</h3>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                <div class="text-xs sm:text-sm text-gray-600">
                    <span class="font-medium">Total Revenue:</span>
                    <span id="repairerPartsTotalRevenue" class="ml-2 font-bold text-gray-900">₵0.00</span>
                </div>
                <div class="text-xs sm:text-sm text-gray-600">
                    <span class="font-medium">Total Profit:</span>
                    <span id="repairerPartsTotalProfit" class="ml-2 font-bold text-green-600">₵0.00</span>
                </div>
            </div>
        </div>
        <div class="w-full -mx-3 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Repairer</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Repair ID</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Customer</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Selling Price</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Cost Price</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                        </tr>
                    </thead>
                    <tbody id="repairerPartsTableBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="10" class="px-4 py-3 text-center text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-white rounded-lg shadow p-3 sm:p-6 mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 sm:mb-4 gap-3">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">Recent Transactions</h3>
            <select id="transactionTypeFilter" class="border border-gray-300 rounded px-3 py-2 text-sm w-full sm:w-auto">
                <option value="all">All Types</option>
                <option value="sale">Sales</option>
                <option value="repair">Repairs</option>
                <option value="swap">Swaps</option>
            </select>
        </div>
        <div class="w-full -mx-3 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
                <table id="transactionsTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Customer</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Status</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="transactionsTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Logs Live Feed -->
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3 sm:gap-0">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">Audit Trail - Live Feed</h3>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <select id="auditEventTypeFilter" class="border border-gray-300 rounded px-3 py-2 text-sm w-full sm:w-auto">
                    <option value="">All Events</option>
                    <option value="sale.created">Sales</option>
                    <option value="swap.completed">Swaps</option>
                    <option value="repair.created">Repairs</option>
                    <option value="user.login">User Logins</option>
                </select>
                <select id="auditUserFilter" class="border border-gray-300 rounded px-3 py-2 text-sm w-full sm:w-auto">
                    <option value="">All Users</option>
                </select>
                <button id="btnRefreshAuditLogs" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm whitespace-nowrap">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>
        <div class="w-full -mx-3 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
                <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">User</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">IP Address</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogsBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-center text-gray-500">Loading audit logs...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-gray-600" id="auditLogsInfo">
                Showing 0 of 0 logs
            </div>
            <div class="flex items-center gap-2" id="auditLogsPagination">
                <!-- Pagination buttons will be inserted here -->
            </div>
        </div>
    </div>


    <!-- Trace Search Button -->
    <div class="mb-4 sm:mb-6">
        <button id="btnOpenTraceModal" class="bg-purple-600 hover:bg-purple-700 text-white rounded px-4 sm:px-6 py-2 sm:py-3 text-sm sm:text-base font-medium w-full sm:w-auto">
            <i class="fas fa-search mr-2"></i> Trace Item / Customer / Device
        </button>
    </div>

    <!-- Trace Modal -->
    <div id="traceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-full p-3 sm:p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[95vh] sm:max-h-[90vh] relative flex flex-col">
            <div class="p-3 sm:p-4 sm:p-6 flex-shrink-0">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-800">Item Trace</h3>
                    <button id="btnCloseTraceModal" class="text-gray-400 hover:text-gray-600 flex-shrink-0 ml-2">
                        <i class="fas fa-times text-xl sm:text-2xl"></i>
                    </button>
                </div>
                <p class="text-xs sm:text-sm text-gray-600 mb-3 sm:mb-4">Search across all modules by IMEI, product ID, customer phone, sale ID, swap reference (e.g., SWAP-13), or repair tracking code</p>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <input type="text" id="traceSearchInput" placeholder="Enter IMEI, product ID, customer phone, sale ID, swap reference (SWAP-13), or repair code..." class="flex-1 border border-gray-300 rounded px-3 sm:px-4 py-2 text-sm" />
                    <button id="btnTraceSearch" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-4 sm:px-6 py-2 text-sm whitespace-nowrap">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
                <div id="traceResults" class="hidden flex-1 overflow-y-auto">
                    <div class="w-full">
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Customer</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Reference</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="traceResultsBody" class="bg-white divide-y divide-gray-200">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
                <span class="text-gray-700">Loading analytics...</span>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
(function() {
    const BASE = window.APP_BASE_PATH || '';
    // Debug mode - set to false for production
    const DEBUG_MODE = false;
    
    // Debug logging helper
    function debugLog(...args) {
        if (DEBUG_MODE) {
            console.log('[audit-trail]', ...args);
        }
    }
    
    function debugWarn(...args) {
        if (DEBUG_MODE) {
            console.warn('[audit-trail]', ...args);
        }
    }
    
    let revenueChart = null;
    let profitChart = null;
    let topProductsChart = null;
    let topCustomersChart = null;

    let forecastChart = null;

    // Authentication helpers
    function getToken() {
        try {
            return localStorage.getItem('token') || localStorage.getItem('sellapp_token');
        } catch (e) {
            // Handle tracking prevention or storage access issues
            debugWarn('Storage access blocked:', e.message);
            return null;
        }
    }

    function getAuthHeaders() {
        const token = getToken();
        const headers = {
            'Content-Type': 'application/json'
        };
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        return headers;
    }

    // Modal helper functions - prevent body scroll and maintain position
    function openModal(modalId, clickElement = null) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('Modal not found:', modalId);
            return;
        }
        
        // Store current scroll position
        const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
        modal.dataset.scrollY = scrollY;
        
        // Prevent body scroll - lock the page in place
        document.body.classList.add('overflow-hidden');
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.width = '100%';
        document.body.style.left = '0';
        document.body.style.right = '0';
        
        // Show modal - ensure it's visible
        modal.classList.remove('hidden');
        
        // Ensure modal is always in viewport - force recalculation
        requestAnimationFrame(() => {
            modal.style.display = '';
            // Ensure z-index is applied
            if (!modal.style.zIndex) {
                modal.style.zIndex = '9999';
            }
        });
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Hide modal
        modal.classList.add('hidden');
        
        // Restore body scroll
        const scrollY = modal.dataset.scrollY || 0;
        document.body.classList.remove('overflow-hidden');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        
        // Restore scroll position
        window.scrollTo(0, parseInt(scrollY || 0));
    }
    
    // Make closeModal globally accessible
    window.closeModal = closeModal;
    
    // Handle window resize - modals will automatically adjust with flexbox
    // No special handling needed as the new modal structure uses standard flexbox centering

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        
        // Set default to "This Month" - from 1st of current month to today
        const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
        const monthStartStr = monthStart.toISOString().split('T')[0];
        
        document.getElementById('filterDateFrom').value = monthStartStr;
        document.getElementById('filterDateTo').value = todayStr;
        
        // Initialize month selector with current month
        const monthInput = document.getElementById('monthSelector');
        const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
        monthInput.value = currentMonth;
        
        // Activate "This Month" button by default
        const btnThisMonth = document.getElementById('btnThisMonth');
        if (btnThisMonth) {
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            btnThisMonth.classList.add('active');
        }
        
        // Load all data on page load
        loadStaffMembers(); // Load staff dropdown first
        loadAnalytics();
        loadCharts();
        loadTransactions();
        loadAuditUsers();
        loadAuditLogs(1);
        setupEventListeners();
        initializeCharts();
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            loadAuditLogs(auditLogsCurrentPage);
        }, 60000);

        // Real-time data sync - check if enabled
        const autoRefreshEnabled = true; // Get from system settings in production
        if (autoRefreshEnabled) {
            // Initial live data load after 2 seconds (let initial load complete first)
            setTimeout(function() {
                loadLiveData();
            }, 2000);
            
            // Then refresh every 30 seconds for live data
            const refreshInterval = 30000; // 30 seconds for live data
            setInterval(function() {
                loadLiveData();
            }, refreshInterval);
        }
    });

    // Load live data from unified endpoint
    async function loadLiveData() {
        try {
            const dateRange = getCurrentDateRange();
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const staffId = document.getElementById('filterStaff').value;
            const customerId = document.getElementById('filterCustomerId')?.value || '';
            const productId = document.getElementById('filterProductId')?.value || '';
            const productSearch = document.getElementById('filterProduct')?.value || '';
            
            debugLog('loadLiveData called with:', {
                dateRange,
                dateFrom,
                dateTo,
                staffId: staffId || 'none',
                customerId: customerId || 'none',
                productId: productId || 'none',
                productSearch: productSearch || 'none'
            });
            
            const params = new URLSearchParams();
            params.append('date_range', dateRange);
            params.append('module', 'all');
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (staffId) params.append('staff_id', staffId);
            if (customerId) params.append('customer_id', customerId);
            if (productId) params.append('product_id', productId);
            // If product search is provided but no product ID, it might be an IMEI
            if (productSearch && !productId) {
                params.append('imei', productSearch);
            }
            
            debugLog('Fetching:', `${BASE}/api/audit-trail/data?${params.toString()}`);
            
            const response = await fetch(`${BASE}/api/audit-trail/data?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin' // Include session cookies for fallback auth
            });
            
            // Get response text first to handle errors properly
            const responseText = await response.text();
            
            if (!response.ok) {
                // Try to parse error as JSON, but don't display raw JSON
                try {
                    const errorData = JSON.parse(responseText);
                    const errorMessage = errorData.error || errorData.message || 'Failed to load analytics';
                    throw new Error(errorMessage);
                } catch (parseError) {
                    throw new Error('Failed to load analytics');
                }
            }
            
            // Parse JSON response
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invalid JSON response from audit-trail API:', parseError);
                console.error('Response text:', responseText.substring(0, 500));
                throw new Error('Invalid response from server');
            }

            debugLog('loadLiveData response:', {
                success: data.success,
                hasBreakdown: !!data.profit_loss_breakdown,
                breakdownKeys: data.profit_loss_breakdown ? Object.keys(data.profit_loss_breakdown) : [],
                dailyCount: data.profit_loss_breakdown?.daily?.length ?? 0,
                weeklyCount: data.profit_loss_breakdown?.weekly?.length ?? 0,
                monthlyCount: data.profit_loss_breakdown?.monthly?.length ?? 0,
                hasSwaps: !!data.swaps,
                swapsData: data.swaps,
                enabledModules: data.enabled_modules || []
            });

            if (data.success) {
                // Update all metrics with live data
                updateLiveMetrics(data);
                // Update profit/loss breakdown
                // Profit & Loss Breakdown section removed
                
                // Update transactions table with live data
                loadTransactionsFromData(data);
                
                // Update charts from live data if available, otherwise reload charts
                if (data.charts) {
                    updateCharts(data.charts);
                } else {
                    // Reload charts if not in live data
                    loadCharts();
                }
                
                // If staff is selected, load staff details
                const staffId = document.getElementById('filterStaff').value;
                if (staffId) {
                    loadStaffDetails(staffId);
                }
            }
        } catch (error) {
            console.error('Error loading live data:', error);
        }
    }
    
    // Load staff members for dropdown
    async function loadStaffMembers() {
        try {
            const response = await fetch(`${BASE}/api/staff/list`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            const staffSelect = document.getElementById('filterStaff');
            if (staffSelect && data.success && data.staff) {
                // Keep the "All Staff" option
                staffSelect.innerHTML = '<option value="">All Staff</option>';
                
                // Add staff members
                data.staff.forEach(staff => {
                    const option = document.createElement('option');
                    option.value = staff.id;
                    option.textContent = `${staff.full_name || staff.username || staff.name || 'Unknown'} (${staff.role || 'N/A'})`;
                    staffSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading staff members:', error);
        }
    }

    // Get current date range from filters
    function getCurrentDateRange() {
        // ALWAYS check explicit date inputs first - they take priority over button ranges
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        
        // If explicit dates are set, use 'custom' to ensure backend uses these exact dates
        if (dateFrom && dateTo) {
            // Check if dates match a predefined range (for button highlighting)
            const today = new Date().toISOString().split('T')[0];
            const todayDate = new Date(today);
            const fromDate = new Date(dateFrom);
            const toDate = new Date(dateTo);
            
            // Check if dates match "today"
            if (dateFrom === today && dateTo === today) {
                return 'today';
            }
            
            // Check if dates match "this month" (from 1st of current month to today)
            const monthStart = new Date(todayDate.getFullYear(), todayDate.getMonth(), 1);
            const monthStartStr = monthStart.toISOString().split('T')[0];
            if (dateFrom === monthStartStr && dateTo === today) {
                // Only return 'this_month' if button is active, otherwise use 'custom'
                const activeFilter = document.querySelector('.date-filter-btn.active');
                if (activeFilter && activeFilter.dataset.range === 'this_month') {
                    return 'this_month';
                }
            }
            
            // For any other explicit date range, use 'custom' to ensure exact dates are used
            return 'custom';
        }

        // If no explicit dates, check active quick filter button
        const activeFilter = document.querySelector('.date-filter-btn.active');
        if (activeFilter) {
            const range = activeFilter.dataset.range;
            if (range) return range;
        }

        return 'this_month'; // Default
    }

    // Update metrics with live data - comprehensive update
    function updateLiveMetrics(data) {
        // Debug: Log swaps data received
        debugLog('updateLiveMetrics - Swaps data received:', {
            hasSwaps: !!data.swaps,
            swaps: data.swaps,
            swapsKeys: data.swaps ? Object.keys(data.swaps) : [],
            swapsString: data.swaps ? JSON.stringify(data.swaps, null, 2) : 'null',
            enabledModules: data.enabled_modules || []
        });
        
        // Ensure swaps data exists (even if empty) when swap module is enabled
        if (!data.swaps && data.enabled_modules && (data.enabled_modules.includes('swap') || data.enabled_modules.includes('swaps'))) {
            debugLog('Swap module enabled but no swaps data - initializing empty structure');
            data.swaps = {
                pending: 0,
                monthly: { count: 0, revenue: 0, profit: 0 },
                profit: 0,
                period: { count: 0, revenue: 0, profit: 0 },
                filtered: null
            };
        }
        
        // Convert to metrics format and update all metrics
        const metrics = {
            sales: data.sales ? {
                today: data.sales.today || { revenue: 0, count: 0 },
                monthly: data.sales.monthly || { revenue: 0, count: 0 },
                period: data.sales.period || data.sales.filtered || { revenue: 0, count: 0 }
            } : null,
            profit: data.profit || { revenue: 0, cost: 0, profit: 0, margin: 0 },
            repairs: data.repairs ? {
                active: data.repairs.active || 0,
                monthly: {
                    count: data.repairs.monthly?.count || 0,
                    revenue: data.repairs.monthly?.revenue || 0
                },
                filtered: {
                    revenue: data.repairs.monthly?.revenue || 0
                }
            } : null,
            swaps: data.swaps ? {
                pending: data.swaps.pending ?? 0,
                monthly: {
                    count: data.swaps.monthly?.count ?? 0,
                    revenue: data.swaps.monthly?.revenue ?? 0,
                    profit: data.swaps.monthly?.profit ?? 0
                },
                profit: data.swaps.profit ?? 0,
                period: data.swaps.period ? {
                    count: data.swaps.period.count ?? 0,
                    revenue: data.swaps.period.revenue ?? 0,
                    profit: data.swaps.period.profit ?? data.swaps.monthly?.profit ?? data.swaps.profit ?? 0
                } : (data.swaps.filtered ? {
                    count: data.swaps.filtered.count ?? 0,
                    revenue: data.swaps.filtered.revenue ?? 0,
                    profit: data.swaps.monthly?.profit ?? data.swaps.profit ?? 0
                } : {
                    count: data.swaps.monthly?.count ?? 0,
                    revenue: data.swaps.monthly?.revenue ?? 0,
                    profit: data.swaps.monthly?.profit ?? data.swaps.profit ?? 0
                }),
                filtered: {
                    revenue: data.swaps.monthly?.revenue ?? 0
                }
            } : null,
            inventory: data.inventory || null
        };
        
        // Debug: Log constructed metrics
        debugLog('updateLiveMetrics - Constructed metrics:', {
            hasSwaps: !!metrics.swaps,
            swaps: metrics.swaps,
            swapsKeys: metrics.swaps ? Object.keys(metrics.swaps) : []
        });
        
        // Use the same updateMetrics function for consistency
        updateMetrics(metrics, data.enabled_modules || []);
        
        // Update payment stats if available
        if (data.payment_stats) {
            const paymentCard = document.getElementById('audit-payment-status-card');
            if (paymentCard) {
                paymentCard.style.display = 'block';
                const fullyPaidEl = document.getElementById('audit-fully-paid');
                const partialEl = document.getElementById('audit-partial');
                const unpaidEl = document.getElementById('audit-unpaid');
                if (fullyPaidEl) fullyPaidEl.textContent = data.payment_stats.fully_paid || 0;
                if (partialEl) partialEl.textContent = data.payment_stats.partial || 0;
                if (unpaidEl) unpaidEl.textContent = data.payment_stats.unpaid || 0;
            }
        }

        // Trigger chart updates if data changed
        if (data.sales || data.profit || data.swaps || data.repairs) {
            const dateRange = getCurrentDateRange();
            if (dateRange !== 'custom') {
                loadCharts(); // Reload charts with updated data
            }
        }
    }

    // Load analytics data - use unified endpoint for real-time data
    async function loadAnalytics() {
        const loadingEl = document.getElementById('loadingIndicator');
        loadingEl.classList.remove('hidden');

        try {
            // Get current date range
            const dateRange = getCurrentDateRange();
            
            // Get date range from filter inputs
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            
            // Build query params with date range
            const params = new URLSearchParams();
            params.append('date_range', dateRange);
            params.append('module', 'all');
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            // Add staff filter if selected
            const staffId = document.getElementById('filterStaff').value;
            if (staffId) params.append('staff_id', staffId);
            
            // Add customer filter if selected
            const customerId = document.getElementById('filterCustomerId')?.value || '';
            if (customerId) params.append('customer_id', customerId);
            
            // Add product/IMEI filter if selected
            const productId = document.getElementById('filterProductId')?.value || '';
            const productSearch = document.getElementById('filterProduct')?.value || '';
            if (productId) params.append('product_id', productId);
            if (productSearch && !productId) {
                params.append('imei', productSearch);
            }
            
            // Use unified endpoint for real-time data
            const response = await fetch(`${BASE}/api/audit-trail/data?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin' // Include session cookies for fallback auth
            });
            
            // Get response text first to handle errors properly
            const responseText = await response.text();
            
            if (!response.ok) {
                // Try to parse error as JSON, but don't display raw JSON
                try {
                    const errorData = JSON.parse(responseText);
                    const errorMessage = errorData.error || errorData.message || 'Failed to load analytics';
                    throw new Error(errorMessage);
                } catch (parseError) {
                    throw new Error('Failed to load analytics');
                }
            }
            
            // Parse JSON response
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invalid JSON response from analytics API');
                throw new Error('Invalid response from server');
            }

            if (data.success) {
                
                // Convert to metrics format for updateMetrics function
                const metrics = {
                    sales: data.sales ? {
                        today: data.sales.today || { revenue: 0, count: 0 },
                        monthly: data.sales.monthly || { revenue: 0, count: 0 },
                        period: data.sales.period || data.sales.filtered || { revenue: 0, count: 0 }
                    } : null,
                    profit: data.profit || { revenue: 0, cost: 0, profit: 0, margin: 0 },
                    repairs: data.repairs ? {
                        active: data.repairs.active || 0,
                        monthly: {
                            count: data.repairs.monthly?.count || 0,
                            revenue: data.repairs.monthly?.revenue || 0
                        },
                        period: data.repairs.period || data.repairs.filtered || {
                            count: 0,
                            revenue: 0
                        }
                    } : null,
                    swaps: data.swaps ? {
                        pending: data.swaps.pending || 0,
                        monthly: {
                            count: data.swaps.monthly?.count || 0,
                            revenue: data.swaps.monthly?.revenue || 0,
                            profit: data.swaps.monthly?.profit || 0
                        },
                        profit: data.swaps.profit || 0,
                        period: data.swaps.period || (data.swaps.filtered ? {
                            count: data.swaps.filtered.count || 0,
                            revenue: data.swaps.filtered.revenue || 0,
                            profit: data.swaps.period?.profit || data.swaps.profit || 0
                        } : {
                            count: data.swaps.monthly?.count || 0,
                            revenue: data.swaps.monthly?.revenue || 0,
                            profit: data.swaps.monthly?.profit || data.swaps.profit || 0
                        })
                    } : null,
                    inventory: data.inventory || null
                };
                
                updateMetrics(metrics, data.enabled_modules || []);
                
                // Update profit/loss breakdown - always update to clear old data when date range changes
                // API now always returns breakdown structure (even if empty arrays)
                if (data.profit_loss_breakdown) {
                    debugLog('Profit/Loss Breakdown data received from analytics:', {
                        hasBreakdown: !!data.profit_loss_breakdown,
                        breakdown: data.profit_loss_breakdown,
                        dailyCount: data.profit_loss_breakdown?.daily?.length ?? 0,
                        weeklyCount: data.profit_loss_breakdown?.weekly?.length ?? 0,
                        monthlyCount: data.profit_loss_breakdown?.monthly?.length ?? 0
                    });
                    // Profit & Loss Breakdown section removed
                } else {
                    // Profit & Loss Breakdown section removed
                }
                
                // Load transactions from activity logs
                loadTransactionsFromData(data);
                
                // Update repairer stats table
                debugLog('Staff activity data:', data.staff_activity);
                debugLog('Date range:', data.date_range);
                debugLog('Company ID:', data.company_id);
                debugLog('Full API response keys:', Object.keys(data));
                if (data.staff_activity) {
                    debugLog('Staff activity keys:', Object.keys(data.staff_activity));
                    debugLog('Technicians array:', data.staff_activity.technicians);
                    debugLog('Technicians count:', data.staff_activity.technicians ? data.staff_activity.technicians.length : 'null/undefined');
                    debugLog('Total technicians:', data.staff_activity.total_technicians);
                    
                    if (data.staff_activity.technicians && Array.isArray(data.staff_activity.technicians) && data.staff_activity.technicians.length > 0) {
                        debugLog('Technicians found:', data.staff_activity.technicians.length);
                        debugLog('Technicians data:', JSON.stringify(data.staff_activity.technicians, null, 2));
                        updateRepairerStats(data.staff_activity.technicians);
                    } else {
                        debugLog('No technicians array found or array is empty');
                        debugLog('Staff activity structure:', JSON.stringify(data.staff_activity, null, 2));
                        updateRepairerStats([]);
                    }
                } else {
                    debugLog('No staff_activity in response');
                    debugLog('Available keys:', Object.keys(data));
                    updateRepairerStats([]);
                }
                
                // Update repairer parts sales section (only show when staff is selected)
                const staffId = document.getElementById('filterStaff')?.value || '';
                if (staffId && data.repairer_parts_sales) {
                    updateRepairerPartsSales(data.repairer_parts_sales);
                } else {
                    document.getElementById('repairerPartsSection').classList.add('hidden');
                }
                
                // If staff is selected, load staff details
                // Update All Staff Summary if no staff is selected
                if (staffId) {
                    loadStaffDetails(staffId);
                    document.getElementById('allStaffSummarySection').classList.add('hidden');
                } else {
                    // Hide staff detail section if no staff selected
                    document.getElementById('staffDetailSection').classList.add('hidden');
                    // Show all staff summary section
                    document.getElementById('allStaffSummarySection').classList.remove('hidden');
                    // Update all staff summary with data
                    updateAllStaffSummary(data);
                }
            } else {
                // Handle API error response without displaying raw JSON
                const errorMessage = data.error || data.message || 'Failed to load analytics';
                console.error('Error loading analytics:', errorMessage);
            }
        } catch (error) {
            console.error('Error loading analytics:', error.message || error);
            // Don't display raw JSON or error details in UI
        } finally {
            loadingEl.classList.add('hidden');
        }
    }

    // Helper function to safely set text content (global scope)
    function safeSetText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
            return true;
        }
        return false;
    }

    // Update metrics display
    function updateMetrics(metrics, enabledModules) {
        
        // Sales metrics
        if (metrics.sales) {
            // Always show today's data (never filtered)
            safeSetText('metric-today-revenue', formatCurrency(metrics.sales.today.revenue));
            safeSetText('metric-today-sales', metrics.sales.today.count);
            
            // Use period data for Monthly Revenue/Sales when date range is selected
            // Otherwise use monthly data
            const periodRevenue = metrics.sales.period?.revenue ?? metrics.sales.monthly.revenue;
            const periodCount = metrics.sales.period?.count ?? metrics.sales.monthly.count;
            
            safeSetText('metric-monthly-revenue', formatCurrency(periodRevenue));
            safeSetText('metric-monthly-sales', periodCount);
            
            // Update period label
            const periodLabel = document.getElementById('salesPeriodLabel');
            if (periodLabel) {
                const dateRange = getCurrentDateRange();
                const periodNames = {
                    'today': 'Today',
                    'this_week': 'This Week',
                    'this_month': 'This Month',
                    'this_year': 'This Year',
                    'custom': 'Custom Period'
                };
                periodLabel.textContent = periodNames[dateRange] || 'This Month';
            }
        } else {
            const keyMetricsSection = document.getElementById('keyMetricsSection');
            if (keyMetricsSection) {
                keyMetricsSection.classList.add('hidden');
            }
        }

        // Profit metrics - always display, even if 0
        if (metrics.profit) {
            let revenue = metrics.profit.revenue || 0;
            let cost = metrics.profit.cost || 0;
            let profit = metrics.profit.profit || 0;
            let margin = metrics.profit.margin || 0;
            
            // If profit revenue is 0 but we have sales revenue for the selected period, use that
            if (revenue == 0 && metrics.sales && metrics.sales.period && metrics.sales.period.revenue > 0) {
                revenue = metrics.sales.period.revenue;
                // Estimate cost and profit if we don't have cost data
                if (cost == 0) {
                    cost = revenue * 0.8; // Assume 80% cost, 20% margin
                    profit = revenue - cost;
                    margin = 20.0;
                } else {
                    profit = revenue - cost;
                    margin = revenue > 0 ? (profit / revenue) * 100 : 0;
                }
            }
            
            // Note: Repairer profit is now included in the backend profit calculation
            // The profit value from metrics.profit.profit already includes:
            // Sales Profit + Swap Profit + Repairer Profit (workmanship + parts)
            // Store values for reference (no need to add repairer profit again)
            window.baseProfit = profit;
            window.baseRevenue = revenue;
            window.baseCost = cost;
            
            safeSetText('metric-profit-revenue', formatCurrency(revenue));
            safeSetText('metric-profit-cost', formatCurrency(cost));
            safeSetText('metric-profit-profit', formatCurrency(profit));
            
            const profitMarginEl = document.getElementById('profitMarginLabel');
            if (profitMarginEl) {
                profitMarginEl.textContent = margin.toFixed(2) + '%';
            }
        } else {
            // Ensure zero values are displayed if profit data is missing
            safeSetText('metric-profit-revenue', formatCurrency(0));
            safeSetText('metric-profit-cost', formatCurrency(0));
            safeSetText('metric-profit-profit', formatCurrency(0));
            
            const profitMarginEl = document.getElementById('profitMarginLabel');
            if (profitMarginEl) {
                profitMarginEl.textContent = '0%';
            }
        }

        // Services metrics removed - replaced with Repairer Stats
        // Repairer stats are now updated in updateRepairerStats() function

        // Swapping Stats
        const swapCard = document.getElementById('metric-swap-total')?.closest('.bg-gradient-to-br');
        if (metrics.swaps) {
            // Use period data if available (date filtered), otherwise use monthly
            const swapCount = metrics.swaps.period?.count ?? metrics.swaps.monthly?.count ?? 0;
            const swapRevenue = metrics.swaps.period?.revenue ?? metrics.swaps.monthly?.revenue ?? 0;
            const swapProfit = metrics.swaps.period?.profit ?? metrics.swaps.monthly?.profit ?? metrics.swaps.profit ?? 0;
            const pendingSwaps = metrics.swaps.pending ?? 0;
            
            debugLog('Swapping Stats Update:', {
                swapCount,
                swapRevenue,
                swapProfit,
                pendingSwaps,
                period: metrics.swaps.period,
                monthly: metrics.swaps.monthly,
                profit: metrics.swaps.profit
            });
            
            safeSetText('metric-swap-total', swapCount);
            safeSetText('metric-swap-pending', pendingSwaps);
            safeSetText('metric-swap-revenue', formatCurrency(swapRevenue));
            safeSetText('metric-swap-profit', formatCurrency(swapProfit));
            safeSetText('swapTotalRevenue', formatCurrency(swapRevenue));
            
            // Show swap card if it exists
            if (swapCard) {
                swapCard.style.display = '';
                swapCard.classList.remove('hidden');
            }
        } else {
            debugLog('Swapping Stats: metrics.swaps is null or undefined');
            safeSetText('metric-swap-total', '0');
            safeSetText('metric-swap-pending', '0');
            safeSetText('metric-swap-revenue', formatCurrency(0));
            safeSetText('metric-swap-profit', formatCurrency(0));
            safeSetText('swapTotalRevenue', formatCurrency(0));
            
            // Still show the card even if no data (module might be disabled)
            if (swapCard) {
                swapCard.style.display = '';
                swapCard.classList.remove('hidden');
            }
        }
    }

    // Format currency
    function formatCurrency(amount) {
        return '₵' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Setup event listeners
    function setupEventListeners() {
        // Date quick filters
        document.getElementById('btnToday').addEventListener('click', function() {
            // Update active button
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filterDateFrom').value = today;
            document.getElementById('filterDateTo').value = today;
            loadAnalytics();
        });

        document.getElementById('btnThisWeek').addEventListener('click', function() {
            // Update active button
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const today = new Date();
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay()); // Monday
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6); // Sunday
            
            document.getElementById('filterDateFrom').value = weekStart.toISOString().split('T')[0];
            document.getElementById('filterDateTo').value = weekEnd.toISOString().split('T')[0];
            loadAnalytics();
        });

        document.getElementById('btnThisMonth').addEventListener('click', function() {
            // Update active button
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const today = new Date();
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            // Use today's date, not end of month, to include today's sales
            document.getElementById('filterDateFrom').value = monthStart.toISOString().split('T')[0];
            document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];
            
            // Update month selector to current month
            const monthInput = document.getElementById('monthSelector');
            const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
            monthInput.value = currentMonth;
            
            loadAnalytics();
        });

        const btnThisYear = document.getElementById('btnThisYear');
        if (btnThisYear) {
            btnThisYear.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const today = new Date();
                const yearStart = new Date(today.getFullYear(), 0, 1);
                const yearEnd = new Date(today.getFullYear(), 11, 31);
                document.getElementById('filterDateFrom').value = yearStart.toISOString().split('T')[0];
                document.getElementById('filterDateTo').value = yearEnd.toISOString().split('T')[0];
                
                // Clear month selector
                const monthSelector = document.getElementById('monthSelector');
                if (monthSelector) {
                    monthSelector.value = '';
                }
                
                loadAnalytics();
            });
        }

        // Staff filter change handler
        const staffFilter = document.getElementById('filterStaff');
        if (staffFilter) {
            staffFilter.addEventListener('change', function() {
                const staffId = this.value;
                if (staffId) {
                    // Show staff detail section and load details
                    document.getElementById('staffDetailSection').classList.remove('hidden');
                    document.getElementById('allStaffSummarySection').classList.add('hidden');
                    loadStaffDetails(staffId);
                    loadAnalytics(); // Reload analytics with staff filter
                } else {
                    // Hide staff detail section and show all data
                    document.getElementById('staffDetailSection').classList.add('hidden');
                    document.getElementById('allStaffSummarySection').classList.remove('hidden');
                    // Hide repairer parts section when viewing all staff
                    document.getElementById('repairerPartsSection').classList.add('hidden');
                    loadAnalytics(); // Reload analytics without staff filter
                }
            });
        }

        // Month selector change handler
        document.getElementById('monthSelector').addEventListener('change', function() {
            const selectedMonth = this.value;
            if (!selectedMonth) return;
            
            // Update active button - remove active from all buttons
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            
            // Parse the selected month (format: YYYY-MM)
            const [year, month] = selectedMonth.split('-');
            const monthStart = new Date(parseInt(year), parseInt(month) - 1, 1);
            const monthEnd = new Date(parseInt(year), parseInt(month), 0); // Last day of the month
            
            const monthStartStr = monthStart.toISOString().split('T')[0];
            const monthEndStr = monthEnd.toISOString().split('T')[0];
            
            document.getElementById('filterDateFrom').value = monthStartStr;
            document.getElementById('filterDateTo').value = monthEndStr;
            
            // Reload all data with new date range
            loadAnalytics();
            loadCharts();
            loadTransactions();
            loadAuditLogs(1);
        });

        document.getElementById('btnApplyFilters').addEventListener('click', async function() {
            const staffId = document.getElementById('filterStaff').value;
            
            // Load live data first (includes profit/loss breakdown)
            await loadLiveData();
            
            // Only call loadAnalytics() if no staff is selected
            // (loadAnalytics() doesn't support staff filtering and would overwrite breakdown)
            if (!staffId) {
                loadAnalytics();
            }
            
            // Then load other data
            loadCharts();
            loadTransactions();
        });

        // Staff filter change - reload data when staff is selected
        const filterStaff = document.getElementById('filterStaff');
        if (filterStaff) {
            filterStaff.addEventListener('change', async function() {
                // Load live data first (includes profit/loss breakdown with staff filter)
                await loadLiveData();
                // Then load other data (charts and transactions)
                // Don't call loadAnalytics() when staff is selected - it doesn't support staff filter
                // and would overwrite the breakdown with empty data
                loadCharts();
                loadTransactions();
            });
        }

        // Trace modal
        document.getElementById('btnOpenTraceModal').addEventListener('click', function() {
            openModal('traceModal');
        });
        document.getElementById('btnCloseTraceModal').addEventListener('click', function() {
            closeModal('traceModal');
        });
        document.getElementById('traceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('traceModal');
            }
        });

        // Trace search
        const btnTraceSearch = document.getElementById('btnTraceSearch');
        if (btnTraceSearch) {
            btnTraceSearch.addEventListener('click', performTraceSearch);
        }
        const traceSearchInput = document.getElementById('traceSearchInput');
        if (traceSearchInput) {
            traceSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performTraceSearch();
            }
        });
        }

        // Transaction type filter
        const transactionTypeFilter = document.getElementById('transactionTypeFilter');
        if (transactionTypeFilter) {
            transactionTypeFilter.addEventListener('change', loadTransactions);
        }

        // Audit logs
        const btnRefreshAuditLogs = document.getElementById('btnRefreshAuditLogs');
        if (btnRefreshAuditLogs) {
            btnRefreshAuditLogs.addEventListener('click', () => loadAuditLogs(1));
        }
        const auditEventTypeFilter = document.getElementById('auditEventTypeFilter');
        if (auditEventTypeFilter) {
            auditEventTypeFilter.addEventListener('change', () => loadAuditLogs(1));
        }
        const auditUserFilter = document.getElementById('auditUserFilter');
        if (auditUserFilter) {
            auditUserFilter.addEventListener('change', () => loadAuditLogs(1));
        }
        
        // Make loadAuditLogs globally accessible for pagination buttons
        window.loadAuditLogs = loadAuditLogs;
        
        // Customer/product filters removed
    }

    let auditLogsCurrentPage = 1;
    const auditLogsLimit = 10;
    let auditLogsTotal = 0;
    let auditLogsTotalPages = 0;

    // Load users for filter dropdown
    async function loadAuditUsers() {
        try {
            const response = await fetch(`${BASE}/api/staff/list`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                const userSelect = document.getElementById('auditUserFilter');
                if (userSelect && data.success && data.staff) {
                    // Clear existing options except "All Users"
                    userSelect.innerHTML = '<option value="">All Users</option>';
                    
                    // Add users
                    data.staff.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.full_name || user.username || user.name || `User #${user.id}`;
                        userSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading users for audit filter:', error);
        }
    }

    // Load audit logs
    async function loadAuditLogs(page = 1) {
        auditLogsCurrentPage = page;
        const tbody = document.getElementById('auditLogsBody');
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">Loading...</td></tr>';

        try {
            const eventType = document.getElementById('auditEventTypeFilter').value;
            const userId = document.getElementById('auditUserFilter').value;
            const dateFrom = document.getElementById('filterDateFrom')?.value;
            const dateTo = document.getElementById('filterDateTo')?.value;
            
            const params = new URLSearchParams();
            params.append('limit', auditLogsLimit);
            params.append('page', page);
            if (eventType) {
                params.append('event_type', eventType);
            }
            if (userId) {
                params.append('user_id', userId);
            }
            if (dateFrom) {
                params.append('date_from', dateFrom);
            }
            if (dateTo) {
                params.append('date_to', dateTo);
            }

            const response = await fetch(`${BASE}/api/analytics/audit-logs?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.success && data.logs) {
                auditLogsTotal = data.total || 0;
                auditLogsTotalPages = data.total_pages || 0;
                
                if (data.logs.length > 0) {
                    displayAuditLogs(data.logs);
                    updateAuditLogsPagination();
                } else {
                    // Check if filters are applied
                    const eventType = document.getElementById('auditEventTypeFilter').value;
                    const userId = document.getElementById('auditUserFilter').value;
                    const dateFrom = document.getElementById('filterDateFrom')?.value;
                    const dateTo = document.getElementById('filterDateTo')?.value;
                    
                    const hasFilters = eventType || userId || dateFrom || dateTo;
                    
                    let message = 'No audit logs found';
                    if (hasFilters) {
                        message += ' for the selected filters. Try removing filters or selecting a different date range.';
                    } else {
                        message += '. Audit logs will appear here as activities occur (sales, swaps, repairs, logins, etc.).';
                    }
                    
                    tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">${message}</td></tr>`;
                    updateAuditLogsPagination();
                }
            } else if (data.error) {
                console.error('API Error:', data.error);
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-3 text-center text-red-500">Error: ${data.error}</td></tr>`;
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">No audit logs found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading audit logs:', error);
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-3 text-center text-red-500">Error loading audit logs: ${error.message || 'Unknown error'}</td></tr>`;
        }
    }

    // Update pagination controls
    function updateAuditLogsPagination() {
        const infoEl = document.getElementById('auditLogsInfo');
        const paginationEl = document.getElementById('auditLogsPagination');
        
        if (!infoEl || !paginationEl) return;
        
        const start = auditLogsTotal > 0 ? ((auditLogsCurrentPage - 1) * auditLogsLimit) + 1 : 0;
        const end = Math.min(auditLogsCurrentPage * auditLogsLimit, auditLogsTotal);
        
        infoEl.textContent = `Showing ${start} to ${end} of ${auditLogsTotal} logs`;
        
        // Build pagination buttons
        let paginationHTML = '';
        
        // Previous button
        if (auditLogsCurrentPage > 1) {
            paginationHTML += `<button onclick="loadAuditLogs(${auditLogsCurrentPage - 1})" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">Previous</button>`;
        } else {
            paginationHTML += `<button disabled class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-400 cursor-not-allowed">Previous</button>`;
        }
        
        // Page numbers
        const maxPagesToShow = 5;
        let startPage = Math.max(1, auditLogsCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(auditLogsTotalPages, startPage + maxPagesToShow - 1);
        
        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }
        
        if (startPage > 1) {
            paginationHTML += `<button onclick="loadAuditLogs(1)" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">1</button>`;
            if (startPage > 2) {
                paginationHTML += `<span class="px-2 text-gray-500">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === auditLogsCurrentPage) {
                paginationHTML += `<button class="px-3 py-1 border border-blue-500 bg-blue-500 text-white rounded text-sm font-medium">${i}</button>`;
            } else {
                paginationHTML += `<button onclick="loadAuditLogs(${i})" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">${i}</button>`;
            }
        }
        
        if (endPage < auditLogsTotalPages) {
            if (endPage < auditLogsTotalPages - 1) {
                paginationHTML += `<span class="px-2 text-gray-500">...</span>`;
            }
            paginationHTML += `<button onclick="loadAuditLogs(${auditLogsTotalPages})" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">${auditLogsTotalPages}</button>`;
        }
        
        // Next button
        if (auditLogsCurrentPage < auditLogsTotalPages) {
            paginationHTML += `<button onclick="loadAuditLogs(${auditLogsCurrentPage + 1})" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">Next</button>`;
        } else {
            paginationHTML += `<button disabled class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-400 cursor-not-allowed">Next</button>`;
        }
        
        paginationEl.innerHTML = paginationHTML;
    }

    // Display audit logs
    function displayAuditLogs(logs) {
        const tbody = document.getElementById('auditLogsBody');
        
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">No audit logs found</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const entityLink = log.entity_type && log.entity_id 
                ? `<a href="#" onclick="viewEvent(${log.id}, event)" class="text-blue-600 hover:text-blue-800">${log.entity_type} #${log.entity_id}</a>`
                : '-';
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${new Date(log.created_at).toLocaleString()}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100">${log.event_type}</span>
                    </td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden sm:table-cell">${log.user_name || log.user_full_name || '-'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${entityLink}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden md:table-cell">${log.ip_address || '-'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                        <button onclick="viewEvent(${log.id}, event)" class="text-blue-600 hover:text-blue-800 text-xs sm:text-sm">
                            <i class="fas fa-eye"></i> <span class="hidden sm:inline">View</span>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Append audit logs (for load more)
    function appendAuditLogs(logs) {
        const tbody = document.getElementById('auditLogsBody');
        const existing = tbody.innerHTML;
        
        if (logs.length === 0) {
            return;
        }

        const newRows = logs.map(log => {
            const entityLink = log.entity_type && log.entity_id 
                ? `<a href="#" onclick="viewEvent(${log.id}, event)" class="text-blue-600 hover:text-blue-800">${log.entity_type} #${log.entity_id}</a>`
                : '-';
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${new Date(log.created_at).toLocaleString()}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100">${log.event_type}</span>
                    </td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden sm:table-cell">${log.user_name || log.user_full_name || '-'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${entityLink}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden md:table-cell">${log.ip_address || '-'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                        <button onclick="viewEvent(${log.id}, event)" class="text-blue-600 hover:text-blue-800 text-xs sm:text-sm">
                            <i class="fas fa-eye"></i> <span class="hidden sm:inline">View</span>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        tbody.innerHTML = existing + newRows;
    }

    // View event details - navigate to new page
    window.viewEvent = function(logId, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        // Navigate to audit trail details page
        window.location.href = `${BASE}/dashboard/audit-trail/${logId}`;
    };

    // Load charts data
    async function loadCharts() {
        try {
            let dateFrom = document.getElementById('filterDateFrom').value;
            let dateTo = document.getElementById('filterDateTo').value;
            
            if (!dateFrom) {
                const d = new Date();
                d.setDate(d.getDate() - 30);
                dateFrom = d.toISOString().split('T')[0];
            }
            if (!dateTo) {
                dateTo = new Date().toISOString().split('T')[0];
            }
            
            const params = new URLSearchParams();
            params.append('type', 'all');
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);

            const response = await fetch(`${BASE}/api/analytics/charts?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            
            // Get response text first to handle errors properly
            const responseText = await response.text();
            
            if (!response.ok) {
                // Try to parse error as JSON, but don't display raw JSON
                try {
                    const errorData = JSON.parse(responseText);
                    const errorMessage = errorData.error || errorData.message || `HTTP error! status: ${response.status}`;
                    throw new Error(errorMessage);
                } catch (parseError) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            }
            
            // Parse JSON response
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invalid JSON response from charts API');
                throw new Error('Invalid response from server');
            }
            
            debugLog('Charts API response:', {
                success: data.success,
                hasCharts: !!data.charts,
                chartKeys: data.charts ? Object.keys(data.charts) : [],
                topProducts: data.charts?.topProducts,
                topProductsFull: JSON.stringify(data.charts?.topProducts, null, 2)
            });

            if (data.success && data.charts) {
                updateCharts(data.charts);
            } else {
                // Handle API error response without displaying raw JSON
                const errorMessage = data.error || data.message || 'Unknown error';
                console.error('Charts API error:', errorMessage);
                // Don't clear charts, just log the error
                debugWarn('Charts API failed, keeping existing chart data');
            }
        } catch (error) {
            console.error('Error loading charts:', error.message || error);
            // Initialize charts with empty data on error
            if (revenueChart && profitChart) {
                updateCharts({ revenue: { labels: [], datasets: [] }, profit: { labels: [], datasets: [] } });
            }
            // Don't display raw JSON or error details in UI
        }
    }

    // Update charts
    function updateCharts(charts) {
        // Helper function to filter recent dates (last 60 days) and center the chart
        function filterRecentDates(labels, datasets) {
            const now = new Date();
            const cutoffDate = new Date(now);
            cutoffDate.setDate(cutoffDate.getDate() - 60); // Last 60 days only
            
            const filteredIndices = [];
            const filteredLabels = [];
            
            labels.forEach((label, index) => {
                try {
                    const labelDate = new Date(label);
                    if (!isNaN(labelDate.getTime()) && labelDate >= cutoffDate) {
                        filteredIndices.push(index);
                        filteredLabels.push(label);
                    }
                } catch (e) {
                    // Keep non-date labels
                    filteredIndices.push(index);
                    filteredLabels.push(label);
                }
            });
            
            // If we have filtered data, use it; otherwise use all data
            if (filteredLabels.length > 0) {
                const filteredDatasets = datasets.map(dataset => ({
                    ...dataset,
                    data: filteredIndices.map(idx => dataset.data[idx] || 0)
                }));
                return { labels: filteredLabels, datasets: filteredDatasets };
            }
            
            return { labels, datasets };
        }
        
        // Revenue chart
        if (charts.revenue && revenueChart) {
            let labels = charts.revenue.labels || [];
            let datasets = charts.revenue.datasets || [];
            
            // Filter to recent dates only
            const filtered = filterRecentDates(labels, datasets);
            labels = filtered.labels;
            datasets = filtered.datasets;
            
            if (labels.length > 0 && datasets.length > 0) {
                // Update colors to be more vibrant (no red) and add curve tension
                datasets = datasets.map((dataset, idx) => {
                    const colors = [
                        { border: 'rgb(59, 130, 246)', bg: 'rgba(59, 130, 246, 0.1)' }, // Blue
                        { border: 'rgb(34, 197, 94)', bg: 'rgba(34, 197, 94, 0.1)' }, // Green
                        { border: 'rgb(234, 179, 8)', bg: 'rgba(234, 179, 8, 0.1)' }, // Yellow/Amber
                        { border: 'rgb(168, 85, 247)', bg: 'rgba(168, 85, 247, 0.1)' }, // Purple
                        { border: 'rgb(14, 165, 233)', bg: 'rgba(14, 165, 233, 0.1)' }  // Sky Blue
                    ];
                    const color = colors[idx % colors.length];
                    return {
                        ...dataset,
                        borderColor: color.border,
                        backgroundColor: color.bg,
                        tension: 0.4, // Curved lines
                        fill: true,
                        borderWidth: 2
                    };
                });
                
                revenueChart.data.labels = labels.map(label => {
                    try {
                const date = new Date(label);
                        if (!isNaN(date.getTime())) {
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        }
                        return label;
                    } catch (e) {
                        return label;
                    }
                });
                revenueChart.data.datasets = datasets;
                
                // Center the chart by adjusting the x-axis
                if (labels.length > 0) {
                    revenueChart.options.scales.x.offset = true;
                }
                
                revenueChart.update('none');
            }
        }

        // Profit chart (Bubble Chart)
        if (charts.profit && profitChart) {
            let labels = charts.profit.labels || [];
            let datasets = charts.profit.datasets || [];
            
            // Filter to recent dates only
            const filtered = filterRecentDates(labels, datasets);
            labels = filtered.labels;
            datasets = filtered.datasets;
            
            if (labels.length > 0 && datasets.length > 0) {
                // Extract revenue, cost, and profit data
                let revenueData = [];
                let costData = [];
                let profitData = [];
                
                datasets.forEach((dataset) => {
                    if (dataset.label === 'Revenue') {
                        revenueData = dataset.data || [];
                    } else if (dataset.label === 'Cost') {
                        costData = dataset.data || [];
                    } else if (dataset.label === 'Profit') {
                        profitData = dataset.data || [];
                    }
                });
                
                // Find max cost for scaling bubble radius
                const maxCost = Math.max(...costData, 1);
                const minRadius = 5;
                const maxRadius = 30;
                
                // Color function based on profit margin
                function getBubbleColor(revenue, profit) {
                    if (revenue === 0) {
                        return {
                            bg: 'rgba(156, 163, 175, 0.6)', // Gray for no revenue
                            border: 'rgb(156, 163, 175)'
                        };
                    }
                    
                    const profitMargin = (profit / revenue) * 100;
                    
                    if (profitMargin < 0) {
                        // Negative profit - red shades
                        return {
                            bg: 'rgba(239, 68, 68, 0.6)', // Red
                            border: 'rgb(220, 38, 38)'
                        };
                    } else if (profitMargin < 10) {
                        // Low profit margin (0-10%) - orange/yellow shades
                        return {
                            bg: 'rgba(251, 146, 60, 0.6)', // Orange
                            border: 'rgb(249, 115, 22)'
                        };
                    } else if (profitMargin < 25) {
                        // Medium profit margin (10-25%) - yellow/amber shades
                        return {
                            bg: 'rgba(234, 179, 8, 0.6)', // Amber
                            border: 'rgb(202, 138, 4)'
                        };
                    } else if (profitMargin < 40) {
                        // Good profit margin (25-40%) - light green shades
                        return {
                            bg: 'rgba(34, 197, 94, 0.6)', // Green
                            border: 'rgb(22, 163, 74)'
                        };
                    } else {
                        // Excellent profit margin (>40%) - dark green shades
                        return {
                            bg: 'rgba(16, 185, 129, 0.6)', // Emerald
                            border: 'rgb(5, 150, 105)'
                        };
                    }
                }
                
                // Create bubble data points: x = revenue, y = profit, r = cost (scaled)
                const bubbleData = [];
                const backgroundColorArray = [];
                const borderColorArray = [];
                
                for (let i = 0; i < labels.length; i++) {
                    const revenue = parseFloat(revenueData[i] || 0);
                    const profit = parseFloat(profitData[i] || 0);
                    const cost = parseFloat(costData[i] || 0);
                    
                    // Only add bubbles with meaningful data
                    if (revenue > 0 || profit !== 0 || cost > 0) {
                        // Scale radius based on cost (proportional to max cost)
                        const radius = cost > 0 ? minRadius + ((cost / maxCost) * (maxRadius - minRadius)) : minRadius;
                        
                        // Get color based on profit margin
                        const colors = getBubbleColor(revenue, profit);
                        
                        bubbleData.push({
                            x: revenue,
                            y: profit,
                            r: radius,
                            cost: cost, // Store actual cost for tooltip
                            label: labels[i] // Store label for tooltip if needed
                        });
                        
                        backgroundColorArray.push(colors.bg);
                        borderColorArray.push(colors.border);
                    }
                }
                
                // Create bubble chart dataset with individual colors
                const bubbleDataset = {
                    label: 'Profit Breakdown',
                    data: bubbleData,
                    backgroundColor: backgroundColorArray,
                    borderColor: borderColorArray,
                    borderWidth: 2
                };
                
                profitChart.data.datasets = [bubbleDataset];
                profitChart.update('none');
            } else {
                // Clear chart if no data
                profitChart.data.datasets = [];
                profitChart.update('none');
            }
        }

        // Top products chart
        debugLog('Top Products chart check:', {
            hasTopProducts: !!charts.topProducts,
            topProductsData: charts.topProducts,
            chartInitialized: !!topProductsChart
        });
        
        if (charts.topProducts) {
            const labels = charts.topProducts.labels || [];
            const datasets = charts.topProducts.datasets || [];
            
            debugLog('Top Products data:', {
                labelsCount: labels.length,
                datasetsCount: datasets.length,
                labelsSample: labels.slice(0, 5),
                datasetsSample: datasets.map(d => ({
                    label: d.label,
                    dataLength: d.data?.length || 0,
                    dataSample: d.data?.slice(0, 5) || []
                }))
            });
            
            if (!topProductsChart) {
                // Chart not initialized yet, try to initialize it
                const productsCtx = document.getElementById('topProductsChart');
                debugLog('Top Products chart not initialized, canvas element:', !!productsCtx);
                if (productsCtx) {
                    topProductsChart = new Chart(productsCtx, {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    debugLog('Top Products chart initialized');
                } else {
                    debugWarn('Top Products chart canvas element not found');
                }
            }
            
            if (topProductsChart) {
                if (labels.length > 0 && datasets.length > 0 && datasets[0]?.data?.length > 0) {
                    topProductsChart.data.labels = labels;
                    topProductsChart.data.datasets = datasets;
                    topProductsChart.update('none');
                    debugLog('Top Products chart updated with', labels.length, 'products');
                } else if (labels.length > 0 && datasets.length > 0) {
                    // Even if data array is empty, update with empty data to show labels
                    topProductsChart.data.labels = labels;
                    topProductsChart.data.datasets = datasets;
                    topProductsChart.update('none');
                    debugLog('Top Products chart updated with labels but empty data');
                } else {
                    // Reset chart if no data
                    topProductsChart.data.labels = [];
                    topProductsChart.data.datasets = [{
                        label: 'Units Sold',
                        data: [],
                        backgroundColor: 'rgba(34, 197, 94, 0.6)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: 1
                    }];
                    topProductsChart.update('none');
                    debugWarn('Top Products chart: No data available');
                }
            } else {
                debugWarn('Top Products chart not available after initialization attempt');
            }
        } else {
            debugWarn('Top Products chart data not found in charts object');
            if (topProductsChart) {
                // Reset chart if no data
                topProductsChart.data.labels = [];
                topProductsChart.data.datasets = [{
                    label: 'Units Sold',
                    data: [],
                    backgroundColor: 'rgba(34, 197, 94, 0.6)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }];
                topProductsChart.update('none');
            }
        }

        // Top customers chart
        if (charts.topCustomers && topCustomersChart) {
            if (charts.topCustomers.labels && charts.topCustomers.labels.length > 0) {
                topCustomersChart.data.labels = charts.topCustomers.labels;
                topCustomersChart.data.datasets = charts.topCustomers.datasets;
                topCustomersChart.update();
            } else {
                // Reset chart if no data
                topCustomersChart.data.labels = [];
                topCustomersChart.data.datasets = [{
                    label: 'Revenue',
                    data: [],
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }];
                topCustomersChart.update('none');
            }
        }
    }

    // Load transactions - use activity logs from main endpoint
    async function loadTransactions() {
        try {
            const typeFilter = document.getElementById('transactionTypeFilter')?.value || 'all';
            let dateFrom = document.getElementById('filterDateFrom')?.value;
            let dateTo = document.getElementById('filterDateTo')?.value;
            
            // Only set default dates if they're truly not set (shouldn't happen with proper initialization)
            if (!dateFrom || !dateTo) {
                const today = new Date();
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                dateFrom = monthStart.toISOString().split('T')[0];
                dateTo = today.toISOString().split('T')[0];
                // Update the inputs so they're set for next time
                if (!dateFrom) document.getElementById('filterDateFrom').value = dateFrom;
                if (!dateTo) document.getElementById('filterDateTo').value = dateTo;
            }
            
            // Get date range
            const dateRange = getCurrentDateRange() || 'this_month';
            
            // Build query params
            const params = new URLSearchParams();
            params.append('date_range', dateRange);
            params.append('module', 'all');
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            // Add customer filter if selected
            const customerId = document.getElementById('filterCustomerId')?.value || '';
            if (customerId) params.append('customer_id', customerId);
            
            // Add product/IMEI filter if selected
            const productId = document.getElementById('filterProductId')?.value || '';
            const productSearch = document.getElementById('filterProduct')?.value || '';
            if (productId) params.append('product_id', productId);
            if (productSearch && !productId) {
                params.append('imei', productSearch);
            }

            const response = await fetch(`${BASE}/api/audit-trail/data?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            
            // Get response text first to handle errors properly
            const responseText = await response.text();
            
            if (!response.ok) {
                console.error('Failed to fetch transactions:', response.status, response.statusText);
                // Try to parse error as JSON, but don't display raw JSON
                try {
                    const errorData = JSON.parse(responseText);
                    const errorMessage = errorData.error || errorData.message || 'Failed to fetch transactions';
                    console.error('Error response:', errorMessage);
                } catch (parseError) {
                    console.error('Error response (non-JSON)');
                }
                displayTransactions([]);
                return;
            }
            
            // Parse JSON response
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invalid JSON response from transactions API');
                displayTransactions([]);
                return;
            }

            if (data.success) {
                // Get transactions from activity_logs
                let transactions = [];
                
                // Always ensure activity_logs is an array
                const activityLogs = Array.isArray(data.activity_logs) ? data.activity_logs : [];
                
                // Debug logging
                console.log('loadTransactions: Total activity_logs received:', activityLogs.length);
                if (activityLogs.length > 0) {
                    console.log('loadTransactions: Sample activity log structure:', activityLogs[0]);
                    console.log('loadTransactions: All activity types:', activityLogs.map(log => log.activity_type || log.type || 'unknown'));
                }
                const swapLogs = activityLogs.filter(log => {
                    const type = (log.activity_type || log.type || '').toLowerCase();
                    const isSwap = type === 'swap';
                    if (isSwap) {
                        console.log('loadTransactions: Found swap log:', log);
                    }
                    return isSwap;
                });
                console.log('loadTransactions: Swap logs in response:', swapLogs.length);
                if (swapLogs.length > 0) {
                    console.log('loadTransactions: Sample swap log:', swapLogs[0]);
                }
                
                if (activityLogs.length > 0) {
                    // Debug: Log activity types for troubleshooting
                    const activityTypes = activityLogs.map(log => log.activity_type || log.type || log.sale_type || 'unknown');
                    const uniqueTypes = [...new Set(activityTypes)];
                    console.log('loadTransactions: Activity types found:', uniqueTypes);
                    console.log('loadTransactions: Filter type:', typeFilter);
                    
                    // Convert activity logs to transaction format
                    transactions = activityLogs
                        .filter(log => {
                            // Filter by type if needed
                            if (typeFilter === 'all') return true;
                            const logType = (log.activity_type || log.type || log.sale_type || '').toLowerCase().trim();
                            const filterType = typeFilter.toLowerCase().trim();
                            
                            // Debug specific repair filter
                            if (filterType === 'repair') {
                                const isRepair = logType === 'repair' || logType === 'repairs';
                                if (isRepair) {
                                    console.log('loadTransactions: Found repair log:', log);
                                }
                                return isRepair;
                            }
                            
                            // Map: sale/sales (includes repair_part_sale), repair/repairs, swap/swaps
                            if (filterType === 'sale') {
                                return logType === 'sale' || logType === 'sales' || logType === 'repair_part_sale';
                            }
                            if (filterType === 'swap') return logType === 'swap' || logType === 'swaps';
                            return logType === filterType;
                        })
                        .slice(0, 50) // Limit to 50 most recent
                        .map(log => {
                            const amount = parseFloat(log.amount || log.revenue || 0);
                            // Use sale_type if available (for repair_part_sale), otherwise use activity_type
                            let type = (log.sale_type || log.activity_type || log.type || 'unknown').toLowerCase();
                            // Normalize repair_part_sale to 'sale' for display, but keep original for filtering
                            const displayType = type === 'repair_part_sale' ? 'sale' : type;
                            
                            // Calculate profit based on transaction type
                            let profit = 0;
                            if (type === 'swap') {
                                // For swaps, amount should be the profit from swap_profit_links (when both items are sold)
                                profit = parseFloat(log.amount || 0);
                            } else if (type === 'sale' || type === 'repair_part_sale') {
                                // For sales, estimate 25% profit (or use actual if available)
                                profit = parseFloat(log.profit || (log.amount || 0) * 0.25);
                            } else if (type === 'repair') {
                                // For repairs, profit is typically 0 in activity logs (handled separately)
                                profit = parseFloat(log.profit || 0);
                            }
                            
                            return {
                                type: displayType,
                                originalType: type, // Keep original for reference
                                date: log.timestamp || log.date || log.created_at || new Date().toISOString(),
                                customer: log.customer_name || log.customer || 'Walk-in Customer',
                                amount: amount,
                                profit: profit,
                                status: log.status || 'completed',
                                reference: log.reference || log.unique_id || log.id || '-',
                                item: log.item || log.item_description || log.description || '-',
                                user_name: log.user_name || '-',
                                user_role: log.user_role || '-'
                            };
                        });
                    
                    // Debug: Log transaction types
                    const transactionTypes = transactions.map(t => t.type);
                    const swapTransactions = transactions.filter(t => t.originalType === 'swap');
                    const repairTransactions = transactions.filter(t => t.originalType === 'repair' || t.type === 'repair');
                    console.log('loadTransactions: Transaction types:', [...new Set(transactionTypes)]);
                    console.log('loadTransactions: Swap transactions after mapping:', swapTransactions.length);
                    console.log('loadTransactions: Repair transactions after mapping:', repairTransactions.length);
                    if (swapTransactions.length > 0) {
                        console.log('loadTransactions: Sample swap transaction:', swapTransactions[0]);
                    }
                    if (repairTransactions.length > 0) {
                        console.log('loadTransactions: Sample repair transaction:', repairTransactions[0]);
                    } else if (typeFilter === 'repair') {
                        console.warn('loadTransactions: Repair filter selected but no repair transactions found. Activity logs:', activityLogs.length);
                        const repairLogs = activityLogs.filter(log => {
                            const logType = (log.activity_type || log.type || log.sale_type || '').toLowerCase().trim();
                            return logType === 'repair' || logType === 'repairs';
                        });
                        console.log('loadTransactions: Repair logs in activity_logs:', repairLogs.length);
                        if (repairLogs.length > 0) {
                            console.log('loadTransactions: Sample repair log:', repairLogs[0]);
                        }
                    }
                }
                
                displayTransactions(transactions);
            } else {
                console.error('Error loading transactions:', data.error || 'Unknown error');
                displayTransactions([]);
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
            console.error('Error stack:', error.stack);
            displayTransactions([]);
        }
    }

    // Load transactions from analytics data (when data is already loaded)
    function loadTransactionsFromData(data) {
        const typeFilter = document.getElementById('transactionTypeFilter')?.value || 'all';
        
        // Get transactions from activity_logs
        let transactions = [];
        
        if (data.activity_logs && Array.isArray(data.activity_logs)) {
            // Debug: Log activity types for troubleshooting
            const activityTypes = data.activity_logs.map(log => log.activity_type || log.type || log.sale_type || 'unknown');
            const uniqueTypes = [...new Set(activityTypes)];
            console.log('loadTransactionsFromData: Activity types found:', uniqueTypes);
            console.log('loadTransactionsFromData: Filter type:', typeFilter);
            console.log('loadTransactionsFromData: Total activity logs:', data.activity_logs.length);
            
            transactions = data.activity_logs
                .filter(log => {
                    // Filter by type if needed
                    if (typeFilter === 'all') return true;
                    const logType = (log.sale_type || log.activity_type || log.type || '').toLowerCase().trim();
                    const filterType = typeFilter.toLowerCase().trim();
                    
                    // Debug specific repair filter
                    if (filterType === 'repair') {
                        const isRepair = logType === 'repair' || logType === 'repairs';
                        if (isRepair) {
                            console.log('loadTransactionsFromData: Found repair log:', log);
                        }
                        return isRepair;
                    }
                    
                    // Map: sale/sales (includes repair_part_sale), repair/repairs, swap/swaps
                    if (filterType === 'sale') {
                        return logType === 'sale' || logType === 'sales' || logType === 'repair_part_sale';
                    }
                    if (filterType === 'swap') return logType === 'swap' || logType === 'swaps';
                    return logType === filterType;
                })
                .slice(0, 50) // Limit to 50 most recent
                .map(log => {
                    const amount = parseFloat(log.amount || log.revenue || 0);
                    // Use sale_type if available (for repair_part_sale), otherwise use activity_type
                    let type = (log.sale_type || log.activity_type || log.type || 'unknown').toLowerCase();
                    // Normalize repair_part_sale to 'sale' for display, but keep original for filtering
                    const displayType = type === 'repair_part_sale' ? 'sale' : type;
                    
                    // Calculate profit based on transaction type
                    let profit = 0;
                    if (type === 'swap') {
                        // For swaps, amount should be the profit from swap_profit_links (when both items are sold)
                        profit = parseFloat(log.amount || 0);
                    } else if (type === 'sale' || type === 'repair_part_sale') {
                        // For sales, estimate 25% profit (or use actual if available)
                        profit = parseFloat(log.profit || (log.amount || 0) * 0.25);
                    } else if (type === 'repair') {
                        // For repairs, profit is typically 0 in activity logs (handled separately)
                        profit = parseFloat(log.profit || 0);
                    }
                    
                    return {
                        type: displayType,
                        originalType: type, // Keep original for reference
                        date: log.timestamp || log.date || log.created_at || new Date().toISOString(),
                        customer: log.customer_name || log.customer || 'Walk-in Customer',
                        amount: amount,
                        profit: profit,
                        status: log.status || 'completed',
                        reference: log.reference || log.unique_id || log.id || '-',
                        item: log.item || log.item_description || log.description || '-',
                        user_name: log.user_name || '-',
                        user_role: log.user_role || '-'
                    };
                });
        }
        
        displayTransactions(transactions);
    }

    // Display transactions in table
    function displayTransactions(transactions) {
        const tbody = document.getElementById('transactionsTableBody');
        if (!tbody) {
            debugWarn('transactionsTableBody element not found');
            return;
        }
        
        tbody.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">No transactions found</td></tr>';
            return;
        }

        // Sort by date (newest first)
        transactions.sort((a, b) => {
            try {
                return new Date(b.date) - new Date(a.date);
            } catch (e) {
                return 0;
            }
        });

        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 cursor-pointer';
            
            // Format type badge colors
            const typeClass = {
                'sale': 'bg-green-100 text-green-800',
                'sales': 'bg-green-100 text-green-800',
                'repair': 'bg-blue-100 text-blue-800',
                'repairs': 'bg-blue-100 text-blue-800',
                'swap': 'bg-purple-100 text-purple-800',
                'swaps': 'bg-purple-100 text-purple-800',
                'repair_part_sale': 'bg-teal-100 text-teal-800' // Parts sold by repairers
            };
            const typeBadgeClass = typeClass[transaction.type] || 'bg-gray-100 text-gray-800';
            // Show "Repair Parts" for repair_part_sale, otherwise capitalize first letter
            let typeLabel = transaction.originalType === 'repair_part_sale' 
                ? 'Repair Parts' 
                : transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1);
            
            // Format date
            let dateStr = '-';
            try {
                const date = new Date(transaction.date);
                if (!isNaN(date.getTime())) {
                    dateStr = date.toLocaleString();
                }
            } catch (e) {
                debugWarn('Invalid date:', transaction.date);
            }
            
            // Format status
            const status = transaction.status || 'pending';
            const statusClass = (status === 'completed' || status === 'PAID' || status === 'paid') 
                ? 'bg-green-100 text-green-800' 
                : 'bg-yellow-100 text-yellow-800';
            
            row.innerHTML = `
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium ${typeBadgeClass}">${typeLabel}</span>
                </td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${dateStr}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden sm:table-cell">${transaction.customer || '-'}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-medium">${formatCurrency(transaction.amount || 0)}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden md:table-cell">
                    <span class="px-2 py-1 rounded text-xs font-medium ${statusClass}">${status}</span>
                </td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                    ${transaction.reference && transaction.reference !== '-' ? `<button onclick="traceItem('${transaction.reference}')" class="text-blue-600 hover:text-blue-800 text-xs sm:text-sm">View</button>` : '-'}
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    // Load staff details when staff is selected
    async function loadStaffDetails(staffId) {
        try {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            
            const params = new URLSearchParams();
            params.append('staff_id', staffId);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            const response = await fetch(`${BASE}/api/audit-trail/data?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            if (data.success) {
                updateStaffDetailView(staffId, data);
            }
        } catch (error) {
            console.error('Error loading staff details:', error);
        }
    }
    
    // Update staff detail view with data
    function updateStaffDetailView(staffId, data) {
        // Get staff info from dropdown
        const staffSelect = document.getElementById('filterStaff');
        const selectedOption = staffSelect.options[staffSelect.selectedIndex];
        const staffName = selectedOption.textContent.split(' (')[0];
        const staffRole = selectedOption.textContent.match(/\(([^)]+)\)/)?.[1] || 'N/A';
        
        // Update staff name and role
        const staffDetailNameEl = document.getElementById('staffDetailName');
        const staffDetailRoleEl = document.getElementById('staffDetailRole');
        if (staffDetailNameEl) staffDetailNameEl.textContent = staffName;
        if (staffDetailRoleEl) staffDetailRoleEl.textContent = staffRole;
        
        // Hide technician breakdown initially (will be shown if technician data exists)
        document.getElementById('technicianBreakdownSection').classList.add('hidden');
        
        // Calculate totals from sales data (which is already filtered by staff_id)
        // Priority: Use staff_activity.salespersons as primary source (most accurate for staff filtering)
        let totalSales = 0;
        let totalRevenue = 0;
        let totalProfit = 0;
        let totalLosses = 0;
        
        // First, try to get data from staff_activity (most accurate, specifically filtered by staff_id)
        if (data.staff_activity && data.staff_activity.salespersons && data.staff_activity.salespersons.length > 0) {
            const staff = data.staff_activity.salespersons[0];
            // Use staff_activity data as it's specifically filtered for this staff member and date range
            totalSales = parseInt(staff.sales_count || 0);
            totalRevenue = parseFloat(staff.sales_revenue || 0);
        } else if (data.sales) {
            // Fallback to sales data if staff_activity is not available
            // For totals, use the period data (which represents the selected date range)
            if (data.sales.period) {
                totalSales = parseInt(data.sales.period.count || 0);
                totalRevenue = parseFloat(data.sales.period.revenue || 0);
            } else if (data.sales.filtered) {
                totalSales = parseInt(data.sales.filtered.count || 0);
                totalRevenue = parseFloat(data.sales.filtered.revenue || 0);
            } else if (data.sales.monthly) {
                totalSales = parseInt(data.sales.monthly.count || 0);
                totalRevenue = parseFloat(data.sales.monthly.revenue || 0);
            } else if (data.sales.today) {
                totalSales = parseInt(data.sales.today.count || 0);
                totalRevenue = parseFloat(data.sales.today.revenue || 0);
            }
        }
        
        // Check for technician data (repairs) - add to totals if staff is a technician
        if (data.staff_activity) {
            // Technician data - separate workmanship and parts
            if (data.staff_activity.technicians && data.staff_activity.technicians.length > 0) {
                const tech = data.staff_activity.technicians[0];
                totalSales += parseInt(tech.repairs_count || 0);
                
                // For technicians, show workmanship and parts separately
                const workmanshipRevenue = parseFloat(tech.workmanship_revenue || 0);
                const labourCost = parseFloat(tech.labour_cost || 0);
                const workmanshipProfit = parseFloat(tech.workmanship_profit || (workmanshipRevenue - labourCost));
                const partsRevenue = parseFloat(tech.parts_revenue || 0);
                const partsCount = parseInt(tech.parts_count || 0);
                const partsProfit = parseFloat(tech.parts_profit || 0);
                const totalRepairRevenue = workmanshipRevenue + partsRevenue;
                
                totalRevenue += totalRepairRevenue;
                
                // Show technician breakdown section
                const breakdownSection = document.getElementById('technicianBreakdownSection');
                if (breakdownSection) breakdownSection.classList.remove('hidden');
                safeSetText('staffWorkmanshipRevenue', formatCurrency(workmanshipRevenue));
                safeSetText('staffPartsRevenue', formatCurrency(partsRevenue));
                safeSetText('staffPartsCount', partsCount);
                safeSetText('staffPartsProfit', formatCurrency(partsProfit));
            } else {
                // Hide technician breakdown for non-technicians
                document.getElementById('technicianBreakdownSection').classList.add('hidden');
            }
        }
        
        // Get profit data - use profit_loss_breakdown as fallback if profit.profit is 0
        if (data.profit) {
            totalProfit = parseFloat(data.profit.profit || 0);
            // Losses should only show actual losses (negative profit or refunds/returns)
            // Don't calculate as cost - profit, that's just showing cost, not losses
            // Only show losses if profit is negative
            if (totalProfit < 0) {
                totalLosses = Math.abs(totalProfit); // Loss is the absolute value of negative profit
            } else {
                totalLosses = 0; // No losses if profit is positive
            }
        }
        
        // If profit is 0, try to get from profit_loss_breakdown (monthly data)
        if (totalProfit === 0 && data.profit_loss_breakdown && data.profit_loss_breakdown.monthly) {
            const monthlyBreakdown = data.profit_loss_breakdown.monthly;
            if (monthlyBreakdown.length > 0) {
                // Sum all monthly profits
                totalProfit = monthlyBreakdown.reduce((sum, month) => sum + parseFloat(month.profit || 0), 0);
                // Calculate losses only if profit is negative
                if (totalProfit < 0) {
                    totalLosses = Math.abs(totalProfit);
                } else {
                    totalLosses = 0;
                }
            }
        }
        
        // Check for actual loss transactions (refunds, returns, damaged items, etc.)
        // This would need to come from a separate data source if available
        // For now, losses are only shown when profit is negative
        
        // Update summary metrics
        safeSetText('staffTotalSales', totalSales);
        safeSetText('staffTotalRevenue', formatCurrency(totalRevenue));
        safeSetText('staffTotalProfit', formatCurrency(totalProfit));
        safeSetText('staffTotalLosses', formatCurrency(totalLosses));
        
        // Update period breakdowns - use actual period data, not period for all
        if (data.sales) {
            // Today's data
            safeSetText('staffTodaySales', data.sales.today?.count || 0);
            safeSetText('staffTodayRevenue', formatCurrency(data.sales.today?.revenue || 0));
            
            // Week data - calculate from profit_loss_breakdown weekly data or use period if it's a week range
            // For now, use monthly as week approximation (we need proper week calculation)
            // TODO: Add proper week calculation from profit_loss_breakdown.weekly
            const weekRevenue = data.sales.monthly?.revenue || 0;
            const weekCount = data.sales.monthly?.count || 0;
            safeSetText('staffWeekSales', weekCount);
            safeSetText('staffWeekRevenue', formatCurrency(weekRevenue));
            
            // Month data
            safeSetText('staffMonthSales', data.sales.monthly?.count || 0);
            safeSetText('staffMonthRevenue', formatCurrency(data.sales.monthly?.revenue || 0));
        }
        
        // Calculate period profits - calculate from profit_loss_breakdown for accurate period-specific profits
        let todayProfit = 0;
        let weekProfit = 0;
        let monthProfit = 0;
        
        // First try to get from profit_loss_breakdown for accurate period-specific data
        if (data.profit_loss_breakdown) {
            // Get today's profit from daily breakdown
            if (data.profit_loss_breakdown.daily && data.profit_loss_breakdown.daily.length > 0) {
                const today = new Date().toISOString().split('T')[0];
                const todayData = data.profit_loss_breakdown.daily.find(d => 
                    d.date === today || d.date_str === today || d.day === today
                );
                if (todayData) {
                    todayProfit = parseFloat(todayData.profit || 0);
                }
            }
            
            // Get week profit from weekly breakdown (sum of last 7 days or current week)
            if (data.profit_loss_breakdown.weekly && data.profit_loss_breakdown.weekly.length > 0) {
                // Sum all weekly profits for the period, or use the most recent week
                const recentWeeks = data.profit_loss_breakdown.weekly.slice(0, 1); // Most recent week
                weekProfit = recentWeeks.reduce((sum, week) => sum + parseFloat(week.profit || 0), 0);
            }
            
            // Get month profit from monthly breakdown
            if (data.profit_loss_breakdown.monthly && data.profit_loss_breakdown.monthly.length > 0) {
                // Sum all monthly profits for the period, or use the most recent month
                const recentMonths = data.profit_loss_breakdown.monthly.slice(0, 1); // Most recent month
                monthProfit = recentMonths.reduce((sum, month) => sum + parseFloat(month.profit || 0), 0);
            }
        }
        
        // Fallback to data.profit if profit_loss_breakdown doesn't have data
        if (todayProfit === 0 && weekProfit === 0 && monthProfit === 0 && data.profit) {
            // Use the period profit for all if breakdown is not available
            const periodProfit = parseFloat(data.profit.profit || 0);
            todayProfit = periodProfit;
            weekProfit = periodProfit;
            monthProfit = periodProfit;
        }
            
        safeSetText('staffTodayProfit', formatCurrency(todayProfit));
        safeSetText('staffWeekProfit', formatCurrency(weekProfit));
        safeSetText('staffMonthProfit', formatCurrency(monthProfit));
        
        // Load transaction history
        loadStaffTransactionHistory(staffId, data);
    }
    
    // Load staff transaction history
    async function loadStaffTransactionHistory(staffId, data) {
        const tbody = document.getElementById('staffTransactionHistory');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        // Combine sales and repairs into transaction history
        // Note: Swaps are excluded - they should be tracked separately on the swap page
        const transactions = [];
        
        // Get sales transactions for this staff
        // Exclude swap transactions - swaps should be tracked separately on the swap page
        // This matches the revenue calculation which also excludes swap sales
        if (data.activity_logs && data.activity_logs.length > 0) {
            const staffTransactions = data.activity_logs.filter(log => 
                (log.activity_type === 'sale' || log.activity_type === 'repair') &&
                log.activity_type !== 'swap'
            );
            
            staffTransactions.forEach(log => {
                // Calculate profit - use actual profit if available, otherwise estimate
                let profit = 0;
                if (log.activity_type === 'sale') {
                    // Try to get profit from breakdown data if available
                    if (data.profit_loss_breakdown && data.profit_loss_breakdown.daily) {
                        const logDate = new Date(log.timestamp).toISOString().split('T')[0];
                        const dayData = data.profit_loss_breakdown.daily.find(d => 
                            (d.date === logDate || d.date_str === logDate)
                        );
                        if (dayData && dayData.profit) {
                            // Estimate profit per sale (profit / sales_count)
                            profit = dayData.sales_count > 0 ? 
                                (parseFloat(dayData.profit) / dayData.sales_count) : 
                                (parseFloat(log.amount || 0) * 0.25);
                        } else {
                            // Fallback to 25% profit estimate
                            profit = parseFloat(log.amount || 0) * 0.25;
                        }
                    } else {
                        // Fallback to 25% profit estimate
                        profit = parseFloat(log.amount || 0) * 0.25;
                    }
                } else if (log.activity_type === 'repair') {
                    // For repairs, profit calculation would need repair-specific logic
                    // For now, use 0 or calculate from repair data if available
                    profit = parseFloat(log.amount || 0) * 0.20; // Estimate 20% profit for repairs
                }
                
                transactions.push({
                    date: log.timestamp,
                    type: log.activity_type,
                    reference: log.reference || log.description || 'N/A',
                    amount: log.amount,
                    profit: profit,
                    status: 'completed'
                });
            });
        } else if (data.profit_loss_breakdown && data.profit_loss_breakdown.daily) {
            // Fallback: Create transactions from daily breakdown if activity_logs is empty
            data.profit_loss_breakdown.daily.forEach(day => {
                if (day.sales_count > 0 && day.revenue > 0) {
                    // Create a transaction entry for each day with sales
                    const profitPerSale = day.profit / day.sales_count;
                    for (let i = 0; i < day.sales_count; i++) {
                        transactions.push({
                            date: day.date || day.date_str,
                            type: 'sale',
                            reference: `Sale #${i + 1}`,
                            amount: day.revenue / day.sales_count,
                            profit: profitPerSale,
                            status: 'completed'
                        });
                    }
                }
            });
        }
        
        // Sort by date (newest first)
        transactions.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No transactions found</td></tr>';
            return;
        }
        
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            const typeClass = {
                'sale': 'bg-green-100 text-green-800',
                'repair': 'bg-blue-100 text-blue-800',
                'swap': 'bg-purple-100 text-purple-800'
            }[transaction.type] || 'bg-gray-100 text-gray-800';
            
            row.innerHTML = `
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${new Date(transaction.date).toLocaleDateString()}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm"><span class="px-2 py-1 rounded text-xs font-medium ${typeClass}">${transaction.type || 'N/A'}</span></td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm hidden sm:table-cell">${transaction.reference || 'N/A'}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right font-medium">${formatCurrency(transaction.amount || 0)}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right ${transaction.profit >= 0 ? 'text-green-600' : 'text-red-600'} hidden md:table-cell">${formatCurrency(transaction.profit || 0)}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right"><span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">${transaction.status || 'N/A'}</span></td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Clear staff selection
    window.clearStaffSelection = function() {
        document.getElementById('filterStaff').value = '';
        document.getElementById('staffDetailSection').classList.add('hidden');
        document.getElementById('allStaffSummarySection').classList.remove('hidden');
        loadAnalytics(); // Reload without staff filter
    }
    
    // Update All Staff Summary with data
    function updateAllStaffSummary(data) {
        // Get today's date
        const today = new Date().toISOString().split('T')[0];
        
        // Get today's profit from daily breakdown
        let todaySales = 0;
        let todayRevenue = 0;
        let todayProfit = 0;
        
        if (data.profit_loss_breakdown && data.profit_loss_breakdown.daily && data.profit_loss_breakdown.daily.length > 0) {
            const todayData = data.profit_loss_breakdown.daily.find(d => {
                const dateStr = d.date || d.date_str || '';
                return dateStr === today || dateStr.startsWith(today);
            });
            if (todayData) {
                todaySales = parseInt(todayData.sales_count || 0);
                todayRevenue = parseFloat(todayData.revenue || 0);
                todayProfit = parseFloat(todayData.profit || 0);
            }
        }
        
        // If no daily breakdown, try to get from sales data
        if (todaySales === 0 && todayRevenue === 0 && data.sales && data.sales.today) {
            todaySales = parseInt(data.sales.today.count || 0);
            todayRevenue = parseFloat(data.sales.today.revenue || 0);
        }
        
        // Get week profit from weekly breakdown
        let weekSales = 0;
        let weekRevenue = 0;
        let weekProfit = 0;
        
        if (data.profit_loss_breakdown && data.profit_loss_breakdown.weekly && data.profit_loss_breakdown.weekly.length > 0) {
            // Get the latest week (first in array)
            const latestWeek = data.profit_loss_breakdown.weekly[0];
            weekSales = parseInt(latestWeek.sales_count || 0);
            weekRevenue = parseFloat(latestWeek.revenue || 0);
            weekProfit = parseFloat(latestWeek.profit || 0);
        }
        
        // If no weekly breakdown, try to get from sales data
        if (weekSales === 0 && weekRevenue === 0 && data.sales) {
            const weekRevenueData = data.sales.period?.revenue || data.sales.weekly?.revenue || 0;
            const weekCount = data.sales.period?.count || data.sales.weekly?.count || 0;
            weekSales = parseInt(weekCount || 0);
            weekRevenue = parseFloat(weekRevenueData || 0);
        }
        
        // Get month profit from monthly breakdown
        let monthSales = 0;
        let monthRevenue = 0;
        let monthProfit = 0;
        
        if (data.profit_loss_breakdown && data.profit_loss_breakdown.monthly && data.profit_loss_breakdown.monthly.length > 0) {
            // Get the latest month (first in array)
            const latestMonth = data.profit_loss_breakdown.monthly[0];
            monthSales = parseInt(latestMonth.sales_count || 0);
            monthRevenue = parseFloat(latestMonth.revenue || 0);
            monthProfit = parseFloat(latestMonth.profit || 0);
        }
        
        // If no monthly breakdown, try to get from sales data
        if (monthSales === 0 && monthRevenue === 0 && data.sales) {
            monthSales = parseInt(data.sales.monthly?.count || 0);
            monthRevenue = parseFloat(data.sales.monthly?.revenue || 0);
        }
        
        // Add swap profit to totals (swap profit is realized when customer item is resold)
        // Swap profit should be included in the profit calculation
        if (data.swaps) {
            const todaySwapProfit = parseFloat(data.swaps.today?.profit || data.swaps.period?.profit || 0);
            const weekSwapProfit = parseFloat(data.swaps.weekly?.profit || data.swaps.period?.profit || 0);
            const monthSwapProfit = parseFloat(data.swaps.monthly?.profit || data.swaps.period?.profit || 0);
            
            todayProfit += todaySwapProfit;
            weekProfit += weekSwapProfit;
            monthProfit += monthSwapProfit;
        }
        
        if (data.repairs) {
            const repairCount = data.repairs.today?.count || 0;
            const repairRevenue = data.repairs.today?.revenue || 0;
            todaySales += parseInt(repairCount || 0);
            todayRevenue += parseFloat(repairRevenue || 0);
            
            const weekRepairCount = data.repairs.weekly?.count || data.repairs.period?.count || 0;
            const weekRepairRevenue = data.repairs.weekly?.revenue || data.repairs.period?.revenue || 0;
            weekSales += parseInt(weekRepairCount || 0);
            weekRevenue += parseFloat(weekRepairRevenue || 0);
            
            const monthRepairCount = data.repairs.monthly?.count || 0;
            const monthRepairRevenue = data.repairs.monthly?.revenue || 0;
            monthSales += parseInt(monthRepairCount || 0);
            monthRevenue += parseFloat(monthRepairRevenue || 0);
        }
        
        // Update the UI
        safeSetText('allStaffTodaySales', todaySales);
        safeSetText('allStaffTodayRevenue', formatCurrency(todayRevenue));
        safeSetText('allStaffTodayProfit', formatCurrency(todayProfit));
        
        safeSetText('allStaffWeekSales', weekSales);
        safeSetText('allStaffWeekRevenue', formatCurrency(weekRevenue));
        safeSetText('allStaffWeekProfit', formatCurrency(weekProfit));
        
        safeSetText('allStaffMonthSales', monthSales);
        safeSetText('allStaffMonthRevenue', formatCurrency(monthRevenue));
        safeSetText('allStaffMonthProfit', formatCurrency(monthProfit));
    }

    // Update repairer stats (card style - showing totals across all repairers)
    function updateRepairerStats(technicians) {
        debugLog('Updating repairer stats with:', technicians);
        
        if (!technicians || technicians.length === 0) {
            // Show zero values if no technicians
            safeSetText('repairerStatsRepairsCount', '0');
            safeSetText('repairerStatsTotalRevenue', formatCurrency(0));
            safeSetText('repairerStatsWorkmanshipRevenue', formatCurrency(0));
            safeSetText('repairerStatsPartsRevenue', formatCurrency(0));
            safeSetText('repairerStatsPartsCount', '0');
            safeSetText('repairerStatsTotalProfit', formatCurrency(0));
            return;
        }
        
        // Calculate totals across all technicians
        let totalRepairs = 0;
        let totalWorkmanshipRevenue = 0;
        let totalPartsRevenue = 0;
        let totalPartsCount = 0;
        let totalWorkmanshipProfit = 0;
        let totalPartsProfit = 0;
        
        technicians.forEach(tech => {
            totalRepairs += parseInt(tech.repairs_count || 0);
            totalWorkmanshipRevenue += parseFloat(tech.workmanship_revenue || 0);
            totalPartsRevenue += parseFloat(tech.parts_revenue || 0);
            totalPartsCount += parseInt(tech.parts_count || 0);
            
            // Calculate profits
            const workmanshipRevenue = parseFloat(tech.workmanship_revenue || 0);
            const labourCost = parseFloat(tech.labour_cost || 0);
            const workmanshipProfit = parseFloat(tech.workmanship_profit || (workmanshipRevenue - labourCost));
            const partsProfit = parseFloat(tech.parts_profit || 0);
            
            totalWorkmanshipProfit += workmanshipProfit;
            totalPartsProfit += partsProfit;
        });
        
        const totalRevenue = totalWorkmanshipRevenue + totalPartsRevenue;
        // Only show parts profit (items sold by technician), not workmanship profit
        const totalProfit = totalPartsProfit;
        
        // Update the card with totals
        safeSetText('repairerStatsRepairsCount', totalRepairs);
        safeSetText('repairerStatsTotalRevenue', formatCurrency(totalRevenue));
        safeSetText('repairerStatsWorkmanshipRevenue', formatCurrency(totalWorkmanshipRevenue));
        safeSetText('repairerStatsPartsRevenue', formatCurrency(totalPartsRevenue));
        safeSetText('repairerStatsPartsCount', totalPartsCount);
        safeSetText('repairerStatsTotalProfit', formatCurrency(totalProfit));
        
        // Note: Repairer profit is now included in the backend profit calculation
        // So we don't need to add it again here to avoid double-counting
        // The backend profit already includes: Sales Profit + Swap Profit + Repairer Profit
        // We only update the repairer stats card, not the main profit display
    }

    // Update repairer parts sales display
    function updateRepairerPartsSales(data) {
        // Check if a staff is selected - hide section if viewing all staff
        const staffId = document.getElementById('filterStaff')?.value || '';
        if (!staffId) {
            // No staff selected (all staff view) - hide the section
            document.getElementById('repairerPartsSection').classList.add('hidden');
            return;
        }
        
        if (!data || !data.items || data.items.length === 0) {
            document.getElementById('repairerPartsSection').classList.add('hidden');
            return;
        }
        
        // Show section only when staff is selected
        document.getElementById('repairerPartsSection').classList.remove('hidden');
        
        // Update totals
        safeSetText('repairerPartsTotalRevenue', formatCurrency(data.total_revenue || 0));
        safeSetText('repairerPartsTotalProfit', formatCurrency(data.total_profit || 0));
        
        // Update table
        const tbody = document.getElementById('repairerPartsTableBody');
        tbody.innerHTML = '';
        
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                const row = document.createElement('tr');
                const date = new Date(item.sold_date);
                const dateStr = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                
                row.innerHTML = `
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900">${dateStr}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900 hidden sm:table-cell">${item.repairer_name || 'Unknown'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                        <a href="${BASE}/dashboard/repairs/${item.repair_id}" class="text-blue-600 hover:text-blue-800">
                            ${item.tracking_code || 'N/A'}
                        </a>
                    </td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900 hidden md:table-cell">${item.customer_name || 'Walk-in'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900">${item.product_name || 'Unknown Product'}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900">${item.quantity || 0}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right text-gray-900 hidden lg:table-cell">${formatCurrency(item.selling_price || 0)}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right text-gray-600 hidden lg:table-cell">${formatCurrency(item.cost_price || 0)}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right font-medium text-gray-900">${formatCurrency(item.revenue || 0)}</td>
                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-right font-medium text-green-600">${formatCurrency(item.profit || 0)}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-3 text-center text-gray-500">No items sold by repairers in this period</td></tr>';
        }
    }

    // Profit & Loss Breakdown function removed

    // Trace item from transaction (global function for onclick)
    window.traceItem = function(query) {
        if (!query || query === '') return;
        document.getElementById('traceSearchInput').value = query;
        document.getElementById('traceModal').classList.remove('hidden');
        performTraceSearch();
    };

    // Export data (global function for onclick)
    window.exportData = function(type, format) {
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        const staffSelect = document.getElementById('filterStaff');
        const staffId = staffSelect ? staffSelect.value : '';
        
        const params = new URLSearchParams();
        params.append('format', format);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (staffId) params.append('staff_id', staffId);

        window.location.href = `${BASE}/api/analytics/export/${type}?${params.toString()}`;
    }

    // Perform trace search
    async function performTraceSearch() {
        const query = document.getElementById('traceSearchInput').value.trim();
        if (!query) {
            alert('Please enter a search term');
            return;
        }

        const loadingEl = document.getElementById('loadingIndicator');
        loadingEl.classList.remove('hidden');

        try {
            console.log('Trace search: Searching for:', query);
            const response = await fetch(`${BASE}/api/analytics/trace?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            console.log('Trace search: Response:', data);
            if (data.debug) {
                console.log('Trace search: Debug info:', data.debug);
            }

            if (data.success && data.results && data.results.length > 0) {
                console.log('Trace search: Found', data.results.length, 'results');
                displayTraceResults(data.results);
            } else {
                console.log('Trace search: No results found');
                const resultsEl = document.getElementById('traceResults');
                const bodyEl = document.getElementById('traceResultsBody');
                let message = 'No results found';
                if (data.debug) {
                    message += ` (Query: ${data.debug.query}, Company ID: ${data.debug.company_id})`;
                    console.log('Trace search: Debug -', data.debug.message);
                }
                bodyEl.innerHTML = `<tr><td colspan="7" class="px-4 py-3 text-center text-gray-500">${message}</td></tr>`;
                resultsEl.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Trace search error:', error);
            alert('Error performing search: ' + error.message);
        } finally {
            loadingEl.classList.add('hidden');
        }
    }

    // Display trace results
    function displayTraceResults(results) {
        const resultsEl = document.getElementById('traceResults');
        const bodyEl = document.getElementById('traceResultsBody');
        
        bodyEl.innerHTML = '';

        if (results.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="7" class="px-4 py-3 text-center text-gray-500">No results found</td></tr>';
            resultsEl.classList.remove('hidden');
            return;
        }

        results.forEach(result => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 border-b border-gray-200';
            
            // Determine type badge color
            let typeBadgeClass = 'bg-blue-100 text-blue-800';
            if (result.type === 'swap') {
                typeBadgeClass = 'bg-purple-100 text-purple-800';
            } else if (result.type === 'repair') {
                typeBadgeClass = 'bg-orange-100 text-orange-800';
            } else if (result.type === 'sale') {
                typeBadgeClass = 'bg-green-100 text-green-800';
            }
            
            // Build action buttons based on type
            let actionButtons = '';
            if (result.type === 'swap' && result.id) {
                actionButtons = `
                    <a href="${BASE}/dashboard/swaps/${result.id}" target="_blank" 
                       class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 mr-2">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </a>
                    <a href="${BASE}/dashboard/swaps/${result.id}/receipt" target="_blank" 
                       class="inline-flex items-center px-3 py-1 text-xs font-medium text-green-700 bg-green-50 rounded hover:bg-green-100">
                        <i class="fas fa-receipt mr-1"></i> Receipt
                    </a>
                `;
            } else if (result.type === 'sale' && result.id) {
                actionButtons = `
                    <a href="${BASE}/dashboard/sales/${result.id}" target="_blank" 
                       class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </a>
                `;
            } else if (result.type === 'repair' && result.id) {
                actionButtons = `
                    <a href="${BASE}/dashboard/repairs/${result.id}" target="_blank" 
                       class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </a>
                `;
            }
            
            row.innerHTML = `
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium ${typeBadgeClass}">${result.type.toUpperCase()}</span>
                </td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-700">${new Date(result.date).toLocaleString()}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-900">${formatCurrency(result.amount || 0)}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-700 hidden sm:table-cell">${result.customer || '-'}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-700">${result.item || '-'}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-mono text-gray-600 hidden md:table-cell">${result.reference || '-'}</td>
                <td class="px-2 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm">${actionButtons || '-'}</td>
            `;
            bodyEl.appendChild(row);
        });

        resultsEl.classList.remove('hidden');
    }

    // Initialize charts
    function initializeCharts() {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    elements: {
                        line: {
                            tension: 0.4, // Curved lines
                            borderWidth: 2
                        },
                        point: {
                            radius: 4,
                            hitRadius: 10,
                            hoverRadius: 6
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            reverse: false, // Oldest on left, newest on right
                            offset: true, // Center the chart
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Profit Chart (Bubble Chart)
        const profitCtx = document.getElementById('profitChart');
        if (profitCtx) {
            profitChart = new Chart(profitCtx, {
                type: 'bubble',
                data: {
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    elements: {
                        point: {
                            borderWidth: 2,
                            hoverBorderWidth: 3
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                generateLabels: function(chart) {
                                    return [
                                        {
                                            text: 'Excellent (>40% margin)',
                                            fillStyle: 'rgba(16, 185, 129, 0.6)',
                                            strokeStyle: 'rgb(5, 150, 105)',
                                            lineWidth: 2
                                        },
                                        {
                                            text: 'Good (25-40% margin)',
                                            fillStyle: 'rgba(34, 197, 94, 0.6)',
                                            strokeStyle: 'rgb(22, 163, 74)',
                                            lineWidth: 2
                                        },
                                        {
                                            text: 'Medium (10-25% margin)',
                                            fillStyle: 'rgba(234, 179, 8, 0.6)',
                                            strokeStyle: 'rgb(202, 138, 4)',
                                            lineWidth: 2
                                        },
                                        {
                                            text: 'Low (0-10% margin)',
                                            fillStyle: 'rgba(251, 146, 60, 0.6)',
                                            strokeStyle: 'rgb(249, 115, 22)',
                                            lineWidth: 2
                                        },
                                        {
                                            text: 'Loss (negative profit)',
                                            fillStyle: 'rgba(239, 68, 68, 0.6)',
                                            strokeStyle: 'rgb(220, 38, 38)',
                                            lineWidth: 2
                                        }
                                    ];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const point = context[0].raw;
                                    if (point.label) {
                                        try {
                                            const date = new Date(point.label);
                                            if (!isNaN(date.getTime())) {
                                                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                            }
                                        } catch (e) {
                                            return point.label;
                                        }
                                    }
                                    return 'Profit Breakdown';
                                },
                                label: function(context) {
                                    const point = context.raw;
                                    const cost = point.cost || 0;
                                    const revenue = point.x || 0;
                                    const profit = point.y || 0;
                                    const profitMargin = revenue > 0 ? ((profit / revenue) * 100) : 0;
                                    
                                    return [
                                        'Revenue: ₵' + revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                                        'Profit: ₵' + profit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                                        'Cost: ₵' + cost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                                        'Margin: ' + profitMargin.toFixed(2) + '%'
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Revenue (₵)'
                            },
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toLocaleString();
                                }
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Profit (₵)'
                            },
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Top Products Chart
        const productsCtx = document.getElementById('topProductsChart');
        if (productsCtx) {
            topProductsChart = new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Top Customers Chart
        const customersCtx = document.getElementById('topCustomersChart');
        if (customersCtx) {
            topCustomersChart = new Chart(customersCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₵' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }
})();
</script>


