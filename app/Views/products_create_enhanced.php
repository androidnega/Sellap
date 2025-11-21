<?php
// Enhanced product creation form with dynamic category-driven fields
$isEdit = isset($product) && !empty($product);
$formTitle = $isEdit ? 'Edit Product' : 'Add New Product';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/inventory/update/' . $product['id'] : BASE_URL_PATH . '/dashboard/inventory/store';

// Get prefill data from purchase order if available
$prefillData = $GLOBALS['prefill_product_data'] ?? null;
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/inventory" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Product Management
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
        <p class="text-gray-600">Create a new product with dynamic specifications</p>
    </div>


    <form id="productForm" method="POST" action="<?= $formAction ?>" enctype="multipart/form-data" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column -->
            <div class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                    
                    <div class="space-y-4">
                        <!-- Product Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Product Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($product['name'] ?? $prefillData['name'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., iPhone 14 Pro Max"
                                   title="Enter the full product name as it appears on the device"
                                   required>
                        </div>

                        <!-- Category -->
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select id="categorySelect" 
                                    name="category_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    title="Select the product category to show relevant fields"
                                    required>
                                <option value="">Select a category</option>
                                <?php 
                                $uniqueCategories = [];
                                foreach ($categories as $category) {
                                    if (!in_array($category['name'], $uniqueCategories)) {
                                        $uniqueCategories[] = $category['name'];
                                        echo '<option value="' . $category['id'] . '" ' . 
                                             (($product['category_id'] ?? '') == $category['id'] ? 'selected' : '') . '>' .
                                             htmlspecialchars($category['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- SKU/Barcode -->
                        <div>
                            <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                                SKU / Barcode
                            </label>
                            <input type="text" 
                                   id="sku" 
                                   name="sku" 
                                   value="<?= htmlspecialchars($product['sku'] ?? $prefillData['sku'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Auto-generated if empty"
                                   title="Leave empty to auto-generate SKU">
                        </div>

                        <!-- Subcategory (Dynamic) -->
                        <div id="subcategoryWrapper" style="display:none;">
                            <label for="subcategory_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Subcategory
                            </label>
                            <select id="subcategorySelect" 
                                    name="subcategory_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select subcategory</option>
                            </select>
                        </div>

                        <!-- Brand (Dynamic) -->
                        <div id="brandWrapper" style="display:none;">
                            <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Brand <span class="text-red-500">*</span>
                            </label>
                            <select id="brandSelect" 
                                    name="brand_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required>
                                <option value="">Select brand</option>
                                <?php if (isset($product) && !empty($product['brand_id'])): ?>
                                    <option value="<?= $product['brand_id'] ?>" selected><?= htmlspecialchars($product['brand_name'] ?? 'Selected Brand') ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Available for Swap (Dynamic) -->
                        <div id="swapWrapper" style="display:none;" class="mt-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="available_for_swap" 
                                       name="available_for_swap" 
                                       value="1"
                                       <?= (($product['available_for_swap'] ?? 0) == 1) ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="available_for_swap" class="ml-2 block text-sm text-gray-700">
                                    Available for Swap
                                </label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Only available for phone categories</p>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Pricing & Inventory</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Cost Price -->
                        <div>
                            <label for="cost_price" class="block text-sm font-medium text-gray-700 mb-2">
                                Cost Price (₵)
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   id="cost_price" 
                                   name="cost_price" 
                                   value="<?= htmlspecialchars($product['cost'] ?? $prefillData['cost_price'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0.00"
                                   title="Enter the cost price you paid for this product">
                        </div>

                        <!-- Selling Price -->
                        <div>
                            <label for="selling_price" class="block text-sm font-medium text-gray-700 mb-2">
                                Selling Price (₵) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   id="selling_price" 
                                   name="selling_price" 
                                   value="<?= htmlspecialchars($product['price'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0.00"
                                   title="Enter the price you will sell this product for"
                                   required>
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                Stock Quantity <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   id="quantity" 
                                   name="quantity" 
                                   value="<?= htmlspecialchars($product['quantity'] ?? $prefillData['quantity'] ?? '0') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   min="0"
                                   title="Enter the number of units in stock"
                                   required>
                        </div>

                        <!-- Item Location -->
                        <div>
                            <label for="item_location" class="block text-sm font-medium text-gray-700 mb-2">
                                Item Location
                            </label>
                            <input type="text" 
                                   id="item_location" 
                                   name="item_location" 
                                   value="<?= htmlspecialchars($product['item_location'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., Shelf A1, Display Case 2, Storage Room"
                                   title="Enter where this item is located in the shop (shelf, display case, storage room, etc.)">
                        </div>

                        <!-- Supplier -->
                        <div>
                            <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Supplier <span class="text-gray-500 text-xs">(for tracking)</span>
                            </label>
                            <select id="supplier_id" 
                                    name="supplier_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    title="Select a supplier to track product source and accumulate quantities/amounts">
                                <option value="">No Supplier (Optional)</option>
                                <?php if (isset($suppliers) && !empty($suppliers)): ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>"
                                                <?= (isset($product) && isset($product['supplier_id']) && $product['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($supplier['name']) ?>
                                            <?php if (!empty($supplier['contact_person'])): ?>
                                                - <?= htmlspecialchars($supplier['contact_person']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Select supplier to track product source. This will accumulate quantities and amounts per supplier.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Product Details -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Product Details</h3>
                    
                    <div class="space-y-4">
                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Product description..."
                                      title="Enter a detailed description of the product"><?= htmlspecialchars($product['description'] ?? $prefillData['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Image Upload -->
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                                Product Image
                            </label>
                            <input type="file" 
                                   id="image" 
                                   name="image" 
                                   accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   title="Upload a clear image of the product"
                                   onchange="previewImage(this)">
                            <p class="text-sm text-gray-500 mt-1">Upload a product image (JPG, PNG, GIF)</p>
                            
                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-3" style="display: none;">
                                <img id="previewImg" src="" alt="Product Preview" class="max-w-xs h-32 object-cover rounded-lg border border-gray-300">
                                <p class="text-sm text-gray-600 mt-1">Image Preview</p>
                            </div>
                            
                            <!-- Existing Image (for edit mode) -->
                            <?php if (isset($product) && !empty($product['image_url'])): ?>
                            <div id="existingImage" class="mt-3">
                                <p class="text-sm text-gray-600 mb-2">Current Image:</p>
                                <?php 
                                $imageUrl = $product['image_url'];
                                if (!str_starts_with($imageUrl, 'http')) {
                                    $imageUrl = BASE_URL_PATH . '/' . $imageUrl;
                                }
                                ?>
                                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Current Product Image" class="max-w-xs h-32 object-cover rounded-lg border border-gray-300">
                                <p class="text-sm text-gray-500 mt-1">Upload a new image to replace this one</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Physical Attributes -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Weight -->
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">
                                    Weight
                                </label>
                                <input type="text" 
                                       id="weight" 
                                       name="weight" 
                                       value="<?= htmlspecialchars($product['weight'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., 200g"
                                       title="Enter the product weight (e.g., 200g, 1.5kg)">
                            </div>

                            <!-- Dimensions -->
                            <div>
                                <label for="dimensions" class="block text-sm font-medium text-gray-700 mb-2">
                                    Dimensions
                                </label>
                                <input type="text" 
                                       id="dimensions" 
                                       name="dimensions" 
                                       value="<?= htmlspecialchars($product['dimensions'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., 160x78x7.7mm"
                                       title="Enter the product dimensions (length x width x height)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Specifications -->
                <div id="specsWrapper" class="bg-white rounded-lg shadow-sm border p-6" style="display:none;">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Specifications</h3>
                    <div id="specsContainer">
                        <!-- Dynamic specs will be loaded here based on brand selection -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4 pt-6 border-t">
            <a href="<?= BASE_URL_PATH ?>/dashboard/inventory" 
               class="px-6 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                <?= $isEdit ? 'Update Product' : 'Create Product' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const categorySelect = document.getElementById('categorySelect');
  const subcategoryWrapper = document.getElementById('subcategoryWrapper');
  const subcategorySelect = document.getElementById('subcategorySelect');
  const brandWrapper = document.getElementById('brandWrapper');
  const brandSelect = document.getElementById('brandSelect');
  const swapWrapper = document.getElementById('swapWrapper');
  const specsContainer = document.getElementById('specsContainer');
  const specsWrapper = document.getElementById('specsWrapper');
  
  // Store existing specs globally for easy access
  const existingSpecs = <?= isset($product) && !empty($product['specs']) ? json_encode($product['specs']) : 'null' ?>;
  console.log('Loaded existing specs:', existingSpecs);

  const fetchJson = (url) => fetch(url).then(r => r.ok ? r.json() : {data: []}).then(data => {
    // Handle different response formats
    if (data.success && data.data) {
      return data.data;
    } else if (Array.isArray(data)) {
      return data;
    } else if (data.data) {
      return data.data;
    }
    return [];
  });

  // Helper function to normalize category names for flexible matching
  function normalizeCategoryName(categoryName) {
    if (!categoryName) return '';
    return categoryName.toLowerCase().trim();
  }

  // Helper function to check if category is phone-related
  function isPhoneCategory(categoryName) {
    const normalized = normalizeCategoryName(categoryName);
    return normalized.includes('phone') || normalized.includes('smart') || normalized.includes('mobile');
  }

  // Helper function to check if category is accessory-related
  function isAccessoryCategory(categoryName) {
    const normalized = normalizeCategoryName(categoryName);
    return normalized.includes('accessory') || normalized.includes('accessories');
  }

  // Helper function to check if category is repair-related
  function isRepairCategory(categoryName) {
    const normalized = normalizeCategoryName(categoryName);
    return normalized.includes('repair') || normalized.includes('part') || normalized.includes('parts');
  }

  function resetSelect(selectEl, placeholder = 'Select') {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
  }
  
  function populateSelect(selectEl, items, placeholder='Select') {
    resetSelect(selectEl, placeholder);
    if(!items || !items.length) return;
    
    // Filter out duplicate brand names
    const uniqueBrands = [];
    const seenNames = new Set();
    
    items.forEach(it => {
      if (!seenNames.has(it.name)) {
        seenNames.add(it.name);
        uniqueBrands.push(it);
      }
    });
    
    console.log('Unique brands after filtering:', uniqueBrands);
    
    uniqueBrands.forEach(it => {
      const o = document.createElement('option');
      o.value = it.id;
      o.textContent = it.name;
      selectEl.appendChild(o);
    });
  }

  async function onCategoryChange(){
    const catId = categorySelect.value;
    console.log('Category changed to:', catId);
    
    const categories = <?= json_encode(array_column($categories, 'name', 'id')) ?>;
    const catName = categories[catId] || '';
    
    console.log('Available categories:', categories);
    console.log('Selected category name:', catName);
    console.log('Category ID:', catId);
    console.log('Normalized category name:', normalizeCategoryName(catName));
    
    const specsWrapper = document.getElementById('specsWrapper');
    
    if(!catId){
      subcategoryWrapper.style.display = 'none';
      brandWrapper.style.display = 'none';
      swapWrapper.style.display = 'none';
      specsContainer.innerHTML = '';
      if (specsWrapper) specsWrapper.style.display = 'none';
      return Promise.resolve();
    }

    // Load subcategories
    const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
    const subs = await fetchJson(`${basePath}/api/subcategories/by-category/${catId}`);
    console.log('Subcategories loaded:', subs);
    if(subs && subs.length){ 
      populateSelect(subcategorySelect, subs, 'Select subcategory'); 
      subcategoryWrapper.style.display = 'block'; 
    } else { 
      subcategoryWrapper.style.display = 'none'; 
      resetSelect(subcategorySelect); 
    }

    // Load brands
    const brandUrl = `${basePath}/api/brands/by-category/${catId}`;
    console.log('Fetching brands from URL:', brandUrl);
    const brands = await fetchJson(brandUrl);
    console.log('Brands loaded:', brands);
    console.log('Brands length:', brands ? brands.length : 'null/undefined');
    
    if(brands && brands.length > 0){ 
      console.log('Showing brand wrapper and populating brands');
      console.log('Brand wrapper element:', brandWrapper);
      console.log('Brand wrapper current display:', brandWrapper.style.display);
      populateSelect(brandSelect, brands, 'Select brand'); 
      brandWrapper.style.display = 'block'; 
      console.log('Brand wrapper display set to:', brandWrapper.style.display);
      
      // Set the selected brand value if editing
      <?php if (isset($product) && !empty($product['brand_id'])): ?>
      const selectedBrandId = '<?= $product['brand_id'] ?>';
      if(selectedBrandId) {
        brandSelect.value = selectedBrandId;
        console.log('Set brand value to:', selectedBrandId);
        // Trigger brand change to load specs
        onBrandChange();
      }
      <?php endif; ?>
    } else { 
      console.log('Hiding brand wrapper - no brands found');
      brandWrapper.style.display = 'none'; 
      resetSelect(brandSelect); 
    }

    // Swap toggle: show only for phone-like categories
    if(isPhoneCategory(catName)) {
      console.log('Showing swap toggle for phone category');
      swapWrapper.style.display = 'block';
    } else {
      console.log('Hiding swap toggle for non-phone category');
      swapWrapper.style.display = 'none';
      const chk = document.querySelector('#available_for_swap');
      if (chk) chk.checked = false;
    }
    
    // Check if category is accessory - show generic accessory specs
    const normalizedCatName = normalizeCategoryName(catName);
    const accessoryCategories = ['accessory', 'accessories'];
    const isAccessoryCategory = accessoryCategories.includes(normalizedCatName);
    
    if (isAccessoryCategory) {
      // For accessories, show generic accessory specs
      console.log('Category is accessory - showing generic accessory specs');
      if (specsWrapper) specsWrapper.style.display = 'block';
      specsContainer.innerHTML = '';
      
      const accessorySpecs = [
        { name: 'type', label: 'Type', type: 'text', placeholder: 'e.g., Charger, Case, Screen Protector' },
        { name: 'color', label: 'Color', type: 'text', placeholder: 'e.g., Black, White, Blue' },
        { name: 'compatibility', label: 'Compatibility', type: 'text', placeholder: 'e.g., iPhone 12, Samsung Galaxy S21' }
      ];
      
      accessorySpecs.forEach(field => {
        const div = document.createElement('div');
        div.classList.add('mb-4');
        const existingValue = existingSpecs && existingSpecs[field.name] ? existingSpecs[field.name] : '';
        div.innerHTML = `
          <label class="block text-sm font-medium text-gray-700 mb-1">${field.label}</label>
          <input type="${field.type}" 
                 name="specs[${field.name}]" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                 placeholder="${field.placeholder}"
                 value="${existingValue || ''}">
        `;
        specsContainer.appendChild(div);
      });
    } else {
      specsContainer.innerHTML = '';
      if (specsWrapper) specsWrapper.style.display = 'none';
    }
    
    return Promise.resolve();
  }

  async function onBrandChange(){
    const brandId = brandSelect.value;
    const brandName = brandSelect.options[brandSelect.selectedIndex].text;
    console.log('Brand changed to:', brandId, 'Name:', brandName);
    
    const specsWrapper = document.getElementById('specsWrapper');
    specsContainer.innerHTML = '';
    
    if(!brandId) {
      if (specsWrapper) specsWrapper.style.display = 'none';
      return;
    }
    
    // CRITICAL: Check if category is accessory - NEVER show phone specs for accessories
    const categoryOption = categorySelect.options[categorySelect.selectedIndex];
    const categoryName = categoryOption ? categoryOption.text.toLowerCase() : '';
    const normalizedCategoryName = categoryName.toLowerCase().trim();
    const accessoryCategories = ['accessory', 'accessories'];
    const isAccessoryCategory = accessoryCategories.includes(normalizedCategoryName);
    
    // Phone-specific spec names that should NEVER appear for accessories
    const phoneSpecNames = ['storage', 'ram', 'battery_health', 'imei', 'model', 'network'];
    
    if (isAccessoryCategory) {
      // For accessories, show generic accessory specs instead of phone specs
      console.log('Category is accessory - showing generic accessory specs instead of phone specs');
      if (specsWrapper) specsWrapper.style.display = 'block';
      specsContainer.innerHTML = '';
      
      // Show generic accessory specs
      const accessorySpecs = [
        { name: 'type', label: 'Type', type: 'text', placeholder: 'e.g., Charger, Case, Screen Protector' },
        { name: 'color', label: 'Color', type: 'text', placeholder: 'e.g., Black, White, Blue' },
        { name: 'compatibility', label: 'Compatibility', type: 'text', placeholder: 'e.g., iPhone 12, Samsung Galaxy S21' }
      ];
      
      accessorySpecs.forEach(field => {
        const div = document.createElement('div');
        div.classList.add('mb-4');
        const existingValue = existingSpecs && existingSpecs[field.name] ? existingSpecs[field.name] : '';
        div.innerHTML = `
          <label class="block text-sm font-medium text-gray-700 mb-1">${field.label}</label>
          <input type="${field.type}" 
                 name="specs[${field.name}]" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                 placeholder="${field.placeholder}"
                 value="${existingValue || ''}">
        `;
        specsContainer.appendChild(div);
      });
      return; // Exit early, don't fetch brand specs
    }
    
    // Check if this is a phone category
    const phoneCategories = ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobiles'];
    const isPhoneCategory = phoneCategories.includes(normalizedCategoryName);
    
    // Try multiple API endpoints
    const apiEndpoints = [
      `<?= BASE_URL_PATH ?>/api/products/brand-specs/${brandId}`,
      `<?= BASE_URL_PATH ?>/api/brands/specs/${brandId}`
    ];
    
    let fields = [];
    for (const endpoint of apiEndpoints) {
      try {
        const response = await fetch(endpoint);
        const data = await response.json();
        console.log('Brand specs loaded from', endpoint, ':', data);
        
        // Handle different response formats
        if (data.success && data.data && Array.isArray(data.data)) {
          fields = data.data;
        } else if (Array.isArray(data)) {
          fields = data;
        } else if (data.data && Array.isArray(data.data)) {
          fields = data.data;
        }
        
        if (fields && fields.length > 0) {
          break;
        }
      } catch (error) {
        console.error('Error loading specs from', endpoint, ':', error);
      }
    }
    
    // Filter out phone-specific specs if category is NOT phone
    if (!isPhoneCategory && fields && fields.length > 0) {
      fields = fields.filter(field => {
        const fieldName = (field.name || '').toLowerCase();
        return !phoneSpecNames.includes(fieldName);
      });
      
      if (fields.length === 0) {
        console.log('All specs were phone-specific and filtered out for non-phone category');
        if (specsWrapper) specsWrapper.style.display = 'none';
        return;
      }
    }
    
    if(!fields || !fields.length) {
      console.log('No brand-specific specs found for', brandName);
      if (specsWrapper) specsWrapper.style.display = 'none';
      return;
    }
    
    // Show specs wrapper
    if (specsWrapper) specsWrapper.style.display = 'block';
    
    // Use the globally stored existing specs
    console.log('Using existing specs for brand specs:', existingSpecs);
    
    fields.forEach(field => {
      const div = document.createElement('div');
      div.classList.add('mb-4');
      
      // Get existing value for this field
      const existingValue = existingSpecs && existingSpecs[field.name] ? existingSpecs[field.name] : '';
      
      let inputHtml = '';
      if (field.type === 'select' && field.options) {
        inputHtml = `
          <select name="specs[${field.name}]" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  ${field.required ? 'required' : ''}
                  title="${field.tooltip || ''}">
            <option value="">Select ${field.label}</option>
            ${field.options.map(option => `<option value="${option}" ${option === existingValue ? 'selected' : ''}>${option}</option>`).join('')}
          </select>
        `;
      } else {
        inputHtml = `
          <input type="${field.type}" 
                 name="specs[${field.name}]" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                 ${field.required ? 'required' : ''}
                 placeholder="${field.placeholder || 'Enter ' + field.label.toLowerCase()}"
                 title="${field.tooltip || ''}"
                 value="${existingValue}">
        `;
      }
      
      div.innerHTML = `
        <label class="block text-sm font-medium text-gray-700 mb-2">
          ${field.label} ${field.required ? '<span class="text-red-500">*</span>' : ''}
        </label>
        ${inputHtml}
      `;
      specsContainer.appendChild(div);
    });
  }

  categorySelect.addEventListener('change', onCategoryChange);
  brandSelect.addEventListener('change', onBrandChange);

  // If edit page has preselected values, trigger load:
  if(categorySelect.value) {
    onCategoryChange().then(() => {
      // After category loads, if brand is already selected, trigger brand change to load specs
      <?php if (isset($product) && !empty($product['brand_id'])): ?>
      const selectedBrandId = '<?= $product['brand_id'] ?>';
      if(selectedBrandId && brandSelect.value == selectedBrandId) {
        // Small delay to ensure brand options are populated
        setTimeout(() => {
          onBrandChange();
        }, 300);
      }
      <?php endif; ?>
    });
  }
});

// Image preview function
function previewImage(input) {
  const preview = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');
  const existingImage = document.getElementById('existingImage');
  
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
      previewImg.src = e.target.result;
      preview.style.display = 'block';
      
      // Hide existing image when new one is selected
      if (existingImage) {
        existingImage.style.display = 'none';
      }
    };
    
    reader.readAsDataURL(input.files[0]);
  } else {
    preview.style.display = 'none';
    
    // Show existing image again if no new file selected
    if (existingImage) {
      existingImage.style.display = 'block';
    }
  }
}
</script>
</div>
