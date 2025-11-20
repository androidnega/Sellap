<?php
// Swap creation form
?>

<div class="w-full p-6">
    <div class="flex items-center mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">New Swap</h1>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/swaps/store" class="space-y-6">
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

        <!-- Store Product -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Store Product</h2>
            
            <div>
                <label for="store_product_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Select Product <span class="text-red-500">*</span>
                </label>
                <select id="store_product_id" name="store_product_id" required 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select product to give customer</option>
                    <?php foreach ($storeProducts as $product): ?>
                        <option value="<?= htmlspecialchars($product['id']) ?>" 
                                data-price="<?= htmlspecialchars($product['price']) ?>"
                                data-name="<?= htmlspecialchars($product['name']) ?>">
                            <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['brand_name'] ?? 'Generic') ?> - ₵<?= number_format($product['price'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="storeProductInfo" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                <h3 class="font-medium text-gray-800">Selected Product Details</h3>
                <div id="storeProductDetails" class="text-sm text-gray-600"></div>
            </div>
        </div>

        <!-- Customer's Product -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Customer's Product</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="customer_brand_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Brand <span class="text-red-500">*</span>
                    </label>
                    <select id="customer_brand_id" name="customer_brand_id" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Brand</option>
                        <!-- Brands will be loaded dynamically -->
                    </select>
                    <!-- Hidden input to store brand name for form submission -->
                    <input type="hidden" id="customer_brand" name="customer_brand">
                </div>
                
                <div>
                    <label for="customer_model" class="block text-sm font-medium text-gray-700 mb-1">
                        Model <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_model" name="customer_model" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. iPhone 12, Galaxy S21">
                </div>
            </div>
            
            <!-- Dynamic Spec Fields Container -->
            <div id="customerSpecsContainer" class="mt-4 hidden">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Device Specifications</h5>
                <div id="customerDynamicSpecs" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Dynamic fields will be inserted here -->
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="customer_condition" class="block text-sm font-medium text-gray-700 mb-1">Condition</label>
                    <select id="customer_condition" name="customer_condition" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="used">Used</option>
                        <option value="new">New</option>
                        <option value="faulty">Faulty</option>
                    </select>
                </div>
                
                <div>
                    <label for="swap_value" class="block text-sm font-medium text-gray-700 mb-1">
                        Estimated Value (₵) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="swap_value" name="swap_value" step="0.01" min="0" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Swap Calculation -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Swap Calculation</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="cash_added" class="block text-sm font-medium text-gray-700 mb-1">Cash to Add (₵)</label>
                    <input type="number" id="cash_added" name="cash_added" step="0.01" min="0" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Transaction Value (₵)</label>
                    <input type="text" id="total_value" readonly 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100"
                           placeholder="0.00">
                </div>
            </div>
            
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-medium text-blue-800 mb-2">Calculation Breakdown:</h3>
                <div class="text-sm text-blue-700">
                    <div class="flex justify-between">
                        <span>Store Product Price:</span>
                        <span id="storePrice">₵0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Customer Product Value:</span>
                        <span id="customerValue">₵0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cash to Add:</span>
                        <span id="cashToAdd">₵0.00</span>
                    </div>
                    <hr class="my-2">
                    <div class="flex justify-between font-bold">
                        <span>Total Transaction:</span>
                        <span id="totalTransaction">₵0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Additional Notes</h2>
            
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Any additional notes about the swap..."></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <a href="/swaps" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Complete Swap</button>
        </div>
    </form>
</div>

<script>
// Swap form JavaScript
(function() {
    const storeProductSelect = document.getElementById('store_product_id');
    const storeProductInfo = document.getElementById('storeProductInfo');
    const storeProductDetails = document.getElementById('storeProductDetails');
    const swapValue = document.getElementById('swap_value');
    const cashAdded = document.getElementById('cash_added');
    const totalValue = document.getElementById('total_value');
    const storePrice = document.getElementById('storePrice');
    const customerValue = document.getElementById('customerValue');
    const cashToAdd = document.getElementById('cashToAdd');
    const totalTransaction = document.getElementById('totalTransaction');

    // Update store product info
    function updateStoreProductInfo() {
        const selectedOption = storeProductSelect.options[storeProductSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const name = selectedOption.dataset.name;
            
            storeProductDetails.innerHTML = `
                <div><strong>Product:</strong> ${name}</div>
                <div><strong>Price:</strong> ₵${price.toFixed(2)}</div>
            `;
            storeProductInfo.classList.remove('hidden');
            updateCalculation();
        } else {
            storeProductInfo.classList.add('hidden');
        }
    }

    // Update calculation
    function updateCalculation() {
        const selectedOption = storeProductSelect.options[storeProductSelect.selectedIndex];
        const storeProductPrice = selectedOption ? parseFloat(selectedOption.dataset.price) : 0;
        const customerProductValue = parseFloat(swapValue.value) || 0;
        const cashToAddValue = parseFloat(cashAdded.value) || 0;
        
        // For swaps, total value is only the cash top-up amount
        // The swapped item (phone) will be resold later to realize its value
        const total = cashToAddValue;
        
        // Update display
        storePrice.textContent = `₵${storeProductPrice.toFixed(2)}`;
        customerValue.textContent = `₵${customerProductValue.toFixed(2)}`;
        cashToAdd.textContent = `₵${cashToAddValue.toFixed(2)}`;
        totalTransaction.textContent = `₵${total.toFixed(2)}`;
        totalValue.value = total.toFixed(2);
    }

    // Auto-fill customer info when selecting existing customer
    document.getElementById('customer_id').addEventListener('change', function() {
        const customerId = this.value;
        if (customerId) {
            const customers = <?= json_encode($customers) ?>;
            const customer = customers.find(c => c.id == customerId);
            if (customer) {
                document.getElementById('customer_name').value = customer.full_name;
                document.getElementById('customer_contact').value = customer.phone_number;
            }
        }
    });

    // Event listeners
    storeProductSelect.addEventListener('change', updateStoreProductInfo);
    swapValue.addEventListener('input', updateCalculation);
    cashAdded.addEventListener('input', updateCalculation);

    // Load brands for phone category (category ID = 1)
    const brandSelect = document.getElementById('customer_brand_id');
    const brandNameInput = document.getElementById('customer_brand');
    
    // Load brands on page load
    loadBrands();
    
    function loadBrands() {
        // Helper function to deduplicate brands by ID
        function deduplicateBrands(brands) {
            const seen = new Map();
            return brands.filter(brand => {
                if (!brand || !brand.id) return false;
                if (seen.has(brand.id)) return false;
                seen.set(brand.id, true);
                return true;
            });
        }
        
        // Helper function to populate select
        function populateBrandSelect(brands) {
            const uniqueBrands = deduplicateBrands(brands);
            if (uniqueBrands.length > 0) {
                brandSelect.innerHTML = '<option value="">Select Brand</option>';
                uniqueBrands.forEach(brand => {
                    const opt = document.createElement('option');
                    opt.value = brand.id;
                    opt.textContent = brand.name;
                    opt.dataset.brandName = brand.name;
                    brandSelect.appendChild(opt);
                });
                return true;
            }
            return false;
        }
        
        // Phone category ID is typically 1
        fetch('<?= BASE_URL_PATH ?>/api/brands/by-category/1')
            .then(response => response.json())
            .then(result => {
                // Handle both formats: direct array or {success: true, data: [...]}
                let brands = Array.isArray(result) ? result : (result.data || []);
                if (!populateBrandSelect(brands)) {
                    // Fallback: try alternative API endpoint
                    fetch('<?= BASE_URL_PATH ?>/api/products/brands/1')
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
                fetch('<?= BASE_URL_PATH ?>/api/products/brands/1')
                    .then(response => response.json())
                    .then(data => {
                        let brands = data.success && data.data ? data.data : (Array.isArray(data) ? data : []);
                        populateBrandSelect(brands);
                    })
                    .catch(err => console.error('Error loading brands:', err));
            });
    }
    
    // Handle brand selection - load specs
    brandSelect.addEventListener('change', function() {
        const brandId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        const brandName = selectedOption ? selectedOption.dataset.brandName : '';
        
        // Store brand name for form submission
        brandNameInput.value = brandName || '';
        
        // Load brand specs
        if (brandId) {
            loadBrandSpecs(brandId);
        } else {
            // Hide specs container if no brand selected
            document.getElementById('customerSpecsContainer').classList.add('hidden');
            document.getElementById('customerDynamicSpecs').innerHTML = '';
        }
    });
    
    function loadBrandSpecs(brandId) {
        const specsContainer = document.getElementById('customerSpecsContainer');
        const dynamicSpecs = document.getElementById('customerDynamicSpecs');
        
        if (!brandId) {
            specsContainer.classList.add('hidden');
            dynamicSpecs.innerHTML = '';
            return;
        }
        
        // Fetch brand specs from API
        fetch('<?= BASE_URL_PATH ?>/api/brands/specs/' + encodeURIComponent(brandId))
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    specsContainer.classList.remove('hidden');
                    dynamicSpecs.innerHTML = '';
                    
                    data.forEach(spec => {
                        // Skip model and imei as they're already in the form
                        if (spec.name === 'model' || spec.name === 'imei') return;
                        
                        const div = document.createElement('div');
                        const label = document.createElement('label');
                        label.className = 'block text-sm font-medium text-gray-700 mb-1';
                        label.textContent = spec.label + (spec.required ? ' *' : '');
                        label.setAttribute('for', 'spec_' + spec.name);
                        
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
                        
                        input.id = 'spec_' + spec.name;
                        input.name = 'customer_spec_' + spec.name;
                        if (spec.required) input.required = true;
                        
                        div.appendChild(label);
                        div.appendChild(input);
                        dynamicSpecs.appendChild(div);
                    });
                } else {
                    // No specs found, hide container
                    specsContainer.classList.add('hidden');
                    dynamicSpecs.innerHTML = '';
                }
            })
            .catch(error => {
                console.error('Error loading brand specs:', error);
                specsContainer.classList.add('hidden');
                dynamicSpecs.innerHTML = '';
            });
    }

    // Initialize
    updateStoreProductInfo();
})();
</script>
