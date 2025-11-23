<?php
// Brand Management Index View
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Brand Management</h2>
        <p class="text-gray-600">Manage product brands and their settings</p>
    </div>
    
    <div class="flex justify-between items-center mb-4">
        <p class="text-gray-600">View and manage all product brands</p>
        <a href="<?= BASE_URL_PATH ?>/dashboard/brands/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">+ Add New Brand</a>
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

    <div class="bg-white rounded-lg shadow-sm border">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($brands)): ?>
                        <?php foreach ($brands as $brand): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($brand['id']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php
                                            $categoryNames = $brand['category_names'] ?? [];
                                            $categoryLabel = !empty($categoryNames)
                                                ? implode(', ', $categoryNames)
                                                : ($brand['category_name'] ?? 'General');
                                        ?>
                                        <?= htmlspecialchars($categoryLabel) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($brand['description'] ?? 'No description') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($brand['product_count'] ?? 0) ?> products
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/brands/edit/<?= $brand['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/brands/delete/<?= $brand['id'] ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to delete this brand?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No brands found. <a href="<?= BASE_URL_PATH ?>/dashboard/brands/create" class="text-blue-600 hover:text-blue-800">Create your first brand</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 text-sm text-gray-600">
        <p><strong>Note:</strong> Brands with linked products cannot be deleted. This prevents data loss.</p>
    </div>

    <!-- Pagination -->
    <?= \App\Helpers\PaginationHelper::render($pagination) ?>
</div>
</div>
