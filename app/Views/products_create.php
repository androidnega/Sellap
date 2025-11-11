<?php
// Enhanced Product Creation Form with User-Friendly Features
?>

<div class="max-w-6xl mx-auto">
    <!-- Header Section -->
    <div class="flex items-center mb-8">
        <a href="/products" class="text-gray-500 hover:text-gray-700 mr-4 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Add New Product</h1>
            <p class="text-gray-600 mt-1">Create a new product with comprehensive details and specifications</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form id="addProductForm" method="POST" action="/products/store" enctype="multipart/form-data" class="space-y-8">
        <!-- Basic Information Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Basic Information</h2>
                    <p class="text-sm text-gray-600">Essential product details and identification</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Product Name -->
                <div class="lg:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Product Name <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <div class="relative">
                        <input type="text" id="name" name="name" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="E.g. iPhone 14 Pro Max / Samsung S22 Ultra / Anker Charger">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Enter a descriptive name that clearly identifies the product</p>
                </div>

                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Category <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <select id="category_id" name="category_id" required 
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="">Select product type (Phone, Tablet, Accessory, etc.)</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['id']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Choose the main product category</p>
                </div>

                <!-- SKU/Barcode -->
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                        SKU / Barcode <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <div class="relative">
                        <input type="text" id="sku" name="sku" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Enter unique SKU or scan barcode">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Unique identifier for inventory tracking</p>
                </div>

                <!-- Model Name -->
                <div>
                    <label for="model_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Model Name <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <input type="text" id="model_name" name="model_name" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           placeholder="E.g. A2890 / SM-G998B / Baseus Rapid PD">
                    <p class="mt-1 text-xs text-gray-500">Specific model identifier or part number</p>
                </div>

                <!-- Brand Container (Dynamic) -->
                <div id="brandContainer" style="display:none;">
                    <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Brand <span id="brandRequired" class="text-red-500" style="display:none;">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Conditional)</span>
                    </label>
                    <select id="brand_id" name="brand_id" 
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="">Select brand (Apple, Samsung, etc.)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Required for phones, optional for other categories</p>
                </div>

                <!-- Subcategory Container (Dynamic) -->
                <div id="subcategoryContainer" style="display:none;">
                    <label for="subcategory_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Subcategory
                        <span class="ml-2 text-gray-400 text-xs">(Auto if category=Accessory)</span>
                    </label>
                    <select id="subcategory_id" name="subcategory_id" 
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="">E.g. Chargers, Batteries, Earbuds…</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">More specific product classification</p>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                    <span class="ml-2 text-gray-400 text-xs">(Optional)</span>
                </label>
                <textarea id="description" name="description" rows="4" 
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                          placeholder="Describe product, specs, or features"></textarea>
                <p class="mt-1 text-xs text-gray-500">Detailed product description and key features</p>
            </div>
        </div>

        <!-- Pricing & Inventory Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Pricing & Inventory</h2>
                    <p class="text-sm text-gray-600">Cost, selling price, and stock management</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Cost Price -->
                <div>
                    <label for="cost_price" class="block text-sm font-medium text-gray-700 mb-2">
                        Cost Price (₵) <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₵</span>
                        </div>
                        <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-lg pl-8 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0.00">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Enter product purchase cost (₵)</p>
                </div>
                
                <!-- Selling Price -->
                <div>
                    <label for="selling_price" class="block text-sm font-medium text-gray-700 mb-2">
                        Selling Price (₵) <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₵</span>
                        </div>
                        <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" required
                               class="w-full border border-gray-300 rounded-lg pl-8 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0.00">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Enter selling price (₵)</p>
                </div>
                
                <!-- Quantity/Stock -->
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                        Quantity / Stock <span class="text-red-500">*</span>
                        <span class="ml-2 text-gray-400 text-xs">(Required)</span>
                    </label>
                    <div class="relative">
                        <input type="number" id="quantity" name="quantity" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Enter number of units available</p>
                </div>
            </div>
        </div>

        <!-- Product Details Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Product Details</h2>
                    <p class="text-sm text-gray-600">Additional information and specifications</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Image Upload -->
                <div class="lg:col-span-2">
                    <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                        Image Upload
                        <span class="ml-2 text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="image" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                    <span>Upload a file</span>
                                    <input id="image" name="image" type="file" accept="image/*" class="sr-only">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Upload clear image of product</p>
                </div>

                <!-- Supplier Name -->
                <div>
                    <label for="supplier" class="block text-sm font-medium text-gray-700 mb-2">
                        Supplier Name
                        <span class="ml-2 text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <input type="text" id="supplier" name="supplier" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           placeholder="E.g. Kwame Tech Supplies / MTN Device Hub">
                    <p class="mt-1 text-xs text-gray-500">Product supplier or vendor name</p>
                </div>

                <!-- Weight -->
                <div>
                    <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">
                        Weight / Dimensions
                        <span class="ml-2 text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <input type="text" id="weight" name="weight" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           placeholder="E.g. 250g / 6.7-inch display">
                    <p class="mt-1 text-xs text-gray-500">Product weight and physical dimensions</p>
                </div>
            </div>
        </div>

        <!-- Dynamic Specifications Container -->
        <div id="specsContainer" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8" style="display:none;">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Product Specifications</h2>
                    <p class="text-sm text-gray-600">Technical details and specifications</p>
                </div>
            </div>
            <div id="dynamicSpecs"></div>
        </div>

        <!-- Swap Options Section (Only for Phones) -->
        <div id="swapContainer" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8" style="display:none;">
            <div class="flex items-center mb-6">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Swap Options</h2>
                    <p class="text-sm text-gray-600">Configure swap availability for phone products</p>
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Swap Availability</h3>
                        <div class="mt-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="availableForSwap" name="available_for_swap" value="1" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="availableForSwap" class="ml-3 text-sm text-yellow-700">
                                    <span class="font-medium">Available for Swap</span>
                                    <span class="block text-xs text-yellow-600 mt-1">Tick if this phone can be swapped with customers</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    <span class="text-red-500">*</span> Required fields
                </div>
                <div class="flex space-x-4">
                    <a href="/products" 
                       class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all font-medium">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Product
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Enhanced Dynamic Product Form with Improved UX
document.addEventListener('DOMContentLoaded', () => {
    const categorySelect = document.getElementById('category_id');
    const brandContainer = document.getElementById('brandContainer');
    const brandSelect = document.getElementById('brand_id');
    const brandRequired = document.getElementById('brandRequired');
    const subcategoryContainer = document.getElementById('subcategoryContainer');
    const subcategorySelect = document.getElementById('subcategory_id');
    const specsContainer = document.getElementById('specsContainer');
    const dynamicSpecs = document.getElementById('dynamicSpecs');
    const swapContainer = document.getElementById('swapContainer');

    // Form validation and real-time feedback
    function validateField(field, isValid, message = '') {
        const input = field.querySelector('input, select, textarea');
        const helpText = field.querySelector('.text-xs');
        
        if (isValid) {
            input.classList.remove('border-red-300', 'focus:ring-red-500');
            input.classList.add('border-gray-300', 'focus:ring-blue-500');
            if (helpText) helpText.classList.remove('text-red-500');
        } else {
            input.classList.remove('border-gray-300', 'focus:ring-blue-500');
            input.classList.add('border-red-300', 'focus:ring-red-500');
            if (helpText) {
                helpText.classList.add('text-red-500');
                helpText.textContent = message;
            }
        }
    }

    // Real-time validation
    function setupFieldValidation() {
        const requiredFields = ['name', 'category_id', 'sku', 'model_name', 'cost_price', 'selling_price', 'quantity'];
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('blur', () => {
                    const value = field.value.trim();
                    const isValid = value !== '';
                    const fieldContainer = field.closest('div');
                    validateField(fieldContainer, isValid, 'This field is required');
                });
            }
        });

        // Price validation
        const priceFields = ['cost_price', 'selling_price'];
        priceFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('input', () => {
                    const value = parseFloat(field.value);
                    const isValid = !isNaN(value) && value >= 0;
                    const fieldContainer = field.closest('div');
                    validateField(fieldContainer, isValid, 'Please enter a valid price');
                });
            }
        });
    }

    // Category change handler with enhanced logic
    categorySelect.addEventListener('change', () => {
        const categoryId = categorySelect.value;
        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = categoryOption ? categoryOption.text.toLowerCase() : '';

        // Reset form sections with smooth transitions
        [brandContainer, subcategoryContainer, specsContainer, swapContainer].forEach(container => {
            if (container) {
                container.style.display = 'none';
                container.style.opacity = '0';
            }
        });
        
        dynamicSpecs.innerHTML = '';
        brandSelect.innerHTML = '<option value="">Select brand (Apple, Samsung, etc.)</option>';
        subcategorySelect.innerHTML = '<option value="">E.g. Chargers, Batteries, Earbuds…</option>';

        if (!categoryId) return;

        // Show brand dropdown for categories that have brands
        const brandCategories = ['1', '4', '2', '5']; // Phone, Tablet, Accessory, Repair Parts
        if (brandCategories.includes(categoryId)) {
            brandContainer.style.display = 'block';
            setTimeout(() => brandContainer.style.opacity = '1', 100);

            // Make brand required for phones
            if (categoryId === '1') {
                brandRequired.style.display = 'inline';
                brandSelect.required = true;
            } else {
                brandRequired.style.display = 'none';
                brandSelect.required = false;
            }

            // Fetch brands dynamically
            fetch(`/api/products/brands/${categoryId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        brandSelect.innerHTML = '<option value="">Select brand (Apple, Samsung, etc.)</option>';
                        data.data.forEach(brand => {
                            const opt = document.createElement('option');
                            opt.value = brand.id;
                            opt.textContent = brand.name;
                            brandSelect.appendChild(opt);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading brands:', error);
                    showNotification('Error loading brands. Please try again.', 'error');
                });
        }

        // Show subcategory dropdown for Accessories, Repair Parts, and Wearables
        const subcategoryCategories = ['2', '5', '6'];
        if (subcategoryCategories.includes(categoryId)) {
            subcategoryContainer.style.display = 'block';
            setTimeout(() => subcategoryContainer.style.opacity = '1', 100);

            // Fetch subcategories dynamically
            fetch(`/api/products/subcategories/${categoryId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        subcategorySelect.innerHTML = '<option value="">E.g. Chargers, Batteries, Earbuds…</option>';
                        data.data.forEach(subcategory => {
                            const opt = document.createElement('option');
                            opt.value = subcategory.id;
                            opt.textContent = subcategory.name;
                            subcategorySelect.appendChild(opt);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading subcategories:', error);
                    showNotification('Error loading subcategories. Please try again.', 'error');
                });
        }

        // Show swap option only for Phone category
        if (categoryId === '1') {
            swapContainer.style.display = 'block';
            setTimeout(() => swapContainer.style.opacity = '1', 100);
        }

        // Show generic specs for categories without brand-specific specs
        if (categoryId && !['1', '4'].includes(categoryId)) {
            showGenericSpecs(categoryName);
        }
    });

    // Brand change handler with enhanced specs loading
    brandSelect.addEventListener('change', () => {
        const brandId = brandSelect.value;
        if (!brandId) {
            specsContainer.style.display = 'none';
            return;
        }

        // Show loading state
        specsContainer.style.display = 'block';
        dynamicSpecs.innerHTML = '<div class="text-center py-4"><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div><p class="mt-2 text-sm text-gray-500">Loading specifications...</p></div>';

        // Fetch brand-specific specs
        fetch(`/api/products/brand-specs/${brandId}`)
            .then(res => res.json())
            .then(data => {
                dynamicSpecs.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(field => {
                        const div = document.createElement('div');
                        div.classList.add('mb-6');
                        
                        const label = document.createElement('label');
                        label.className = 'block text-sm font-medium text-gray-700 mb-2';
                        label.textContent = field.label;
                        if (field.required) {
                            const required = document.createElement('span');
                            required.className = 'text-red-500 ml-1';
                            required.textContent = '*';
                            label.appendChild(required);
                        }
                        
                        let input;
                        if (field.type === 'select') {
                            input = document.createElement('select');
                            input.className = 'w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all';
                            input.name = `specs[${field.name}]`;
                            if (field.required) input.required = true;
                            
                            const defaultOption = document.createElement('option');
                            defaultOption.value = '';
                            defaultOption.textContent = `Select ${field.label}`;
                            input.appendChild(defaultOption);
                            
                            if (field.options) {
                                field.options.forEach(option => {
                                    const opt = document.createElement('option');
                                    opt.value = option;
                                    opt.textContent = option;
                                    input.appendChild(opt);
                                });
                            }
                        } else if (field.type === 'textarea') {
                            input = document.createElement('textarea');
                            input.className = 'w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none';
                            input.name = `specs[${field.name}]`;
                            input.rows = 3;
                            if (field.placeholder) input.placeholder = field.placeholder;
                            if (field.required) input.required = true;
                        } else {
                            input = document.createElement('input');
                            input.type = field.type;
                            input.className = 'w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all';
                            input.name = `specs[${field.name}]`;
                            if (field.placeholder) input.placeholder = field.placeholder;
                            if (field.required) input.required = true;
                        }
                        
                        const helpText = document.createElement('p');
                        helpText.className = 'mt-1 text-xs text-gray-500';
                        helpText.textContent = field.help || `Enter ${field.label.toLowerCase()}`;
                        
                        div.appendChild(label);
                        div.appendChild(input);
                        div.appendChild(helpText);
                        dynamicSpecs.appendChild(div);
                    });
                } else {
                    dynamicSpecs.innerHTML = '<p class="text-gray-500 text-center py-4">No specific specifications available for this brand.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading brand specs:', error);
                dynamicSpecs.innerHTML = '<p class="text-red-500 text-center py-4">Error loading specifications. Please try again.</p>';
                showNotification('Error loading specifications. Please try again.', 'error');
            });
    });

    // Show generic specs for non-phone categories
    function showGenericSpecs(categoryName) {
        specsContainer.style.display = 'block';
        dynamicSpecs.innerHTML = '';

        let fields = [];
        
        if (categoryName.includes('accessory')) {
            fields = [
                { name: 'material', label: 'Material', type: 'text', placeholder: 'e.g. Plastic, Metal, Leather', help: 'Primary material used in the accessory' },
                { name: 'color', label: 'Color', type: 'text', placeholder: 'e.g. Black, White, Blue', help: 'Available color options' },
                { name: 'compatibility', label: 'Compatibility', type: 'text', placeholder: 'e.g. iPhone 12+, Samsung Galaxy S21+', help: 'Device compatibility information' }
            ];
        } else if (categoryName.includes('repair') || categoryName.includes('part')) {
            fields = [
                { name: 'part_type', label: 'Part Type', type: 'text', placeholder: 'e.g. Screen, Battery, Charging Port', required: true, help: 'Type of repair part' },
                { name: 'compatible_models', label: 'Compatible Models', type: 'text', placeholder: 'e.g. iPhone 12, iPhone 13', help: 'Models this part is compatible with' },
                { name: 'condition', label: 'Condition', type: 'select', options: ['New', 'Refurbished', 'Used'], help: 'Condition of the part' }
            ];
        }

        if (fields.length > 0) {
            fields.forEach(field => {
                const div = document.createElement('div');
                div.classList.add('mb-6');
                
                const label = document.createElement('label');
                label.className = 'block text-sm font-medium text-gray-700 mb-2';
                label.textContent = field.label;
                if (field.required) {
                    const required = document.createElement('span');
                    required.className = 'text-red-500 ml-1';
                    required.textContent = '*';
                    label.appendChild(required);
                }
                
                let input;
                if (field.type === 'select') {
                    input = document.createElement('select');
                    input.className = 'w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all';
                    input.name = `specs[${field.name}]`;
                    if (field.required) input.required = true;
                    
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = `Select ${field.label}`;
                    input.appendChild(defaultOption);
                    
                    if (field.options) {
                        field.options.forEach(option => {
                            const opt = document.createElement('option');
                            opt.value = option;
                            opt.textContent = option;
                            input.appendChild(opt);
                        });
                    }
                } else {
                    input = document.createElement('input');
                    input.type = field.type;
                    input.className = 'w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all';
                    input.name = `specs[${field.name}]`;
                    if (field.placeholder) input.placeholder = field.placeholder;
                    if (field.required) input.required = true;
                }
                
                const helpText = document.createElement('p');
                helpText.className = 'mt-1 text-xs text-gray-500';
                helpText.textContent = field.help || `Enter ${field.label.toLowerCase()}`;
                
                div.appendChild(label);
                div.appendChild(input);
                div.appendChild(helpText);
                dynamicSpecs.appendChild(div);
            });
        } else {
            dynamicSpecs.innerHTML = '<p class="text-gray-500 text-center py-4">No additional specifications required for this category.</p>';
        }
    }

    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
            type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 
            type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
            'bg-blue-100 border border-blue-400 text-blue-700'
        }`;
        notification.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        ${type === 'error' ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />' :
                        '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'}
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">${message}</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Initialize form validation
    setupFieldValidation();

    // Form submission validation
    document.getElementById('addProductForm').addEventListener('submit', (e) => {
        const requiredFields = ['name', 'category_id', 'sku', 'model_name', 'cost_price', 'selling_price', 'quantity'];
        let isValid = true;
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field && !field.value.trim()) {
                isValid = false;
                const fieldContainer = field.closest('div');
                validateField(fieldContainer, false, 'This field is required');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Please fill in all required fields.', 'error');
        }
    });
});
</script>
