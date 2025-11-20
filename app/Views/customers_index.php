<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Customer Management</h2>
        <p class="text-gray-600">Manage all customers in the system</p>
    </div>
    
    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1">
                <label for="customerSearch" class="block text-sm font-medium text-gray-700 mb-1">Search Customers</label>
                <div class="relative">
                    <input type="text" id="customerSearch" 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                           placeholder="Search by name, phone, email, or customer ID..." 
                           class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </div>
            
            <!-- Show Duplicates Toggle -->
            <div class="md:w-48 flex items-end">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="showDuplicatesOnly" 
                           class="mr-2 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                           onchange="toggleDuplicatesFilter()">
                    <span class="text-sm text-gray-700">Show duplicates only</span>
                </label>
            </div>
            
            <!-- Date Filter -->
            <div class="md:w-48">
                <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-1">Date Filter</label>
                <select id="dateFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Time</option>
                    <option value="today" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'today') ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'week') ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="month" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'month') ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="year" <?= (isset($_GET['date_filter']) && $_GET['date_filter'] === 'year') ? 'selected' : '' ?>>Last Year</option>
                </select>
            </div>
            
            <!-- New Customer Button - Only for salesperson and technician (repairer) -->
            <?php
            // Get user role from session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userRole = $_SESSION['user']['role'] ?? '';
            $canCreateCustomer = in_array($userRole, ['salesperson', 'technician']);
            ?>
            <?php if ($canCreateCustomer): ?>
            <div class="flex items-end">
                <button id="openCustomerModal" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>New Customer
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Filter Info -->
        <div id="customerFilterInfo" class="text-xs text-gray-500 mt-2 hidden">
            Filtered: <span id="customerFilteredCount">0</span> of <span id="customerTotalCount">0</span> customers
        </div>
    </div>
    
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="customersTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $customer): ?>
                        <?php 
                        $isDuplicate = $customer['is_duplicate'] ?? false;
                        $duplicateCount = $customer['duplicate_count'] ?? 1;
                        $rowClass = $isDuplicate ? 'bg-yellow-50 hover:bg-yellow-100 border-l-4 border-yellow-400' : '';
                        ?>
                        <tr data-customer-id="<?= $customer['id'] ?>" class="<?= $rowClass ?>" data-phone="<?= htmlspecialchars($customer['phone_number'] ?? '') ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($customer['unique_id']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center gap-2">
                                    <?= htmlspecialchars($customer['full_name']) ?>
                                    <?php if ($isDuplicate): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800" title="Duplicate phone number">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Duplicate (<?= $duplicateCount ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center gap-2">
                                    <?= htmlspecialchars($customer['phone_number'] ?? 'N/A') ?>
                                    <?php if ($isDuplicate && !empty($customer['phone_number'])): ?>
                                        <button onclick="showDuplicateCustomers('<?= htmlspecialchars($customer['phone_number']) ?>')" 
                                                class="text-blue-600 hover:text-blue-800 text-xs underline" 
                                                title="View all customers with this phone number">
                                            <i class="fas fa-search"></i> Find All
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($customer['email'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('M j, Y', strtotime($customer['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <button onclick="viewCustomer(<?= $customer['id'] ?>)" class="text-blue-600 hover:text-blue-900 transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="viewCustomerHistory(<?= $customer['id'] ?>, '<?= htmlspecialchars($customer['full_name']) ?>')" class="text-purple-600 hover:text-purple-900 transition-colors relative group" title="Purchase History">
                                        <i class="fas fa-history"></i>
                                        <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                            Purchase History
                                        </span>
                                    </button>
                                    <button onclick="editCustomer(<?= $customer['id'] ?>)" class="text-green-600 hover:text-green-900 transition-colors" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars($customer['full_name']) ?>')" class="text-red-600 hover:text-red-900 transition-colors" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            No customers found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?= \App\Helpers\PaginationHelper::render($pagination) ?>
</div>

<!-- Customer Creation Modal -->
<div id="customerModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Create New Customer</h3>
                <button id="closeCustomerModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form id="customerForm" class="px-6 py-4">
            <div class="space-y-4">
                <div>
                    <label for="customerFullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" id="customerFullName" name="full_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter customer's full name">
                </div>
                
                <div>
                    <label for="customerPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                    <input type="tel" id="customerPhone" name="phone_number" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter phone number">
                </div>
                
                <div>
                    <label for="customerEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" id="customerEmail" name="email"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter email address (optional)">
                </div>
                
                <div>
                    <label for="customerAddress" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="customerAddress" name="address" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                              placeholder="Enter customer address (optional)"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" id="cancelCustomerModal" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="submitCustomer" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Create Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Customer View Modal -->
<div id="viewCustomerModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Customer Details</h3>
                <button id="closeViewCustomerModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="px-6 py-4">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer ID</label>
                    <p id="viewCustomerId" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <p id="viewCustomerName" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <p id="viewCustomerPhone" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <p id="viewCustomerEmail" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <p id="viewCustomerAddress" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded min-h-[60px]"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Created</label>
                    <p id="viewCustomerCreated" class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded"></p>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200">
                <button id="viewCustomerHistoryBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-history mr-2"></i>View History
                </button>
                <button id="closeViewCustomerModalBtn" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer History Modal -->
<div id="customerHistoryModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 my-8 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Purchase History</h3>
                    <p class="text-sm text-gray-500 mt-1" id="historyCustomerName"></p>
                </div>
                <button id="closeHistoryModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="px-6 py-4 overflow-y-auto flex-1">
            <!-- Search and Filters -->
            <div id="customerHistoryFilters" class="mb-4 hidden">
                <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <div class="flex flex-col md:flex-row gap-3">
                        <!-- Search Input -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <input type="text" id="historySearchInput" 
                                       placeholder="Search by item name, reference, description..." 
                                       class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <!-- Type Filter -->
                        <div class="md:w-48">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="historyTypeFilter" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Types</option>
                                <option value="sale">Direct Purchase</option>
                                <option value="repair">Repair Service</option>
                                <option value="swap">Product Swap</option>
                            </select>
                        </div>
                        
                        <!-- Date Range Filter -->
                        <div class="md:w-40">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
                            <select id="historyDateFilter" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="year">Last Year</option>
                            </select>
                        </div>
                        
                        <!-- Clear Filters Button -->
                        <div class="flex items-end">
                            <button id="clearHistoryFilters" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    
                    <!-- Results Count -->
                    <div class="text-xs text-gray-500">
                        Showing <span id="historyResultsCount">0</span> of <span id="historyTotalCount">0</span> transactions
                    </div>
                </div>
            </div>
            
            <div id="customerHistoryLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <p class="mt-2 text-sm text-gray-500">Loading history...</p>
            </div>
            
            <div id="customerHistoryContent" class="hidden">
                <div id="customerHistoryList" class="space-y-3">
                    <!-- History items will be loaded here -->
                </div>
                <div id="customerHistoryEmpty" class="hidden text-center py-8">
                    <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                    <p class="text-sm text-gray-500">No purchase history found</p>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end flex-shrink-0">
            <button id="closeHistoryModalBtn" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Customer Edit Modal -->
<div id="editCustomerModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Edit Customer</h3>
                <button id="closeEditCustomerModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form id="editCustomerForm" class="px-6 py-4">
            <input type="hidden" id="editCustomerId" name="id">
            
            <div class="space-y-4">
                <div>
                    <label for="editCustomerFullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" id="editCustomerFullName" name="full_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter customer's full name">
                </div>
                
                <div>
                    <label for="editCustomerPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                    <input type="tel" id="editCustomerPhone" name="phone_number" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter phone number">
                </div>
                
                <div>
                    <label for="editCustomerEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" id="editCustomerEmail" name="email"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Enter email address (optional)">
                </div>
                
                <div>
                    <label for="editCustomerAddress" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="editCustomerAddress" name="address" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                              placeholder="Enter customer address (optional)"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" id="cancelEditCustomerModal" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="submitEditCustomer" 
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Update Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Success/Error Notification -->
<div id="customerNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-xl hidden z-50 max-w-sm">
    <div class="flex items-center justify-between">
        <span id="customerNotificationMessage">Customer created successfully!</span>
        <button id="closeCustomerNotification" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="ml-3 text-lg font-medium text-gray-900">Error</h3>
            </div>
            <div class="mt-4">
                <p id="errorModalMessage" class="text-sm text-gray-600"></p>
            </div>
            <div class="mt-6 flex justify-end">
                <button id="closeErrorModal" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global notification function - accessible from anywhere
function showNotification(message, type = 'success') {
    const notification = document.getElementById('customerNotification');
    const notificationMessage = document.getElementById('customerNotificationMessage');
    
    if (notificationMessage) {
        notificationMessage.textContent = message;
    }
    if (notification) {
        notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-xl z-50 max-w-sm ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
        notification.classList.remove('hidden');
        
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 5000);
    } else {
        // Fallback to alert if notification element doesn't exist
        alert(message);
    }
}

// Global error modal function - shows centered modal for errors
function showErrorModal(message) {
    const errorModal = document.getElementById('errorModal');
    const errorModalMessage = document.getElementById('errorModalMessage');
    
    if (errorModalMessage) {
        errorModalMessage.textContent = message;
    }
    if (errorModal) {
        errorModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    } else {
        // Fallback to alert if modal doesn't exist
        alert(message);
    }
}

// Close error modal
function closeErrorModal() {
    const errorModal = document.getElementById('errorModal');
    if (errorModal) {
        errorModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('customerModal');
    const form = document.getElementById('customerForm');
    const openBtn = document.getElementById('openCustomerModal');
    const closeBtn = document.getElementById('closeCustomerModal');
    const cancelBtn = document.getElementById('cancelCustomerModal');
    const submitBtn = document.getElementById('submitCustomer');
    const notification = document.getElementById('customerNotification');
    const notificationMessage = document.getElementById('customerNotificationMessage');
    const closeNotification = document.getElementById('closeCustomerNotification');

    // Early return if essential elements don't exist
    if (!modal || !form) {
        console.warn('Customer modal or form elements not found');
        return;
    }

    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', function() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }

    // Close modal
    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        if (form) form.reset();
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Show loading state
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('<?= BASE_URL_PATH ?>/api/customers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (localStorage.getItem('sellapp_token') || localStorage.getItem('token'))
                },
                body: JSON.stringify(data)
            });
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid JSON response from server');
            }
            
            // Check if response is ok
            if (!response.ok) {
                // Handle error response
                let errorMsg = result.error || 'Failed to create customer';
                if (result.existing_customer) {
                    errorMsg = `${errorMsg}\n\nExisting customer: ${result.existing_customer.full_name} (ID: ${result.existing_customer.unique_id})`;
                }
                showNotification(errorMsg, 'error');
                return;
            }
            
            if (result.success) {
                showNotification('Customer created successfully!', 'success');
                closeModal();
                // Clear form and reload immediately to show the new customer
                form.reset();
                // Force reload without cache
                window.location.reload(true);
            } else {
                // Check if it's a duplicate phone number error
                let errorMsg = result.error || 'Failed to create customer';
                if (result.existing_customer) {
                    errorMsg = `${errorMsg}\n\nExisting customer: ${result.existing_customer.full_name} (ID: ${result.existing_customer.unique_id})`;
                }
                showNotification(errorMsg, 'error');
            }
        } catch (error) {
            console.error('Error creating customer:', error);
            showNotification('Error creating customer: ' + error.message, 'error');
        } finally {
            // Reset button
            if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }
    });

    // Close notification
    if (closeNotification) {
        closeNotification.addEventListener('click', function() {
            if (notification) {
                notification.classList.add('hidden');
            }
        });
    }
    
    // Close error modal
    const closeErrorModalBtn = document.getElementById('closeErrorModal');
    const errorModal = document.getElementById('errorModal');
    if (closeErrorModalBtn) {
        closeErrorModalBtn.addEventListener('click', closeErrorModal);
    }
    
    // Close error modal when clicking outside
    if (errorModal) {
        errorModal.addEventListener('click', function(e) {
            if (e.target === errorModal) {
                closeErrorModal();
            }
        });
    }
});

// Store current customer data for history view
let currentViewCustomerId = null;
let currentViewCustomerName = null;

// Global functions for customer actions
async function viewCustomer(customerId) {
    try {
        const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        const response = await fetch(`${base}/api/customers/${customerId}`);
        
        if (!response.ok) {
            if (response.status === 404) {
                if (typeof showNotification === 'function') {
                    showNotification('Customer not found', 'error');
                } else {
                    alert('Customer not found');
                }
                return;
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            const customer = result.data;
            
            // Store customer data for history view
            currentViewCustomerId = customerId;
            currentViewCustomerName = customer.full_name || 'Customer';
            
            // Populate view modal
            document.getElementById('viewCustomerId').textContent = customer.unique_id || 'N/A';
            document.getElementById('viewCustomerName').textContent = customer.full_name || 'N/A';
            document.getElementById('viewCustomerPhone').textContent = customer.phone_number || 'N/A';
            document.getElementById('viewCustomerEmail').textContent = customer.email || 'N/A';
            document.getElementById('viewCustomerAddress').textContent = customer.address || 'N/A';
            document.getElementById('viewCustomerCreated').textContent = customer.created_at ? new Date(customer.created_at).toLocaleDateString() : 'N/A';
            
            // Show modal
            document.getElementById('viewCustomerModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            if (typeof showNotification === 'function') {
                showNotification(result.error || 'Failed to load customer details', 'error');
            } else {
                alert(result.error || 'Failed to load customer details');
            }
        }
    } catch (error) {
        console.error('Error loading customer:', error);
        if (typeof showNotification === 'function') {
            showNotification('Error loading customer details: ' + error.message, 'error');
        } else {
            alert('Error loading customer details: ' + error.message);
        }
    }
}

async function viewCustomerHistory(customerId, customerName) {
    // Set customer name in modal
    document.getElementById('historyCustomerName').textContent = customerName;
    
    // Show modal
    document.getElementById('customerHistoryModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load history
    await loadCustomerHistory(customerId);
}

let allHistoryData = []; // Store all history for filtering

async function loadCustomerHistory(customerId) {
    try {
        const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        
        // Show loading state
        document.getElementById('customerHistoryLoading').classList.remove('hidden');
        document.getElementById('customerHistoryContent').classList.add('hidden');
        document.getElementById('customerHistoryFilters').classList.add('hidden');
        document.getElementById('customerHistoryEmpty').classList.add('hidden');
        
        const response = await fetch(`${base}/api/customers/${customerId}/history`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        // Hide loading
        document.getElementById('customerHistoryLoading').classList.add('hidden');
        
        if (result.success && result.data && result.data.length > 0) {
            allHistoryData = result.data; // Store for filtering
            document.getElementById('historyTotalCount').textContent = result.data.length;
            renderHistoryList(result.data);
            document.getElementById('customerHistoryFilters').classList.remove('hidden');
            document.getElementById('customerHistoryContent').classList.remove('hidden');
        } else {
            document.getElementById('customerHistoryEmpty').classList.remove('hidden');
            document.getElementById('customerHistoryContent').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading customer history:', error);
        document.getElementById('customerHistoryLoading').classList.add('hidden');
        document.getElementById('customerHistoryEmpty').classList.remove('hidden');
        document.getElementById('customerHistoryContent').classList.remove('hidden');
    }
}

function renderHistoryList(data) {
    const historyList = document.getElementById('customerHistoryList');
    
    if (!data || data.length === 0) {
        historyList.innerHTML = '';
        document.getElementById('customerHistoryEmpty').classList.remove('hidden');
        document.getElementById('historyResultsCount').textContent = '0';
        return;
    }
    
    document.getElementById('customerHistoryEmpty').classList.add('hidden');
    document.getElementById('historyResultsCount').textContent = data.length;
    
    historyList.innerHTML = data.map(item => {
        const date = new Date(item.timestamp);
        const typeIcon = item.type === 'sale' ? 'fa-shopping-cart' : 
                       item.type === 'repair' ? 'fa-tools' : 'fa-exchange-alt';
        const typeColor = item.type === 'sale' ? 'bg-green-100 text-green-800' : 
                        item.type === 'repair' ? 'bg-purple-100 text-purple-800' : 
                        'bg-orange-100 text-orange-800';
        
        // Item name/details
        let itemName = 'N/A';
        let additionalDetails = '';
        
        if (item.type === 'sale') {
            itemName = item.items_preview || item.items?.join(', ') || 'Various items';
            additionalDetails = `${item.item_count || 0} item(s) • Payment: ${item.payment_method || 'Cash'}`;
        } else if (item.type === 'repair') {
            itemName = item.item_name || item.device_info || 'Device';
            additionalDetails = `Issue: ${item.issue || 'N/A'} • Status: ${item.status || 'pending'}`;
        } else if (item.type === 'swap') {
            itemName = item.item_name || 'Product';
            additionalDetails = `Status: ${item.status || 'pending'}`;
        }
        
        return `
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors" data-type="${item.type}">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-1 text-xs font-medium rounded ${typeColor}">
                                <i class="fas ${typeIcon} mr-1"></i>${item.type_label}
                            </span>
                            <span class="text-sm font-medium text-gray-900">${item.reference || 'N/A'}</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 mb-1">${itemName}</p>
                        <p class="text-xs text-gray-600 mb-1">${additionalDetails}</p>
                        <p class="text-xs text-gray-500">${date.toLocaleString()}</p>
                    </div>
                    <div class="text-right ml-4">
                        <p class="text-lg font-bold text-gray-900">₵${parseFloat(item.amount || 0).toFixed(2)}</p>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function filterHistory() {
    const searchTerm = document.getElementById('historySearchInput').value.toLowerCase();
    const typeFilter = document.getElementById('historyTypeFilter').value;
    const dateFilter = document.getElementById('historyDateFilter').value;
    
    let filtered = allHistoryData.filter(item => {
        // Search filter
        if (searchTerm) {
            const searchableText = [
                item.item_name || '',
                item.items_preview || '',
                item.items?.join(' ') || '',
                item.reference || '',
                item.description || '',
                item.device_info || '',
                item.issue || ''
            ].join(' ').toLowerCase();
            
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        // Type filter
        if (typeFilter && item.type !== typeFilter) {
            return false;
        }
        
        // Date filter
        if (dateFilter) {
            const itemDate = new Date(item.timestamp);
            const now = new Date();
            const diffTime = Math.abs(now - itemDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            switch (dateFilter) {
                case 'today':
                    if (diffDays > 1) return false;
                    break;
                case 'week':
                    if (diffDays > 7) return false;
                    break;
                case 'month':
                    if (diffDays > 30) return false;
                    break;
                case 'year':
                    if (diffDays > 365) return false;
                    break;
            }
        }
        
        return true;
    });
    
    renderHistoryList(filtered);
}

async function editCustomer(customerId) {
    try {
        const response = await fetch(`<?= BASE_URL_PATH ?>/api/customers/${customerId}`, {
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('sellapp_token') || localStorage.getItem('token'))
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            const customer = result.data;
            
            // Populate edit form
            document.getElementById('editCustomerId').value = customer.id;
            document.getElementById('editCustomerFullName').value = customer.full_name || '';
            document.getElementById('editCustomerPhone').value = customer.phone_number || '';
            document.getElementById('editCustomerEmail').value = customer.email || '';
            document.getElementById('editCustomerAddress').value = customer.address || '';
            
            // Show modal
            document.getElementById('editCustomerModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            showNotification(result.error || 'Failed to load customer details', 'error');
        }
    } catch (error) {
        console.error('Error loading customer:', error);
        showNotification('Error loading customer details: ' + error.message, 'error');
    }
}

async function deleteCustomer(customerId, customerName) {
    if (!confirm(`Are you sure you want to delete customer "${customerName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`<?= BASE_URL_PATH ?>/api/customers/${customerId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('sellapp_token') || localStorage.getItem('token'))
            },
            credentials: 'same-origin'
        });
        
        // Get response text first
        const responseText = await response.text();
        let result;
        
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            showErrorModal('Failed to delete customer. Server returned an invalid response.');
            return;
        }
        
        // Check if response indicates an error
        if (!response.ok || !result.success) {
            // Show clear error message in modal
            const errorMessage = result.error || 'Failed to delete customer. Please try again or contact support.';
            console.log('Delete error:', errorMessage);
            showErrorModal(errorMessage);
            return;
        }
        
        // Success - remove the row from the table
        const row = document.querySelector(`tr[data-customer-id="${customerId}"]`);
        if (row) {
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                // Update duplicate counts if needed
                updateDuplicateDisplay();
            }, 300);
        }
        
        showNotification('Customer deleted successfully!', 'success');
        
        // Refresh the list after a short delay to ensure consistency
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } catch (error) {
        console.error('Error deleting customer:', error);
        showErrorModal('Error deleting customer: ' + (error.message || 'An unexpected error occurred. Please try again.'));
    }
}

// Update duplicate display after deletion
function updateDuplicateDisplay() {
    // Re-check for duplicates in visible rows
    const rows = document.querySelectorAll('tbody tr[data-phone]');
    const phoneCounts = {};
    
    rows.forEach(row => {
        const phone = row.getAttribute('data-phone');
        if (phone) {
            const normalized = phone.replace(/[\s\-\(\)]/g, '');
            phoneCounts[normalized] = (phoneCounts[normalized] || 0) + 1;
        }
    });
    
    // Update duplicate badges
    rows.forEach(row => {
        const phone = row.getAttribute('data-phone');
        if (phone) {
            const normalized = phone.replace(/[\s\-\(\)]/g, '');
            const count = phoneCounts[normalized] || 1;
            
            if (count > 1) {
                row.classList.add('bg-yellow-50', 'hover:bg-yellow-100', 'border-l-4', 'border-yellow-400');
                let badge = row.querySelector('.duplicate-badge');
                if (!badge) {
                    const nameCell = row.querySelector('td:nth-child(2)');
                    if (nameCell) {
                        badge = document.createElement('span');
                        badge.className = 'duplicate-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2';
                        badge.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i>Duplicate (${count})`;
                        nameCell.appendChild(badge);
                    }
                } else {
                    badge.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i>Duplicate (${count})`;
                }
            } else {
                row.classList.remove('bg-yellow-50', 'hover:bg-yellow-100', 'border-l-4', 'border-yellow-400');
                const badge = row.querySelector('.duplicate-badge');
                if (badge) badge.remove();
            }
        }
    });
}

// View modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    const viewModal = document.getElementById('viewCustomerModal');
    const closeViewBtn = document.getElementById('closeViewCustomerModal');
    const closeViewBtn2 = document.getElementById('closeViewCustomerModalBtn');
    
    function closeViewModal() {
        if (viewModal) {
            viewModal.classList.add('hidden');
        }
        document.body.style.overflow = 'auto';
    }
    
    // History modal event listeners
    const historyModal = document.getElementById('customerHistoryModal');
    const closeHistoryBtn = document.getElementById('closeHistoryModal');
    const closeHistoryBtn2 = document.getElementById('closeHistoryModalBtn');
    const historySearchInput = document.getElementById('historySearchInput');
    const historyTypeFilter = document.getElementById('historyTypeFilter');
    const historyDateFilter = document.getElementById('historyDateFilter');
    const clearHistoryFilters = document.getElementById('clearHistoryFilters');
    
    function closeHistoryModal() {
        if (historyModal) {
            historyModal.classList.add('hidden');
        }
        document.body.style.overflow = 'auto';
        // Reset history section
        const historyLoading = document.getElementById('customerHistoryLoading');
        const historyContent = document.getElementById('customerHistoryContent');
        const historyFilters = document.getElementById('customerHistoryFilters');
        const historyEmpty = document.getElementById('customerHistoryEmpty');
        const historyList = document.getElementById('customerHistoryList');
        if (historyLoading) historyLoading.classList.remove('hidden');
        if (historyContent) historyContent.classList.add('hidden');
        if (historyFilters) historyFilters.classList.add('hidden');
        if (historyEmpty) historyEmpty.classList.add('hidden');
        if (historyList) historyList.innerHTML = '';
        // Reset filters
        if (historySearchInput) historySearchInput.value = '';
        if (historyTypeFilter) historyTypeFilter.value = '';
        if (historyDateFilter) historyDateFilter.value = '';
        allHistoryData = [];
    }
    
    if (closeHistoryBtn) {
        closeHistoryBtn.addEventListener('click', closeHistoryModal);
    }
    if (closeHistoryBtn2) {
        closeHistoryBtn2.addEventListener('click', closeHistoryModal);
    }
    
    if (historyModal) {
        historyModal.addEventListener('click', function(e) {
            if (e.target === historyModal) {
                closeHistoryModal();
            }
        });
    }
    
    // History filter event listeners
    
    if (historySearchInput) {
        historySearchInput.addEventListener('input', filterHistory);
    }
    if (historyTypeFilter) {
        historyTypeFilter.addEventListener('change', filterHistory);
    }
    if (historyDateFilter) {
        historyDateFilter.addEventListener('change', filterHistory);
    }
    if (clearHistoryFilters) {
        clearHistoryFilters.addEventListener('click', function() {
            historySearchInput.value = '';
            historyTypeFilter.value = '';
            historyDateFilter.value = '';
            filterHistory();
        });
    }
    
    if (closeViewBtn) {
        closeViewBtn.addEventListener('click', closeViewModal);
    }
    if (closeViewBtn2) {
        closeViewBtn2.addEventListener('click', closeViewModal);
    }
    
    // View History button
    const viewHistoryBtn = document.getElementById('viewCustomerHistoryBtn');
    if (viewHistoryBtn) {
        viewHistoryBtn.addEventListener('click', function() {
            if (currentViewCustomerId && currentViewCustomerName) {
                // Close view modal first
                closeViewModal();
                // Open history modal
                viewCustomerHistory(currentViewCustomerId, currentViewCustomerName);
            }
        });
    }
    
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === viewModal) {
                closeViewModal();
            }
        });
    }
    
    // Edit modal event listeners
    const editModal = document.getElementById('editCustomerModal');
    const editForm = document.getElementById('editCustomerForm');
    const closeEditBtn = document.getElementById('closeEditCustomerModal');
    const cancelEditBtn = document.getElementById('cancelEditCustomerModal');
    const submitEditBtn = document.getElementById('submitEditCustomer');
    
    function closeEditModal() {
        if (editModal) {
            editModal.classList.add('hidden');
        }
        document.body.style.overflow = 'auto';
        if (editForm) editForm.reset();
    }
    
    if (closeEditBtn) {
        closeEditBtn.addEventListener('click', closeEditModal);
    }
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', closeEditModal);
    }
    
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                closeEditModal();
            }
        });
    }
    
    // Edit form submission
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(editForm);
        const data = Object.fromEntries(formData.entries());
        const customerId = data.id;
        delete data.id; // Remove id from data
        
        // Show loading state
        const originalText = submitEditBtn.innerHTML;
        submitEditBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        submitEditBtn.disabled = true;
        
        try {
            const response = await fetch(`<?= BASE_URL_PATH ?>/api/customers/${customerId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (localStorage.getItem('sellapp_token') || localStorage.getItem('token'))
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Customer updated successfully!', 'success');
                closeEditModal();
                // Reload the page to show updated customer
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification(result.error || 'Failed to update customer', 'error');
            }
        } catch (error) {
            console.error('Error updating customer:', error);
            showNotification('Error updating customer: ' + error.message, 'error');
        } finally {
            // Reset button
            if (submitEditBtn) {
                submitEditBtn.innerHTML = originalText;
                submitEditBtn.disabled = false;
            }
        }
    });
    }
});

// Search and Filter Functionality
(function(){
    const searchInput = document.getElementById('customerSearch');
    const dateFilter = document.getElementById('dateFilter');
    const tbody = document.getElementById('customersTableBody');
    const info = document.getElementById('customerFilterInfo');
    const filteredCountEl = document.getElementById('customerFilteredCount');
    const totalCountEl = document.getElementById('customerTotalCount');
    
    if (!searchInput || !dateFilter || !tbody) return;
    
    // Store original HTML for restoration
    const originalHTML = tbody.innerHTML;
    
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']+/g, s => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[s]));
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    function customerRowHTML(customer) {
        const id = customer.id || 0;
        const name = escapeHtml(customer.full_name || '');
        const phone = escapeHtml(customer.phone_number || '');
        const email = escapeHtml(customer.email || 'N/A');
        const uniqueId = escapeHtml(customer.unique_id || '');
        const created = formatDate(customer.created_at);
        
        return `
            <tr data-customer-id="${id}">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${uniqueId}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${phone}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${email}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${created}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center gap-2">
                        <button onclick="viewCustomer(${id})" class="text-blue-600 hover:text-blue-900 transition-colors" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="viewCustomerHistory(${id}, '${name.replace(/'/g, "\\'")}')" class="text-purple-600 hover:text-purple-900 transition-colors relative group" title="Purchase History">
                            <i class="fas fa-history"></i>
                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                Purchase History
                            </span>
                        </button>
                        <button onclick="editCustomer(${id})" class="text-green-600 hover:text-green-900 transition-colors" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteCustomer(${id}, '${name.replace(/'/g, "\\'")}')" class="text-red-600 hover:text-red-900 transition-colors" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
    
    async function remoteSearch(q) {
        const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        const url = `${base}/api/customers/search?q=${encodeURIComponent(q)}`;
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('Search request failed');
            const data = await res.json();
            if (!data.success) return [];
            return data.data || [];
        } catch (error) {
            console.error('Search error:', error);
            return [];
        }
    }
    
    function applyFilters() {
        const searchTerm = (searchInput.value || '').trim();
        const dateValue = dateFilter.value || '';
        
        // If both are empty, restore original HTML
        if (!searchTerm && !dateValue) {
            tbody.innerHTML = originalHTML;
            info.classList.add('hidden');
            return;
        }
        
        // If only date filter is set, reload page with filter
        if (!searchTerm && dateValue) {
            const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
            const url = `${base}/dashboard/customers?date_filter=${encodeURIComponent(dateValue)}`;
            window.location.href = url;
            return;
        }
        
        // If search term exists, perform remote search
        if (searchTerm) {
            performSearch(searchTerm, dateValue);
        }
    }
    
    async function performSearch(searchTerm, dateFilterValue) {
        try {
            const results = await remoteSearch(searchTerm);
            
            // Apply date filter if set
            let filteredResults = results;
            if (dateFilterValue) {
                const now = new Date();
                let cutoffDate = new Date();
                
                switch (dateFilterValue) {
                    case 'today':
                        cutoffDate.setHours(0, 0, 0, 0);
                        break;
                    case 'week':
                        cutoffDate.setDate(now.getDate() - 7);
                        break;
                    case 'month':
                        cutoffDate.setDate(now.getDate() - 30);
                        break;
                    case 'year':
                        cutoffDate.setDate(now.getDate() - 365);
                        break;
                }
                
                filteredResults = results.filter(customer => {
                    if (!customer.created_at) return false;
                    const createdDate = new Date(customer.created_at);
                    return createdDate >= cutoffDate;
                });
            }
            
            if (!filteredResults || filteredResults.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No matching customers</td></tr>';
                filteredCountEl.textContent = 0;
                totalCountEl.textContent = results.length || 0;
            } else {
                tbody.innerHTML = filteredResults.map(customerRowHTML).join('');
                filteredCountEl.textContent = filteredResults.length;
                totalCountEl.textContent = results.length || filteredResults.length;
            }
            
            info.classList.remove('hidden');
        } catch (error) {
            console.error('Search error:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error performing search</td></tr>';
            info.classList.add('hidden');
        }
    }
    
    let debounceTimer;
    let lastQuery = '';
    
    // Search input event listener with debounce
    searchInput.addEventListener('input', () => {
        const q = (searchInput.value || '').trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            lastQuery = q;
            applyFilters();
        }, 300);
    });
    
    // Date filter change listener
    dateFilter.addEventListener('change', () => {
        const searchTerm = (searchInput.value || '').trim();
        if (!searchTerm) {
            // If no search term, reload page with date filter
            const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
            const dateValue = dateFilter.value || '';
            if (dateValue) {
                window.location.href = `${base}/dashboard/customers?date_filter=${encodeURIComponent(dateValue)}`;
            } else {
                window.location.href = `${base}/dashboard/customers`;
            }
        } else {
            // If search term exists, apply filter to current results
            applyFilters();
        }
    });
})();

// Duplicate customers functionality
function toggleDuplicatesFilter() {
    const showDuplicates = document.getElementById('showDuplicatesOnly').checked;
    const rows = document.querySelectorAll('#customersTableBody tr[data-customer-id]');
    
    rows.forEach(row => {
        const hasDuplicateBadge = row.querySelector('.bg-yellow-100');
        if (showDuplicates) {
            // Show only rows with duplicates
            if (hasDuplicateBadge) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        } else {
            // Show all rows
            row.style.display = '';
        }
    });
    
    // Update filter info
    const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none').length;
    const totalRows = rows.length;
    const info = document.getElementById('customerFilterInfo');
    const filteredCountEl = document.getElementById('customerFilteredCount');
    const totalCountEl = document.getElementById('customerTotalCount');
    
    if (showDuplicates && visibleRows > 0) {
        filteredCountEl.textContent = visibleRows;
        totalCountEl.textContent = totalRows;
        info.classList.remove('hidden');
    } else if (!showDuplicates) {
        info.classList.add('hidden');
    }
}

async function showDuplicateCustomers(phoneNumber) {
    try {
        const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        const response = await fetch(`${base}/api/customers/search?q=${encodeURIComponent(phoneNumber)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
            // Filter to only show customers with exact phone match
            const normalizedPhone = phoneNumber.replace(/[\s\-\(\)]/g, '');
            const duplicates = result.data.filter(c => {
                const customerPhone = (c.phone_number || '').replace(/[\s\-\(\)]/g, '');
                return customerPhone === normalizedPhone;
            });
            
            if (duplicates.length > 1) {
                // Show duplicates in a modal or alert
                const duplicateList = duplicates.map((c, idx) => 
                    `${idx + 1}. ${c.full_name} (ID: ${c.unique_id}) - ${c.phone_number}`
                ).join('\n');
                
                alert(`Found ${duplicates.length} customers with phone number: ${phoneNumber}\n\n${duplicateList}`);
            } else {
                alert('No duplicates found for this phone number.');
            }
        } else {
            alert('No customers found with this phone number.');
        }
    } catch (error) {
        console.error('Error finding duplicates:', error);
        alert('Error finding duplicate customers: ' + error.message);
    }
}
</script>
