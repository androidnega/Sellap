<?php
// Repair list view for managers and technicians
?>

<div class="w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div class="flex items-center flex-wrap gap-3">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Repairs</h1>
            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full whitespace-nowrap">
                <?= count($repairs) ?> Total
            </span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/reports" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Reports
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Repair
            </a>
        </div>
    </div>

    <!-- Status Filter -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= !isset($_GET['status']) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                All
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=pending" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Pending
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=in_progress" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'in_progress' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                In Progress
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=completed" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Completed
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=delivered" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'delivered' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Delivered
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=failed" class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors <?= ($_GET['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Failed
            </a>
        </div>
    </div>

    <!-- Repairs Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <?php if (empty($repairs)): ?>
            <div class="p-6 sm:p-8 text-center">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No repairs found</h3>
                <p class="text-gray-500 mb-4">Get started by creating your first repair record.</p>
                <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    Create Repair
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                    Device
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                    Issue
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                    Payment
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cost
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                    Date
                                </th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($repairs as $repair): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($repair['customer_name']) ?>
                                            </div>
                                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                                <?= htmlspecialchars($repair['customer_contact']) ?>
                                            </div>
                                            <!-- Mobile: Show device and issue info -->
                                            <div class="sm:hidden mt-2 space-y-1">
                                                <div class="text-xs text-gray-600">
                                                    <span class="font-medium">Device:</span> 
                                                    <?php if ($repair['product_name']): ?>
                                                        <?= htmlspecialchars($repair['product_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">Customer's device</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-600 truncate">
                                                    <span class="font-medium">Issue:</span> <?= htmlspecialchars($repair['issue_description']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                        <div class="text-sm text-gray-900">
                                            <?php if ($repair['product_name']): ?>
                                                <?= htmlspecialchars($repair['product_name']) ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">Customer's device</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 hidden md:table-cell">
                                        <div class="text-sm text-gray-900 max-w-xs truncate">
                                            <?= htmlspecialchars($repair['issue_description']) ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
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
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <?php
                                        // Delivered and completed repairs are always considered paid
                                        $displayPaymentStatus = $repair['payment_status'];
                                        if (in_array($repair['status'], ['delivered', 'completed'])) {
                                            $displayPaymentStatus = 'paid';
                                        }
                                        
                                        $paymentColors = [
                                            'unpaid' => 'bg-red-100 text-red-800',
                                            'partial' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800'
                                        ];
                                        $paymentColor = $paymentColors[$displayPaymentStatus] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $paymentColor ?>">
                                            <?= ucfirst($displayPaymentStatus) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        ₵<?= number_format($repair['total_cost'], 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                        <?= date('M j, Y', strtotime($repair['created_at'])) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex flex-col sm:flex-row gap-1 sm:gap-2">
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                                View
                                            </a>
                                            <?php if ($repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled'): ?>
                                                <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>/edit" class="text-indigo-600 hover:text-indigo-900">
                                                    Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <?php if (!empty($repairs)): ?>
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            $totalCost = array_sum(array_column($repairs, 'total_cost'));
            $completedCount = count(array_filter($repairs, fn($r) => $r['status'] === 'completed'));
            $pendingCount = count(array_filter($repairs, fn($r) => $r['status'] === 'pending'));
            $paidCount = count(array_filter($repairs, fn($r) => $r['payment_status'] === 'paid'));
            ?>
            
            <div class="bg-white p-4 sm:p-5 rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-2 sm:p-3 bg-blue-100 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Revenue</p>
                        <p class="text-base sm:text-lg font-semibold text-gray-900 truncate">₵<?= number_format($totalCost, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 sm:p-5 rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-2 sm:p-3 bg-green-100 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed</p>
                        <p class="text-base sm:text-lg font-semibold text-gray-900"><?= $completedCount ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 sm:p-5 rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-2 sm:p-3 bg-yellow-100 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending</p>
                        <p class="text-base sm:text-lg font-semibold text-gray-900"><?= $pendingCount ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 sm:p-5 rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-2 sm:p-3 bg-green-100 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 min-w-0 flex-1">
                        <p class="text-xs sm:text-sm font-medium text-gray-500 truncate">Paid</p>
                        <p class="text-base sm:text-lg font-semibold text-gray-900"><?= $paidCount ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
