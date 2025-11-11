<?php
// Repair creation form
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center mb-6">
        <a href="/repairs" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">New Repair</h1>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <form id="repairForm" method="POST" action="/repairs/store" class="space-y-6">
        <!-- Customer Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h2>
            
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
                        Contact <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_contact" name="customer_contact" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Phone number">
                </div>
            </div>

            <div class="mt-4">
                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Existing Customer</label>
                <select id="customer_id" name="customer_id" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select existing customer (optional)</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= htmlspecialchars($customer['id']) ?>">
                            <?= htmlspecialchars($customer['full_name']) ?> - <?= htmlspecialchars($customer['phone_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Device Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Device Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Device from Stock</label>
                    <select id="product_id" name="product_id" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Customer's own device</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['id']) ?>">
                                <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['brand_name'] ?? 'Generic') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label for="issue_description" class="block text-sm font-medium text-gray-700 mb-1">
                    Issue Description <span class="text-red-500">*</span>
                </label>
                <textarea id="issue_description" name="issue_description" rows="4" required
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Describe the issue with the device..."></textarea>
            </div>
        </div>

        <!-- Repair Costs -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Repair Costs</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="repair_cost" class="block text-sm font-medium text-gray-700 mb-1">Repair Cost (₵)</label>
                    <input type="number" id="repair_cost" name="repair_cost" step="0.01" min="0" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label for="parts_cost" class="block text-sm font-medium text-gray-700 mb-1">Parts Cost (₵)</label>
                    <input type="number" id="parts_cost" name="parts_cost" step="0.01" min="0" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label for="total_cost" class="block text-sm font-medium text-gray-700 mb-1">Total Cost (₵)</label>
                    <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" readonly
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100"
                           placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Accessories Used -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Accessories Used</h2>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Accessories (Multi-select)</label>
                <select id="accessoriesSelect" multiple class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" style="min-height: 120px;">
                    <?php foreach ($products as $product): ?>
                        <?php if ($product['category_name'] === 'Accessory' && $product['quantity'] > 0): ?>
                            <option value="<?= htmlspecialchars($product['id']) ?>" 
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-price="<?= htmlspecialchars($product['price']) ?>"
                                    data-stock="<?= htmlspecialchars($product['quantity']) ?>">
                                <?= htmlspecialchars($product['name']) ?> - ₵<?= number_format($product['price'], 2) ?> (Stock: <?= $product['quantity'] ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple accessories</p>
            </div>

            <!-- Dynamic Quantity Fields -->
            <div id="selectedAccessories" class="space-y-3">
                <!-- Selected accessories with quantity inputs will appear here -->
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Additional Notes</h2>
            
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Any additional notes about the repair..."></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <a href="/repairs" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Repair</button>
        </div>
    </form>
</div>

<script>
// Enhanced Repair Booking Form with Multi-Select Accessories
document.addEventListener('DOMContentLoaded', () => {
    const accessoriesSelect = document.getElementById('accessoriesSelect');
    const selectedAccessoriesDiv = document.getElementById('selectedAccessories');
    const repairForm = document.getElementById('repairForm');

    // Calculate total cost (repair + parts)
    function calculateTotal() {
        const repairCost = parseFloat(document.getElementById('repair_cost').value) || 0;
        const partsCost = parseFloat(document.getElementById('parts_cost').value) || 0;
        const total = repairCost + partsCost;
        document.getElementById('total_cost').value = total.toFixed(2);
    }

    // Handle accessory selection changes
    accessoriesSelect.addEventListener('change', () => {
        selectedAccessoriesDiv.innerHTML = ''; // Clear previous selections
        
        const selectedOptions = Array.from(accessoriesSelect.selectedOptions);
        
        selectedOptions.forEach((option, index) => {
            const productId = option.value;
            const productName = option.dataset.name;
            const productPrice = option.dataset.price;
            const stockQuantity = parseInt(option.dataset.stock);
            
            // Create wrapper div for each selected accessory
            const wrapper = document.createElement('div');
            wrapper.className = 'bg-gray-50 border border-gray-200 rounded-lg p-4';
            wrapper.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Accessory</label>
                        <input type="text" value="${productName}" readonly 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-600">
                        <input type="hidden" name="accessories[${index}][product_id]" value="${productId}">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Used</label>
                        <input type="number" name="accessories[${index}][quantity]" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Quantity" min="1" max="${stockQuantity}" required>
                        <p class="text-xs text-gray-500 mt-1">Available: ${stockQuantity}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price (₵)</label>
                        <input type="number" name="accessories[${index}][price]" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               value="${productPrice}" step="0.01" min="0" required>
                    </div>
                </div>
            `;
            
            selectedAccessoriesDiv.appendChild(wrapper);
        });
        
        // Update total cost display
        updateAccessoryTotalCost();
    });

    // Add event listeners to quantity and price inputs for real-time total calculation
    selectedAccessoriesDiv.addEventListener('input', (e) => {
        if (e.target.name && (e.target.name.includes('[quantity]') || e.target.name.includes('[price]'))) {
            updateAccessoryTotalCost();
        }
    });

    // Calculate and display total accessory cost
    function updateAccessoryTotalCost() {
        const quantityInputs = selectedAccessoriesDiv.querySelectorAll('input[name*="[quantity]"]');
        const priceInputs = selectedAccessoriesDiv.querySelectorAll('input[name*="[price]"]');
        
        let totalCost = 0;
        
        for (let i = 0; i < quantityInputs.length; i++) {
            const quantity = parseFloat(quantityInputs[i].value) || 0;
            const price = parseFloat(priceInputs[i].value) || 0;
            totalCost += quantity * price;
        }
        
        // Update or create total cost display
        let totalDisplay = document.getElementById('accessoryTotalDisplay');
        if (!totalDisplay) {
            totalDisplay = document.createElement('div');
            totalDisplay.id = 'accessoryTotalDisplay';
            totalDisplay.className = 'mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg';
            selectedAccessoriesDiv.appendChild(totalDisplay);
        }
        
        totalDisplay.innerHTML = `
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-700">Total Accessory Cost:</span>
                <span class="text-lg font-bold text-blue-600">₵${totalCost.toFixed(2)}</span>
            </div>
        `;
    }

    // Auto-fill customer info when selecting existing customer
    document.getElementById('customer_id').addEventListener('change', function() {
        const customerId = this.value;
        if (customerId) {
            // Find customer data and auto-fill
            const customers = <?= json_encode($customers) ?>;
            const customer = customers.find(c => c.id == customerId);
            if (customer) {
                document.getElementById('customer_name').value = customer.full_name;
                document.getElementById('customer_contact').value = customer.phone_number;
            }
        }
    });

    // Event listeners for cost calculation
    document.getElementById('repair_cost').addEventListener('input', calculateTotal);
    document.getElementById('parts_cost').addEventListener('input', calculateTotal);

    // Form validation
    repairForm.addEventListener('submit', function(e) {
        const customerName = document.getElementById('customer_name').value.trim();
        const customerContact = document.getElementById('customer_contact').value.trim();
        const issueDescription = document.getElementById('issue_description').value.trim();
        
        if (!customerName || !customerContact || !issueDescription) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return false;
        }
        
        // Validate accessory quantities
        const quantityInputs = selectedAccessoriesDiv.querySelectorAll('input[name*="[quantity]"]');
        let hasInvalidQuantity = false;
        
        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value);
            const maxQuantity = parseInt(input.max);
            
            if (quantity > maxQuantity) {
                hasInvalidQuantity = true;
                input.classList.add('border-red-500');
            } else {
                input.classList.remove('border-red-500');
            }
        });
        
        if (hasInvalidQuantity) {
            e.preventDefault();
            alert('One or more accessory quantities exceed available stock. Please adjust quantities.');
            return false;
        }
        
        // Check if at least one accessory is selected
        const selectedOptions = Array.from(accessoriesSelect.selectedOptions);
        if (selectedOptions.length === 0) {
            if (!confirm('No accessories selected. Continue without accessories?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Initialize
    calculateTotal();
});
</script>
