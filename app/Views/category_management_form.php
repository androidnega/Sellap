<?php
// Category Management Form View
$isEdit = isset($category) && !empty($category);
$formTitle = $isEdit ? 'Edit Category' : 'Add New Category';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/categories/update/' . $category['id'] : BASE_URL_PATH . '/dashboard/categories/store';
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/categories" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Categories
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
        <p class="text-gray-600">
            <?= $isEdit ? 'Update category information' : 'Create a new product category' ?>
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
                <!-- Category Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($category['name'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., Phone, Tablet, Accessory"
                           required>
                    <p class="text-sm text-gray-500 mt-1">Enter a unique category name</p>
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
                              placeholder="Brief description of this category"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Optional description for this category</p>
                </div>

                <!-- Status -->
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1"
                               <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-700">
                            Active Category
                        </label>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Inactive categories won't appear in product forms</p>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/categories" 
                       class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <?= $isEdit ? 'Update Category' : 'Create Category' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Category Examples -->
    <div class="mt-8 bg-blue-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-900 mb-3">Category Examples</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-medium text-blue-800 mb-2">Main Categories:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Phone - Mobile phones and smartphones</li>
                    <li>• Tablet - Tablets and iPads</li>
                    <li>• Accessory - Phone accessories</li>
                    <li>• Repair Parts - For repairs</li>
                    <li>• Wearables - Smartwatches, etc.</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-blue-800 mb-2">Usage:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Categories determine product form behavior</li>
                    <li>• Phones/Tablets show brand dropdowns</li>
                    <li>• Accessories show subcategory dropdowns</li>
                    <li>• Only phones can be "Available for Swap"</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
