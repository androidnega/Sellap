<?php
// Technician Repairs List View
?>

<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="<?= BASE_URL_PATH ?>/dashboard" class="text-gray-500 hover:text-gray-700 mr-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-800">My Repairs</h1>
            <span class="ml-3 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                <?= count($repairs) ?> Total
            </span>
        </div>
        <a href="<?= BASE_URL_PATH ?>/dashboard/booking" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>
            New Booking
        </a>
    </div>

    <!-- Status Filter -->
    <div class="mb-6">
        <div class="flex space-x-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= !isset($_GET['status']) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                All
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=pending" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= ($_GET['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Pending
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=in_progress" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= ($_GET['status'] ?? '') === 'in_progress' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                In Progress
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=completed" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= ($_GET['status'] ?? '') === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Completed
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=delivered" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= ($_GET['status'] ?? '') === 'delivered' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Delivered
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=failed" 
               class="px-4 py-2 rounded-lg text-sm font-medium <?= ($_GET['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Failed
            </a>
        </div>
    </div>

    <!-- Repairs Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <?php if (empty($repairs)): ?>
            <div class="p-8 text-center">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No repairs found</h3>
                <p class="text-gray-500 mb-4">Get started by creating your first repair booking.</p>
                <a href="<?= BASE_URL_PATH ?>/dashboard/booking" class="btn btn-primary">Create Booking</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($repairs as $repair): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($repair['customer_name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($repair['customer_contact']) ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php if ($repair['product_name']): ?>
                                            <?= htmlspecialchars($repair['product_name']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-500">Customer's device</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                        <?= htmlspecialchars($repair['issue_description']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_progress' => 'bg-orange-100 text-orange-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'delivered' => 'bg-blue-100 text-blue-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'failed' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusColor = $statusColors[$repair['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColor ?>">
                                        <?= ucfirst(str_replace('_', ' ', $repair['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ₵<?= number_format($repair['total_cost'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($repair['created_at'])) ?>
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
        <?php endif; ?>
    </div>
</div>

