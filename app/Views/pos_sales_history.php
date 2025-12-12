<?php
/**
 * POS Sales History View
 */
?>

<style>
    /* Ensure table is fluid and responsive */
    #salesTableBody td {
        word-break: break-word;
        overflow-wrap: break-word;
    }
    
    /* Prevent horizontal scroll but allow content to be visible */
    .sales-history-container {
        max-width: 100%;
        overflow-x: visible;
    }
    
    /* Remove vertical scrollbar from table */
    .sales-history-container table,
    .sales-history-container table tbody {
        overflow-y: visible !important;
    }
    
    /* Ensure Actions column is always visible */
    #salesTableBody td:last-child {
        position: relative;
        z-index: 10;
        background-color: white;
        min-width: 140px;
        white-space: nowrap;
        overflow: visible !important;
    }
    
    /* Make action buttons always visible */
    #salesTableBody td:last-child button {
        display: inline-flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        min-height: 36px;
        flex-shrink: 0;
    }
    
    /* Ensure action buttons container doesn't wrap */
    #salesTableBody td:last-child > div {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow: visible !important;
        white-space: nowrap !important;
    }
    
    /* Responsive table adjustments */
    @media (max-width: 640px) {
        #salesTableBody td {
            font-size: 0.75rem;
            padding: 0.5rem 0.5rem;
        }
        
        #salesTableBody td:last-child {
            min-width: 90px;
        }
        
        #salesTableBody td:last-child button {
            min-width: 24px;
            min-height: 24px;
            padding: 0.25rem;
        }
        
        table thead th {
            font-size: 0.7rem;
            padding: 0.5rem 0.5rem;
        }
    }
    
    @media (max-width: 768px) {
        table thead th {
            font-size: 0.75rem;
        }
    }
    
    /* Ensure table doesn't overflow - auto-fluid layout */
    table {
        table-layout: auto;
        width: 100%;
    }
    
    /* Make table cells auto-adjust width */
    table th,
    table td {
        width: auto;
        min-width: fit-content;
    }
    
    /* Ensure table container doesn't have vertical scroll */
    .sales-history-container .overflow-x-auto {
        overflow-y: visible !important;
    }
    
    /* Make sure Actions column header is visible */
    table thead th:last-child {
        min-width: 120px;
    }
</style>

<div class="w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6 sales-history-container">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Sales History</h2>
        <p class="text-sm sm:text-base text-gray-600">Transaction records and sales reports</p>
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
        
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">Filter Sales</h3>
        </div>
        <div class="p-4 sm:p-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Sales</label>
                    <div class="relative">
                        <input type="text" id="salesSearch" placeholder="Search by customer, sale ID..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                <div class="md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input type="date" id="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input type="date" id="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="md:w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter</label>
                    <button id="filterBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
                <div class="md:w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
                    <select id="itemsPerPage" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6 mb-6" id="summaryCardsContainer">
        <div class="bg-white rounded-lg shadow-sm border p-4 sm:p-6">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shopping-cart text-blue-600 text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <p class="text-xs sm:text-sm text-gray-600">Total Sales</p>
                    <p id="totalSales" class="text-xl sm:text-2xl font-bold text-gray-800 break-words overflow-hidden">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border p-4 sm:p-6">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-dollar-sign text-green-600 text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <p class="text-xs sm:text-sm text-gray-600">Total Revenue</p>
                    <p id="totalRevenue" class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 break-words overflow-hidden cursor-default">₵0.00</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border p-4 sm:p-6">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-percentage text-yellow-600 text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <p class="text-xs sm:text-sm text-gray-600">Avg. Sale</p>
                    <p id="avgSale" class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 break-words overflow-hidden cursor-default">₵0.00</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border p-4 sm:p-6">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calendar-day text-purple-600 text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <p class="text-xs sm:text-sm text-gray-600">Today's Sales</p>
                    <p id="todaySales" class="text-xl sm:text-2xl font-bold text-gray-800 break-words overflow-hidden">0</p>
                </div>
            </div>
        </div>
        
        <!-- Profit Card (Managers Only) -->
        <div id="profitCard" class="bg-white rounded-lg shadow-sm border p-4 sm:p-6 hidden">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-chart-line text-emerald-600 text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <p class="text-xs sm:text-sm text-gray-600">Total Profit</p>
                    <p id="totalProfit" class="text-lg sm:text-xl md:text-2xl font-bold text-gray-800 break-words overflow-hidden cursor-default">₵0.00</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-800">Recent Sales</h3>
            <div id="bulkActionsContainer" class="hidden">
                <button id="bulkDeleteBtn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors text-sm">
                    <i class="fas fa-trash mr-2"></i>Delete Selected
                </button>
            </div>
        </div>
        
        <!-- Responsive table container - fluid and auto-adjusting -->
        <div class="w-full overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <table class="w-full divide-y divide-gray-200" style="table-layout: auto; width: 100%;">
                    <thead class="bg-gray-50">
                        <tr>
                            <th id="checkboxHeader" class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden whitespace-nowrap">
                                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Sale ID
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Customer
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Item
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Category
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Total
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Payment
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Date
                            </th>
                            <th class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap" style="min-width: 100px; width: auto;">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="9" class="px-4 sm:px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-2xl mb-4"></i>
                                <p>Loading sales data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs sm:text-sm text-gray-700 text-center sm:text-left">
                    Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalItems">0</span> results
                </div>
                <div class="flex items-center space-x-2">
                    <button id="prevPageBtn" class="px-2 sm:px-3 py-1 text-xs sm:text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-chevron-left mr-1"></i><span class="hidden sm:inline">Previous</span>
                    </button>
                    <div id="pageNumbers" class="flex items-center space-x-1">
                        <!-- Page numbers will be generated here -->
                    </div>
                    <button id="nextPageBtn" class="px-2 sm:px-3 py-1 text-xs sm:text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <span class="hidden sm:inline">Next</span><i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sale Details Modal -->
<div id="saleDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded shadow-xl max-w-2xl w-full p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Sale Details</h3>
                <button id="closeSaleDetailsModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="saleDetailsContent">
                <!-- Sale details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded shadow-xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Record Payment</h3>
                <button id="closePaymentModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="paymentModalContent">
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Sale ID: <span id="paymentSaleId" class="font-semibold"></span></p>
                    <p class="text-sm text-gray-600 mb-2">Total Amount: <span id="paymentTotalAmount" class="font-semibold"></span></p>
                    <p class="text-sm text-gray-600 mb-2">Total Paid: <span id="paymentTotalPaid" class="font-semibold"></span></p>
                    <p class="text-sm text-gray-600 mb-4">Remaining: <span id="paymentRemaining" class="font-semibold text-orange-600"></span></p>
                </div>
                
                <form id="paymentForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount *</label>
                        <input type="number" id="paymentAmount" step="0.01" min="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                        <select id="paymentMethod" required
                                class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="CASH">Cash</option>
                            <option value="MOBILE_MONEY">Mobile Money</option>
                            <option value="CARD">Card</option>
                            <option value="BANK_TRANSFER">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea id="paymentNotes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelPaymentBtn" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadPermissions();
    loadSalesHistory();
    setupEventListeners();
});

let salesData = [];
let currentPage = 1;
let itemsPerPage = 20;
let totalPages = 1;
let totalItems = 0;
let canDeleteSales = false;
let canBulkDeleteSales = false;
let selectedSales = new Set();

let partialPaymentsEnabled = false;
let isManager = false;

// Format currency - show full numbers until millions, then show M with tooltip
function formatCurrencyForDisplay(amount) {
    if (amount >= 1000000) {
        const millions = amount / 1000000;
        return millions >= 10 ? millions.toFixed(1) + 'M' : millions.toFixed(2) + 'M';
    } else {
        // Show full number with commas for thousands
        return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

// Get full currency amount for tooltips
function getFullCurrencyAmountForDisplay(amount) {
    return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadPermissions() {
    try {
        // Get user role and company ID from session/context
        // We'll check permissions based on user role
        const userRole = '<?= isset($_SESSION["user"]["role"]) ? $_SESSION["user"]["role"] : "" ?>';
        const companyId = <?= isset($_SESSION["user"]["company_id"]) ? (int)$_SESSION["user"]["company_id"] : "null" ?>;
        
        // Check if user is a manager (manager, admin, or system_admin)
        isManager = ['manager', 'admin', 'system_admin'].includes(userRole);
        
        // Show/hide profit card based on role
        const profitCard = document.getElementById('profitCard');
        if (profitCard) {
            if (isManager) {
                profitCard.classList.remove('hidden');
                // Update grid to accommodate 5 cards for managers
                const summaryCardsContainer = document.getElementById('summaryCardsContainer');
                if (summaryCardsContainer) {
                    summaryCardsContainer.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 sm:gap-6 mb-6';
                }
            } else {
                profitCard.classList.add('hidden');
            }
        }
        
        if (companyId) {
            // Check permissions via API
            const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
            const response = await fetch(`${basePath}/api/admin/company/${companyId}/modules`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.modules) {
                    const modules = data.modules;
                    canDeleteSales = modules.find(m => m.key === 'manager_delete_sales')?.enabled || false;
                    canBulkDeleteSales = modules.find(m => m.key === 'manager_bulk_delete_sales')?.enabled || false;
                    partialPaymentsEnabled = modules.find(m => m.key === 'partial_payments')?.enabled || false;
                    
                    // Show/hide bulk actions
                    if (canBulkDeleteSales) {
                        document.getElementById('checkboxHeader').classList.remove('hidden');
                        document.getElementById('bulkActionsContainer').classList.remove('hidden');
                    }
                }
            }
        }
        
        if (userRole === 'system_admin' || userRole === 'admin') {
            // Admins can always delete
            canDeleteSales = true;
            canBulkDeleteSales = true;
            document.getElementById('checkboxHeader').classList.remove('hidden');
            document.getElementById('bulkActionsContainer').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading permissions:', error);
    }
}

function setupEventListeners() {
    // Filter button
    document.getElementById('filterBtn').addEventListener('click', () => {
        currentPage = 1;
        loadSalesHistory();
    });
    
    // Search input
    document.getElementById('salesSearch').addEventListener('input', filterSales);
    
    // Items per page change
    document.getElementById('itemsPerPage').addEventListener('change', (e) => {
        itemsPerPage = parseInt(e.target.value);
        currentPage = 1;
        loadSalesHistory();
    });
    
    // Pagination buttons
    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadSalesHistory();
        }
    });
    
    document.getElementById('nextPageBtn').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadSalesHistory();
        }
    });
    
    // Close modals
    document.getElementById('closeSaleDetailsModal').addEventListener('click', () => {
        document.getElementById('saleDetailsModal').classList.add('hidden');
    });
    
    document.getElementById('closePaymentModal').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.add('hidden');
    });
    
    document.getElementById('cancelPaymentBtn').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.add('hidden');
    });
    
    // Payment form submission
    document.getElementById('paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitPayment();
    });
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            const checked = e.target.checked;
            salesData.forEach(sale => {
                if (checked) {
                    selectedSales.add(sale.id);
                } else {
                    selectedSales.delete(sale.id);
                }
            });
            updateCheckboxes();
        });
    }
    
    // Bulk delete button
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', bulkDeleteSales);
    }
}

async function loadSalesHistory() {
    try {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        // Use BASE variable if available, otherwise fallback
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        let url = basePath + '/api/pos/sales';
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        params.append('page', currentPage);
        params.append('limit', itemsPerPage);
        url += '?' + params.toString();
        
        const response = await fetch(url, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'  // Include cookies for session-based auth
        });
        
        const data = await response.json();
        
        if (data.success) {
            salesData = data.data || [];
            totalPages = data.pagination?.total_pages || 1;
            totalItems = data.pagination?.total_items || 0;
            
            renderSalesTable();
            updateSummaryCards(data.total_profit);
            updatePagination();
        } else {
            console.error('Failed to load sales:', data.error);
        }
    } catch (error) {
        console.error('Error loading sales:', error);
    }
}

function renderSalesTable() {
    const tbody = document.getElementById('salesTableBody');
    
    if (salesData.length === 0) {
        tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-4 sm:px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-receipt text-2xl mb-4"></i>
                            <p>No sales found</p>
                        </td>
                    </tr>
        `;
        return;
    }
    
    tbody.innerHTML = salesData.map(sale => {
        // Get item name - prefer product name, then item description
        const itemName = sale.first_item_product_name || sale.first_item_name || 'Various items';
        const itemCategory = sale.first_item_category || '';
        const itemCount = sale.item_count || 0;
        const showItemCount = itemCount > 1 ? ` +${itemCount - 1} more` : '';
        const isChecked = selectedSales.has(sale.id);
        
        // Check if this sale contains swapped items (resold items)
        // Check both field names for compatibility (has_swapped_items or is_swapped_item)
        const hasSwappedItems = (sale.has_swapped_items == 1 || sale.has_swapped_items === '1' || sale.has_swapped_items === true) ||
                                (sale.is_swapped_item == 1 || sale.is_swapped_item === '1' || sale.is_swapped_item === true);
        // Use purple/lavender theme to match POS content page for swapped items
        const rowStyle = hasSwappedItems ? 'style="background-color: #f3e8ff; border-left: 4px solid #a78bfa;" onmouseover="this.style.backgroundColor=\'#e9d5ff\'" onmouseout="this.style.backgroundColor=\'#f3e8ff\'"' : 'class="hover:bg-gray-50"';
        const textColorStyle = hasSwappedItems ? 'style="color: #6b21a8;"' : '';
        const badgeStyle = hasSwappedItems ? 'style="background-color: #c4b5fd; color: #6b21a8; border-color: #8b5cf6;"' : '';
        
        // Format date for display
        const saleDate = new Date(sale.created_at);
        const formattedDate = saleDate.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric' 
        });
        const formattedDateShort = saleDate.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: 'short'
        });
        
        return `
        <tr ${rowStyle}>
            ${canBulkDeleteSales ? `
            <td class="px-3 sm:px-4 py-4 whitespace-nowrap hidden">
                <input type="checkbox" class="sale-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                       data-sale-id="${sale.id}" ${isChecked ? 'checked' : ''}>
            </td>
            ` : ''}
            <td class="px-3 sm:px-4 py-4 whitespace-nowrap text-sm font-medium" ${textColorStyle}>
                ${sale.unique_id || 'SEL-SALE-' + sale.id}
            </td>
            <td class="px-3 sm:px-4 py-4 text-sm" ${textColorStyle}>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 min-w-0">
                    <span class="truncate">${sale.customer_name || sale.customer_name_from_table || 'Walk-in'}</span>
                    ${sale.cashier_role ? `
                        <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded text-xs font-medium flex-shrink-0 ${
                            sale.cashier_role === 'technician' ? 'bg-purple-100 text-purple-800' : 
                            sale.cashier_role === 'salesperson' ? 'bg-blue-100 text-blue-800' : 
                            'bg-gray-100 text-gray-800'
                        }" title="Sold by ${sale.cashier_role}">
                            ${sale.cashier_role === 'technician' ? '<i class="fas fa-tools mr-1"></i><span class="hidden sm:inline">Tech</span>' : 
                              sale.cashier_role === 'salesperson' ? '<i class="fas fa-user-tie mr-1"></i><span class="hidden sm:inline">Sales</span>' : 
                              sale.cashier_role}
                        </span>
                    ` : ''}
                </div>
            </td>
            <td class="px-3 sm:px-4 py-4 text-sm" ${textColorStyle}>
                <div class="font-medium truncate max-w-xs">
                    ${hasSwappedItems ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium mr-1" ${badgeStyle} title="Swap Resold">
                        <i class="fas fa-exchange-alt mr-1"></i>Swap Resold
                    </span>` : ''}
                    ${itemName}${showItemCount}
                </div>
                ${itemCount > 1 ? `<div class="text-xs ${hasSwappedItems ? 'text-purple-600' : 'text-gray-500'} mt-1">${itemCount} items</div>` : ''}
            </td>
            <td class="px-2 sm:px-3 md:px-4 py-3 sm:py-4 text-xs sm:text-sm" ${textColorStyle}>
                <span class="truncate block max-w-xs">${itemCategory || '-'}</span>
            </td>
            <td class="px-2 sm:px-3 md:px-4 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium" ${textColorStyle}>
                ${parseFloat(sale.final_amount || sale.total || 0) >= 1000000 ? `
                    <span title="₵${getFullCurrencyAmountForDisplay(parseFloat(sale.final_amount || sale.total || 0))}">₵${formatCurrencyForDisplay(parseFloat(sale.final_amount || sale.total || 0))}</span>
                ` : `
                    ₵${formatCurrencyForDisplay(parseFloat(sale.final_amount || sale.total || 0))}
                `}
            </td>
            <td class="px-2 sm:px-3 md:px-4 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                ${getPaymentStatusBadge(sale.payment_status || 'PAID', sale.final_amount || sale.total || 0)}
            </td>
            <td class="px-2 sm:px-3 md:px-4 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm" ${textColorStyle}>
                ${formattedDate}
            </td>
            <td class="px-2 sm:px-3 md:px-4 py-3 sm:py-4 text-xs sm:text-sm">
                <div class="flex items-center justify-start gap-1 sm:gap-1.5 md:gap-2 flex-nowrap">
                    <button onclick="viewSaleDetails(${sale.id})" class="inline-flex items-center justify-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded p-1.5 sm:p-2 transition-colors min-w-[28px] min-h-[28px] sm:min-w-[32px] sm:min-h-[32px]" title="View Details">
                        <i class="fas fa-eye text-xs sm:text-sm"></i>
                    </button>
                    ${partialPaymentsEnabled && (sale.payment_status === 'PARTIAL' || sale.payment_status === 'UNPAID') ? `
                    <button onclick="openPaymentModal(${sale.id})" class="inline-flex items-center justify-center text-orange-600 hover:text-orange-800 hover:bg-orange-50 rounded p-1.5 sm:p-2 transition-colors min-w-[28px] min-h-[28px] sm:min-w-[32px] sm:min-h-[32px]" title="Add Payment">
                        <i class="fas fa-money-bill-wave text-xs sm:text-sm"></i>
                    </button>
                    ` : ''}
                    <button onclick="printReceipt(${sale.id})" class="inline-flex items-center justify-center text-green-600 hover:text-green-800 hover:bg-green-50 rounded p-1.5 sm:p-2 transition-colors min-w-[28px] min-h-[28px] sm:min-w-[32px] sm:min-h-[32px]" title="Print Receipt">
                        <i class="fas fa-print text-xs sm:text-sm"></i>
                    </button>
                    ${canDeleteSales ? `
                    <button onclick="deleteSale(${sale.id})" class="inline-flex items-center justify-center text-red-600 hover:text-red-800 hover:bg-red-50 rounded p-1.5 sm:p-2 transition-colors min-w-[28px] min-h-[28px] sm:min-w-[32px] sm:min-h-[32px]" title="Delete Sale">
                        <i class="fas fa-trash text-xs sm:text-sm"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
        `;
    }).join('');
    
    // Attach checkbox event listeners
    if (canBulkDeleteSales) {
        document.querySelectorAll('.sale-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const saleId = parseInt(e.target.getAttribute('data-sale-id'));
                if (e.target.checked) {
                    selectedSales.add(saleId);
                } else {
                    selectedSales.delete(saleId);
                }
                updateSelectAllCheckbox();
            });
        });
        updateSelectAllCheckbox();
    }
}

function updateCheckboxes() {
    document.querySelectorAll('.sale-checkbox').forEach(checkbox => {
        const saleId = parseInt(checkbox.getAttribute('data-sale-id'));
        checkbox.checked = selectedSales.has(saleId);
    });
    updateSelectAllCheckbox();
}

function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        const allChecked = salesData.length > 0 && salesData.every(sale => selectedSales.has(sale.id));
        const someChecked = salesData.some(sale => selectedSales.has(sale.id));
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
    }
}

async function deleteSale(saleId) {
    if (!confirm('Are you sure you want to delete this sale? This action cannot be undone.')) {
        return;
    }
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
        // Use POST route directly since DELETE may not be properly supported
        // The server might not route DELETE requests correctly in some configurations
        const response = await fetch(`${basePath}/api/pos/sale/${saleId}/delete`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            // Try to get error message from response
            let errorMsg = `Error ${response.status}: `;
            try {
                const errorData = await response.json();
                errorMsg += errorData.error || errorData.message || 'Failed to delete sale';
                console.error('Delete sale error:', errorData);
            } catch (e) {
                errorMsg += 'Failed to delete sale. Please check the console for details.';
            }
            alert(errorMsg);
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            alert('Sale deleted successfully');
            loadSalesHistory();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete sale'));
        }
    } catch (error) {
        console.error('Error deleting sale:', error);
        alert('Error deleting sale. Please try again.');
    }
}

async function bulkDeleteSales() {
    if (selectedSales.size === 0) {
        alert('Please select at least one sale to delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${selectedSales.size} sale(s)? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
        // Use POST route directly since DELETE may not be properly supported
        const response = await fetch(`${basePath}/api/pos/sales/bulk-delete`, {
            method: 'POST',
            headers: {
                ...getAuthHeaders(),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: Array.from(selectedSales) }),
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Successfully deleted ${data.deleted_count || selectedSales.size} sale(s)`);
            selectedSales.clear();
            loadSalesHistory();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete sales'));
        }
    } catch (error) {
        console.error('Error bulk deleting sales:', error);
        alert('Error deleting sales. Please try again.');
    }
}

function getPaymentStatusBadge(status, totalAmount) {
    const statusUpper = (status || 'PAID').toUpperCase();
    let badgeClass = '';
    let badgeText = '';
    
    switch(statusUpper) {
        case 'PAID':
            badgeClass = 'bg-green-100 text-green-800';
            badgeText = 'Paid';
            break;
        case 'PARTIAL':
            badgeClass = 'bg-yellow-100 text-yellow-800';
            badgeText = 'Partial';
            break;
        case 'UNPAID':
            badgeClass = 'bg-red-100 text-red-800';
            badgeText = 'Unpaid';
            break;
        default:
            badgeClass = 'bg-gray-100 text-gray-800';
            badgeText = statusUpper;
    }
    
    return `<span class="px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">${badgeText}</span>`;
}

function getPaymentMethodClass(method) {
    switch(method?.toLowerCase()) {
        case 'cash': return 'bg-green-100 text-green-800';
        case 'card': return 'bg-blue-100 text-blue-800';
        case 'mobile_money': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function updateSummaryCards(totalProfit = null) {
    const totalSales = salesData.length;
    const totalRevenue = salesData.reduce((sum, sale) => sum + parseFloat(sale.final_amount || sale.total || 0), 0);
    const avgSale = totalSales > 0 ? totalRevenue / totalSales : 0;
    
    // Calculate today's sales count
    const today = new Date().toISOString().split('T')[0];
    const todaySales = salesData.filter(sale => {
        const saleDate = new Date(sale.created_at).toISOString().split('T')[0];
        return saleDate === today;
    }).length;
    
    // Calculate payment statistics
    let fullyPaidCount = 0;
    let partialPaymentsCount = 0;
    let unpaidCount = 0;
    let totalPaid = 0;
    let remainingAmount = 0;
    
    salesData.forEach(sale => {
        const status = (sale.payment_status || 'PAID').toUpperCase();
        const finalAmount = parseFloat(sale.final_amount || sale.total || 0);
        const paid = parseFloat(sale.total_paid || (status === 'PAID' ? finalAmount : 0));
        const remaining = Math.max(0, finalAmount - paid);
        
        totalPaid += paid;
        remainingAmount += remaining;
        
        if (status === 'PAID') {
            fullyPaidCount++;
        } else if (status === 'PARTIAL') {
            partialPaymentsCount++;
        } else if (status === 'UNPAID') {
            unpaidCount++;
        }
    });
    
    // Safely update elements only if they exist
    const totalSalesEl = document.getElementById('totalSales');
    if (totalSalesEl) totalSalesEl.textContent = totalSales;
    
    const totalRevenueEl = document.getElementById('totalRevenue');
    if (totalRevenueEl) {
        if (totalRevenue >= 1000000) {
            totalRevenueEl.textContent = `₵${formatCurrencyForDisplay(totalRevenue)}`;
            totalRevenueEl.setAttribute('title', `₵${getFullCurrencyAmountForDisplay(totalRevenue)}`);
        } else {
            totalRevenueEl.textContent = `₵${formatCurrencyForDisplay(totalRevenue)}`;
            totalRevenueEl.removeAttribute('title');
        }
    }
    
    const avgSaleEl = document.getElementById('avgSale');
    if (avgSaleEl) {
        if (avgSale >= 1000000) {
            avgSaleEl.textContent = `₵${formatCurrencyForDisplay(avgSale)}`;
            avgSaleEl.setAttribute('title', `₵${getFullCurrencyAmountForDisplay(avgSale)}`);
        } else {
            avgSaleEl.textContent = `₵${formatCurrencyForDisplay(avgSale)}`;
            avgSaleEl.removeAttribute('title');
        }
    }
    
    const todaySalesEl = document.getElementById('todaySales');
    if (todaySalesEl) todaySalesEl.textContent = todaySales;
    
    // Update profit card for managers
    if (isManager && totalProfit !== null && totalProfit !== undefined) {
        const totalProfitEl = document.getElementById('totalProfit');
        if (totalProfitEl) {
            const profitAmount = parseFloat(totalProfit);
            if (profitAmount >= 1000000) {
                totalProfitEl.textContent = `₵${formatCurrencyForDisplay(profitAmount)}`;
                totalProfitEl.setAttribute('title', `₵${getFullCurrencyAmountForDisplay(profitAmount)}`);
            } else {
                totalProfitEl.textContent = `₵${formatCurrencyForDisplay(profitAmount)}`;
                totalProfitEl.removeAttribute('title');
            }
        }
    }
    
    const fullyPaidCountEl = document.getElementById('fullyPaidCount');
    if (fullyPaidCountEl) fullyPaidCountEl.textContent = fullyPaidCount;
    
    const partialPaymentsCountEl = document.getElementById('partialPaymentsCount');
    if (partialPaymentsCountEl) partialPaymentsCountEl.textContent = partialPaymentsCount;
    
    const unpaidCountEl = document.getElementById('unpaidCount');
    if (unpaidCountEl) unpaidCountEl.textContent = unpaidCount;
    
    const totalPaidEl = document.getElementById('totalPaid');
    if (totalPaidEl) totalPaidEl.textContent = `₵${totalPaid.toFixed(2)}`;
    
    const remainingAmountEl = document.getElementById('remainingAmount');
    if (remainingAmountEl) remainingAmountEl.textContent = `₵${remainingAmount.toFixed(2)}`;
}

function updatePagination() {
    // Update showing info
    const start = (currentPage - 1) * itemsPerPage + 1;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    
    const showingStartEl = document.getElementById('showingStart');
    if (showingStartEl) showingStartEl.textContent = totalItems > 0 ? start : 0;
    
    const showingEndEl = document.getElementById('showingEnd');
    if (showingEndEl) showingEndEl.textContent = end;
    
    const totalItemsEl = document.getElementById('totalItems');
    if (totalItemsEl) totalItemsEl.textContent = totalItems;
    
    // Update pagination buttons
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
    
    // Generate page numbers
    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';
    
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.textContent = i;
        pageBtn.className = `px-3 py-1 text-sm rounded ${
            i === currentPage 
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
        }`;
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            loadSalesHistory();
        });
        pageNumbers.appendChild(pageBtn);
    }
}

function filterSales() {
    const searchTerm = document.getElementById('salesSearch').value.toLowerCase();
    const filteredSales = salesData.filter(sale => 
        sale.id.toString().includes(searchTerm) ||
        (sale.customer_name && sale.customer_name.toLowerCase().includes(searchTerm))
    );
    
    const originalData = salesData;
    salesData = filteredSales;
    renderSalesTable();
    salesData = originalData;
}

async function viewSaleDetails(saleId) {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const response = await fetch(`${basePath}/api/pos/sale/${saleId}`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            alert('Error loading sale details: ' + (errorData.error || errorData.message || `Status ${response.status}`));
            console.error('Error loading sale details:', errorData);
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            const sale = data.data;
            const content = document.getElementById('saleDetailsContent');
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sale ID</label>
                            <p class="text-lg font-semibold">${sale.unique_id || 'SEL-SALE-' + sale.id}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date & Time</label>
                            <p class="text-lg">${new Date(sale.created_at).toLocaleString('en-GB', { 
                                day: '2-digit', 
                                month: 'short', 
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer</label>
                            <p class="text-lg">${sale.customer_name || sale.customer_name_from_table || 'Walk-in Customer'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                            <p class="text-lg">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getPaymentMethodClass(sale.payment_method)}">
                                    ${sale.payment_method || 'Cash'}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Items (${sale.items?.length || 0})</label>
                        <div class="mt-2 space-y-2 max-h-96 overflow-y-auto">
                            ${(sale.items || []).map(item => `
                                <div class="flex justify-between items-start p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">${item.item_description || item.product_name || 'Product'}</p>
                                        ${item.product_name && item.item_description !== item.product_name ? `<p class="text-xs text-blue-600 mt-1">Product: ${item.product_name}</p>` : ''}
                                        <div class="flex gap-4 mt-2 text-xs text-gray-600">
                                            <span>Qty: ${item.quantity}</span>
                                            <span>× ₵${parseFloat(item.unit_price || 0).toFixed(2)}</span>
                                            ${item.category_name ? `<span class="text-gray-500">Category: ${item.category_name}</span>` : ''}
                                            ${item.brand_name ? `<span class="text-gray-500">Brand: ${item.brand_name}</span>` : ''}
                                        </div>
                                        ${item.product_id ? `<p class="text-xs text-gray-400 mt-1">Product ID: ${item.product_id}</p>` : ''}
                                    </div>
                                    <div class="text-right ml-4">
                                        <p class="font-semibold text-gray-900">₵${parseFloat(item.total_price || 0).toFixed(2)}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total Amount:</span>
                            <span class="text-green-600">₵${parseFloat(sale.final_amount || sale.total || 0).toFixed(2)}</span>
                        </div>
                        ${sale.discount ? `<div class="flex justify-between text-sm text-gray-600 mt-2">
                            <span>Discount:</span>
                            <span>-₵${parseFloat(sale.discount).toFixed(2)}</span>
                        </div>` : ''}
                        ${sale.tax ? `<div class="flex justify-between text-sm text-gray-600">
                            <span>Tax:</span>
                            <span>₵${parseFloat(sale.tax).toFixed(2)}</span>
                        </div>` : ''}
                        ${sale.cashier_name ? `<div class="flex justify-between text-sm text-gray-500 mt-2 pt-2 border-t">
                            <span>Cashier:</span>
                            <span>${sale.cashier_name}</span>
                        </div>` : ''}
                    </div>
                </div>
            `;
            
            document.getElementById('saleDetailsModal').classList.remove('hidden');
        } else {
            alert('Error: ' + (data.error || data.message || 'Failed to load sale details'));
            console.error('Failed to load sale details:', data);
        }
    } catch (error) {
        console.error('Error loading sale details:', error);
        alert('Error loading sale details. Please check the console for details.');
    }
}

function printReceipt(saleId) {
    // Open receipt in new window for printing
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
    window.open(`${basePath}/pos/receipt/${saleId}`, '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
}

let currentPaymentSaleId = null;

async function openPaymentModal(saleId) {
    currentPaymentSaleId = saleId;
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
        // Fetch payment information
        const response = await fetch(`${basePath}/api/pos/sale/${saleId}/payments`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            alert('Error loading payment information');
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            const stats = data.payment_stats;
            const finalAmount = parseFloat(data.final_amount || 0);
            const totalPaid = parseFloat(stats.total_paid || 0);
            const remaining = parseFloat(stats.remaining || 0);
            
            // Update modal content
            document.getElementById('paymentSaleId').textContent = `#${saleId}`;
            document.getElementById('paymentTotalAmount').textContent = `₵${finalAmount.toFixed(2)}`;
            document.getElementById('paymentTotalPaid').textContent = `₵${totalPaid.toFixed(2)}`;
            document.getElementById('paymentRemaining').textContent = `₵${remaining.toFixed(2)}`;
            
            // Set max amount to remaining
            const amountInput = document.getElementById('paymentAmount');
            amountInput.max = remaining;
            amountInput.value = '';
            
            // Reset form
            document.getElementById('paymentMethod').value = 'CASH';
            document.getElementById('paymentNotes').value = '';
            
            // Show modal
            document.getElementById('paymentModal').classList.remove('hidden');
        } else {
            alert('Error: ' + (data.message || 'Failed to load payment information'));
        }
    } catch (error) {
        console.error('Error opening payment modal:', error);
        alert('Error loading payment information. Please try again.');
    }
}

async function submitPayment() {
    if (!currentPaymentSaleId) {
        alert('No sale selected');
        return;
    }
    
    const amount = parseFloat(document.getElementById('paymentAmount').value);
    const paymentMethod = document.getElementById('paymentMethod').value;
    const notes = document.getElementById('paymentNotes').value;
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid payment amount');
        return;
    }
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
        const response = await fetch(`${basePath}/api/pos/sale/${currentPaymentSaleId}/payment`, {
            method: 'POST',
            headers: {
                ...getAuthHeaders(),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                amount: amount,
                payment_method: paymentMethod,
                notes: notes || null
            }),
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Payment recorded successfully!');
            document.getElementById('paymentModal').classList.add('hidden');
            loadSalesHistory(); // Reload sales to update payment status
        } else {
            alert('Error: ' + (data.message || 'Failed to record payment'));
        }
    } catch (error) {
        console.error('Error submitting payment:', error);
        alert('Error recording payment. Please try again.');
    }
}

// Check if token is expired (simple JWT expiration check)
function isTokenExpired(token) {
    if (!token) return true;
    try {
        const parts = token.split(".");
        if (parts.length !== 3) return true;
        
        let base64 = parts[1].replace(/-/g, "+").replace(/_/g, "/");
        const payload = JSON.parse(atob(base64));
        
        if (payload.exp) {
            return payload.exp * 1000 < Date.now();
        }
        return false;
    } catch (e) {
        return true;
    }
}

function getAuthHeaders() {
    const headers = {
        'Content-Type': 'application/json'
    };
    const token = localStorage.getItem('sellapp_token') || localStorage.getItem('token');
    if (token && !isTokenExpired(token)) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
}

// Make functions global
window.viewSaleDetails = viewSaleDetails;
window.printReceipt = printReceipt;
window.deleteSale = deleteSale;
window.openPaymentModal = openPaymentModal;
</script>
