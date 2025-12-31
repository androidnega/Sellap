<?php
// Enhanced product creation form with dynamic category-driven fields
$isEdit = isset($product) && !empty($product);
$formTitle = $isEdit ? 'Edit Product' : 'Add New Product';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/inventory/update/' . $product['id'] : BASE_URL_PATH . '/dashboard/inventory/store';

// Get variables from controller
$categories = $GLOBALS['categories'] ?? [];
$suppliers = $GLOBALS['suppliers'] ?? [];
$prefillData = $GLOBALS['prefill_product_data'] ?? null;

// Ensure categories is an array
if (!is_array($categories)) {
    $categories = [];
}

// Debug: Log if categories are empty (remove in production)
if (empty($categories)) {
    error_log("WARNING: No categories found in GLOBALS for inventory create page");
    // Try to load categories directly as fallback
    try {
        $categoryModel = new \App\Models\Category();
        $categories = $categoryModel->getAll();
        error_log("Fallback: Loaded " . count($categories) . " categories directly from model");
    } catch (\Exception $e) {
        error_log("Error loading categories in fallback: " . $e->getMessage());
    }
}
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
                                if (!empty($categories) && is_array($categories)) {
                                    $uniqueCategories = [];
                                    $categoryCount = 0;
                                    foreach ($categories as $category) {
                                        if (isset($category['id']) && isset($category['name'])) {
                                            // Only show unique category names to avoid duplicates
                                            if (!in_array($category['name'], $uniqueCategories)) {
                                                $uniqueCategories[] = $category['name'];
                                                $selected = (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($category['id']) . '" ' . $selected . '>' .
                                                     htmlspecialchars($category['name']) . '</option>';
                                                $categoryCount++;
                                            }
                                        }
                                    }
                                    // Debug output (remove in production)
                                    if ($categoryCount === 0) {
                                        error_log("WARNING: Categories array is not empty but no valid categories found. Count: " . count($categories));
                                        echo '<!-- DEBUG: Categories array has ' . count($categories) . ' items but none were valid -->';
                                    }
                                } else {
                                    echo '<option value="" disabled>No categories available. Please create categories first.</option>';
                                    error_log("ERROR: Categories array is empty or not an array. Type: " . gettype($categories) . ", Count: " . (is_array($categories) ? count($categories) : 'N/A'));
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
                                Brand <span id="brandRequiredIndicator" class="text-red-500" style="display:none;">*</span>
                            </label>
                            <select id="brandSelect" 
                                    name="brand_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
  // Check if category dropdown is empty and load via API if needed
  const categorySelect = document.getElementById('categorySelect');
  if (categorySelect && categorySelect.options.length <= 1) {
    // Only "Select a category" option exists, try to load via API
    console.log('Category dropdown is empty, attempting to load categories via API...');
    loadCategoriesViaAPI();
  }
  
  async function loadCategoriesViaAPI() {
    try {
      const basePath = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '<?= BASE_URL_PATH ?>');
      const response = await fetch(`${basePath}/api/categories`);
      const data = await response.json();
      
      if (data.success && data.data && data.data.length > 0) {
        console.log('Loaded ' + data.data.length + ' categories via API');
        const uniqueCategories = [];
        data.data.forEach(category => {
          if (category.id && category.name && !uniqueCategories.includes(category.name)) {
            uniqueCategories.push(category.name);
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            categorySelect.appendChild(option);
          }
        });
        console.log('Added ' + uniqueCategories.length + ' categories to dropdown');
      } else {
        console.error('API returned no categories:', data);
      }
    } catch (error) {
      console.error('Error loading categories via API:', error);
    }
  }
  // Store existing specs globally for easy access
  const existingSpecs = <?= isset($product) && !empty($product['specs']) ? json_encode($product['specs']) : 'null' ?>;
  console.log('Loaded existing specs:', existingSpecs);
  console.log('DOM elements initialized. Category select:', categorySelect, 'Brand wrapper:', brandWrapper);
  
  // Verify elements exist before proceeding
  if (!categorySelect) {
    console.error('CRITICAL: categorySelect element not found! Cannot proceed with dynamic form.');
    return;
  }
  if (!brandWrapper) {
    console.error('CRITICAL: brandWrapper element not found! Brand selection will not work.');
  }
  if (!brandSelect) {
    console.error('CRITICAL: brandSelect element not found! Brand selection will not work.');
  }

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

  function updateBrandFieldVisibility(hasBrands, requireBrand = false) {
    if (!brandWrapper || !brandSelect) {
      console.error('updateBrandFieldVisibility: brandWrapper or brandSelect not found!');
      return;
    }
    
    console.log('updateBrandFieldVisibility called:', { hasBrands, requireBrand });
    
    if (hasBrands) {
      // Show brand field - use both display and visibility to ensure it's visible
      brandWrapper.style.display = 'block';
      brandWrapper.style.visibility = 'visible';
      brandSelect.disabled = false;
      
      if (requireBrand) {
        brandSelect.required = true;
        brandSelect.setAttribute('required', 'required');
        brandSelect.setAttribute('aria-required', 'true');
        console.log('Brand is required for this category');
      } else {
        brandSelect.required = false;
        brandSelect.removeAttribute('required');
        brandSelect.removeAttribute('aria-required');
      }
      
      if (brandRequiredIndicator) {
        brandRequiredIndicator.style.display = requireBrand ? 'inline' : 'none';
      }
      
      console.log('✓ Brand field is now visible');
    } else {
      // Hide brand field
      brandWrapper.style.display = 'none';
      brandSelect.disabled = true;
      brandSelect.required = false;
      brandSelect.removeAttribute('required');
      brandSelect.removeAttribute('aria-required');
      brandSelect.value = '';
      
      if (brandRequiredIndicator) {
        brandRequiredIndicator.style.display = 'none';
      }
      
      console.log('Brand field is now hidden');
    }
  }

  // Ensure the brand field starts disabled until we know the category context
  updateBrandFieldVisibility(false);

  async function onCategoryChange(){
    const catId = categorySelect.value;
    console.log('Category changed to:', catId);
    
    // Get category name from the selected option
    const selectedOption = categorySelect.options[categorySelect.selectedIndex];
    const catName = selectedOption ? selectedOption.text : '';
    
    // Fallback: try to get from PHP categories array if available
    let categories = {};
    try {
      const phpCategories = <?= json_encode(!empty($categories) && is_array($categories) ? array_column($categories, 'name', 'id') : []) ?>;
      categories = phpCategories || {};
    } catch(e) {
      console.warn('Could not load categories from PHP:', e);
    }
    
    // Use category name from selected option, or fallback to categories array
    const finalCatName = catName || categories[catId] || '';
    
    console.log('Available categories:', categories);
    console.log('Selected category name:', finalCatName);
    console.log('Category ID:', catId);
    console.log('Normalized category name:', normalizeCategoryName(finalCatName));
    
    const specsWrapper = document.getElementById('specsWrapper');
    
    if(!catId){
      subcategoryWrapper.style.display = 'none';
      resetSelect(subcategorySelect);
      updateBrandFieldVisibility(false);
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

    // Load brands for this category
    const brandUrl = `${basePath}/api/brands/by-category/${catId}`;
    console.log('Fetching brands from URL:', brandUrl);
    const brands = await fetchJson(brandUrl);
    console.log('Brands loaded:', brands);
    console.log('Brands count:', brands ? brands.length : 0);
    
    const requiresBrand = isPhoneCategory(finalCatName);
    console.log('Brand required for this category?', requiresBrand);
    
    if(brands && brands.length > 0){ 
      console.log('✓ Brands found - showing brand dropdown');
      if (!brandSelect) {
        console.error('ERROR: brandSelect element not found!');
      } else {
        populateSelect(brandSelect, brands, 'Select brand'); 
      }
      updateBrandFieldVisibility(true, requiresBrand);
      console.log('Brand wrapper should now be visible');
      
      // Verify brand wrapper is visible
      setTimeout(() => {
        if (brandWrapper) {
          console.log('Brand wrapper display after update:', brandWrapper.style.display);
          if (brandWrapper.style.display === 'none') {
            console.error('WARNING: Brand wrapper is still hidden! Forcing display...');
            brandWrapper.style.display = 'block';
          }
        }
      }, 100);
      
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
      resetSelect(brandSelect); 
      updateBrandFieldVisibility(false);
    }

    // Swap toggle: show only for phone-like categories
    if(isPhoneCategory(finalCatName)) {
      console.log('Showing swap toggle for phone category');
      if (swapWrapper) swapWrapper.style.display = 'block';
    } else {
      console.log('Hiding swap toggle for non-phone category');
      if (swapWrapper) swapWrapper.style.display = 'none';
      const chk = document.querySelector('#available_for_swap');
      if (chk) chk.checked = false;
    }
    
    // Check if category is accessory - show generic accessory specs
    const normalizedCatName = normalizeCategoryName(finalCatName);
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
    const selectedOption = brandSelect.selectedIndex >= 0 ? brandSelect.options[brandSelect.selectedIndex] : null;
    const brandName = selectedOption ? selectedOption.text : '';
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
    
    // Get category ID and name for API call
    const categoryId = categorySelect.value;
    const categoryNameParam = categoryOption ? encodeURIComponent(categoryOption.text) : '';
    
    // Build API endpoints with category parameters
    const baseEndpoints = [
      `<?= BASE_URL_PATH ?>/api/products/brand-specs/${brandId}`,
      `<?= BASE_URL_PATH ?>/api/brands/specs/${brandId}`
    ];
    
    // Add category parameters to endpoints
    const apiEndpoints = baseEndpoints.map(endpoint => {
      const params = new URLSearchParams();
      if (categoryId) params.append('category_id', categoryId);
      if (categoryNameParam) params.append('category_name', categoryNameParam);
      const queryString = params.toString();
      return queryString ? `${endpoint}?${queryString}` : endpoint;
    });
    
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

  // Attach event listeners
  if (categorySelect) {
    categorySelect.addEventListener('change', function() {
      console.log('Category change event fired!');
      onCategoryChange();
    });
    console.log('Category change event listener attached');
  } else {
    console.error('Cannot attach category change listener - element not found');
  }
  
  if (brandSelect) {
    brandSelect.addEventListener('change', function() {
      console.log('Brand change event fired!');
      onBrandChange();
    });
    console.log('Brand change event listener attached');
  } else {
    console.error('Cannot attach brand change listener - element not found');
  }

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
