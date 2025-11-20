<?php
// Enhanced product creation form with dynamic category-driven fields for dashboard
?>

<div class="container mx-auto px-4">
    <div class="flex items-center mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/inventory" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800"><?= isset($product) ? 'Edit Product' : 'Add New Product' ?></h1>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <form id="addProductForm" method="POST" action="<?= isset($product) ? BASE_URL_PATH.'/dashboard/inventory/update/'.$product['id'] : BASE_URL_PATH.'/dashboard/inventory/store' ?>" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Product Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($product['name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter product name">
                </div>
                
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select id="category_id" name="category_id" required 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['id']) ?>" 
                                    <?= (isset($product) && $product['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Brand Container (For Phones, Tablets, Accessories, Repair Parts) -->
            <div id="brandContainer" class="mt-4" style="display:none;">
                <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                <select id="brand_id" name="brand_id" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Brand</option>
                    <?php if (isset($brands) && !empty($brands)): ?>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= htmlspecialchars($brand['id']) ?>" 
                                    <?= (isset($product) && $product['brand_id'] == $brand['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Subcategory Container (For Accessories, Repair Parts, Wearables) -->
            <div id="subcategoryContainer" class="mt-4" style="display:none;">
                <label for="subcategory_id" class="block text-sm font-medium text-gray-700 mb-1">Subcategory</label>
                <select id="subcategory_id" name="subcategory_id" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Subcategory</option>
                </select>
            </div>
        </div>

        <!-- Dynamic Specifications Container -->
        <div id="specsContainer" class="bg-white p-6 rounded-lg shadow-sm border" style="display:none;">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Product Specifications</h2>
            <div id="dynamicSpecs"></div>
        </div>

        <!-- Pricing & Inventory -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Pricing & Inventory</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="cost" class="block text-sm font-medium text-gray-700 mb-1">Cost Price (₵)</label>
                    <input type="number" id="cost" name="cost" step="0.01" min="0" 
                           value="<?= htmlspecialchars($product['cost'] ?? '0') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                        Selling Price (₵) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required 
                           value="<?= htmlspecialchars($product['price'] ?? '0') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="0" 
                           value="<?= htmlspecialchars($product['quantity'] ?? '0') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0">
                </div>
            </div>
        </div>

        <!-- Additional Product Information -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Additional Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700 mb-1">SKU/Barcode</label>
                    <input type="text" id="sku" name="sku" 
                           value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Product SKU (optional)">
                </div>
                <div>
                    <label for="model_name" class="block text-sm font-medium text-gray-700 mb-1">Model Name</label>
                    <input type="text" id="model_name" name="model_name" 
                           value="<?= htmlspecialchars($product['model_name'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Model name (optional)">
                </div>
            </div>
            
            <div class="mt-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Product Description</label>
                <textarea id="description" name="description" rows="3" 
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Detailed product description (optional)"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight</label>
                    <input type="text" id="weight" name="weight" 
                           value="<?= htmlspecialchars($product['weight'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. 200g">
                </div>
                <div>
                    <label for="dimensions" class="block text-sm font-medium text-gray-700 mb-1">Dimensions</label>
                    <input type="text" id="dimensions" name="dimensions" 
                           value="<?= htmlspecialchars($product['dimensions'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. 15x8x1 cm">
                </div>
                <div>
                    <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                    <input type="text" id="supplier" name="supplier" 
                           value="<?= htmlspecialchars($product['supplier'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Supplier name (optional)">
                </div>
            </div>
            
            <div class="mt-4">
                <label for="image_url" class="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                <input type="url" id="image_url" name="image_url" 
                       value="<?= htmlspecialchars($product['image_url'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="https://example.com/image.jpg (optional)">
            </div>
        </div>

        <!-- Available for Swap (Only for Phones) -->
        <div id="swapContainer" class="bg-white p-6 rounded-lg shadow-sm border" style="display:none;">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Swap Options</h2>
            <div class="flex items-center">
                <input type="checkbox" id="available_for_swap" name="available_for_swap" value="1" 
                       <?= (isset($product) && $product['available_for_swap']) ? 'checked' : '' ?>
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="available_for_swap" class="ml-2 block text-sm text-gray-700">
                    Available for Swap
                </label>
            </div>
            <p class="text-sm text-gray-500 mt-2">Check this if customers can trade in their old phones for this product.</p>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <a href="<?= BASE_URL_PATH ?>/dashboard/inventory" 
               class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?= isset($product) ? 'Update Product' : 'Create Product' ?>
            </button>
        </div>
    </form>
</div>

<script>
// Enhanced Dynamic Product Form
document.addEventListener('DOMContentLoaded', () => {
    // Set BASE URL for API calls
    const BASE = window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>';
    
    const categorySelect = document.getElementById('category_id');
    const brandContainer = document.getElementById('brandContainer');
    const brandSelect = document.getElementById('brand_id');
    const subcategoryContainer = document.getElementById('subcategoryContainer');
    const subcategorySelect = document.getElementById('subcategory_id');
    const specsContainer = document.getElementById('specsContainer');
    const dynamicSpecs = document.getElementById('dynamicSpecs');
    const swapContainer = document.getElementById('swapContainer');

    // Category change handler
    categorySelect.addEventListener('change', () => {
        const categoryId = categorySelect.value;
        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = categoryOption ? categoryOption.text.toLowerCase() : '';

        // Reset form sections
        brandContainer.style.display = 'none';
        subcategoryContainer.style.display = 'none';
        specsContainer.style.display = 'none';
        swapContainer.style.display = 'none';
        dynamicSpecs.innerHTML = '';
        brandSelect.innerHTML = '<option value="">Select Brand</option>';
        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

        // Show brand dropdown for any category that might have brands
        // Check by category name (phone, tablet, accessory, repair, etc.)
        if (categoryId) {
            const normalizedCategoryName = categoryName.toLowerCase().trim();
            const phoneCategories = ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobiles'];
            const accessoryCategories = ['accessory', 'accessories'];
            const repairCategories = ['repair', 'repairs', 'part', 'parts', 'repair part', 'repair parts'];
            const tabletCategories = ['tablet', 'tablets'];
            
            const shouldShowBrand = phoneCategories.includes(normalizedCategoryName) ||
                                   accessoryCategories.includes(normalizedCategoryName) ||
                                   repairCategories.includes(normalizedCategoryName) ||
                                   tabletCategories.includes(normalizedCategoryName);
            
            if (shouldShowBrand) {
                brandContainer.style.display = 'block';

                // Fetch brands dynamically - try multiple API endpoints
                const apiEndpoints = [
                    `${BASE}/api/products/brands/${categoryId}`,
                    `${BASE}/api/brands/by-category/${categoryId}`,
                    `${BASE}/api/pos/products/brands?category_id=${categoryId}`
                ];
                
                let fetchAttempt = 0;
                function tryFetchBrands() {
                    if (fetchAttempt >= apiEndpoints.length) {
                        console.error('All brand API endpoints failed');
                        return;
                    }
                    
                    fetch(apiEndpoints[fetchAttempt])
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(`HTTP ${res.status}`);
                            }
                            return res.json();
                        })
                        .then(data => {
                            // Handle different response formats
                            let brands = [];
                            if (data.success && data.data) {
                                brands = data.data;
                            } else if (Array.isArray(data)) {
                                brands = data;
                            } else if (data.brands) {
                                brands = data.brands;
                            }
                            
                            if (brands && brands.length > 0) {
                                brandSelect.innerHTML = '<option value="">Select Brand</option>';
                                brands.forEach(brand => {
                                    const opt = document.createElement('option');
                                    opt.value = brand.id || brand.brand_id;
                                    opt.textContent = brand.name || brand.brand_name;
                                    brandSelect.appendChild(opt);
                                });
                            } else {
                                // No brands found, but keep the field visible
                                console.log('No brands found for this category');
                            }
                        })
                        .catch(error => {
                            console.error(`Error loading brands from endpoint ${fetchAttempt}:`, error);
                            fetchAttempt++;
                            if (fetchAttempt < apiEndpoints.length) {
                                tryFetchBrands();
                            }
                        });
                }
                
                tryFetchBrands();
            }
        }

        // Show subcategory dropdown for Accessories, Repair Parts, and Wearables
        if (categoryId && ['2', '5', '6'].includes(categoryId)) {
            subcategoryContainer.style.display = 'block';

            // Fetch subcategories dynamically
            fetch(`${BASE}/api/products/subcategories/${categoryId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                        data.data.forEach(subcategory => {
                            const opt = document.createElement('option');
                            opt.value = subcategory.id;
                            opt.textContent = subcategory.name;
                            subcategorySelect.appendChild(opt);
                        });
                    }
                })
                .catch(error => console.error('Error loading subcategories:', error));
        }

        // Show swap option only for Phone category (check by name)
        const normalizedCategoryName = categoryName.toLowerCase().trim();
        const phoneCategories = ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobiles'];
        const accessoryCategories = ['accessory', 'accessories'];
        const isPhoneCategory = phoneCategories.includes(normalizedCategoryName);
        const isAccessoryCategory = accessoryCategories.includes(normalizedCategoryName);
        
        if (isPhoneCategory) {
            swapContainer.style.display = 'block';
        } else {
            swapContainer.style.display = 'none';
        }

        // Show generic specs for categories without brand-specific specs
        // For accessories, always show generic specs (not phone specs, even if brand is Apple/iPhone)
        if (categoryId) {
            if (isAccessoryCategory) {
                // For accessories, show generic accessory specs
                // Clear any phone specs that might be showing
                specsContainer.style.display = 'block';
                dynamicSpecs.innerHTML = '';
                showGenericSpecs(categoryName);
            } else if (!['1', '4'].includes(categoryId)) {
                // For other non-phone categories, show generic specs
                showGenericSpecs(categoryName);
            } else {
                // For phone categories, if brand is already selected, reload specs
                if (brandSelect.value) {
                    brandSelect.dispatchEvent(new Event('change'));
                }
            }
        }
    });

    // Brand change handler
    brandSelect.addEventListener('change', () => {
        const brandId = brandSelect.value;
        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = categoryOption ? categoryOption.text.toLowerCase() : '';
        const normalizedCategoryName = categoryName.toLowerCase().trim();
        
        // Check if category is accessory
        const accessoryCategories = ['accessory', 'accessories'];
        const isAccessoryCategory = accessoryCategories.includes(normalizedCategoryName);
        
        // Check if category is phone
        const phoneCategories = ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobiles'];
        const isPhoneCategory = phoneCategories.includes(normalizedCategoryName);
        
        if (!brandId) {
            specsContainer.style.display = 'none';
            dynamicSpecs.innerHTML = '';
            return;
        }
        
        // CRITICAL: For accessories, NEVER show phone specs, regardless of brand
        // Even if brand is Apple/iPhone, show generic accessory specs
        if (isAccessoryCategory) {
            // Clear any existing specs first
            specsContainer.style.display = 'block';
            dynamicSpecs.innerHTML = '';
            // Show generic accessory specs instead of phone specs
            showGenericSpecs(categoryName);
            return; // Exit early, don't fetch brand specs
        }
        
        // Only fetch and show phone-specific specs if category is actually "phone"
        if (isPhoneCategory) {
            // Fetch brand-specific specifications for phones
            fetch(`${BASE}/api/products/brand-specs/${brandId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        // Double-check category hasn't changed to accessory
                        const currentCategoryOption = categorySelect.options[categorySelect.selectedIndex];
                        const currentCategoryName = currentCategoryOption ? currentCategoryOption.text.toLowerCase() : '';
                        const currentNormalized = currentCategoryName.toLowerCase().trim();
                        const isCurrentlyAccessory = accessoryCategories.includes(currentNormalized);
                        
                        if (isCurrentlyAccessory) {
                            // Category changed to accessory while fetching, show generic specs
                            showGenericSpecs(currentCategoryName);
                        } else {
                            showBrandSpecs(data.data);
                        }
                    }
                })
                .catch(error => console.error('Error loading brand specs:', error));
        } else {
            // For other categories (tablets, etc.), try to fetch brand specs
            fetch(`${BASE}/api/products/brand-specs/${brandId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        showBrandSpecs(data.data);
                    }
                })
                .catch(error => console.error('Error loading brand specs:', error));
        }
    });

    // Initialize form if editing existing product
    <?php if (isset($product)): ?>
        // Trigger category change to load appropriate fields
        // Make sure to check category first and prevent phone specs for accessories
        if (categorySelect.value) {
            const initCategoryOption = categorySelect.options[categorySelect.selectedIndex];
            const initCategoryName = initCategoryOption ? initCategoryOption.text.toLowerCase() : '';
            const initNormalized = initCategoryName.toLowerCase().trim();
            const initAccessoryCategories = ['accessory', 'accessories'];
            const isInitAccessory = initAccessoryCategories.includes(initNormalized);
            
            // If editing an accessory, clear any phone specs that might be in the product data
            if (isInitAccessory) {
                specsContainer.style.display = 'block';
                dynamicSpecs.innerHTML = '';
                showGenericSpecs(initCategoryName);
            } else {
                categorySelect.dispatchEvent(new Event('change'));
            }
        }
    <?php endif; ?>

    function showBrandSpecs(specs) {
        // CRITICAL SAFETY CHECK: Don't show phone specs if category is accessory
        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = categoryOption ? categoryOption.text.toLowerCase() : '';
        const normalizedCategoryName = categoryName.toLowerCase().trim();
        const accessoryCategories = ['accessory', 'accessories'];
        const isAccessoryCategory = accessoryCategories.includes(normalizedCategoryName);
        
        // Phone-specific spec names that should NEVER appear for accessories
        const phoneSpecNames = ['storage', 'ram', 'battery_health', 'imei', 'model', 'network'];
        
        if (isAccessoryCategory) {
            // If category is accessory, show generic accessory specs instead
            // Clear any phone specs that might have been passed
            specsContainer.style.display = 'block';
            dynamicSpecs.innerHTML = '';
            showGenericSpecs(categoryName);
            return;
        }
        
        // Check if this is a phone category
        const phoneCategories = ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobiles'];
        const isPhoneCategory = phoneCategories.includes(normalizedCategoryName);
        
        // Specs come as an array of objects, not an object
        // Each spec has: { name, label, type, placeholder, options, etc. }
        const specsArray = Array.isArray(specs) ? specs : Object.values(specs);
        
        // Additional safety: Filter out phone-specific specs if category is NOT phone
        // This prevents phone specs from appearing for accessories, tablets, or any other category
        let specsToShow = specsArray;
        if (!isPhoneCategory) {
            specsToShow = specsArray.filter(spec => {
                // Check if spec name is in the phone-specific list
                const specName = (spec.name || '').toLowerCase();
                return !phoneSpecNames.includes(specName);
            });
            
            // If we filtered out all specs (meaning they were all phone specs), don't show anything
            if (specsToShow.length === 0 && specsArray.length > 0) {
                console.warn('Blocked phone specs (storage, RAM, IMEI, model, etc.) from being displayed for non-phone category');
                specsContainer.style.display = 'none';
                dynamicSpecs.innerHTML = '';
                return;
            }
        }
        
        specsContainer.style.display = 'block';
        dynamicSpecs.innerHTML = '';
        
        // Handle array format (each spec is an object with name, label, type, etc.)
        specsToShow.forEach(spec => {
            const key = spec.name || spec.key || 'spec';
            const div = document.createElement('div');
            div.className = 'mb-4';
            
            const label = document.createElement('label');
            label.className = 'block text-sm font-medium text-gray-700 mb-1';
            label.textContent = spec.label || spec.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            label.setAttribute('for', `spec_${key}`);
            
            let input;
            if (spec.type === 'select' && spec.options) {
                input = document.createElement('select');
                input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
                
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = `Select ${spec.label || spec.name}`;
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
                input.placeholder = spec.placeholder || `Enter ${spec.label || spec.name}`;
            }
            
            input.id = `spec_${key}`;
            input.name = `specs[${key}]`;
            
            // Set existing value if editing
            <?php if (isset($product) && !empty($product['specs'])): ?>
                const existingSpecs = <?= json_encode(json_decode($product['specs'] ?? '{}', true)) ?>;
                if (existingSpecs[key]) {
                    input.value = existingSpecs[key];
                }
            <?php endif; ?>
            
            div.appendChild(label);
            div.appendChild(input);
            dynamicSpecs.appendChild(div);
        });
    }

    function showGenericSpecs(categoryName) {
        specsContainer.style.display = 'block';
        dynamicSpecs.innerHTML = '';
        
        const genericSpecs = {
            'accessory': [
                { key: 'type', label: 'Type', type: 'text', placeholder: 'e.g., Charger, Case, Screen Protector' },
                { key: 'color', label: 'Color', type: 'text', placeholder: 'e.g., Black, White, Blue' },
                { key: 'compatibility', label: 'Compatibility', type: 'text', placeholder: 'e.g., iPhone 12, Samsung Galaxy S21' }
            ],
            'repair parts': [
                { key: 'part_type', label: 'Part Type', type: 'text', placeholder: 'e.g., Display, Battery, Camera' },
                { key: 'compatibility', label: 'Device Compatibility', type: 'text', placeholder: 'e.g., iPhone 12, Samsung Galaxy S21' },
                { key: 'condition', label: 'Condition', type: 'select', options: ['New', 'Refurbished', 'Used'] }
            ],
            'wearables': [
                { key: 'type', label: 'Type', type: 'text', placeholder: 'e.g., Smartwatch, Fitness Band' },
                { key: 'color', label: 'Color', type: 'text', placeholder: 'e.g., Black, Silver, Rose Gold' },
                { key: 'size', label: 'Size', type: 'text', placeholder: 'e.g., 42mm, 44mm, One Size' }
            ]
        };
        
        const specs = genericSpecs[categoryName] || [];
        
        specs.forEach(spec => {
            const div = document.createElement('div');
            div.className = 'mb-4';
            
            const label = document.createElement('label');
            label.className = 'block text-sm font-medium text-gray-700 mb-1';
            label.textContent = spec.label;
            label.setAttribute('for', `spec_${spec.key}`);
            
            let input;
            if (spec.type === 'select' && spec.options) {
                input = document.createElement('select');
                input.className = 'w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
                
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = `Select ${spec.label}`;
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
                input.placeholder = spec.placeholder;
            }
            
            input.id = `spec_${spec.key}`;
            input.name = `spec_${spec.key}`;
            
            div.appendChild(label);
            div.appendChild(input);
            dynamicSpecs.appendChild(div);
        });
    }
});
</script>
