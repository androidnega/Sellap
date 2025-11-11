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

<div class="p-4 pb-4" data-server-rendered="true">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Audit Trail - Manager Analytics</h2>
        <p class="text-gray-600">Complete operational visibility, filtering, traceability, and export capabilities</p>
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
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
            <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">From Date</label>
                    <input type="date" id="filterDateFrom" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">To Date</label>
                    <input type="date" id="filterDateTo" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Customer</label>
                    <input type="text" id="filterCustomer" placeholder="Search customer..." class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Product/IMEI</label>
                    <input type="text" id="filterProduct" placeholder="Search product/IMEI..." class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Staff Member</label>
                    <select id="filterStaff" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="">All Staff</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button id="btnToday" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm" data-range="today">Today</button>
                <button id="btnThisWeek" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm" data-range="this_week">This Week</button>
                <button id="btnThisMonth" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm active" data-range="this_month">This Month</button>
                <button id="btnThisYear" class="date-filter-btn bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm" data-range="this_year">This Year</button>
                <div class="flex items-center gap-2 ml-2">
                    <label for="monthSelector" class="text-sm text-gray-600 font-medium">Select Month:</label>
                    <input type="month" id="monthSelector" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="">
                </div>
                <button id="btnApplyFilters" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-3 py-2 text-sm">
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
    <div id="keyMetricsSection" class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Key Metrics</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Sales & Revenue -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow p-6 border border-blue-200">
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
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-lg shadow p-6 border border-emerald-200">
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
            <div id="repairerStatsSection" class="bg-gradient-to-br from-indigo-50 to-purple-100 rounded-lg shadow p-6 border border-indigo-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 rounded-lg bg-indigo-200">
                        <i class="fas fa-wrench text-indigo-700 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-600">Repairs: <span id="repairerStatsRepairsCount">0</span></span>
                </div>
                <p class="text-sm font-medium text-gray-600 mb-1">Repairer Stats</p>
                <p class="text-2xl font-bold text-gray-900" id="repairerStatsTotalRevenue">₵0.00</p>
                <div class="mt-2 flex flex-wrap items-center text-xs text-gray-600">
                    <span>Workmanship: <span id="repairerStatsWorkmanshipRevenue">₵0.00</span></span>
                    <span class="mx-2">•</span>
                    <span>Parts Sales: <span id="repairerStatsPartsRevenue">₵0.00</span></span>
                    <span class="mx-2">•</span>
                    <span>Products Sold: <span id="repairerStatsPartsCount">0</span></span>
                    <span class="mx-2">•</span>
                    <span>Profit: <span id="repairerStatsTotalProfit">₵0.00</span></span>
                </div>
            </div>
            
            <!-- Swapping Stats -->
            <div class="bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg shadow p-6 border border-amber-200">
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
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-4 border border-gray-200" id="audit-payment-status-card" style="display: none;">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-money-bill-wave text-gray-600 mr-2"></i>
                    Payment Status
                </h4>
            </div>
            <div class="grid grid-cols-3 gap-4">
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
    <div id="staffDetailSection" class="mb-6 hidden">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800" id="staffDetailName">Staff Member Details</h3>
                    <p class="text-gray-600" id="staffDetailRole">Role</p>
                </div>
                <button onclick="clearStaffSelection()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 rounded px-4 py-2 text-sm">
                    <i class="fas fa-times mr-2"></i> View All Staff
                </button>
            </div>
            
            <!-- Staff Performance Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <p class="text-sm font-medium text-gray-600">Total Sales</p>
                    <p class="text-2xl font-bold text-gray-900" id="staffTotalSales">0</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900" id="staffTotalRevenue">₵0.00</p>
                </div>
                <div class="bg-emerald-50 rounded-lg p-4 border border-emerald-200">
                    <p class="text-sm font-medium text-gray-600">Total Profit</p>
                    <p class="text-2xl font-bold text-gray-900" id="staffTotalProfit">₵0.00</p>
                </div>
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <p class="text-sm font-medium text-gray-600">Total Losses</p>
                    <p class="text-2xl font-bold text-gray-900" id="staffTotalLosses">₵0.00</p>
                </div>
            </div>
            
            <!-- Technician-Specific Breakdown (shown only for technicians) -->
            <div id="technicianBreakdownSection" class="hidden mb-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-4">Repair Breakdown</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                        <p class="text-sm font-medium text-gray-600">Workmanship Revenue</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffWorkmanshipRevenue">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Repair charges</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                        <p class="text-sm font-medium text-gray-600">Labour Cost</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffLabourCost">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Cost of labour</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-sm font-medium text-gray-600">Workmanship Profit</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffWorkmanshipProfit">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Revenue - Labour Cost</p>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                        <p class="text-sm font-medium text-gray-600">Parts & Accessories Revenue</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffPartsRevenue">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Spare parts sold</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-sm font-medium text-gray-600">Products Sold</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffPartsCount">0</p>
                        <p class="text-xs text-gray-500 mt-1">Spare parts count</p>
                    </div>
                    <div class="bg-teal-50 rounded-lg p-4 border border-teal-200">
                        <p class="text-sm font-medium text-gray-600">Parts Profit</p>
                        <p class="text-2xl font-bold text-gray-900" id="staffPartsProfit">₵0.00</p>
                        <p class="text-xs text-gray-500 mt-1">Parts revenue - cost</p>
                    </div>
                </div>
            </div>
            
            <!-- Staff Breakdown by Period -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">Today</h4>
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
            <div class="mt-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-4">Transaction History</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Status</th>
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

    <!-- Profit/Loss Breakdown Section -->
    <div id="profitLossBreakdownSection" class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Profit & Loss Breakdown</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Daily Breakdown -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-4">Daily</h4>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b sticky top-0 bg-white">
                                <th class="text-left py-2">Date</th>
                                <th class="text-right py-2">Sales</th>
                                <th class="text-right py-2">Revenue</th>
                                <th class="text-right py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="dailyBreakdownBody">
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Weekly Breakdown -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-4">Weekly</h4>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b sticky top-0 bg-white">
                                <th class="text-left py-2">Week</th>
                                <th class="text-right py-2">Sales</th>
                                <th class="text-right py-2">Revenue</th>
                                <th class="text-right py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="weeklyBreakdownBody">
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Monthly Breakdown -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-4">Monthly</h4>
                <div id="monthlyBreakdownContainer" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="text-left py-2">Month</th>
                                <th class="text-right py-2">Sales</th>
                                <th class="text-right py-2">Revenue</th>
                                <th class="text-right py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="monthlyBreakdownBody">
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Revenue Trend</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Profit Breakdown</h3>
            <canvas id="profitChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Products</h3>
            <canvas id="topProductsChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Customers</h3>
            <canvas id="topCustomersChart" height="200"></canvas>
        </div>
    </div>

    <!-- Items Sold by Repairers Section -->
    <div id="repairerPartsSection" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Items Sold by Repairers</h3>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-600">
                    <span class="font-medium">Total Revenue:</span>
                    <span id="repairerPartsTotalRevenue" class="ml-2 font-bold text-gray-900">₵0.00</span>
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-medium">Total Profit:</span>
                    <span id="repairerPartsTotalProfit" class="ml-2 font-bold text-green-600">₵0.00</span>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Repairer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Repair ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
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

    <!-- Recent Transactions Table -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Recent Transactions</h3>
            <select id="transactionTypeFilter" class="border border-gray-300 rounded px-3 py-2 text-sm">
                <option value="all">All Types</option>
                <option value="sale">Sales</option>
                <option value="repair">Repairs</option>
                <option value="swap">Swaps</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table id="transactionsTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="transactionsTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alerts Panel -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Active Alerts</h3>
            <button id="btnTestAlerts" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-4 py-2 text-sm">
                <i class="fas fa-bell mr-1"></i> Test Alerts
            </button>
        </div>
        <div id="alertsPanel" class="space-y-3">
            <!-- Alerts will be loaded here -->
        </div>
    </div>

    <!-- Audit Logs Live Feed -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Audit Trail - Live Feed</h3>
            <div class="flex gap-2">
                <select id="auditEventTypeFilter" class="border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="">All Events</option>
                    <option value="sale.created">Sales</option>
                    <option value="swap.completed">Swaps</option>
                    <option value="repair.created">Repairs</option>
                    <option value="user.login">User Logins</option>
                </select>
                <button id="btnRefreshAuditLogs" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded px-3 py-2 text-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="auditLogsBody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center text-gray-500">Loading audit logs...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <button id="btnLoadMoreAuditLogs" class="text-blue-600 hover:text-blue-800 text-sm">
                Load More
            </button>
        </div>
    </div>

    <!-- Event Viewer Modal -->
    <div id="eventViewerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Event Details</h3>
                    <button id="btnCloseEventViewer" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div id="eventViewerContent" class="space-y-4">
                    <!-- Event details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Trace Search Button -->
    <div class="mb-6">
        <button id="btnOpenTraceModal" class="bg-purple-600 hover:bg-purple-700 text-white rounded px-6 py-3 font-medium">
            <i class="fas fa-search mr-2"></i> Trace Item / Customer / Device
        </button>
    </div>

    <!-- Trace Modal -->
    <div id="traceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Item Trace</h3>
                    <button id="btnCloseTraceModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-4">Search across all modules by IMEI, product ID, customer phone, or sale ID</p>
                <div class="flex gap-3 mb-4">
                    <input type="text" id="traceSearchInput" placeholder="Enter IMEI, product ID, customer phone, or sale ID..." class="flex-1 border border-gray-300 rounded px-4 py-2" />
                    <button id="btnTraceSearch" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-6 py-2">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
                <div id="traceResults" class="hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
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
    let revenueChart = null;
    let profitChart = null;
    let topProductsChart = null;
    let topCustomersChart = null;

    let forecastChart = null;

    // Authentication helpers
    function getToken() {
        return localStorage.getItem('token') || localStorage.getItem('sellapp_token');
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
        loadAlerts();
        loadAuditLogs();
        setupEventListeners();
        initializeCharts();
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            loadAlerts();
            loadAuditLogs();
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
            
            console.log('loadLiveData called with:', {
                dateRange,
                dateFrom,
                dateTo,
                staffId: staffId || 'none'
            });
            
            const params = new URLSearchParams();
            params.append('date_range', dateRange);
            params.append('module', 'all');
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (staffId) params.append('staff_id', staffId);
            
            console.log('Fetching:', `${BASE}/api/audit-trail/data?${params.toString()}`);
            
            const response = await fetch(`${BASE}/api/audit-trail/data?${params.toString()}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin' // Include session cookies for fallback auth
            });
            const data = await response.json();

            console.log('loadLiveData response:', {
                success: data.success,
                hasBreakdown: !!data.profit_loss_breakdown,
                breakdownKeys: data.profit_loss_breakdown ? Object.keys(data.profit_loss_breakdown) : [],
                dailyCount: data.profit_loss_breakdown?.daily?.length ?? 0,
                weeklyCount: data.profit_loss_breakdown?.weekly?.length ?? 0,
                monthlyCount: data.profit_loss_breakdown?.monthly?.length ?? 0
            });

            if (data.success) {
                // Update all metrics with live data
                updateLiveMetrics(data);
                // Update profit/loss breakdown
                if (data.profit_loss_breakdown) {
                    console.log('Calling updateProfitLossBreakdown with data:', data.profit_loss_breakdown);
                    updateProfitLossBreakdown(data.profit_loss_breakdown);
                } else {
                    console.warn('No profit_loss_breakdown in response, clearing data');
                    updateProfitLossBreakdown({ daily: [], weekly: [], monthly: [] });
                }
                
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
                pending: data.swaps.pending || 0,
                monthly: {
                    count: data.swaps.monthly?.count || 0,
                    revenue: data.swaps.monthly?.revenue || 0
                },
                profit: data.swaps.profit || 0,
                filtered: {
                    revenue: data.swaps.monthly?.revenue || 0
                }
            } : null,
            inventory: data.inventory || null
        };
        
        // Use the same updateMetrics function for consistency
        updateMetrics(metrics, data.enabled_modules || []);
        
        // Update payment stats if available
        if (data.payment_stats) {
            const paymentCard = document.getElementById('audit-payment-status-card');
            if (paymentCard) {
                paymentCard.style.display = 'block';
                document.getElementById('audit-fully-paid').textContent = data.payment_stats.fully_paid || 0;
                document.getElementById('audit-partial').textContent = data.payment_stats.partial || 0;
                document.getElementById('audit-unpaid').textContent = data.payment_stats.unpaid || 0;
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
                            revenue: data.swaps.monthly?.revenue || 0
                        },
                        profit: data.swaps.profit || 0,
                        period: data.swaps.period || data.swaps.filtered || {
                            count: 0,
                            revenue: 0,
                            profit: 0
                        }
                    } : null,
                    inventory: data.inventory || null
                };
                
                updateMetrics(metrics, data.enabled_modules || []);
                
                // Update profit/loss breakdown - always update to clear old data when date range changes
                // API now always returns breakdown structure (even if empty arrays)
                if (data.profit_loss_breakdown) {
                    console.log('Profit/Loss Breakdown data received from analytics:', {
                        hasBreakdown: !!data.profit_loss_breakdown,
                        breakdown: data.profit_loss_breakdown,
                        dailyCount: data.profit_loss_breakdown?.daily?.length ?? 0,
                        weeklyCount: data.profit_loss_breakdown?.weekly?.length ?? 0,
                        monthlyCount: data.profit_loss_breakdown?.monthly?.length ?? 0
                    });
                    updateProfitLossBreakdown(data.profit_loss_breakdown);
                } else {
                    // If no breakdown in response, clear it (date range might have no sales)
                    console.log('No profit_loss_breakdown in analytics response - clearing breakdown');
                    updateProfitLossBreakdown({ daily: [], weekly: [], monthly: [] });
                }
                
                // Load transactions from activity logs
                loadTransactionsFromData(data);
                
                // Update repairer stats table
                console.log('Staff activity data:', data.staff_activity);
                console.log('Date range:', data.date_range);
                console.log('Company ID:', data.company_id);
                console.log('Full API response keys:', Object.keys(data));
                if (data.staff_activity) {
                    console.log('Staff activity keys:', Object.keys(data.staff_activity));
                    console.log('Technicians array:', data.staff_activity.technicians);
                    console.log('Technicians count:', data.staff_activity.technicians ? data.staff_activity.technicians.length : 'null/undefined');
                    console.log('Total technicians:', data.staff_activity.total_technicians);
                    
                    if (data.staff_activity.technicians && Array.isArray(data.staff_activity.technicians) && data.staff_activity.technicians.length > 0) {
                        console.log('Technicians found:', data.staff_activity.technicians.length);
                        console.log('Technicians data:', JSON.stringify(data.staff_activity.technicians, null, 2));
                        updateRepairerStats(data.staff_activity.technicians);
                    } else {
                        console.log('No technicians array found or array is empty');
                        console.log('Staff activity structure:', JSON.stringify(data.staff_activity, null, 2));
                        updateRepairerStats([]);
                    }
                } else {
                    console.log('No staff_activity in response');
                    console.log('Available keys:', Object.keys(data));
                    updateRepairerStats([]);
                }
                
                // Update repairer parts sales section
                if (data.repairer_parts_sales) {
                    updateRepairerPartsSales(data.repairer_parts_sales);
                } else {
                    document.getElementById('repairerPartsSection').classList.add('hidden');
                }
                
                // If staff is selected, load staff details
                const staffId = document.getElementById('filterStaff').value;
                if (staffId) {
                    loadStaffDetails(staffId);
                } else {
                    // Hide staff detail section if no staff selected
                    document.getElementById('staffDetailSection').classList.add('hidden');
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

    // Update metrics display
    function updateMetrics(metrics, enabledModules) {
        // Helper function to safely set text content
        function safeSetText(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
                return true;
            }
            return false;
        }
        
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
        if (metrics.swaps) {
            // Use period data if available (date filtered), otherwise use monthly
            const swapCount = metrics.swaps.period?.count ?? metrics.swaps.monthly?.count ?? 0;
            const swapRevenue = metrics.swaps.period?.revenue ?? metrics.swaps.monthly?.revenue ?? 0;
            const swapProfit = metrics.swaps.period?.profit ?? metrics.swaps.profit ?? 0;
            const pendingSwaps = metrics.swaps.pending ?? 0;
            
            safeSetText('metric-swap-total', swapCount);
            safeSetText('metric-swap-pending', pendingSwaps);
            safeSetText('metric-swap-revenue', formatCurrency(swapRevenue));
            safeSetText('metric-swap-profit', formatCurrency(swapProfit));
            safeSetText('swapTotalRevenue', formatCurrency(swapRevenue));
        } else {
            safeSetText('metric-swap-total', '0');
            safeSetText('metric-swap-pending', '0');
            safeSetText('metric-swap-revenue', formatCurrency(0));
            safeSetText('metric-swap-profit', formatCurrency(0));
            safeSetText('swapTotalRevenue', formatCurrency(0));
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
                    loadStaffDetails(staffId);
                    loadAnalytics(); // Reload analytics with staff filter
                } else {
                    // Hide staff detail section and show all data
                    document.getElementById('staffDetailSection').classList.add('hidden');
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
            loadAuditLogs();
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
            document.getElementById('traceModal').classList.remove('hidden');
        });
        document.getElementById('btnCloseTraceModal').addEventListener('click', function() {
            document.getElementById('traceModal').classList.add('hidden');
        });
        document.getElementById('traceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
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

        // Alerts
        const btnTestAlerts = document.getElementById('btnTestAlerts');
        if (btnTestAlerts) {
            btnTestAlerts.addEventListener('click', testAlerts);
        }

        // Audit logs
        const btnRefreshAuditLogs = document.getElementById('btnRefreshAuditLogs');
        if (btnRefreshAuditLogs) {
            btnRefreshAuditLogs.addEventListener('click', loadAuditLogs);
        }
        const auditEventTypeFilter = document.getElementById('auditEventTypeFilter');
        if (auditEventTypeFilter) {
            auditEventTypeFilter.addEventListener('change', loadAuditLogs);
        }
        const btnLoadMoreAuditLogs = document.getElementById('btnLoadMoreAuditLogs');
        if (btnLoadMoreAuditLogs) {
            btnLoadMoreAuditLogs.addEventListener('click', loadMoreAuditLogs);
        }

        // Event viewer modal
        const btnCloseEventViewer = document.getElementById('btnCloseEventViewer');
        if (btnCloseEventViewer) {
            btnCloseEventViewer.addEventListener('click', function() {
                const eventViewerModal = document.getElementById('eventViewerModal');
                if (eventViewerModal) {
                    eventViewerModal.classList.add('hidden');
                }
            });
        }
        const eventViewerModal = document.getElementById('eventViewerModal');
        if (eventViewerModal) {
            eventViewerModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        }
    }


    let auditLogsOffset = 0;
    const auditLogsLimit = 50;

    // Load alerts
    async function loadAlerts() {
        try {
            const response = await fetch(`${BASE}/api/analytics/alerts?unhandled_only=true&limit=10`);
            const data = await response.json();

            if (data.success && data.notifications) {
                displayAlerts(data.notifications);
            }
        } catch (error) {
            console.error('Error loading alerts:', error);
        }
    }

    // Display alerts
    function displayAlerts(notifications) {
        const panel = document.getElementById('alertsPanel');
        
        if (notifications.length === 0) {
            panel.innerHTML = '<p class="text-gray-500 text-sm">No active alerts</p>';
            return;
        }

        panel.innerHTML = notifications.map(notif => {
            const severityColors = {
                'critical': 'bg-red-100 border-red-300 text-red-800',
                'warning': 'bg-yellow-100 border-yellow-300 text-yellow-800',
                'info': 'bg-blue-100 border-blue-300 text-blue-800'
            };
            const colorClass = severityColors[notif.severity] || severityColors['warning'];
            
            return `
                <div class="border rounded-lg p-4 ${colorClass} cursor-pointer hover:shadow-md transition-shadow" onclick="viewAlertDetails('${notif.id}')">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold mb-1">${notif.alert_title}</div>
                            <div class="text-sm opacity-90">${new Date(notif.triggered_at).toLocaleString()}</div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="event.stopPropagation(); acknowledgeAlert(${notif.id})" class="bg-white hover:bg-gray-100 rounded px-3 py-1 text-sm">
                                Acknowledge
                            </button>
                            <button onclick="event.stopPropagation(); clearAlert(${notif.id})" class="bg-white hover:bg-red-100 text-red-600 rounded px-3 py-1 text-sm">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Acknowledge alert
    window.acknowledgeAlert = async function(notificationId) {
        try {
            const response = await fetch(`${BASE}/api/analytics/alerts/${notificationId}/acknowledge`, {
                method: 'POST',
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success) {
                loadAlerts();
                showNotification('Alert acknowledged', 'success');
            } else {
                showNotification('Failed to acknowledge alert: ' + (data.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Error acknowledging alert:', error);
            showNotification('Error acknowledging alert', 'error');
        }
    };
    
    // Clear alert
    window.clearAlert = async function(notificationId) {
        if (!confirm('Are you sure you want to clear this alert?')) {
            return;
        }
        
        try {
            // For now, just acknowledge it (alerts are managed by the alert service)
            await acknowledgeAlert(notificationId);
            showNotification('Alert cleared', 'success');
        } catch (error) {
            console.error('Error clearing alert:', error);
            showNotification('Error clearing alert', 'error');
        }
    };
    
    // View alert details
    window.viewAlertDetails = function(notificationId) {
        // Navigate to notifications page with the alert ID
        window.location.href = `${BASE}/dashboard/notifications?view=${notificationId}`;
    };

    // Test alerts
    async function testAlerts() {
        try {
            const response = await fetch(`${BASE}/api/analytics/alerts/test`, {
                method: 'POST'
            });
            const data = await response.json();

            if (data.success) {
                alert(`Alert check completed. Triggered ${data.count} alerts.`);
                loadAlerts();
            }
        } catch (error) {
            console.error('Error testing alerts:', error);
        }
    }


    // Load audit logs
    async function loadAuditLogs() {
        auditLogsOffset = 0;
        const tbody = document.getElementById('auditLogsBody');
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">Loading...</td></tr>';

        try {
            const eventType = document.getElementById('auditEventTypeFilter').value;
            const dateFrom = document.getElementById('filterDateFrom')?.value;
            const dateTo = document.getElementById('filterDateTo')?.value;
            
            const params = new URLSearchParams();
            params.append('limit', auditLogsLimit);
            params.append('offset', auditLogsOffset);
            if (eventType) {
                params.append('event_type', eventType);
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
            const data = await response.json();

            if (data.success && data.logs) {
                displayAuditLogs(data.logs);
                auditLogsOffset = data.logs.length;
            } else if (data.error) {
                console.error('API Error:', data.error);
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-3 text-center text-red-500">Error: ${data.error}</td></tr>`;
            }
        } catch (error) {
            console.error('Error loading audit logs:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-red-500">Error loading audit logs</td></tr>';
        }
    }

    // Load more audit logs
    async function loadMoreAuditLogs() {
        try {
            const eventType = document.getElementById('auditEventTypeFilter').value;
            const dateFrom = document.getElementById('filterDateFrom')?.value;
            const dateTo = document.getElementById('filterDateTo')?.value;
            
            const params = new URLSearchParams();
            params.append('limit', auditLogsLimit);
            params.append('offset', auditLogsOffset);
            if (eventType) {
                params.append('event_type', eventType);
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
            const data = await response.json();

            if (data.success && data.logs) {
                appendAuditLogs(data.logs);
                auditLogsOffset += data.logs.length;
            }
        } catch (error) {
            console.error('Error loading more audit logs:', error);
        }
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
                ? `<a href="#" onclick="viewEvent(${log.id})" class="text-blue-600 hover:text-blue-800">${log.entity_type} #${log.entity_id}</a>`
                : '-';
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">${new Date(log.created_at).toLocaleString()}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100">${log.event_type}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">${log.user_name || log.user_full_name || '-'}</td>
                    <td class="px-4 py-3 text-sm">${entityLink}</td>
                    <td class="px-4 py-3 text-sm">${log.ip_address || '-'}</td>
                    <td class="px-4 py-3 text-sm">
                        <button onclick="viewEvent(${log.id})" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-eye"></i> View
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
                ? `<a href="#" onclick="viewEvent(${log.id})" class="text-blue-600 hover:text-blue-800">${log.entity_type} #${log.entity_id}</a>`
                : '-';
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">${new Date(log.created_at).toLocaleString()}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100">${log.event_type}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">${log.user_name || log.user_full_name || '-'}</td>
                    <td class="px-4 py-3 text-sm">${entityLink}</td>
                    <td class="px-4 py-3 text-sm">${log.ip_address || '-'}</td>
                    <td class="px-4 py-3 text-sm">
                        <button onclick="viewEvent(${log.id})" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        tbody.innerHTML = existing + newRows;
    }

    // View event details
    window.viewEvent = async function(logId) {
        try {
            // Fetch all logs and find the one we need (in production, add a specific endpoint)
            const response = await fetch(`${BASE}/api/analytics/audit-logs?limit=1000`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success && data.logs) {
                const log = data.logs.find(l => l.id == logId);
                
                if (!log) {
                    alert('Event not found');
                    return;
                }
                
                const modal = document.getElementById('eventViewerModal');
                const content = document.getElementById('eventViewerContent');
                
                content.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Event Type</label>
                            <div class="text-lg font-semibold">${log.event_type}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Time</label>
                            <div>${new Date(log.created_at).toLocaleString()}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">User</label>
                            <div>${log.user_name || log.user_full_name || '-'}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">IP Address</label>
                            <div>${log.ip_address || '-'}</div>
                        </div>
                        <div class="col-span-2">
                            <label class="text-sm font-medium text-gray-600">Entity</label>
                            <div>${log.entity_type || '-'} ${log.entity_id ? '#' + log.entity_id : ''}</div>
                        </div>
                        <div class="col-span-2">
                            <label class="text-sm font-medium text-gray-600">Payload</label>
                            <pre class="bg-gray-50 p-4 rounded text-xs overflow-auto max-h-96">${JSON.stringify(log.payload || {}, null, 2)}</pre>
                        </div>
                    </div>
                `;
                
                modal.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading event:', error);
            alert('Error loading event details');
        }
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
            
            console.log('Charts API response:', {
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
                console.warn('Charts API failed, keeping existing chart data');
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

        // Profit chart
        if (charts.profit && profitChart) {
            let labels = charts.profit.labels || [];
            let datasets = charts.profit.datasets || [];
            
            // Filter to recent dates only
            const filtered = filterRecentDates(labels, datasets);
            labels = filtered.labels;
            datasets = filtered.datasets;
            
            if (labels.length > 0 && datasets.length > 0) {
                // Update colors to be more vibrant (no red) and add curve tension
                datasets = datasets.map((dataset, idx) => {
                    const colors = [
                        { border: 'rgb(34, 197, 94)', bg: 'rgba(34, 197, 94, 0.1)' }, // Green
                        { border: 'rgb(59, 130, 246)', bg: 'rgba(59, 130, 246, 0.1)' }, // Blue
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
                
                profitChart.data.labels = labels.map(label => {
                    try {
                        if (typeof label === 'string' && label.includes('-')) {
                const date = new Date(label);
                            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            }
                        }
                        return label;
                    } catch (e) {
                        return label;
                    }
                });
                profitChart.data.datasets = datasets;
                
                // Center the chart by adjusting the x-axis
                if (labels.length > 0) {
                    profitChart.options.scales.x.offset = true;
                }
                
                profitChart.update('none');
            }
        }

        // Top products chart
        console.log('Top Products chart check:', {
            hasTopProducts: !!charts.topProducts,
            topProductsData: charts.topProducts,
            chartInitialized: !!topProductsChart
        });
        
        if (charts.topProducts) {
            const labels = charts.topProducts.labels || [];
            const datasets = charts.topProducts.datasets || [];
            
            console.log('Top Products data:', {
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
                console.log('Top Products chart not initialized, canvas element:', !!productsCtx);
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
                    console.log('Top Products chart initialized');
                } else {
                    console.warn('Top Products chart canvas element not found');
                }
            }
            
            if (topProductsChart) {
                if (labels.length > 0 && datasets.length > 0 && datasets[0]?.data?.length > 0) {
                    topProductsChart.data.labels = labels;
                    topProductsChart.data.datasets = datasets;
                    topProductsChart.update('none');
                    console.log('Top Products chart updated with', labels.length, 'products');
                } else if (labels.length > 0 && datasets.length > 0) {
                    // Even if data array is empty, update with empty data to show labels
                    topProductsChart.data.labels = labels;
                    topProductsChart.data.datasets = datasets;
                    topProductsChart.update('none');
                    console.log('Top Products chart updated with labels but empty data');
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
                    console.warn('Top Products chart: No data available');
                }
            } else {
                console.warn('Top Products chart not available after initialization attempt');
            }
        } else {
            console.warn('Top Products chart data not found in charts object');
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
                
                if (activityLogs.length > 0) {
                    // Convert activity logs to transaction format
                    transactions = activityLogs
                        .filter(log => {
                            // Filter by type if needed
                            if (typeFilter === 'all') return true;
                            const logType = (log.activity_type || log.type || '').toLowerCase();
                            const filterType = typeFilter.toLowerCase();
                            // Map: sale/sales, repair/repairs, swap/swaps
                            if (filterType === 'sale') return logType === 'sale' || logType === 'sales';
                            if (filterType === 'repair') return logType === 'repair' || logType === 'repairs';
                            if (filterType === 'swap') return logType === 'swap' || logType === 'swaps';
                            return logType === filterType;
                        })
                        .slice(0, 50) // Limit to 50 most recent
                        .map(log => {
                            const amount = parseFloat(log.amount || log.revenue || 0);
                            const type = (log.activity_type || log.type || 'unknown').toLowerCase();
                            
                            return {
                                type: type,
                                date: log.timestamp || log.date || log.created_at || new Date().toISOString(),
                                customer: log.customer_name || log.customer || 'Walk-in Customer',
                                amount: amount,
                                status: log.status || 'completed',
                                reference: log.reference || log.unique_id || log.id || '-',
                                item: log.item || log.item_description || log.description || '-'
                            };
                        });
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
            transactions = data.activity_logs
                .filter(log => {
                    // Filter by type if needed
                    if (typeFilter === 'all') return true;
                    const logType = (log.activity_type || log.type || '').toLowerCase();
                    const filterType = typeFilter.toLowerCase();
                    // Map: sale/sales, repair/repairs, swap/swaps
                    if (filterType === 'sale') return logType === 'sale' || logType === 'sales';
                    if (filterType === 'repair') return logType === 'repair' || logType === 'repairs';
                    if (filterType === 'swap') return logType === 'swap' || logType === 'swaps';
                    return logType === filterType;
                })
                .slice(0, 50) // Limit to 50 most recent
                .map(log => {
                    const amount = parseFloat(log.amount || log.revenue || 0);
                    const type = (log.activity_type || log.type || 'unknown').toLowerCase();
                    
                    return {
                        type: type,
                        date: log.timestamp || log.date || log.created_at || new Date().toISOString(),
                        customer: log.customer_name || log.customer || 'Walk-in Customer',
                        amount: amount,
                        status: log.status || 'completed',
                        reference: log.reference || log.unique_id || log.id || '-',
                        item: log.item || log.item_description || log.description || '-'
                    };
                });
        }
        
        displayTransactions(transactions);
    }

    // Display transactions in table
    function displayTransactions(transactions) {
        const tbody = document.getElementById('transactionsTableBody');
        if (!tbody) {
            console.warn('transactionsTableBody element not found');
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
                'swaps': 'bg-purple-100 text-purple-800'
            };
            const typeBadgeClass = typeClass[transaction.type] || 'bg-gray-100 text-gray-800';
            const typeLabel = transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1);
            
            // Format date
            let dateStr = '-';
            try {
                const date = new Date(transaction.date);
                if (!isNaN(date.getTime())) {
                    dateStr = date.toLocaleString();
                }
            } catch (e) {
                console.warn('Invalid date:', transaction.date);
            }
            
            // Format status
            const status = transaction.status || 'pending';
            const statusClass = (status === 'completed' || status === 'PAID' || status === 'paid') 
                ? 'bg-green-100 text-green-800' 
                : 'bg-yellow-100 text-yellow-800';
            
            row.innerHTML = `
                <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium ${typeBadgeClass}">${typeLabel}</span>
                </td>
                <td class="px-4 py-3 text-sm">${dateStr}</td>
                <td class="px-4 py-3 text-sm">${transaction.customer || '-'}</td>
                <td class="px-4 py-3 text-sm font-medium">${formatCurrency(transaction.amount || 0)}</td>
                <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium ${statusClass}">${status}</span>
                </td>
                <td class="px-4 py-3 text-sm">
                    ${transaction.reference && transaction.reference !== '-' ? `<button onclick="traceItem('${transaction.reference}')" class="text-blue-600 hover:text-blue-800 text-sm">View</button>` : '-'}
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
        document.getElementById('staffDetailName').textContent = staffName;
        document.getElementById('staffDetailRole').textContent = staffRole;
        
        // Hide technician breakdown initially (will be shown if technician data exists)
        document.getElementById('technicianBreakdownSection').classList.add('hidden');
        
        // Calculate totals from sales data (which is already filtered by staff_id)
        let totalSales = 0;
        let totalRevenue = 0;
        let totalProfit = 0;
        let totalLosses = 0;
        
        // Use sales data directly (already filtered by staff_id when staff is selected)
        if (data.sales) {
            // Use period data if available (for selected date range), otherwise use monthly
            // period is the same as filtered, which uses the date_from and date_to parameters
            if (data.sales.period && (data.sales.period.count > 0 || data.sales.period.revenue > 0)) {
                totalSales = parseInt(data.sales.period.count || 0);
                totalRevenue = parseFloat(data.sales.period.revenue || 0);
            } else if (data.sales.filtered && (data.sales.filtered.count > 0 || data.sales.filtered.revenue > 0)) {
                totalSales = parseInt(data.sales.filtered.count || 0);
                totalRevenue = parseFloat(data.sales.filtered.revenue || 0);
            } else if (data.sales.monthly) {
                totalSales = parseInt(data.sales.monthly.count || 0);
                totalRevenue = parseFloat(data.sales.monthly.revenue || 0);
            } else if (data.sales.today) {
                totalSales = parseInt(data.sales.today.count || 0);
                totalRevenue = parseFloat(data.sales.today.revenue || 0);
            }
            
            // Also check staff_activity for additional data (swaps, repairs)
        if (data.staff_activity) {
            // Salesperson data
            if (data.staff_activity.salespersons && data.staff_activity.salespersons.length > 0) {
                const staff = data.staff_activity.salespersons[0];
                    // Use sales data if it's higher (more accurate)
                    if (staff.sales_count > totalSales) {
                totalSales = parseInt(staff.sales_count || 0);
                totalRevenue = parseFloat(staff.sales_revenue || 0);
                    }
            }
            
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
                    document.getElementById('technicianBreakdownSection').classList.remove('hidden');
                    document.getElementById('staffWorkmanshipRevenue').textContent = formatCurrency(workmanshipRevenue);
                    document.getElementById('staffLabourCost').textContent = formatCurrency(labourCost);
                    document.getElementById('staffWorkmanshipProfit').textContent = formatCurrency(workmanshipProfit);
                    document.getElementById('staffPartsRevenue').textContent = formatCurrency(partsRevenue);
                    document.getElementById('staffPartsCount').textContent = partsCount;
                    document.getElementById('staffPartsProfit').textContent = formatCurrency(partsProfit);
                } else {
                    // Hide technician breakdown for non-technicians
                    document.getElementById('technicianBreakdownSection').classList.add('hidden');
                }
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
        document.getElementById('staffTotalSales').textContent = totalSales;
        document.getElementById('staffTotalRevenue').textContent = formatCurrency(totalRevenue);
        document.getElementById('staffTotalProfit').textContent = formatCurrency(totalProfit);
        document.getElementById('staffTotalLosses').textContent = formatCurrency(totalLosses);
        
        // Update period breakdowns
        if (data.sales) {
            document.getElementById('staffTodaySales').textContent = data.sales.today?.count || 0;
            document.getElementById('staffTodayRevenue').textContent = formatCurrency(data.sales.today?.revenue || 0);
            
            // Calculate week data (last 7 days)
            const weekRevenue = data.sales.period?.revenue || data.sales.monthly?.revenue || 0;
            const weekCount = data.sales.period?.count || data.sales.monthly?.count || 0;
            document.getElementById('staffWeekSales').textContent = weekCount;
            document.getElementById('staffWeekRevenue').textContent = formatCurrency(weekRevenue);
            
            document.getElementById('staffMonthSales').textContent = data.sales.monthly?.count || 0;
            document.getElementById('staffMonthRevenue').textContent = formatCurrency(data.sales.monthly?.revenue || 0);
        }
        
        // Calculate period profits - use profit_loss_breakdown as fallback
        let todayProfit = 0;
        let weekProfit = 0;
        let monthProfit = 0;
        
        if (data.profit) {
            todayProfit = parseFloat(data.profit.profit || 0);
            weekProfit = parseFloat(data.profit.profit || 0);
            monthProfit = parseFloat(data.profit.profit || 0);
        }
        
        // Fallback to profit_loss_breakdown if profit is 0
        if ((todayProfit === 0 && weekProfit === 0 && monthProfit === 0) && data.profit_loss_breakdown) {
            // Get today's profit from daily breakdown
            if (data.profit_loss_breakdown.daily && data.profit_loss_breakdown.daily.length > 0) {
                const today = new Date().toISOString().split('T')[0];
                const todayData = data.profit_loss_breakdown.daily.find(d => d.date === today || d.date_str === today);
                if (todayData) {
                    todayProfit = parseFloat(todayData.profit || 0);
                }
            }
            
            // Get week profit from weekly breakdown
            if (data.profit_loss_breakdown.weekly && data.profit_loss_breakdown.weekly.length > 0) {
                // Get the most recent week
                const latestWeek = data.profit_loss_breakdown.weekly[0];
                weekProfit = parseFloat(latestWeek.profit || 0);
            }
            
            // Get month profit from monthly breakdown
            if (data.profit_loss_breakdown.monthly && data.profit_loss_breakdown.monthly.length > 0) {
                // Get the most recent month
                const latestMonth = data.profit_loss_breakdown.monthly[0];
                monthProfit = parseFloat(latestMonth.profit || 0);
            }
        }
            
            document.getElementById('staffTodayProfit').textContent = formatCurrency(todayProfit);
        document.getElementById('staffWeekProfit').textContent = formatCurrency(weekProfit);
            document.getElementById('staffMonthProfit').textContent = formatCurrency(monthProfit);
        
        // Load transaction history
        loadStaffTransactionHistory(staffId, data);
    }
    
    // Load staff transaction history
    async function loadStaffTransactionHistory(staffId, data) {
        const tbody = document.getElementById('staffTransactionHistory');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        // Combine sales, repairs, and swaps into transaction history
        const transactions = [];
        
        // Get sales transactions for this staff
        if (data.activity_logs && data.activity_logs.length > 0) {
            const staffTransactions = data.activity_logs.filter(log => 
                log.activity_type === 'sale' || 
                log.activity_type === 'repair' || 
                log.activity_type === 'swap'
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
                } else if (log.activity_type === 'swap') {
                    profit = parseFloat(log.amount || 0);
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
                <td class="px-4 py-3 text-sm">${new Date(transaction.date).toLocaleDateString()}</td>
                <td class="px-4 py-3 text-sm"><span class="px-2 py-1 rounded text-xs font-medium ${typeClass}">${transaction.type || 'N/A'}</span></td>
                <td class="px-4 py-3 text-sm">${transaction.reference || 'N/A'}</td>
                <td class="px-4 py-3 text-sm text-right font-medium">${formatCurrency(transaction.amount || 0)}</td>
                <td class="px-4 py-3 text-sm text-right ${transaction.profit >= 0 ? 'text-green-600' : 'text-red-600'}">${formatCurrency(transaction.profit || 0)}</td>
                <td class="px-4 py-3 text-sm text-right"><span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">${transaction.status || 'N/A'}</span></td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Clear staff selection
    window.clearStaffSelection = function() {
        document.getElementById('filterStaff').value = '';
        document.getElementById('staffDetailSection').classList.add('hidden');
        loadAnalytics(); // Reload without staff filter
    }

    // Update repairer stats (card style - showing totals across all repairers)
    function updateRepairerStats(technicians) {
        console.log('Updating repairer stats with:', technicians);
        
        if (!technicians || technicians.length === 0) {
            // Show zero values if no technicians
            document.getElementById('repairerStatsRepairsCount').textContent = '0';
            document.getElementById('repairerStatsTotalRevenue').textContent = formatCurrency(0);
            document.getElementById('repairerStatsWorkmanshipRevenue').textContent = formatCurrency(0);
            document.getElementById('repairerStatsPartsRevenue').textContent = formatCurrency(0);
            document.getElementById('repairerStatsPartsCount').textContent = '0';
            document.getElementById('repairerStatsTotalProfit').textContent = formatCurrency(0);
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
        const totalProfit = totalWorkmanshipProfit + totalPartsProfit;
        
        // Update the card with totals
        document.getElementById('repairerStatsRepairsCount').textContent = totalRepairs;
        document.getElementById('repairerStatsTotalRevenue').textContent = formatCurrency(totalRevenue);
        document.getElementById('repairerStatsWorkmanshipRevenue').textContent = formatCurrency(totalWorkmanshipRevenue);
        document.getElementById('repairerStatsPartsRevenue').textContent = formatCurrency(totalPartsRevenue);
        document.getElementById('repairerStatsPartsCount').textContent = totalPartsCount;
        document.getElementById('repairerStatsTotalProfit').textContent = formatCurrency(totalProfit);
        
        // Note: Repairer profit is now included in the backend profit calculation
        // So we don't need to add it again here to avoid double-counting
        // The backend profit already includes: Sales Profit + Swap Profit + Repairer Profit
        // We only update the repairer stats card, not the main profit display
    }

    // Update repairer parts sales display
    function updateRepairerPartsSales(data) {
        if (!data || !data.items || data.items.length === 0) {
            document.getElementById('repairerPartsSection').classList.add('hidden');
            return;
        }
        
        // Show section
        document.getElementById('repairerPartsSection').classList.remove('hidden');
        
        // Update totals
        document.getElementById('repairerPartsTotalRevenue').textContent = formatCurrency(data.total_revenue || 0);
        document.getElementById('repairerPartsTotalProfit').textContent = formatCurrency(data.total_profit || 0);
        
        // Update table
        const tbody = document.getElementById('repairerPartsTableBody');
        tbody.innerHTML = '';
        
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                const row = document.createElement('tr');
                const date = new Date(item.sold_date);
                const dateStr = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm text-gray-900">${dateStr}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.repairer_name || 'Unknown'}</td>
                    <td class="px-4 py-3 text-sm">
                        <a href="${BASE}/dashboard/repairs/${item.repair_id}" class="text-blue-600 hover:text-blue-800">
                            ${item.tracking_code || 'N/A'}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.customer_name || 'Walk-in'}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.product_name || 'Unknown Product'}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${item.quantity || 0}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900">${formatCurrency(item.selling_price || 0)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">${formatCurrency(item.cost_price || 0)}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">${formatCurrency(item.revenue || 0)}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-green-600">${formatCurrency(item.profit || 0)}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-3 text-center text-gray-500">No items sold by repairers in this period</td></tr>';
        }
    }

    // Update profit/loss breakdown
    function updateProfitLossBreakdown(breakdown) {
        console.log('updateProfitLossBreakdown called with:', breakdown);
        console.log('Breakdown structure:', {
            hasBreakdown: !!breakdown,
            hasDaily: !!(breakdown?.daily),
            hasWeekly: !!(breakdown?.weekly),
            hasMonthly: !!(breakdown?.monthly),
            dailyType: typeof breakdown?.daily,
            weeklyType: typeof breakdown?.weekly,
            monthlyType: typeof breakdown?.monthly,
            dailyIsArray: Array.isArray(breakdown?.daily),
            weeklyIsArray: Array.isArray(breakdown?.weekly),
            monthlyIsArray: Array.isArray(breakdown?.monthly),
            dailyLength: breakdown?.daily?.length ?? 0,
            weeklyLength: breakdown?.weekly?.length ?? 0,
            monthlyLength: breakdown?.monthly?.length ?? 0
        });
        console.log('Full breakdown data:', JSON.stringify(breakdown, null, 2));
        
        if (!breakdown) {
            console.warn('No breakdown data provided');
            // If no breakdown data, show empty state
            const dailyBody = document.getElementById('dailyBreakdownBody');
            const weeklyBody = document.getElementById('weeklyBreakdownBody');
            const monthlyBody = document.getElementById('monthlyBreakdownBody');
            
            if (dailyBody) dailyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No daily data</td></tr>';
            if (weeklyBody) weeklyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No weekly data</td></tr>';
            if (monthlyBody) monthlyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No monthly data</td></tr>';
            return;
        }
        
        // Daily breakdown
        const dailyBody = document.getElementById('dailyBreakdownBody');
        if (dailyBody) {
            dailyBody.innerHTML = '';
            console.log('Daily breakdown check:', {
                hasDaily: !!breakdown.daily,
                isArray: Array.isArray(breakdown.daily),
                length: breakdown.daily?.length ?? 0,
                sample: breakdown.daily?.slice(0, 3)
            });
            if (breakdown.daily && Array.isArray(breakdown.daily) && breakdown.daily.length > 0) {
                breakdown.daily.forEach(day => {
                    const row = document.createElement('tr');
                    row.className = 'border-b hover:bg-gray-50';
                    // Format date - handle both string and Date objects
                    const dateStr = day.date || day.date_str || '';
                    let dateObj;
                    if (dateStr) {
                        // Try parsing as YYYY-MM-DD format
                        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            dateObj = new Date(dateStr + 'T00:00:00');
                        } else {
                            dateObj = new Date(dateStr);
                        }
                    } else {
                        dateObj = new Date();
                    }
                    
                    // Validate date
                    if (isNaN(dateObj.getTime())) {
                        console.warn('Invalid date in daily breakdown:', dateStr);
                        dateObj = new Date();
                    }
                    
                    row.innerHTML = `
                        <td class="py-2">${dateObj.toLocaleDateString()}</td>
                        <td class="text-right py-2">${day.sales_count || 0}</td>
                        <td class="text-right py-2">${formatCurrency(day.revenue || 0)}</td>
                        <td class="text-right py-2 ${(day.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'}">${formatCurrency(day.profit || 0)}</td>
                    `;
                    dailyBody.appendChild(row);
                });
                console.log('Daily breakdown rendered:', breakdown.daily.length, 'days');
            } else {
                console.warn('No daily data available. Breakdown.daily:', breakdown.daily);
                console.log('Date range:', document.getElementById('filterDateFrom')?.value, 'to', document.getElementById('filterDateTo')?.value);
                dailyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No daily data</td></tr>';
            }
        }
        
        // Weekly breakdown
        const weeklyBody = document.getElementById('weeklyBreakdownBody');
        if (weeklyBody) {
            weeklyBody.innerHTML = '';
            console.log('Weekly breakdown check:', {
                hasWeekly: !!breakdown.weekly,
                isArray: Array.isArray(breakdown.weekly),
                length: breakdown.weekly?.length ?? 0,
                sample: breakdown.weekly?.slice(0, 3)
            });
            if (breakdown.weekly && Array.isArray(breakdown.weekly) && breakdown.weekly.length > 0) {
                breakdown.weekly.forEach(week => {
                    const row = document.createElement('tr');
                    row.className = 'border-b hover:bg-gray-50';
                    const weekYear = Math.floor(week.week / 100);
                    const weekNum = week.week % 100;
                    row.innerHTML = `
                        <td class="py-2">Week ${weekNum}, ${weekYear}</td>
                        <td class="text-right py-2">${week.sales_count || 0}</td>
                        <td class="text-right py-2">${formatCurrency(week.revenue || 0)}</td>
                        <td class="text-right py-2 ${(week.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'}">${formatCurrency(week.profit || 0)}</td>
                    `;
                    weeklyBody.appendChild(row);
                });
                console.log('Weekly breakdown rendered:', breakdown.weekly.length, 'weeks');
            } else {
                console.warn('No weekly data available. Breakdown.weekly:', breakdown.weekly);
                weeklyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No weekly data</td></tr>';
            }
        }
        
        // Monthly breakdown
        const monthlyBody = document.getElementById('monthlyBreakdownBody');
        const monthlyContainer = document.getElementById('monthlyBreakdownContainer');
        if (monthlyBody) {
            monthlyBody.innerHTML = '';
            console.log('Monthly breakdown check:', {
                hasMonthly: !!breakdown.monthly,
                isArray: Array.isArray(breakdown.monthly),
                length: breakdown.monthly?.length ?? 0,
                sample: breakdown.monthly?.slice(0, 3)
            });
            if (breakdown.monthly && Array.isArray(breakdown.monthly) && breakdown.monthly.length > 0) {
                breakdown.monthly.forEach(month => {
                    const row = document.createElement('tr');
                    row.className = 'border-b hover:bg-gray-50';
                    // Handle month format - could be 'YYYY-MM' or other formats
                    const monthStr = month.month || '';
                    let monthName = monthStr;
                    if (monthStr && monthStr.match(/^\d{4}-\d{2}$/)) {
                        // Format: YYYY-MM
                        const date = new Date(monthStr + '-01');
                        monthName = date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    } else if (monthStr) {
                        // Try to parse other formats
                        try {
                            const date = new Date(monthStr);
                            if (!isNaN(date.getTime())) {
                                monthName = date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                            }
                        } catch (e) {
                            console.warn('Could not parse month:', monthStr);
                        }
                    }
                    row.innerHTML = `
                        <td class="py-2">${monthName}</td>
                        <td class="text-right py-2">${month.sales_count || 0}</td>
                        <td class="text-right py-2">${formatCurrency(month.revenue || 0)}</td>
                        <td class="text-right py-2 ${(month.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'}">${formatCurrency(month.profit || 0)}</td>
                    `;
                    monthlyBody.appendChild(row);
                });
                
                // Show scrollbar only if more than 6 items
                // Calculate max height: ~50px per row (header + 6 rows = ~350px)
                if (monthlyContainer) {
                    if (breakdown.monthly.length > 6) {
                        monthlyContainer.style.maxHeight = '350px';
                        monthlyContainer.style.overflowY = 'auto';
                        monthlyContainer.style.overflowX = 'hidden';
            } else {
                        monthlyContainer.style.maxHeight = 'none';
                        monthlyContainer.style.overflowY = 'visible';
                        monthlyContainer.style.overflowX = 'auto';
                    }
                }
                
                console.log('Monthly breakdown rendered:', breakdown.monthly.length, 'months');
            } else {
                console.warn('No monthly data available. Breakdown.monthly:', breakdown.monthly);
                monthlyBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No monthly data</td></tr>';
            }
        }
        
        console.log('Profit/Loss Breakdown update complete:', {
            daily: breakdown.daily?.length ?? 0,
            weekly: breakdown.weekly?.length ?? 0,
            monthly: breakdown.monthly?.length ?? 0
        });
    }

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
            const response = await fetch(`${BASE}/api/analytics/trace?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.results) {
                displayTraceResults(data.results);
            } else {
                alert('No results found');
            }
        } catch (error) {
            console.error('Trace search error:', error);
            alert('Error performing search');
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
            bodyEl.innerHTML = '<tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">No results found</td></tr>';
            resultsEl.classList.remove('hidden');
            return;
        }

        results.forEach(result => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
                <td class="px-4 py-3 text-sm"><span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">${result.type}</span></td>
                <td class="px-4 py-3 text-sm">${new Date(result.date).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm font-medium">${formatCurrency(result.amount || 0)}</td>
                <td class="px-4 py-3 text-sm">${result.customer || '-'}</td>
                <td class="px-4 py-3 text-sm">${result.item || '-'}</td>
                <td class="px-4 py-3 text-sm">${result.reference || '-'}</td>
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

        // Profit Chart
        const profitCtx = document.getElementById('profitChart');
        if (profitCtx) {
            profitChart = new Chart(profitCtx, {
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

