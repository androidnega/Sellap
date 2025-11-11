<?php
/**
 * Partial Payments Management Page
 * Allows salespersons to view and manage partial payments for sales
 */
?>
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Partial Payments Management</h2>
        <p class="text-gray-600">Track and manage customer payments for sales</p>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded shadow mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Filter Sales</h3>
        </div>
        <div class="p-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Sales</label>
                    <div class="relative">
                        <input type="text" id="paymentsSearch" placeholder="Search by sale ID, customer..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                <div class="md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                    <select id="paymentStatusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        <option value="">Partial & Unpaid Only</option>
                        <option value="PARTIAL">Partial Only</option>
                        <option value="UNPAID">Unpaid Only</option>
                        <option value="PAID">Fully Paid</option>
                    </select>
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
                    <button id="filterPaymentsBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow-sm border border-blue-200 p-5 payment-card">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-blue-200 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-receipt text-blue-700 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Sales</p>
                    <p id="totalSalesCount" class="text-2xl font-bold text-gray-800 mt-1">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg shadow-sm border border-green-200 p-5 payment-card">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-green-200 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-check-circle text-green-700 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Fully Paid</p>
                    <p id="paidSalesCount" class="text-2xl font-bold text-gray-800 mt-1">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg shadow-sm border border-yellow-200 p-5 payment-card">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-yellow-200 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-exclamation-circle text-yellow-700 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Partial Payments</p>
                    <p id="partialSalesCount" class="text-2xl font-bold text-gray-800 mt-1">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg shadow-sm border border-red-200 p-5 payment-card">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-red-200 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-times-circle text-red-700 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Unpaid</p>
                    <p id="unpaidSalesCount" class="text-2xl font-bold text-gray-800 mt-1">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales with Payments Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-list-alt text-gray-600 mr-2"></i>
                Sales & Payments
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gradient-to-r from-gray-100 to-gray-50 text-gray-700 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-6 py-4 text-left">Sale ID</th>
                        <th class="px-6 py-4 text-left">Customer</th>
                        <th class="px-6 py-4 text-left">Total Amount</th>
                        <th class="px-6 py-4 text-left">Paid</th>
                        <th class="px-6 py-4 text-left">Remaining</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin text-2xl mb-4 text-blue-600"></i>
                            <p class="text-gray-600">Loading payments data...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-gray-700 font-medium">
                    Showing <span id="showingStart" class="font-semibold text-gray-900">0</span> to <span id="showingEnd" class="font-semibold text-gray-900">0</span> of <span id="totalItems" class="font-semibold text-gray-900">0</span> results
                </div>
                <div class="flex items-center space-x-2">
                    <button id="prevPageBtn" class="px-4 py-2 text-sm bg-white hover:bg-gray-100 text-gray-700 rounded-lg border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-medium shadow-sm hover:shadow disabled:shadow-none" disabled>
                        <i class="fas fa-chevron-left mr-1"></i>Previous
                    </button>
                    <div id="pageNumbers" class="flex items-center space-x-1">
                        <!-- Page numbers will be generated here -->
                    </div>
                    <button id="nextPageBtn" class="px-4 py-2 text-sm bg-white hover:bg-gray-100 text-gray-700 rounded-lg border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-medium shadow-sm hover:shadow disabled:shadow-none" disabled>
                        Next<i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal (Reuse from sales history) -->
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 backdrop-blur-sm">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-money-bill-wave text-blue-600 mr-2"></i>
                    Record Payment
                </h3>
                <button id="closePaymentModal" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="paymentModalContent">
                <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><span class="font-medium">Sale ID:</span> <span id="paymentSaleId" class="font-semibold text-gray-900"></span></p>
                        <p class="text-sm text-gray-700"><span class="font-medium">Total Amount:</span> <span id="paymentTotalAmount" class="font-semibold text-gray-900"></span></p>
                        <p class="text-sm text-gray-700"><span class="font-medium">Total Paid:</span> <span id="paymentTotalPaid" class="font-semibold text-green-700"></span></p>
                        <p class="text-sm text-gray-700"><span class="font-medium">Remaining:</span> <span id="paymentRemaining" class="font-semibold text-orange-700"></span></p>
                    </div>
                </div>
                
                <form id="paymentForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount *</label>
                        <input type="number" id="paymentAmount" step="0.01" min="0.01" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                        <select id="paymentMethod" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            <option value="CASH">Cash</option>
                            <option value="MOBILE_MONEY">Mobile Money</option>
                            <option value="CARD">Card</option>
                            <option value="BANK_TRANSFER">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea id="paymentNotes" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" id="cancelPaymentBtn" class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-all font-medium shadow-sm hover:shadow">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg transition-all font-medium shadow-md hover:shadow-lg">
                            <i class="fas fa-save mr-2"></i>Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div id="paymentHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 backdrop-blur-sm">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto border border-gray-200">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-history text-blue-600 mr-2"></i>
                    Payment History
                </h3>
                <button id="closePaymentHistoryModal" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="paymentHistoryContent">
                <!-- Payment history will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadPartialPayments();
    setupEventListeners();
});

let paymentsData = [];
let currentPage = 1;
let itemsPerPage = 20;
let totalPages = 1;
let totalItems = 0;
let partialPaymentsEnabled = false;

async function loadPartialPayments() {
    try {
        const companyId = <?= isset($_SESSION['user']['company_id']) ? (int)$_SESSION['user']['company_id'] : 'null' ?>;
        if (!companyId) {
            console.error('No company ID found');
            return;
        }
        
        // Check if partial payments module is enabled
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        const modulesResponse = await fetch(`${basePath}/api/admin/company/${companyId}/modules`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (modulesResponse.ok) {
            const modulesData = await modulesResponse.json();
            if (modulesData.success && modulesData.modules) {
                const module = modulesData.modules.find(m => m.key === 'partial_payments');
                partialPaymentsEnabled = module ? module.enabled : false;
                
                if (!partialPaymentsEnabled) {
                    document.getElementById('paymentsTableBody').innerHTML = `
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-ban text-2xl mb-4 text-red-500"></i>
                                <p class="font-semibold">Partial Payments feature is not enabled</p>
                                <p class="text-sm mt-2">Please contact your administrator to enable this feature.</p>
                            </td>
                        </tr>
                    `;
                    return;
                }
            }
        }
        
        // Get filter values
        const search = document.getElementById('paymentsSearch').value;
        const statusFilter = document.getElementById('paymentStatusFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        // Build query string
        const params = new URLSearchParams({
            page: currentPage,
            limit: itemsPerPage
        });
        
        if (search) params.append('search', search);
        if (statusFilter) params.append('payment_status', statusFilter);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        
        const response = await fetch(`${basePath}/api/pos/partial-payments?${params.toString()}`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage = 'Failed to load payments data';
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.message || errorData.error || errorMessage;
            } catch (e) {
                errorMessage = `Server error: ${response.status} ${response.statusText}`;
            }
            throw new Error(errorMessage);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 200));
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
            paymentsData = data.sales || [];
            totalItems = data.total || 0;
            totalPages = Math.ceil(totalItems / itemsPerPage);
            
            renderPaymentsTable();
            updateSummaryCards();
            updatePagination();
        } else {
            throw new Error(data.message || 'Failed to load payments');
        }
    } catch (error) {
        console.error('Error loading partial payments:', error);
        document.getElementById('paymentsTableBody').innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-2xl mb-4"></i>
                    <p>Error loading payments: ${error.message}</p>
                </td>
            </tr>
        `;
    }
}

function renderPaymentsTable() {
    const tbody = document.getElementById('paymentsTableBody');
    
    if (paymentsData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                    <i class="fas fa-receipt text-2xl mb-4"></i>
                    <p>No sales found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = paymentsData.map(sale => {
        const totalPaid = parseFloat(sale.total_paid || 0);
        const finalAmount = parseFloat(sale.final_amount || 0);
        const remaining = Math.max(0, finalAmount - totalPaid);
        const paymentStatus = sale.payment_status || 'PAID';
        
        return `
        <tr class="payment-table-row">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                #${sale.id}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                ${sale.customer_name || sale.customer_name_from_table || 'Walk-in'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                ₵${finalAmount.toFixed(2)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700 font-semibold">
                ₵${totalPaid.toFixed(2)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-700 font-semibold">
                ₵${remaining.toFixed(2)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                ${getPaymentStatusBadge(paymentStatus, finalAmount)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                ${new Date(sale.created_at).toLocaleDateString('en-GB', { 
                    day: '2-digit', 
                    month: 'short', 
                    year: 'numeric' 
                })}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="flex items-center space-x-2">
                    <button onclick="viewPaymentHistory(${sale.id})" class="payment-action-btn text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition-all" title="View Payment History">
                        <i class="fas fa-history"></i>
                    </button>
                    ${(paymentStatus === 'PARTIAL' || paymentStatus === 'UNPAID') ? `
                    <button onclick="openPaymentModal(${sale.id})" class="payment-action-btn text-orange-600 hover:text-orange-800 p-2 rounded-lg hover:bg-orange-50 transition-all" title="Add Payment">
                        <i class="fas fa-money-bill-wave"></i>
                    </button>
                    ` : ''}
                    <button onclick="printReceipt(${sale.id})" class="payment-action-btn text-green-600 hover:text-green-800 p-2 rounded-lg hover:bg-green-50 transition-all" title="Print Receipt">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </td>
        </tr>
        `;
    }).join('');
}

function getPaymentStatusBadge(status, totalAmount) {
    const statusUpper = (status || 'PAID').toUpperCase();
    let badgeClass = '';
    let badgeText = '';
    let icon = '';
    
    switch(statusUpper) {
        case 'PAID':
            badgeClass = 'bg-green-100 text-green-800 border border-green-200';
            badgeText = 'Paid';
            icon = '<i class="fas fa-check-circle mr-1"></i>';
            break;
        case 'PARTIAL':
            badgeClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
            badgeText = 'Partial';
            icon = '<i class="fas fa-exclamation-circle mr-1"></i>';
            break;
        case 'UNPAID':
            badgeClass = 'bg-red-100 text-red-800 border border-red-200';
            badgeText = 'Unpaid';
            icon = '<i class="fas fa-times-circle mr-1"></i>';
            break;
        default:
            badgeClass = 'bg-gray-100 text-gray-800 border border-gray-200';
            badgeText = statusUpper;
    }
    
    return `<span class="status-badge ${badgeClass}">${icon}${badgeText}</span>`;
}

function updateSummaryCards() {
    const total = paymentsData.length;
    const paid = paymentsData.filter(s => (s.payment_status || 'PAID').toUpperCase() === 'PAID').length;
    const partial = paymentsData.filter(s => (s.payment_status || '').toUpperCase() === 'PARTIAL').length;
    const unpaid = paymentsData.filter(s => (s.payment_status || '').toUpperCase() === 'UNPAID').length;
    
    document.getElementById('totalSalesCount').textContent = total;
    document.getElementById('paidSalesCount').textContent = paid;
    document.getElementById('partialSalesCount').textContent = partial;
    document.getElementById('unpaidSalesCount').textContent = unpaid;
}

function updatePagination() {
    const start = totalItems > 0 ? ((currentPage - 1) * itemsPerPage) + 1 : 0;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    
    document.getElementById('showingStart').textContent = start;
    document.getElementById('showingEnd').textContent = end;
    document.getElementById('totalItems').textContent = totalItems;
    
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
    
    // Generate page numbers
    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';
    
    const maxPages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `px-3 py-1 text-sm rounded ${i === currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            loadPartialPayments();
        });
        pageNumbers.appendChild(pageBtn);
    }
}

function setupEventListeners() {
    document.getElementById('filterPaymentsBtn').addEventListener('click', () => {
        currentPage = 1;
        loadPartialPayments();
    });
    
    document.getElementById('paymentsSearch').addEventListener('input', debounce(() => {
        currentPage = 1;
        loadPartialPayments();
    }, 500));
    
    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadPartialPayments();
        }
    });
    
    document.getElementById('nextPageBtn').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadPartialPayments();
        }
    });
    
    // Payment modal
    document.getElementById('closePaymentModal').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.add('hidden');
    });
    
    document.getElementById('cancelPaymentBtn').addEventListener('click', () => {
        document.getElementById('paymentModal').classList.add('hidden');
    });
    
    document.getElementById('paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitPayment();
    });
    
    // Payment history modal
    document.getElementById('closePaymentHistoryModal').addEventListener('click', () => {
        document.getElementById('paymentHistoryModal').classList.add('hidden');
    });
}

let currentPaymentSaleId = null;

async function openPaymentModal(saleId) {
    currentPaymentSaleId = saleId;
    
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
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
            
            document.getElementById('paymentSaleId').textContent = `#${saleId}`;
            document.getElementById('paymentTotalAmount').textContent = `₵${finalAmount.toFixed(2)}`;
            document.getElementById('paymentTotalPaid').textContent = `₵${totalPaid.toFixed(2)}`;
            document.getElementById('paymentRemaining').textContent = `₵${remaining.toFixed(2)}`;
            
            const amountInput = document.getElementById('paymentAmount');
            amountInput.max = remaining;
            amountInput.value = '';
            
            document.getElementById('paymentMethod').value = 'CASH';
            document.getElementById('paymentNotes').value = '';
            
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
            loadPartialPayments();
        } else {
            alert('Error: ' + (data.message || 'Failed to record payment'));
        }
    } catch (error) {
        console.error('Error submitting payment:', error);
        alert('Error recording payment. Please try again.');
    }
}

async function viewPaymentHistory(saleId) {
    try {
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
        
        const response = await fetch(`${basePath}/api/pos/sale/${saleId}/payments`, {
            headers: getAuthHeaders(),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            alert('Error loading payment history');
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            const payments = data.payments || [];
            const stats = data.payment_stats;
            
            let historyHTML = `
                <div class="mb-6 p-5 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                    <h4 class="font-semibold mb-3 text-gray-800 flex items-center">
                        <i class="fas fa-receipt text-blue-600 mr-2"></i>
                        Sale #${saleId} - Payment Summary
                    </h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="p-2 bg-white rounded"><span class="text-gray-600">Total Amount:</span> <span class="font-semibold text-gray-900">₵${parseFloat(data.final_amount || 0).toFixed(2)}</span></div>
                        <div class="p-2 bg-white rounded"><span class="text-gray-600">Total Paid:</span> <span class="font-semibold text-green-700">₵${parseFloat(stats.total_paid || 0).toFixed(2)}</span></div>
                        <div class="p-2 bg-white rounded"><span class="text-gray-600">Remaining:</span> <span class="font-semibold text-orange-700">₵${parseFloat(stats.remaining || 0).toFixed(2)}</span></div>
                        <div class="p-2 bg-white rounded"><span class="text-gray-600">Status:</span> ${getPaymentStatusBadge(stats.payment_status, data.final_amount)}</div>
                    </div>
                </div>
                
                <h4 class="font-semibold mb-4 text-gray-800 flex items-center">
                    <i class="fas fa-history text-gray-600 mr-2"></i>
                    Payment History
                </h4>
            `;
            
            if (payments.length === 0) {
                historyHTML += '<p class="text-gray-500 text-center py-4">No payments recorded yet</p>';
            } else {
                historyHTML += `
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gradient-to-r from-gray-100 to-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Recorded By</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${payments.map(payment => `
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-gray-700">${new Date(payment.created_at).toLocaleString()}</td>
                                        <td class="px-4 py-3 font-semibold text-green-700">₵${parseFloat(payment.amount || 0).toFixed(2)}</td>
                                        <td class="px-4 py-3 text-gray-700"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">${payment.payment_method || 'CASH'}</span></td>
                                        <td class="px-4 py-3 text-gray-700">${payment.recorded_by_name || payment.recorded_by_username || 'N/A'}</td>
                                        <td class="px-4 py-3 text-gray-600">${payment.notes || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            document.getElementById('paymentHistoryContent').innerHTML = historyHTML;
            document.getElementById('paymentHistoryModal').classList.remove('hidden');
        } else {
            alert('Error: ' + (data.message || 'Failed to load payment history'));
        }
    } catch (error) {
        console.error('Error loading payment history:', error);
        alert('Error loading payment history. Please try again.');
    }
}

function printReceipt(saleId) {
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');
    window.open(`${basePath}/pos/receipt/${saleId}`, '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
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
window.openPaymentModal = openPaymentModal;
window.viewPaymentHistory = viewPaymentHistory;
window.printReceipt = printReceipt;
</script>

