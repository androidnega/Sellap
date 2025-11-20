<?php
// Repair details view
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Convert $repair object to array if it's an object
if (is_object($repair)) {
    // If it's a model object, try to get its properties
    if (method_exists($repair, 'toArray')) {
        $repair = $repair->toArray();
    } elseif (method_exists($repair, 'getAttributes')) {
        $repair = $repair->getAttributes();
    } else {
        // Fallback: use json_encode/decode to convert object to array
        // This handles both public and private properties
        $repair = json_decode(json_encode($repair), true);
        // If json conversion fails, try get_object_vars (only public properties)
        if (!is_array($repair)) {
            $repair = get_object_vars($repair);
        }
    }
}
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
            <?php 
            // Only show edit button for technicians, not managers
            $userRole = $_SESSION['user']['role'] ?? '';
            if ($userRole === 'technician' && $repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled'): 
            ?>
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
                        <p class="text-sm sm:text-base text-gray-900">
                            <?php 
                            $customerName = $repair['customer_name'] ?? '';
                            if (empty(trim($customerName))) {
                                echo '<span class="text-gray-500 italic">Not provided</span>';
                            } else {
                                echo htmlspecialchars($customerName);
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500 mb-1">Contact</label>
                        <p class="text-sm sm:text-base text-gray-900">
                            <?php 
                            $customerContact = $repair['customer_contact'] ?? '';
                            if (empty(trim($customerContact))) {
                                echo '<span class="text-gray-500 italic">Not provided</span>';
                            } else {
                                echo htmlspecialchars($customerContact);
                            }
                            ?>
                        </p>
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
                        <p class="text-sm sm:text-base text-gray-900 whitespace-pre-wrap">
                            <?php 
                            $issueDescription = $repair['issue_description'] ?? '';
                            if (empty(trim($issueDescription))) {
                                echo '<span class="text-gray-500 italic">No issue description provided</span>';
                            } else {
                                echo htmlspecialchars($issueDescription);
                            }
                            ?>
                        </p>
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
                <?php
                // Get user role from session
                $userRole = $_SESSION['user']['role'] ?? 'technician';
                
                // Filter out profit information for technicians
                $notes = $repair['notes'];
                if ($userRole === 'technician') {
                    // Remove profit information from notes - catch all variations
                    // Pattern 1: " | Parts Profit: ₵X.XX" or "| Parts Profit: ₵X.XX"
                    $notes = preg_replace('/\s*\|\s*Parts\s+Profit:\s*₵[\d,]+\.?\d*/i', '', $notes);
                    // Pattern 2: "Parts Profit: ₵X.XX" at start or anywhere
                    $notes = preg_replace('/Parts\s+Profit:\s*₵[\d,]+\.?\d*/i', '', $notes);
                    // Pattern 3: Clean up any remaining " | " or "|" at start/end
                    $notes = preg_replace('/^\s*\|\s*/', '', $notes);
                    $notes = preg_replace('/\s*\|\s*$/', '', $notes);
                    // Pattern 4: Remove any standalone " | " sequences
                    $notes = preg_replace('/\s*\|\s*/', ' ', $notes);
                    $notes = trim($notes);
                }
                ?>
                <?php if (!empty($notes)): ?>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Notes</h2>
                    <p class="text-sm sm:text-base text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($notes) ?></p>
                </div>
                <?php endif; ?>
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
                        // All repairs are considered paid at booking time (payment received when service is booked)
                        // Normalize payment_status (handle both 'paid' and 'PAID', 'unpaid' and 'UNPAID')
                        $paymentStatus = strtolower(trim($repair['payment_status'] ?? 'paid'));
                        
                        // If payment_status is empty, null, or 'unpaid', default to 'paid' (all bookings are paid)
                        if (empty($paymentStatus) || $paymentStatus === 'null' || $paymentStatus === 'unpaid') {
                            $paymentStatus = 'paid';
                        }
                        
                        // Delivered and completed repairs are always considered paid
                        if (in_array($repair['status'], ['delivered', 'completed'])) {
                            $paymentStatus = 'paid';
                        }
                        
                        $paymentColors = [
                            'unpaid' => 'bg-red-100 text-red-800',
                            'partial' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800'
                        ];
                        $paymentColor = $paymentColors[$paymentStatus] ?? 'bg-green-100 text-green-800';
                        ?>
                        <span class="inline-block px-3 py-1 text-xs sm:text-sm font-medium rounded-full <?= $paymentColor ?>">
                            <?= ucfirst($paymentStatus) ?>
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
                    <?php
                    // Get actual values from database - use the exact values saved during booking
                    // repair_cost is the charge entered by the user during booking
                    $repairCost = isset($repair['repair_cost']) ? floatval($repair['repair_cost']) : 0;
                    $labourCost = isset($repair['labour_cost']) ? floatval($repair['labour_cost']) : 0;
                    $partsCost = isset($repair['parts_cost']) ? floatval($repair['parts_cost']) : 0;
                    $accessoryCost = isset($repair['accessory_cost']) ? floatval($repair['accessory_cost']) : 0;
                    
                    // Calculate parts cost from accessories array (source of truth)
                    // This is the actual cost of parts used during the repair
                    if (is_array($accessories) && !empty($accessories)) {
                        $totalPartsCost = array_sum(array_map(function($acc) {
                            return floatval($acc['quantity'] ?? 0) * floatval($acc['price'] ?? 0);
                        }, $accessories));
                    } else {
                        // If no accessories, use parts_cost from database
                        // parts_cost and accessory_cost are the same when accessories are selected,
                        // so use the maximum to avoid double counting
                        $totalPartsCost = max($partsCost, $accessoryCost);
                    }
                    
                    // Get total cost from database - this is the sum of repair_cost + parts_cost saved during booking
                    $totalCost = isset($repair['total_cost']) ? floatval($repair['total_cost']) : 0;
                    
                    // Always use the repair_cost value from database (what user entered during booking)
                    // Only calculate if repair_cost is truly 0 AND we have a total_cost that suggests it should be non-zero
                    // This preserves the actual value entered by the user
                    if ($repairCost == 0 && $totalCost > $totalPartsCost) {
                        // Only calculate if it seems like repair_cost might have been missed
                        $calculatedRepairCost = $totalCost - $totalPartsCost;
                        if ($calculatedRepairCost > 0) {
                            $repairCost = $calculatedRepairCost;
                        }
                    }
                    
                    // If total cost is 0 but we have repair cost or parts, calculate total
                    if ($totalCost == 0 && ($repairCost > 0 || $totalPartsCost > 0)) {
                        $totalCost = $repairCost + $totalPartsCost;
                    }
                    
                    // Recalculate total to ensure accuracy
                    $calculatedTotal = $repairCost + $totalPartsCost;
                    if ($totalCost == 0 || abs($totalCost - $calculatedTotal) > 0.01) {
                        $totalCost = $calculatedTotal;
                    }
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600 font-medium">Repair Cost (Workmanship)</span>
                        <span class="text-xs sm:text-sm text-gray-900 font-semibold">₵<?= number_format($repairCost, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600 font-medium">Parts Cost</span>
                        <span class="text-xs sm:text-sm text-gray-900 font-semibold">₵<?= number_format($totalPartsCost, 2) ?></span>
                    </div>
                    <hr class="border-gray-200">
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-sm sm:text-base font-medium text-gray-900">Total Cost</span>
                        <span class="text-sm sm:text-base font-bold text-gray-900">₵<?= number_format($totalCost, 2) ?></span>
                    </div>
                    <?php if ($repair['status'] === 'failed' && $repairCost > 0): ?>
                        <hr class="border-gray-200 mt-2">
                        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded">
                            <div class="flex justify-between items-center">
                                <span class="text-xs sm:text-sm font-medium text-yellow-800">Workmanship Refund</span>
                                <span class="text-xs sm:text-sm font-bold text-yellow-800">₵<?= number_format($repairCost, 2) ?></span>
                            </div>
                            <p class="text-xs text-yellow-700 mt-1">
                                Parts (₵<?= number_format($totalPartsCost, 2) ?>) are not refundable as they were already sold.
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
            <?php 
            // Only show quick actions for technicians, not managers
            $userRole = $_SESSION['user']['role'] ?? '';
            if ($userRole === 'technician' && $repair['status'] !== 'delivered' && $repair['status'] !== 'cancelled'): 
            ?>
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
                            
                            <form method="POST" action="<?= BASE_URL_PATH ?>/dashboard/repairs/update-status/<?= $repair['id'] ?>" class="inline w-full mt-4">
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
    
    <!-- Related In Progress Repairs Section -->
    <?php if (!empty($inProgressRepairs)): ?>
        <div class="mt-8">
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800">In Progress Repairs</h2>
                    <div class="flex flex-wrap gap-4">
                        <div class="bg-blue-50 px-4 py-2 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Total Revenue</p>
                            <p class="text-lg font-bold text-gray-900">₵<?= number_format($inProgressStats['total_revenue'], 2) ?></p>
                        </div>
                        <div class="bg-orange-50 px-4 py-2 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">In Progress</p>
                            <p class="text-lg font-bold text-gray-900"><?= $inProgressStats['total'] ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Device
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Cost
                                    </th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($inProgressRepairs as $relatedRepair): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($relatedRepair['customer_name'] ?? $relatedRepair['customer_name_from_table'] ?? 'Unknown Customer') ?></div>
                                            <?php if (!empty($relatedRepair['customer_contact'])): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($relatedRepair['customer_contact']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php
                                            // Display device information properly
                                            $deviceInfo = '';
                                            if (!empty($relatedRepair['product_name'])) {
                                                $deviceInfo = htmlspecialchars($relatedRepair['product_name']);
                                            } elseif (!empty($relatedRepair['device_brand']) || !empty($relatedRepair['device_model'])) {
                                                $deviceInfo = trim(($relatedRepair['device_brand'] ?? '') . ' ' . ($relatedRepair['device_model'] ?? ''));
                                            } else {
                                                $deviceInfo = 'Customer Device';
                                            }
                                            echo htmlspecialchars($deviceInfo);
                                            ?>
                                        </td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                            ₵<?= number_format($relatedRepair['total_cost'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $relatedRepair['id'] ?>" class="text-blue-600 hover:text-blue-900 flex items-center gap-1">
                                                <i class="fas fa-eye text-sm"></i>
                                                <span class="hidden sm:inline">View</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
