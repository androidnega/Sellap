<?php
// Repair details view
?>

<div class="w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div class="flex items-center">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs" class="text-gray-500 hover:text-gray-700 mr-3 sm:mr-4 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Repair Details</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>/receipt" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" target="_blank">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Print Receipt
            </a>
            <?php if ($repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled'): ?>
                <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>/edit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Edit Repair
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                Back to Repairs
            </a>
        </div>
    </div>

    <!-- Repair Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Customer Information -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Customer Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Customer Name</label>
                        <p class="text-sm sm:text-base text-gray-900"><?= htmlspecialchars($repair['customer_name']) ?></p>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Contact</label>
                        <p class="text-sm sm:text-base text-gray-900"><?= htmlspecialchars($repair['customer_contact']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Device Information -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Device Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Device Source</label>
                        <p class="text-sm sm:text-base text-gray-900">
                            <?php if ($repair['product_name']): ?>
                                <?= htmlspecialchars($repair['product_name']) ?> (From Stock)
                            <?php else: ?>
                                <span class="text-gray-500">Customer's own device</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!$repair['product_name'] && ($repair['device_brand'] || $repair['device_model'])): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php if ($repair['device_brand']): ?>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Device Brand</label>
                                    <p class="text-sm sm:text-base text-gray-900"><?= htmlspecialchars($repair['device_brand']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($repair['device_model']): ?>
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Device Model</label>
                                    <p class="text-sm sm:text-base text-gray-900"><?= htmlspecialchars($repair['device_model']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Issue/Fault Description</label>
                        <p class="text-sm sm:text-base text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($repair['issue_description']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Accessories Used -->
            <?php if (!empty($accessories)): ?>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Accessories Used</h2>
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Accessory
                                        </th>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Quantity
                                        </th>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Unit Price
                                        </th>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($accessories as $accessory): ?>
                                        <tr>
                                            <td class="px-3 sm:px-4 py-3 text-sm text-gray-900">
                                                <?= htmlspecialchars($accessory['product_name']) ?>
                                            </td>
                                            <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <?= $accessory['quantity'] ?>
                                            </td>
                                            <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                ₵<?= number_format($accessory['price'], 2) ?>
                                            </td>
                                            <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-medium">
                                                ₵<?= number_format($accessory['quantity'] * $accessory['price'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($repair['notes'])): ?>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Notes</h2>
                    <p class="text-sm sm:text-base text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($repair['notes']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Status & Payment -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Status & Payment</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-2">Status</label>
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
                        <span class="inline-block px-3 py-1 text-xs sm:text-sm font-medium rounded-full <?= $statusColor ?>">
                            <?= ucfirst(str_replace('_', ' ', $repair['status'])) ?>
                        </span>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-2">Payment Status</label>
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
                        <span class="inline-block px-3 py-1 text-xs sm:text-sm font-medium rounded-full <?= $paymentColor ?>">
                            <?= ucfirst($displayPaymentStatus) ?>
                        </span>
                    </div>
                    <?php if (!empty($repair['tracking_code'])): ?>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Tracking Code</label>
                            <p class="text-xs sm:text-sm text-gray-900 font-mono break-all"><?= htmlspecialchars($repair['tracking_code']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cost Breakdown -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Cost Breakdown</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Repair Cost</span>
                        <span class="text-xs sm:text-sm text-gray-900 font-medium">₵<?= number_format($repair['repair_cost'], 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Parts Cost</span>
                        <span class="text-xs sm:text-sm text-gray-900 font-medium">₵<?= number_format($repair['parts_cost'], 2) ?></span>
                    </div>
                    <?php if ($repair['accessory_cost'] > 0): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-xs sm:text-sm text-gray-600">Accessories</span>
                            <span class="text-xs sm:text-sm text-gray-900 font-medium">₵<?= number_format($repair['accessory_cost'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <hr class="border-gray-200">
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-sm sm:text-base font-medium text-gray-900">Total Cost</span>
                        <span class="text-sm sm:text-base font-bold text-gray-900">₵<?= number_format($repair['total_cost'], 2) ?></span>
                    </div>
                    <?php if ($repair['status'] === 'failed' && $repair['repair_cost'] > 0): ?>
                        <hr class="border-gray-200 mt-2">
                        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded">
                            <div class="flex justify-between items-center">
                                <span class="text-xs sm:text-sm font-medium text-yellow-800">Workmanship Refund</span>
                                <span class="text-xs sm:text-sm font-bold text-yellow-800">₵<?= number_format($repair['repair_cost'], 2) ?></span>
                            </div>
                            <p class="text-xs text-yellow-700 mt-1">
                                Parts (₵<?= number_format($repair['parts_cost'], 2) ?>) and Accessories (₵<?= number_format($repair['accessory_cost'], 2) ?>) are not refundable as they were already sold.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Repair Information -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Repair Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Repair ID</label>
                        <p class="text-xs sm:text-sm text-gray-900">#<?= $repair['id'] ?></p>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Technician</label>
                        <p class="text-xs sm:text-sm text-gray-900"><?= htmlspecialchars($repair['technician_name'] ?? 'Unknown') ?></p>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Created</label>
                        <p class="text-xs sm:text-sm text-gray-900"><?= date('M j, Y g:i A', strtotime($repair['created_at'])) ?></p>
                    </div>
                    <?php if ($repair['updated_at'] !== $repair['created_at']): ?>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Last Updated</label>
                            <p class="text-xs sm:text-sm text-gray-900"><?= date('M j, Y g:i A', strtotime($repair['updated_at'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <?php if ($repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled'): ?>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <?php if ($repair['status'] === 'pending'): ?>
                            <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline w-full">
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-play mr-2"></i>Start Repair
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($repair['status'] === 'in_progress'): ?>
                            <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline w-full">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-check mr-2"></i>Mark Complete
                                </button>
                            </form>
                            
                            <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline w-full">
                                <input type="hidden" name="status" value="failed">
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors" 
                                        onclick="return confirm('Are you sure you want to mark this repair as failed? The customer will be notified via SMS.');">
                                    <i class="fas fa-times mr-2"></i>Mark as Failed
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($repair['status'] === 'completed'): ?>
                            <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline w-full">
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-check-circle mr-2"></i>Mark Delivered
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
