<?php
// Technician Dashboard View
?>

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Technician Dashboard</h1>
        <p class="text-gray-600 mt-2">Manage your repairs and bookings</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-2xl font-bold text-gray-900"><?= count($pendingRepairs) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                    <i class="fas fa-tools text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">In Progress</p>
                    <p class="text-2xl font-bold text-gray-900"><?= count($inProgressRepairs) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Completed</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $completedCount ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">₵<?= number_format($totalRevenue, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="<?= BASE_URL_PATH ?>/dashboard/technician/booking" class="flex items-center p-4 border rounded-lg hover:bg-gray-50 transition">
                <div class="p-2 bg-blue-100 rounded-lg mr-3">
                    <i class="fas fa-plus text-blue-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">New Booking</h3>
                    <p class="text-sm text-gray-600">Create a new repair booking</p>
                </div>
            </a>
            
            <a href="<?= BASE_URL_PATH ?>/dashboard/technician/repairs" class="flex items-center p-4 border rounded-lg hover:bg-gray-50 transition">
                <div class="p-2 bg-green-100 rounded-lg mr-3">
                    <i class="fas fa-list text-green-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">My Repairs</h3>
                    <p class="text-sm text-gray-600">View all my repairs</p>
                </div>
            </a>
            
            <a href="<?= BASE_URL_PATH ?>/dashboard/technician/repairs?status=pending" class="flex items-center p-4 border rounded-lg hover:bg-gray-50 transition">
                <div class="p-2 bg-yellow-100 rounded-lg mr-3">
                    <i class="fas fa-exclamation-circle text-yellow-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">Pending Repairs</h3>
                    <p class="text-sm text-gray-600"><?= count($pendingRepairs) ?> waiting</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Pending Repairs -->
    <?php if (!empty($pendingRepairs)): ?>
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold text-gray-800">Pending Repairs</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($pendingRepairs, 0, 5) as $repair): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($repair['customer_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($repair['customer_contact']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($repair['product_name'] ?? 'Customer Device') ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                                    <?= htmlspecialchars($repair['issue_description']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($pendingRepairs) > 5): ?>
                <div class="p-4 text-center border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/technician/repairs?status=pending" class="text-blue-600 hover:text-blue-900">
                        View all <?= count($pendingRepairs) ?> pending repairs →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- In Progress Repairs -->
    <?php if (!empty($inProgressRepairs)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold text-gray-800">In Progress</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($inProgressRepairs, 0, 5) as $repair): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($repair['customer_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($repair['customer_contact']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($repair['product_name'] ?? 'Customer Device') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ₵<?= number_format($repair['total_cost'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($inProgressRepairs) > 5): ?>
                <div class="p-4 text-center border-t">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/technician/repairs?status=in_progress" class="text-blue-600 hover:text-blue-900">
                        View all <?= count($inProgressRepairs) ?> in-progress repairs →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

