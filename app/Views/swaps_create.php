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
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Customer Information</h2>
            <p class="text-sm text-gray-600 mb-4">Enter customer details or search for an existing customer</p>
            
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <label for="customer_search" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-search mr-1 text-blue-600"></i>Search Existing Customer
                    </label>
                <p class="text-xs text-gray-600 mb-2">Quickly find and select a customer from your database</p>
                <div class="relative">
                    <input type="text" id="customer_search" placeholder="Type customer name or phone number to search..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           autocomplete="off"
                           aria-label="Search for existing customer">
                    <div id="customer_dropdown" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto mt-1"></div>
                </div>
                <!-- Hidden select for form submission -->
                <select id="customer_id" name="customer_id" class="hidden">
                    <option value="">Select existing customer (optional)</option>
                </select>
                <!-- Hidden input backup to ensure customer_id is submitted -->
                <input type="hidden" id="customer_id_backup" name="customer_id_backup" value="">
                
                <!-- Selected Customer Display -->
                <div id="selected_customer_info" class="hidden mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-xs text-gray-600 mb-1">Selected Customer:</div>
                            <div class="font-medium text-green-800" id="selected_customer_name">Customer Name</div>
                            <div class="text-sm text-green-600" id="selected_customer_phone">Phone Number</div>
                        </div>
                        <button type="button" id="clear_customer_btn" 
                                class="text-green-600 hover:text-green-800 focus:outline-none"
                                aria-label="Clear selected customer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <p class="text-sm font-medium text-gray-700 mb-3">Or Enter New Customer Details:</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="customer_name" name="customer_name" required 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g. John Doe"
                               aria-required="true"
                               aria-describedby="customer_name_help">
                        <p id="customer_name_help" class="mt-1 text-xs text-gray-500">Enter the customer's full name</p>
                    </div>
                    
                    <div>
                        <label for="customer_contact" class="block text-sm font-medium text-gray-700 mb-1">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" id="customer_contact" name="customer_contact" required 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g. 0244123456 or 0501234567"
                               aria-required="true"
                               aria-describedby="customer_contact_help">
                        <p id="customer_contact_help" class="mt-1 text-xs text-gray-500">Enter customer's phone number</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Product -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Store Product</h2>
            <p class="text-sm text-gray-600 mb-4">Select the product from your inventory to give to the customer</p>
            
            <div>
                <label for="product_search" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-box mr-1 text-blue-600"></i>Search & Select Product <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-gray-500 mb-2">Start typing to search by product name, brand, or model</p>
                <div class="relative">
                    <input type="text" id="product_search" placeholder="Type product name, brand, or model (e.g. iPhone 13, Samsung Galaxy)" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           autocomplete="off"
                           aria-required="true"
                           aria-describedby="product_search_help">
                    <div id="product_dropdown" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto mt-1"></div>
                </div>
                <p id="product_search_help" class="mt-1 text-xs text-gray-500">Select a product from the dropdown that appears as you type</p>
                <!-- Hidden select for form submission -->
                <select id="store_product_id" name="store_product_id" class="hidden">
                    <option value="">Select product to give customer</option>
                </select>
                <!-- Hidden input to track if product is selected -->
                <input type="hidden" id="store_product_selected" value="0">
            </div>
            
            <div id="storeProductInfo" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                <h3 class="font-medium text-gray-800 mb-2">Selected Product Details</h3>
                <div id="storeProductDetails" class="text-sm text-gray-600"></div>
            </div>
            
            <!-- Selected Product Display -->
            <div id="selected_product_info" class="hidden mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-xs text-gray-600 mb-1">Selected Product:</div>
                        <div class="font-medium text-green-800" id="selected_product_name">Product Name</div>
                        <div class="text-sm text-green-600" id="selected_product_details">Brand - Price</div>
                    </div>
                    <button type="button" id="clear_product_btn" 
                            class="text-green-600 hover:text-green-800 focus:outline-none"
                            aria-label="Clear selected product">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Customer's Product -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Customer's Product</h2>
            <p class="text-sm text-gray-600 mb-4">Enter details about the product the customer is bringing in for swap</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="customer_brand_id" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-tag mr-1 text-blue-600"></i>Brand <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                    <select id="customer_brand_id" name="customer_brand_id" required 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                               aria-required="true"
                               aria-describedby="customer_brand_help">
                        <option value="">Select Brand</option>
                        <!-- Brands will be loaded dynamically -->
                    </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    <p id="customer_brand_help" class="mt-1 text-xs text-gray-500">Select the brand of the customer's device</p>
                    <!-- Hidden input to store brand name for form submission -->
                    <input type="hidden" id="customer_brand" name="customer_brand">
                </div>
                
                <div>
                    <label for="customer_model" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-mobile-alt mr-1 text-blue-600"></i>Model <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_model" name="customer_model" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. iPhone 12 Pro, Galaxy S21 Ultra"
                           aria-required="true"
                           aria-describedby="customer_model_help">
                    <p id="customer_model_help" class="mt-1 text-xs text-gray-500">Enter the specific model name</p>
                </div>
            </div>
            
            <!-- Dynamic Spec Fields Container -->
            <div id="customerSpecsContainer" class="mt-4 hidden">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-info-circle mr-1 text-blue-600"></i>Device Specifications
                </h5>
                <p class="text-xs text-gray-500 mb-3">Additional details about the device (e.g. Storage, Color, etc.)</p>
                <div id="customerDynamicSpecs" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Dynamic fields will be inserted here -->
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="customer_condition" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-check-circle mr-1 text-blue-600"></i>Condition
                    </label>
                    <div class="relative">
                    <select id="customer_condition" name="customer_condition" 
                                class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                                aria-describedby="customer_condition_help">
                            <option value="used">Used - Good working condition</option>
                            <option value="new">New - Unused, sealed</option>
                            <option value="faulty">Faulty - Has issues or damage</option>
                    </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    <p id="customer_condition_help" class="mt-1 text-xs text-gray-500">Select the condition of the customer's device</p>
                </div>
                
                <div>
                    <label for="swap_value" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-money-bill-wave mr-1 text-blue-600"></i>Estimated Value (₵) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="swap_value" name="swap_value" step="0.01" min="0" required 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00"
                           aria-required="true"
                           aria-describedby="swap_value_help">
                    <p id="swap_value_help" class="mt-1 text-xs text-gray-500">Enter the estimated value of the customer's device in Ghana Cedis</p>
                </div>
            </div>
        </div>

        <!-- Swap Calculation -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Swap Calculation</h2>
            <p class="text-sm text-gray-600 mb-4">Enter the cash amount customer needs to add to complete the swap</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="cash_added" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-coins mr-1 text-blue-600"></i>Cash to Add (₵)
                    </label>
                    <input type="number" id="cash_added" name="cash_added" step="0.01" min="0" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00"
                           aria-describedby="cash_added_help">
                    <p id="cash_added_help" class="mt-1 text-xs text-gray-500">Additional cash customer pays (calculated automatically)</p>
                </div>
                
                <div>
                    <label for="total_value" class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-calculator mr-1 text-blue-600"></i>Total Transaction Value (₵)
                    </label>
                    <input type="text" id="total_value" readonly 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 font-semibold"
                           placeholder="0.00"
                           aria-readonly="true">
                    <p class="mt-1 text-xs text-gray-500">Total amount for this swap transaction</p>
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
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Additional Notes</h2>
            <p class="text-sm text-gray-600 mb-4">Add any extra information about this swap transaction</p>
            
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-sticky-note mr-1 text-blue-600"></i>Notes (Optional)
                </label>
                <textarea id="notes" name="notes" rows="4"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Enter any additional notes about the swap, customer preferences, or special conditions..."
                          aria-describedby="notes_help"></textarea>
                <p id="notes_help" class="mt-1 text-xs text-gray-500">Optional: Add any relevant information about this swap</p>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row justify-end gap-3 sm:gap-4 mt-6">
            <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="w-full sm:w-auto px-4 py-2 sm:py-2.5 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors text-center text-sm sm:text-base font-medium">Cancel</a>
            <button type="submit" id="completeSwapBtn" class="w-full sm:w-auto px-4 py-2 sm:py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors text-sm sm:text-base font-medium flex items-center justify-center">
                <i class="fas fa-exchange-alt mr-2"></i>
                <span>Complete Swap</span>
            </button>
        </div>
    </form>
</div>

<script>
// Swap form JavaScript
(function() {
    const storeProducts = <?= json_encode($storeProducts) ?>;
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

    // Searchable product dropdown functionality
    (function() {
        const productSearch = document.getElementById('product_search');
        const productDropdown = document.getElementById('product_dropdown');
        const hiddenSelect = storeProductSelect;
        const selectedInfo = document.getElementById('selected_product_info');
        const selectedName = document.getElementById('selected_product_name');
        const selectedDetails = document.getElementById('selected_product_details');
        const clearBtn = document.getElementById('clear_product_btn');
        
        if (!productSearch || !productDropdown || !hiddenSelect) return;
        
        function renderProductList(filteredProducts) {
            if (!filteredProducts || filteredProducts.length === 0) {
                productDropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No products found</div>';
                return;
            }
            
            productDropdown.innerHTML = filteredProducts.map(product => {
                const brandName = product.brand_name || 'Generic';
                const price = parseFloat(product.price || 0).toFixed(2);
                const displayText = `${product.name} - ${brandName} - ₵${price}`;
                
                return `
                    <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                         data-id="${product.id}" 
                         data-name="${product.name || ''}" 
                         data-brand="${brandName}"
                         data-price="${product.price || 0}">
                        <div class="font-medium text-gray-800">${product.name || 'No name'}</div>
                        <div class="text-sm text-gray-600">${brandName} - ₵${price}</div>
                        ${product.model_name && product.model_name !== 'N/A' ? `<div class="text-xs text-gray-500">Model: ${product.model_name}</div>` : ''}
                    </div>
                `;
            }).join('');
        }
        
        // Initial render - show all products
        renderProductList(storeProducts);
        
        // Open dropdown on focus/click
        productSearch.addEventListener('focus', () => {
            if (storeProducts.length > 0) {
                productDropdown.classList.remove('hidden');
            }
        });
        
        productSearch.addEventListener('click', () => {
            if (storeProducts.length > 0) {
                productDropdown.classList.remove('hidden');
            }
        });
        
        // Filter as user types
        productSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            if (query === '') {
                renderProductList(storeProducts);
            } else {
                const filtered = storeProducts.filter(product => {
                    const name = (product.name || '').toLowerCase();
                    const brand = (product.brand_name || '').toLowerCase();
                    const model = (product.model_name || '').toLowerCase();
                    return name.includes(query) || brand.includes(query) || model.includes(query);
                });
                renderProductList(filtered);
            }
            productDropdown.classList.remove('hidden');
        });
        
        // Select product from dropdown
        productDropdown.addEventListener('click', function(e) {
            const item = e.target.closest('[data-id]');
            if (!item) return;
            
            const productId = item.getAttribute('data-id');
            const productName = item.getAttribute('data-name');
            const productBrand = item.getAttribute('data-brand');
            const productPrice = parseFloat(item.getAttribute('data-price'));
            
            if (!productId) {
                console.error('Product ID is missing');
                return;
            }
            
            // Clear existing options except the default
            hiddenSelect.innerHTML = '<option value="">Select product to give customer</option>';
            
            // Create and add option for selected product
            const option = document.createElement('option');
            option.value = productId;
            option.textContent = `${productName} - ${productBrand}`;
            option.selected = true;
            hiddenSelect.appendChild(option);
            
            // Also set the value directly to ensure it's set
            hiddenSelect.value = productId;
            
            // Update search input
            productSearch.value = `${productName} - ${productBrand} - ₵${productPrice.toFixed(2)}`;
            
            // Hide dropdown
            productDropdown.classList.add('hidden');
            
            // Show selected product info
            selectedName.textContent = productName || 'No name';
            selectedDetails.textContent = `${productBrand} - ₵${productPrice.toFixed(2)}`;
            selectedInfo.classList.remove('hidden');
            
            // Update product details display
            updateStoreProductInfo(productName, productPrice);
            
            // Debug log
            console.log('Product selected:', {
                productId: productId,
                selectValue: hiddenSelect.value,
                hasOption: hiddenSelect.querySelector(`option[value="${productId}"]`) !== null
            });
        });
        
        // Clear product selection
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                productSearch.value = '';
                hiddenSelect.value = '';
                productDropdown.classList.add('hidden');
                selectedInfo.classList.add('hidden');
                storeProductInfo.classList.add('hidden');
                updateCalculation();
            });
        }
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!productSearch.contains(e.target) && !productDropdown.contains(e.target)) {
                productDropdown.classList.add('hidden');
            }
        });
        
        // Handle keyboard navigation
        productSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                productDropdown.classList.add('hidden');
            }
        });
    })();

    // Update store product info
    function updateStoreProductInfo(productName, productPrice) {
        if (productName && productPrice !== undefined) {
            storeProductDetails.innerHTML = `
                <div><strong>Product:</strong> ${productName}</div>
                <div><strong>Price:</strong> ₵${productPrice.toFixed(2)}</div>
            `;
            storeProductInfo.classList.remove('hidden');
            updateCalculation();
        } else {
            storeProductInfo.classList.add('hidden');
        }
    }

    // Update calculation
    function updateCalculation() {
        // Get product price from hidden select or selected product
        let storeProductPrice = 0;
        const selectedProductId = storeProductSelect.value;
        if (selectedProductId) {
            const selectedProduct = storeProducts.find(p => p.id == selectedProductId);
            if (selectedProduct) {
                storeProductPrice = parseFloat(selectedProduct.price || 0);
            }
        }
        
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

    // Searchable customer dropdown functionality with live search
    (function() {
        const searchInput = document.getElementById('customer_search');
        const dropdown = document.getElementById('customer_dropdown');
        const hiddenSelect = document.getElementById('customer_id');
        const selectedInfo = document.getElementById('selected_customer_info');
        const selectedName = document.getElementById('selected_customer_name');
        const selectedPhone = document.getElementById('selected_customer_phone');
        const clearBtn = document.getElementById('clear_customer_btn');
        let searchTimeout = null;
        
        if (!searchInput || !dropdown || !hiddenSelect) return;
        
        function renderCustomerList(customers) {
            if (!customers || customers.length === 0) {
                dropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
                return;
            }
            
            dropdown.innerHTML = customers.map(customer => `
                <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0" 
                     data-id="${customer.id}" 
                     data-name="${customer.full_name || ''}" 
                     data-phone="${customer.phone_number || ''}"
                     data-email="${customer.email || ''}">
                    <div class="font-medium text-gray-800">${customer.full_name || 'No name'}</div>
                    <div class="text-sm text-gray-600">${customer.phone_number || 'No phone'}</div>
                    ${customer.email ? `<div class="text-xs text-gray-500">${customer.email}</div>` : ''}
                </div>
            `).join('');
        }
        
        async function searchCustomers(query = '') {
            try {
                const url = `<?= BASE_URL_PATH ?>/api/swap/customers${query ? '?q=' + encodeURIComponent(query) : ''}`;
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success && result.data) {
                    renderCustomerList(result.data);
                    dropdown.classList.remove('hidden');
                } else {
                    dropdown.innerHTML = '<div class="p-3 text-gray-500 text-center">No customers found</div>';
                    dropdown.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error searching customers:', error);
                dropdown.innerHTML = '<div class="p-3 text-red-500 text-center">Error loading customers</div>';
                dropdown.classList.remove('hidden');
            }
        }
        
        // Open dropdown on focus/click and load all customers
        searchInput.addEventListener('focus', () => {
            searchCustomers('');
        });
        
        searchInput.addEventListener('click', () => {
            searchCustomers('');
        });
        
        // Live search as user types (debounced)
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search - wait 300ms after user stops typing
            searchTimeout = setTimeout(() => {
                searchCustomers(query);
            }, 300);
        });
        
        // Select customer from dropdown
        dropdown.addEventListener('click', function(e) {
            const item = e.target.closest('[data-id]');
            if (!item) return;
            
            const customerId = item.getAttribute('data-id');
            const customerName = item.getAttribute('data-name');
            const customerPhone = item.getAttribute('data-phone');
            
            if (!customerId) {
                console.error('Customer ID is missing');
                return;
            }
            
            // Clear existing options except the default
            hiddenSelect.innerHTML = '<option value="">Select existing customer (optional)</option>';
            
            // Create and add option for selected customer
            const option = document.createElement('option');
            option.value = customerId;
            option.textContent = `${customerName} (${customerPhone})`;
            option.selected = true;
            hiddenSelect.innerHTML = ''; // Clear first
            hiddenSelect.appendChild(option);
            
            // Also set the value directly to ensure it's set
            hiddenSelect.value = customerId;
            
            // Also set the backup hidden input value
            const customerIdBackup = document.getElementById('customer_id_backup');
            if (customerIdBackup) {
                customerIdBackup.value = customerId;
            }
            
            // Update search input
            searchInput.value = `${customerName} (${customerPhone})`;
            
            // Hide dropdown
            dropdown.classList.add('hidden');
            
            // Show selected customer info
            selectedName.textContent = customerName || 'No name';
            selectedPhone.textContent = customerPhone || 'No phone';
            selectedInfo.classList.remove('hidden');
            
            // Auto-fill customer name and contact fields
            document.getElementById('customer_name').value = customerName || '';
            document.getElementById('customer_contact').value = customerPhone || '';
            
            // Debug log
            console.log('Customer selected:', {
                customerId: customerId,
                selectValue: hiddenSelect.value,
                backupInputValue: customerIdBackup ? customerIdBackup.value : 'not found',
                hasOption: hiddenSelect.querySelector(`option[value="${customerId}"]`) !== null
            });
        });
        
        // Clear customer selection
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                // Reset hidden select to default
                hiddenSelect.innerHTML = '<option value="">Select existing customer (optional)</option>';
                hiddenSelect.value = '';
                
                // Clear backup hidden input
                const customerIdBackup = document.getElementById('customer_id_backup');
                if (customerIdBackup) {
                    customerIdBackup.value = '';
                }
                
                dropdown.classList.add('hidden');
                selectedInfo.classList.add('hidden');
                
                // Clear customer name and contact fields
                document.getElementById('customer_name').value = '';
                document.getElementById('customer_contact').value = '';
            });
        }
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.classList.add('hidden');
            }
        });
    })();

    // Event listeners
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
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        fetch(basePath + '/api/brands/by-category/1')
            .then(response => response.json())
            .then(result => {
                // Handle both formats: direct array or {success: true, data: [...]}
                let brands = Array.isArray(result) ? result : (result.data || []);
                if (!populateBrandSelect(brands)) {
                    // Fallback: try alternative API endpoint
                    fetch(basePath + '/api/products/brands/1')
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
                fetch(basePath + '/api/products/brands/1')
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
        const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
        fetch(basePath + '/api/brands/specs/' + encodeURIComponent(brandId))
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

// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const swapForm = document.querySelector('form[action*="/dashboard/swaps/store"]');
    const completeSwapBtn = document.getElementById('completeSwapBtn');
    
    if (swapForm && completeSwapBtn) {
        swapForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to do custom validation
            
            // Validate required fields
            const storeProductId = document.getElementById('store_product_id');
            const customerName = document.getElementById('customer_name');
            const customerContact = document.getElementById('customer_contact');
            const customerBrandId = document.getElementById('customer_brand_id');
            const customerModel = document.getElementById('customer_model');
            const swapValue = document.getElementById('swap_value');
            
            let isValid = true;
            let errorMessage = '';
            
            // Validate store product
            const selectedProductValue = storeProductId ? storeProductId.value : '';
            const selectedProductOption = storeProductId ? storeProductId.options[storeProductId.selectedIndex] : null;
            const hasValidProduct = selectedProductValue && 
                                   selectedProductValue !== '' && 
                                   selectedProductValue !== '0' &&
                                   selectedProductOption &&
                                   selectedProductOption.value !== '';
            
            console.log('Product validation:', {
                storeProductId: storeProductId,
                value: selectedProductValue,
                selectedIndex: storeProductId ? storeProductId.selectedIndex : -1,
                hasValidProduct: hasValidProduct,
                option: selectedProductOption
            });
            
            if (!hasValidProduct) {
                isValid = false;
                errorMessage = 'Please select a store product from the dropdown';
                const productSearch = document.getElementById('product_search');
                if (productSearch) {
                    productSearch.focus();
                    productSearch.classList.add('border-red-500');
                    setTimeout(() => productSearch.classList.remove('border-red-500'), 3000);
                }
            }
            
            // Validate customer name
            if (isValid && (!customerName || !customerName.value.trim())) {
                isValid = false;
                errorMessage = 'Please enter customer name';
                customerName.focus();
                customerName.classList.add('border-red-500');
                setTimeout(() => customerName.classList.remove('border-red-500'), 3000);
            }
            
            // Validate customer contact
            if (isValid && (!customerContact || !customerContact.value.trim())) {
                isValid = false;
                errorMessage = 'Please enter customer phone number';
                customerContact.focus();
                customerContact.classList.add('border-red-500');
                setTimeout(() => customerContact.classList.remove('border-red-500'), 3000);
            }
            
            // Validate customer brand
            if (isValid && (!customerBrandId || !customerBrandId.value || customerBrandId.value === '')) {
                isValid = false;
                errorMessage = 'Please select the brand of the customer\'s product';
                customerBrandId.focus();
                customerBrandId.classList.add('border-red-500');
                setTimeout(() => customerBrandId.classList.remove('border-red-500'), 3000);
            }
            
            // Validate customer model
            if (isValid && (!customerModel || !customerModel.value.trim())) {
                isValid = false;
                errorMessage = 'Please enter the model of the customer\'s product';
                customerModel.focus();
                customerModel.classList.add('border-red-500');
                setTimeout(() => customerModel.classList.remove('border-red-500'), 3000);
            }
            
            // Validate swap value
            if (isValid && (!swapValue || !swapValue.value || parseFloat(swapValue.value) <= 0)) {
                isValid = false;
                errorMessage = 'Please enter a valid estimated value for the customer\'s product';
                swapValue.focus();
                swapValue.classList.add('border-red-500');
                setTimeout(() => swapValue.classList.remove('border-red-500'), 3000);
            }
            
            if (!isValid) {
                // Show error message
                alert(errorMessage);
                return false;
            }
            
            // Show loading state
            completeSwapBtn.disabled = true;
            completeSwapBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span>Processing...</span>';
            
            // Submit the form programmatically
            swapForm.submit();
        });
    }
});
</script>
