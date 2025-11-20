<?php
// Suppliers Index View
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Supplier Management</h2>
        <p class="text-gray-600">Track your suppliers and vendor relationships (Tracking Only)</p>
    </div>
    
    <div class="flex justify-between items-center mb-4">
        <div class="flex gap-4 items-center">
            <form method="GET" action="<?= BASE_URL_PATH ?>/dashboard/suppliers" class="flex gap-2">
                <input type="text" name="search" placeholder="Search suppliers..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select name="status" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">
            <i class="fas fa-plus"></i> Add New Supplier
        </a>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($suppliers)): ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </div>
                                    <?php if (!empty($supplier['contact_person'])): ?>
                                        <div class="text-xs text-gray-500">
                                            Contact: <?= htmlspecialchars($supplier['contact_person']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($supplier['email'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($supplier['phone'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($supplier['total_orders'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($supplier['total_products'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'inactive' => 'bg-gray-100 text-gray-800',
                                        'suspended' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusColor = $statusColors[$supplier['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusColor ?>">
                                        <?= ucfirst($supplier['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/view/<?= $supplier['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/edit/<?= $supplier['id'] ?>" 
                                           class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/delete/<?= $supplier['id'] ?>" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Are you sure you want to delete this supplier?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No suppliers found. <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/create" class="text-blue-600 hover:text-blue-800">Create your first supplier</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?= \App\Helpers\PaginationHelper::render($pagination) ?>
</div>

