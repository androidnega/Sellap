<?php
// Technician Booking Form
$partsAndAccessories = $GLOBALS['partsAndAccessories'] ?? [];
$brands = $GLOBALS['brands'] ?? [];

// Debug: Log parts count
error_log("technician_booking.php: partsAndAccessories count = " . count($partsAndAccessories));
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">New Repair Booking</h1>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <form id="repairForm" method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/store" class="space-y-6">
        <!-- Customer Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h2>
            
            <!-- Searchable Customer Dropdown -->
            <div class="mb-4">
                <label for="customer_search" class="block text-sm font-medium text-gray-700 mb-1">Search Customer (Optional)</label>
                <div class="relative">
                    <input type="text" id="customer_search" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Type to search customers..."
                           autocomplete="off">
                    <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <div id="customer_dropdown" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto mt-1"></div>
                </div>
                <input type="hidden" id="customer_id" name="customer_id" value="">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Customer Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_name" name="customer_name" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter customer name">
                </div>
                
                <div>
                    <label for="customer_contact" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_contact" name="customer_contact" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., 0244123456">
                </div>
            </div>
        </div>

        <!-- Device Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Device Information</h2>
            
            <div class="mb-4">
                <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Device Source</label>
                <select id="product_id" name="product_id" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="toggleDeviceDetails(this.value)">
                    <option value="">Customer's own device</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['brand_name'] ?? 'Generic') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Device Details (shown when "Customer's own device" is selected) -->
            <div id="device_details_section" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="device_brand" class="block text-sm font-medium text-gray-700 mb-1">Device Brand</label>
                        <input type="text" id="device_brand" name="device_brand" 
                               list="brand_list"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter brand (e.g., Apple, Samsung, or custom)">
                        <datalist id="brand_list">
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand['name']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <p class="text-xs text-gray-500 mt-1">Type to search or enter your own brand</p>
                    </div>
                    
                    <div>
                        <label for="device_model" class="block text-sm font-medium text-gray-700 mb-1">Device Model</label>
                        <input type="text" id="device_model" name="device_model" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., iPhone 12, Samsung Galaxy S21">
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <label for="issue_description" class="block text-sm font-medium text-gray-700 mb-1">
                    Issue/Fault Description <span class="text-red-500">*</span>
                </label>
                <textarea id="issue_description" name="issue_description" required rows="4"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Describe the issue with the device in detail..."></textarea>
            </div>
        </div>

        <!-- Repair Parts (Optional) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Repair Parts Used (Optional)</h2>
            <p class="text-sm text-gray-600 mb-4">Select repair parts used for this repair. These will be recorded as sales and stock will be updated.</p>
            
            <div class="mb-4">
                <label for="parts_select" class="block text-sm font-medium text-gray-700 mb-2">Select Repair Parts</label>
                <select id="parts_select" multiple 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        style="min-height: 150px;"
                        onchange="handlePartsSelection()">
                    <?php if (empty($partsAndAccessories)): ?>
                        <option value="" disabled>No repair parts available</option>
                    <?php else: ?>
                        <?php foreach ($partsAndAccessories as $item): ?>
                            <option value="<?= $item['id'] ?>" 
                                    data-name="<?= htmlspecialchars($item['name']) ?>"
                                    data-price="<?= $item['price'] ?? $item['display_price'] ?? 0 ?>"
                                    data-stock="<?= $item['quantity'] ?? 0 ?>"
                                    data-category="<?= htmlspecialchars($item['category_name'] ?? '') ?>">
                                <?= htmlspecialchars($item['name']) ?> - ₵<?= number_format($item['price'] ?? $item['display_price'] ?? 0, 2) ?> (Stock: <?= $item['quantity'] ?? 0 ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple items</p>
            </div>
            
            <!-- Selected Parts with Quantity -->
            <div id="selected_parts" class="space-y-3">
                <!-- Selected parts will appear here -->
            </div>
        </div>

        <!-- Cost Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Cost Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="repair_cost" class="block text-sm font-medium text-gray-700 mb-1">Repair Cost (₵)</label>
                    <input type="number" id="repair_cost" name="repair_cost" step="0.01" min="0" value="0"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="calculateTotal()">
                </div>
                
                <div>
                    <label for="parts_cost" class="block text-sm font-medium text-gray-700 mb-1">Parts Cost (₵)</label>
                    <input type="number" id="parts_cost" name="parts_cost" step="0.01" min="0" value="0" readonly
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost (₵)</label>
                    <div class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                        <span id="total_cost_display">₵0.00</span>
                    </div>
                    <input type="hidden" id="total_cost" name="total_cost" value="0">
                    <input type="hidden" id="accessory_cost" name="accessory_cost" value="0">
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Additional Notes</h2>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Any additional notes or instructions..."></textarea>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="<?= BASE_URL_PATH ?>/dashboard" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Create Repair Booking
            </button>
        </div>
    </form>
</div>

<script>
// Customer data for search
const customers = <?= json_encode(array_map(function($c) {
    return [
        'id' => $c['id'],
        'name' => $c['full_name'] ?? $c['name'] ?? '',
        'phone' => $c['phone_number'] ?? $c['phone'] ?? $c['contact'] ?? '',
        'email' => $c['email'] ?? ''
    ];
}, $customers)) ?>;

// Customer search functionality
let customerSearchTimeout;
const customerSearch = document.getElementById('customer_search');
const customerDropdown = document.getElementById('customer_dropdown');

function filterAndDisplayCustomers(query = '') {
    const searchQuery = query.toLowerCase().trim();
    let filtered = customers;
    
    // Filter if there's a search query
    if (searchQuery.length > 0) {
        filtered = customers.filter(c => 
            (c.name || '').toLowerCase().includes(searchQuery) ||
            (c.phone || '').toLowerCase().includes(searchQuery) ||
            (c.email || '').toLowerCase().includes(searchQuery)
        );
    }
    
    // Display results
    if (filtered.length === 0) {
        customerDropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
    } else {
        customerDropdown.innerHTML = filtered.map(c => `
            <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                 onclick="selectCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}', '${c.phone.replace(/'/g, "\\'")}')">
                <div class="font-medium text-gray-800">${c.name}</div>
                <div class="text-sm text-gray-600">${c.phone}</div>
                ${c.email ? `<div class="text-xs text-gray-500">${c.email}</div>` : ''}
            </div>
        `).join('');
    }
    
    customerDropdown.classList.remove('hidden');
}

if (customerSearch && customerDropdown) {
    // Show all customers when field is focused/clicked
    customerSearch.addEventListener('focus', function() {
        filterAndDisplayCustomers(this.value);
    });
    
    customerSearch.addEventListener('click', function() {
        filterAndDisplayCustomers(this.value);
    });
    
    // Filter as user types
    customerSearch.addEventListener('input', function() {
        clearTimeout(customerSearchTimeout);
        const query = this.value;
        
        customerSearchTimeout = setTimeout(() => {
            filterAndDisplayCustomers(query);
        }, 150); // Reduced delay for more responsive feel
    });
    
    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!customerSearch.contains(e.target) && !customerDropdown.contains(e.target)) {
            customerDropdown.classList.add('hidden');
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    customerDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

function selectCustomer(id, name, phone) {
    document.getElementById('customer_id').value = id;
    document.getElementById('customer_name').value = name;
    document.getElementById('customer_contact').value = phone;
    document.getElementById('customer_search').value = name;
    customerDropdown.classList.add('hidden');
}

// Toggle device details section
function toggleDeviceDetails(productId) {
    const deviceSection = document.getElementById('device_details_section');
    if (!productId || productId === '') {
        deviceSection.style.display = 'block';
    } else {
        deviceSection.style.display = 'none';
    }
}

// Initialize - show device details by default
document.addEventListener('DOMContentLoaded', function() {
    toggleDeviceDetails(document.getElementById('product_id').value);
});

// Handle parts selection
const selectedPartsMap = {};

function handlePartsSelection() {
    const select = document.getElementById('parts_select');
    const selectedDiv = document.getElementById('selected_parts');
    selectedDiv.innerHTML = '';
    
    const selectedOptions = Array.from(select.selectedOptions);
    
    selectedOptions.forEach((option) => {
        const productId = option.value;
        const productName = option.dataset.name;
        const productPrice = parseFloat(option.dataset.price) || 0;
        const stock = parseInt(option.dataset.stock) || 0;
        
        if (!selectedPartsMap[productId]) {
            selectedPartsMap[productId] = {
                name: productName,
                price: productPrice,
                stock: stock,
                quantity: 1
            };
        }
        
        // Create quantity input for this part
        const wrapper = document.createElement('div');
        wrapper.className = 'bg-gray-50 border border-gray-200 rounded-lg p-4';
        wrapper.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <input type="text" value="${productName}" readonly 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                    <input type="number" min="1" max="${stock}" value="${selectedPartsMap[productId].quantity}" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2"
                           onchange="updatePartQuantity(${productId}, this.value)"
                           data-product-id="${productId}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                    <div class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100">
                        <span id="part_total_${productId}">₵${(selectedPartsMap[productId].quantity * productPrice).toFixed(2)}</span>
                    </div>
                </div>
            </div>
            <input type="hidden" name="accessories[${productId}][product_id]" value="${productId}">
            <input type="hidden" name="accessories[${productId}][price]" value="${productPrice}" id="part_price_${productId}">
            <input type="hidden" name="accessories[${productId}][quantity]" value="${selectedPartsMap[productId].quantity}" id="part_qty_${productId}">
        `;
        selectedDiv.appendChild(wrapper);
    });
    
    // Remove unselected parts
    Object.keys(selectedPartsMap).forEach(productId => {
        const isSelected = selectedOptions.some(opt => opt.value === productId);
        if (!isSelected) {
            delete selectedPartsMap[productId];
        }
    });
    
    calculatePartsCost();
    calculateTotal();
}

function updatePartQuantity(productId, quantity) {
    if (selectedPartsMap[productId]) {
        selectedPartsMap[productId].quantity = Math.min(Math.max(1, parseInt(quantity) || 1), selectedPartsMap[productId].stock);
        const qtyInput = document.querySelector(`input[data-product-id="${productId}"]`);
        if (qtyInput) {
            qtyInput.value = selectedPartsMap[productId].quantity;
        }
        const qtyHidden = document.getElementById(`part_qty_${productId}`);
        if (qtyHidden) {
            qtyHidden.value = selectedPartsMap[productId].quantity;
        }
        const price = selectedPartsMap[productId].price;
        const total = selectedPartsMap[productId].quantity * price;
        const totalSpan = document.getElementById(`part_total_${productId}`);
        if (totalSpan) {
            totalSpan.textContent = '₵' + total.toFixed(2);
        }
        calculatePartsCost();
        calculateTotal();
    }
}

function calculatePartsCost() {
    let total = 0;
    Object.values(selectedPartsMap).forEach(part => {
        total += part.quantity * part.price;
    });
    document.getElementById('parts_cost').value = total.toFixed(2);
    document.getElementById('accessory_cost').value = total.toFixed(2);
}

function calculateTotal() {
    const repairCost = parseFloat(document.getElementById('repair_cost').value) || 0;
    const partsCost = parseFloat(document.getElementById('parts_cost').value) || 0;
    const total = repairCost + partsCost;
    
    document.getElementById('total_cost').value = total.toFixed(2);
    document.getElementById('total_cost_display').textContent = '₵' + total.toFixed(2);
}
</script>
