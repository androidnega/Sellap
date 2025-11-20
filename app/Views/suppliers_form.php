<?php
// Supplier Form View
$isEdit = isset($supplier) && !empty($supplier);
$formTitle = $isEdit ? 'Edit Supplier' : 'Add New Supplier';
$formAction = $isEdit ? BASE_URL_PATH . '/dashboard/suppliers/update/' . $supplier['id'] : BASE_URL_PATH . '/dashboard/suppliers/store';
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Suppliers
        </a>
        <h2 class="text-3xl font-bold text-gray-800"><?= $formTitle ?></h2>
        <p class="text-gray-600">
            <?= $isEdit ? 'Update supplier information' : 'Add a new supplier to your system' ?>
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
                <!-- Basic Information -->
                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Supplier Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Supplier Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($supplier['name'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>

                        <!-- Contact Person -->
                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">
                                Contact Person
                            </label>
                            <input type="text" 
                                   id="contact_person" 
                                   name="contact_person" 
                                   value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Contact Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($supplier['email'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone
                            </label>
                            <input type="text" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Alternate Phone -->
                        <div>
                            <label for="alternate_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Alternate Phone
                            </label>
                            <input type="text" 
                                   id="alternate_phone" 
                                   name="alternate_phone" 
                                   value="<?= htmlspecialchars($supplier['alternate_phone'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Address Information</h3>
                    
                    <div class="mb-4">
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                            Street Address
                        </label>
                        <textarea id="address" 
                                  name="address" 
                                  rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- City -->
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                                City
                            </label>
                            <input type="text" 
                                   id="city" 
                                   name="city" 
                                   value="<?= htmlspecialchars($supplier['city'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- State -->
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700 mb-2">
                                State/Province
                            </label>
                            <input type="text" 
                                   id="state" 
                                   name="state" 
                                   value="<?= htmlspecialchars($supplier['state'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Postal Code
                            </label>
                            <input type="text" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   value="<?= htmlspecialchars($supplier['postal_code'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                            Country
                        </label>
                        <input type="text" 
                               id="country" 
                               name="country" 
                               value="<?= htmlspecialchars($supplier['country'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Business Information -->
                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Business Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Tax ID -->
                        <div>
                            <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Tax ID / VAT Number
                            </label>
                            <input type="text" 
                                   id="tax_id" 
                                   name="tax_id" 
                                   value="<?= htmlspecialchars($supplier['tax_id'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Payment Terms -->
                        <div>
                            <label for="payment_terms" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Terms
                            </label>
                            <input type="text" 
                                   id="payment_terms" 
                                   name="payment_terms" 
                                   value="<?= htmlspecialchars($supplier['payment_terms'] ?? '') ?>"
                                   placeholder="e.g., Net 30, COD, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Credit Limit -->
                        <div>
                            <label for="credit_limit" class="block text-sm font-medium text-gray-700 mb-2">
                                Credit Limit
                            </label>
                            <input type="number" 
                                   id="credit_limit" 
                                   name="credit_limit" 
                                   value="<?= htmlspecialchars($supplier['credit_limit'] ?? 0) ?>"
                                   step="0.01"
                                   min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Status
                            </label>
                            <select id="status" 
                                    name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="active" <?= ($supplier['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($supplier['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= ($supplier['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Additional notes about this supplier"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers" 
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?= $isEdit ? 'Update Supplier' : 'Create Supplier' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

