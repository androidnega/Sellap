<?php
// Brand Management Form View
$isEdit = isset($brand) && !empty($brand);
$formTitle = $isEdit ? 'Edit Brand' : 'Add New Brand';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/brands/update/' . $brand['id'] : BASE_URL_PATH . '/dashboard/brands/store';
$selectedCategoryIds = $GLOBALS['selectedCategoryIds'] 
    ?? ($brand['category_ids'] ?? (isset($brand['category_id']) ? [$brand['category_id']] : []));
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/brands" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Brands
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
        <p class="text-gray-600">
            <?= $isEdit ? 'Update brand information' : 'Create a new product brand' ?>
        </p>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border p-6">
        <form method="POST" action="<?= $formAction ?>">
            <div class="space-y-6">
                <!-- Brand Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Brand Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($brand['name'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., Apple, Samsung, Anker"
                           required>
                    <p class="text-sm text-gray-500 mt-1">Enter a unique brand name</p>
                </div>

                <!-- Category Selection (Optional) -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Primary Categories (Optional)
                    </label>
                    <select id="category_id" 
                            name="category_ids[]"
                            multiple
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent min-h-[120px]">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= in_array($category['id'], $selectedCategoryIds ?? [], true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Select one or more categories (hold Ctrl/Cmd to multi-select). The first selection becomes the primary category.</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Brief description of this brand"><?= htmlspecialchars($brand['description'] ?? '') ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Optional description for this brand</p>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/brands" 
                       class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <?= $isEdit ? 'Update Brand' : 'Create Brand' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Brand Examples -->
    <div class="mt-8 bg-blue-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-900 mb-3">Brand Examples</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-medium text-blue-800 mb-2">Phone Brands:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Apple - iPhone series</li>
                    <li>• Samsung - Galaxy series</li>
                    <li>• Tecno - Popular in Ghana</li>
                    <li>• Infinix - Popular in Ghana</li>
                    <li>• Huawei - Android phones</li>
                    <li>• Xiaomi - Budget smartphones</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-blue-800 mb-2">Accessory Brands:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Anker - Premium accessories</li>
                    <li>• Baseus - Quality accessories</li>
                    <li>• Generic - Basic accessories</li>
                    <li>• OEM - Original equipment</li>
                    <li>• Belkin - Apple accessories</li>
                    <li>• Spigen - Phone cases</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
