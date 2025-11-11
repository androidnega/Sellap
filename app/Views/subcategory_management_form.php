<?php
// Subcategory Management Form View
$isEdit = isset($subcategory) && !empty($subcategory);
$formTitle = $isEdit ? 'Edit Subcategory' : 'Add New Subcategory';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/subcategories/update/' . $subcategory['id'] : BASE_URL_PATH . '/dashboard/subcategories/store';
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/subcategories" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Subcategories
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
        <p class="text-gray-600">
            <?= $isEdit ? 'Update subcategory information' : 'Create a new product subcategory' ?>
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
                <!-- Category Selection -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select id="category_id" 
                            name="category_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= ($subcategory['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Choose the parent category for this subcategory</p>
                </div>

                <!-- Subcategory Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Subcategory Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($subcategory['name'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., Charger, Battery, Screen Protector"
                           required>
                    <p class="text-sm text-gray-500 mt-1">Enter a unique subcategory name</p>
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
                              placeholder="Brief description of this subcategory"><?= htmlspecialchars($subcategory['description'] ?? '') ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Optional description for this subcategory</p>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/subcategories" 
                       class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <?= $isEdit ? 'Update Subcategory' : 'Create Subcategory' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Subcategory Examples -->
    <div class="mt-8 bg-blue-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-900 mb-3">Subcategory Examples</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-medium text-blue-800 mb-2">For Accessories:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Charger - Device charging accessories</li>
                    <li>• Battery - Power backup units</li>
                    <li>• Earbud - Audio accessories</li>
                    <li>• Screen Protector - Display protection</li>
                    <li>• Case - Device protection cases</li>
                    <li>• Power Bank - Portable chargers</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium text-blue-800 mb-2">For Repair Parts:</h4>
                <ul class="space-y-1 text-blue-700">
                    <li>• Display - LCD screens and panels</li>
                    <li>• Motherboard - Main circuit boards</li>
                    <li>• Camera Module - Camera components</li>
                    <li>• Charging Port - USB/charging connectors</li>
                    <li>• Battery - Replacement batteries</li>
                    <li>• Speaker - Audio components</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
