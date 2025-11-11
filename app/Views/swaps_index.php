<?php
// Swaps Index View - Table list with modal for phone comparison
?>

<div class="w-full p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">All Swaps</h1>
        <div class="flex space-x-3">
            <?php 
            $userRole = $_SESSION['user']['role'] ?? '';
            $isManager = in_array($userRole, ['manager', 'admin', 'system_admin']);
            $isSalesperson = in_array($userRole, ['salesperson', 'manager', 'admin', 'system_admin']);
            ?>
            <?php if ($isManager): ?>
                <button id="deleteSelectedSwapsBtn" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-trash mr-2"></i>Delete Selected (<span id="selectedSwapsCount">0</span>)
                </button>
            <?php endif; ?>
            <?php if ($isManager): ?>
                <button id="syncSwapsToInventoryBtn" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-sync mr-2"></i>Add Selected Swap Items to Products (<span id="selectedSwapsForSyncCount">0</span>)
                </button>
            <?php endif; ?>
            <?php if (!empty($pendingSwappedItems)): ?>
                <button id="syncToInventoryBtn" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-sync mr-2"></i>Add Selected Items to Products (<span id="selectedCount">0</span>)
                </button>
            <?php endif; ?>
            <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/resale" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                <i class="fas fa-box mr-2"></i>View Resale Items
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/create" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Swap
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php
    $stats = $swapStats ?? [
        'total' => count($swaps),
        'pending' => 0,
        'completed' => 0,
        'resold' => 0,
        'total_value' => 0,
        'in_stock' => 0,
        'total_estimated_profit' => 0,
        'total_final_profit' => 0,
        'total_cash_received' => 0,
        'in_stock_value' => 0
    ];
    $isManager = in_array($_SESSION['user']['role'] ?? '', ['manager', 'admin', 'system_admin']);
    ?>
    
    <!-- Basic Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white p-2 rounded-lg shadow">
            <div class="text-xs text-gray-600">Total Swaps</div>
            <div class="text-lg font-bold text-gray-800"><?= $stats['total'] ?></div>
        </div>
        <div class="bg-yellow-50 p-2 rounded-lg shadow border border-yellow-200">
            <div class="text-xs text-yellow-700">Pending</div>
            <div class="text-lg font-bold text-yellow-800"><?= $stats['pending'] ?></div>
        </div>
        <div class="bg-green-50 p-2 rounded-lg shadow border border-green-200">
            <div class="text-xs text-green-700">Completed</div>
            <div class="text-lg font-bold text-green-800"><?= $stats['completed'] ?></div>
        </div>
        <div class="bg-purple-50 p-2 rounded-lg shadow border border-purple-200">
            <div class="text-xs text-purple-700">Resold</div>
            <div class="text-lg font-bold text-purple-800"><?= $stats['resold'] ?></div>
        </div>
    </div>
    
    <?php if ($isManager): ?>
    <!-- Manager Stats Row - Simplified Swap Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-emerald-50 p-4 rounded-lg shadow border border-emerald-200">
            <div class="text-sm text-emerald-700 font-medium mb-1">Profit from Swaps</div>
            <div class="text-2xl font-bold text-emerald-800">₵<?= number_format($stats['total_profit'] ?? 0, 2) ?></div>
            <div class="text-xs text-emerald-600 mt-1">Realized gains</div>
        </div>
        <div class="bg-red-50 p-4 rounded-lg shadow border border-red-200">
            <div class="text-sm text-red-700 font-medium mb-1">Loss from Swaps</div>
            <div class="text-2xl font-bold text-red-800">₵<?= number_format($stats['total_loss'] ?? 0, 2) ?></div>
            <div class="text-xs text-red-600 mt-1">Realized losses</div>
        </div>
        <div class="bg-blue-50 p-4 rounded-lg shadow border border-blue-200">
            <div class="text-sm text-blue-700 font-medium mb-1">Unsold Items Value</div>
            <div class="text-2xl font-bold text-blue-800">
                ₵<?= number_format($stats['in_stock_value'] ?? 0, 2) ?>
                <span class="text-sm font-normal text-blue-600 ml-2">(<?= $stats['in_stock'] ?? 0 ?> item<?= ($stats['in_stock'] ?? 0) != 1 ? 's' : '' ?>)</span>
            </div>
        </div>
        <div class="bg-indigo-50 p-4 rounded-lg shadow border border-indigo-200">
            <div class="text-sm text-indigo-700 font-medium mb-1">Total Value</div>
            <div class="text-2xl font-bold text-indigo-800">₵<?= number_format($stats['total_value'] ?? 0, 2) ?></div>
            <div class="text-xs text-indigo-600 mt-1">All swaps</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Filter -->
    <div class="mb-4 flex space-x-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="px-4 py-2 rounded-md <?= !isset($_GET['status']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            All
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps?status=pending" class="px-4 py-2 rounded-md <?= (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            Pending
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps?status=completed" class="px-4 py-2 rounded-md <?= (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            Completed
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps?status=resold" class="px-4 py-2 rounded-md <?= (isset($_GET['status']) && $_GET['status'] == 'resold') ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            Resold
        </a>
    </div>

    <?php if (empty($swaps)): ?>
        <div class="bg-white p-8 rounded-lg shadow text-center">
            <p class="text-gray-500">No swaps found.</p>
        </div>
    <?php else: ?>
        <!-- Table View -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                            <?php 
                            $userRole = $_SESSION['user']['role'] ?? '';
                            $isManager = in_array($userRole, ['manager', 'admin', 'system_admin']);
                            $isSalesperson = in_array($userRole, ['salesperson', 'manager', 'admin', 'system_admin']);
                            ?>
                            <?php if ($isManager || $isSalesperson): ?>
                                <input type="checkbox" id="selectAllSwaps" class="cursor-pointer" title="Select All">
                            <?php endif; ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transaction Code
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Product
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total Value
                        </th>
                        <?php if ($isManager): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Profit
                        </th>
                        <?php endif; ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($swaps as $swap): ?>
                        <tr class="hover:bg-gray-50 swap-row" data-swap-id="<?= $swap['id'] ?? '' ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $userRole = $_SESSION['user']['role'] ?? '';
                                $isManager = in_array($userRole, ['manager', 'admin', 'system_admin']);
                                $isSalesperson = in_array($userRole, ['salesperson', 'manager', 'admin', 'system_admin']);
                                ?>
                                <?php if ($isManager): ?>
                                    <input type="checkbox" class="swap-checkbox cursor-pointer" data-swap-id="<?= $swap['id'] ?? '' ?>" value="<?= $swap['id'] ?? '' ?>">
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $transactionCode = $swap['transaction_code'] ?? $swap['unique_id'] ?? null;
                                if (!$transactionCode && isset($swap['id'])) {
                                    $transactionCode = 'SWAP-' . str_pad($swap['id'], 6, '0', STR_PAD_LEFT);
                                }
                                ?>
                                <span class="font-mono text-sm font-semibold"><?= htmlspecialchars($transactionCode ?? 'SWAP-' . str_pad($swap['id'] ?? 0, 6, '0', STR_PAD_LEFT)) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php
                                        $productName = $swap['company_product_name'] ?? null;
                                        if (!$productName || $productName === 'N/A') {
                                            $productName = 'Product ID: ' . ($swap['company_product_id'] ?? $swap['new_phone_id'] ?? $swap['id'] ?? 'N/A');
                                        }
                                        echo htmlspecialchars($productName);
                                        ?>
                                    </div>
                                    <?php if (!empty($swap['company_brand'])): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($swap['company_brand']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($swap['customer_product_brand']) || !empty($swap['customer_product_model'])): ?>
                                        <div class="text-xs font-medium mt-1 px-2 py-1 rounded" style="background-color: #c4b5fd; color: #6b21a8;">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            Swapped: <?= htmlspecialchars(trim(($swap['customer_product_brand'] ?? '') . ' ' . ($swap['customer_product_model'] ?? ''))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($swap['customer_name'] ?? $swap['customer_name_from_table'] ?? 'N/A') ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($swap['customer_phone'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">₵<?= number_format($swap['total_value'] ?? 0, 2) ?></span>
                            </td>
                            <?php if ($isManager): ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $profit = null;
                                $profitEstimate = isset($swap['profit_estimate']) && $swap['profit_estimate'] !== null ? floatval($swap['profit_estimate']) : null;
                                $profitFinal = isset($swap['final_profit']) && $swap['final_profit'] !== null ? floatval($swap['final_profit']) : null;
                                $profitStatus = $swap['profit_status'] ?? null;
                                
                                // Check if both items are sold (both sale IDs exist = profit MUST be realized)
                                $hasCompanySaleId = !empty($swap['company_item_sale_id']);
                                $hasCustomerSaleId = !empty($swap['customer_item_sale_id']);
                                $bothItemsSold = $hasCompanySaleId && $hasCustomerSaleId;
                                $isResold = ($swap['resale_status'] ?? null) === 'sold' || ($swap['status'] ?? null) === 'resold';
                                
                                // CORE RULE: If both items are sold → profit is REALIZED (always)
                                if ($bothItemsSold || $profitStatus === 'finalized' || ($isResold && ($profitFinal !== null || $profitEstimate !== null))) {
                                    // Both items sold = Realized profit
                                    $profit = $profitFinal !== null ? $profitFinal : $profitEstimate;
                                    if ($profit !== null) {
                                        $profitColor = $profit >= 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-red-700 bg-red-50 border-red-200';
                                        $profitLabel = 'Realized';
                                    }
                                } elseif ($profitEstimate !== null) {
                                    // Only estimated profit available and both items NOT sold = Estimated
                                    $profit = $profitEstimate;
                                    $profitColor = $profit >= 0 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-orange-700 bg-orange-50 border-orange-200';
                                    $profitLabel = 'Est.';
                                }
                                
                                if ($profit !== null):
                                ?>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold <?= $profitColor ?> px-2 py-1 rounded border inline-block">
                                            ₵<?= number_format($profit, 2) ?>
                                        </span>
                                        <span class="text-xs text-gray-500 mt-0.5"><?= $profitLabel ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                // Determine actual status based on swap status and resale status
                                $swapStatus = $swap['status'] ?? 'pending';
                                $resaleStatus = $swap['resale_status'] ?? null;
                                
                                // Status display logic:
                                // - If swapped item is sold: "Resold"
                                // - If swapped item is in_stock: "Completed" (item received, ready for resale)
                                // - If swap is pending: "Pending"
                                // - If swap is completed but no swapped item: "Completed"
                                
                                if ($resaleStatus === 'sold') {
                                    $displayStatus = 'Resold';
                                    $statusColor = 'bg-purple-100 text-purple-800';
                                } elseif ($resaleStatus === 'in_stock') {
                                    $displayStatus = 'Completed';
                                    $statusColor = 'bg-green-100 text-green-800';
                                } elseif ($swapStatus === 'completed') {
                                    $displayStatus = 'Completed';
                                    $statusColor = 'bg-green-100 text-green-800';
                                } elseif ($swapStatus === 'resold') {
                                    $displayStatus = 'Resold';
                                    $statusColor = 'bg-purple-100 text-purple-800';
                                } else {
                                    $displayStatus = 'Pending';
                                    $statusColor = 'bg-yellow-100 text-yellow-800';
                                }
                                ?>
                                <div class="flex flex-col gap-1">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusColor ?> inline-block w-fit">
                                        <?= $displayStatus ?>
                                    </span>
                                    <?php if ($resaleStatus === 'in_stock'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 inline-block w-fit" title="Swapped item is in stock and available for resale">
                                            Available for Resale
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <button onclick='showSwapDetailsModal(<?= json_encode($swap) ?>)' 
                                            class="text-blue-600 hover:text-blue-900 p-2 rounded hover:bg-blue-50 transition-colors" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($swap['id'])): ?>
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/<?= $swap['id'] ?>/receipt" target="_blank" 
                                           class="text-green-600 hover:text-green-900 p-2 rounded hover:bg-green-50 transition-colors" 
                                           title="View Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <?php if ($isManager && ($swap['resale_status'] ?? null) === 'in_stock'): 
                                            // Check if item is already added to inventory/products
                                            $hasInventoryProductId = isset($swap['inventory_product_id']) && 
                                                                     $swap['inventory_product_id'] !== null && 
                                                                     $swap['inventory_product_id'] !== 'NULL' && 
                                                                     trim(strval($swap['inventory_product_id'])) !== '' && 
                                                                     intval($swap['inventory_product_id']) > 0;
                                            if ($hasInventoryProductId): ?>
                                                <button disabled
                                                        class="text-gray-400 cursor-not-allowed p-2 rounded opacity-50" 
                                                        title="Add to product already">
                                                    <i class="fas fa-box"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick='showPriceModalForSwap(<?= $swap['id'] ?>, <?= json_encode(['brand' => $swap['customer_product_brand'] ?? '', 'model' => $swap['customer_product_model'] ?? '', 'estimated_value' => $swap['customer_product_value'] ?? 0]) ?>)' 
                                                        class="text-purple-600 hover:text-purple-900 p-2 rounded hover:bg-purple-50 transition-colors" 
                                                        title="Add to Products">
                                                    <i class="fas fa-box"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Price Setting Modal for Adding Swapped Items to Products -->
<div id="swapPriceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Set Resell Price</h3>
                <button onclick="closeSwapPriceModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Item: <span id="swapPriceModalItemName" class="font-semibold"></span></p>
                <p class="text-xs text-gray-500">Estimated Value: ₵<span id="swapPriceModalEstimatedValue">0.00</span></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Resell Price (₵) *</label>
                <input type="number" id="swapPriceModalResellPrice" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Enter resell price">
                <p class="text-xs text-gray-500 mt-1">This is the price the item will be sold for in POS</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="closeSwapPriceModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button onclick="confirmSwapPrice()" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                    Add to Products
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Swap Details Modal -->
<div id="swapDetailsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 md:top-16 mx-auto p-4 md:p-6 border w-11/12 max-w-xl md:max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4 pb-3 border-b">
            <h3 class="text-lg md:text-xl font-bold text-gray-900">Swap Details</h3>
            <button onclick="closeSwapDetailsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl md:text-2xl"></i>
            </button>
        </div>
        
        <div id="swapModalContent" class="mt-4">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>

    <!-- Swapped Items Tracking Section -->
    <?php if (!empty($pendingSwappedItems)): ?>
    <div class="mt-8 bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 bg-gray-50 border-b">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-box-open mr-2"></i>Swapped Items Available for Resale (<?= count($pendingSwappedItems) ?>)
            </h2>
            <p class="text-sm text-gray-600 mt-1">Items received from customers that are ready to be resold</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="p-3 text-left w-12">
                            <input type="checkbox" id="selectAllSwappedItems" class="cursor-pointer" title="Select All">
                        </th>
                        <th class="p-3 text-left">Brand</th>
                        <th class="p-3 text-left">Model</th>
                        <th class="p-3 text-left">Condition</th>
                        <th class="p-3 text-left">IMEI</th>
                        <th class="p-3 text-right">Estimated Value</th>
                        <th class="p-3 text-left">Transaction Code</th>
                        <th class="p-3 text-left">Customer</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSwappedItems as $item): ?>
                        <tr class="border-b swapped-item-row" data-item-id="<?= $item['id'] ?? '' ?>" style="background-color: #f3e8ff; color: #6b21a8;" onmouseover="this.style.backgroundColor='#e9d5ff'" onmouseout="this.style.backgroundColor='#f3e8ff'">
                            <td class="p-3">
                                <input type="checkbox" class="swapped-item-checkbox cursor-pointer" data-item-id="<?= $item['id'] ?? '' ?>" value="<?= $item['id'] ?? '' ?>">
                            </td>
                            <td class="p-3" style="color: #6b21a8;">
                                <span class="inline-block px-2 py-1 rounded text-xs" style="background-color: #c4b5fd; color: #6b21a8;">
                                    <?= htmlspecialchars($item['brand'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="p-3 font-medium" style="color: #6b21a8;"><?= htmlspecialchars($item['model'] ?? 'N/A') ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs" style="background-color: #c4b5fd; color: #6b21a8;">
                                    <?= ucfirst($item['condition'] ?? 'used') ?>
                                </span>
                            </td>
                            <td class="p-3 font-mono text-xs" style="color: #6b21a8;">
                                <?= htmlspecialchars($item['imei'] ?? '-') ?>
                            </td>
                            <td class="p-3 text-right font-semibold" style="color: #6b21a8;">
                                ₵<?= number_format($item['estimated_value'] ?? 0, 2) ?>
                            </td>
                            <td class="p-3 font-mono text-xs" style="color: #6b21a8;">
                                <?= htmlspecialchars($item['transaction_code'] ?? 'N/A') ?>
                            </td>
                            <td class="p-3">
                                <div class="text-sm">
                                    <div class="font-medium" style="color: #6b21a8;"><?= htmlspecialchars($item['customer_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs" style="color: #6b21a8;"><?= htmlspecialchars($item['customer_phone'] ?? '') ?></div>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs" style="background-color: #c4b5fd; color: #6b21a8;">
                                    <?= ucfirst(str_replace('_', ' ', $item['status'] ?? 'in_stock')) ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/<?= $item['swap_id'] ?? '' ?>" 
                                   class="text-sm font-medium hover:underline" style="color: #6b21a8;">
                                    View Swap
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
let currentSwapData = null;

// Sync swapped items to inventory
document.addEventListener('DOMContentLoaded', function() {
    const syncBtn = document.getElementById('syncToInventoryBtn');
    const selectAllCheckbox = document.getElementById('selectAllSwappedItems');
    const itemCheckboxes = document.querySelectorAll('.swapped-item-checkbox');
    
    // Update selected count and enable/disable sync button
    function updateSelectedCount() {
        const selected = document.querySelectorAll('.swapped-item-checkbox:checked');
        const count = selected.length;
        const countSpan = document.getElementById('selectedCount');
        if (countSpan) {
            countSpan.textContent = count;
        }
        if (syncBtn) {
            syncBtn.disabled = count === 0;
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox && itemCheckboxes.length > 0) {
            selectAllCheckbox.checked = count === itemCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < itemCheckboxes.length;
        }
    }
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox change
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Sync button click
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.swapped-item-checkbox:checked')).map(cb => parseInt(cb.value));
            
            if (selected.length === 0) {
                alert('Please select at least one item to sync.');
                return;
            }
            
            if (!confirm(`This will add ${selected.length} selected swapped item(s) to products inventory. Continue?`)) {
                return;
            }
            
            const originalText = syncBtn.innerHTML;
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...';
            
            fetch('<?= BASE_URL_PATH ?>/api/swaps/sync-to-inventory', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    selected_ids: selected
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server returned: ' + text.substring(0, 100));
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message + (data.errors && data.errors.length > 0 ? '\n\nErrors: ' + data.errors.join(', ') : ''));
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to sync swapped items'));
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Sync error:', error);
                alert('Error syncing swapped items: ' + error.message);
                syncBtn.disabled = false;
                syncBtn.innerHTML = originalText;
            });
        });
    }
    
    // Initial count update
    updateSelectedCount();
    
    // Swap selection for salespeople (sync to products) and managers (delete)
    <?php 
    $userRole = $_SESSION['user']['role'] ?? '';
    $isManager = in_array($userRole, ['manager', 'admin', 'system_admin']);
    $isSalesperson = in_array($userRole, ['salesperson', 'manager', 'admin', 'system_admin']);
    ?>
    const isManager = <?= json_encode($isManager) ?>;
    
    // Shared elements for both salesperson and manager
    const selectAllSwaps = document.getElementById('selectAllSwaps');
    const swapCheckboxes = document.querySelectorAll('.swap-checkbox');
    
    <?php if ($isManager): ?>
    // Manager: Sync selected swaps to products (with price modal)
    const syncSwapsToInventoryBtn = document.getElementById('syncSwapsToInventoryBtn');
    
    function updateSelectedSwapsForSyncCount() {
        const selected = document.querySelectorAll('.swap-checkbox:checked');
        const count = selected.length;
        
        const countSpan = document.getElementById('selectedSwapsForSyncCount');
        if (countSpan) {
            countSpan.textContent = count;
        }
        
        if (syncSwapsToInventoryBtn) {
            syncSwapsToInventoryBtn.disabled = count === 0;
            console.log('Sync button disabled:', count === 0, 'Selected count:', count);
        }
        
        if (selectAllSwaps && swapCheckboxes && swapCheckboxes.length > 0) {
            selectAllSwaps.checked = count === swapCheckboxes.length;
            selectAllSwaps.indeterminate = count > 0 && count < swapCheckboxes.length;
        }
    }
    
    if (syncSwapsToInventoryBtn) {
        syncSwapsToInventoryBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.swap-checkbox:checked')).map(cb => parseInt(cb.value));
            
            if (selected.length === 0) {
                alert('Please select at least one swap to add its items to products.');
                return;
            }
            
            // Show price modal for bulk add
            showBulkPriceModal(selected);
        });
    }
    <?php endif; ?>
    
    <?php if ($isManager): ?>
    // Manager: Delete selected swaps
    const deleteSelectedBtn = document.getElementById('deleteSelectedSwapsBtn');
    
    function updateSelectedSwapsCount() {
        const selected = document.querySelectorAll('.swap-checkbox:checked');
        const count = selected.length;
        const countSpan = document.getElementById('selectedSwapsCount');
        if (countSpan) {
            countSpan.textContent = count;
        }
        if (deleteSelectedBtn) {
            deleteSelectedBtn.disabled = count === 0;
        }
        
        if (selectAllSwaps && swapCheckboxes.length > 0) {
            selectAllSwaps.checked = count === swapCheckboxes.length;
            selectAllSwaps.indeterminate = count > 0 && count < swapCheckboxes.length;
        }
    }
    
    // Shared select all handler
    if (selectAllSwaps) {
        selectAllSwaps.addEventListener('change', function() {
            swapCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            <?php if ($isManager): ?>
            updateSelectedSwapsForSyncCount();
            <?php endif; ?>
            <?php if ($isManager): ?>
            updateSelectedSwapsCount();
            <?php endif; ?>
        });
    }
    
    // Shared checkbox change handler
    if (swapCheckboxes && swapCheckboxes.length > 0) {
        swapCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                <?php if ($isSalesperson): ?>
                updateSelectedSwapsForSyncCount();
                <?php endif; ?>
                <?php if ($isManager): ?>
                updateSelectedSwapsCount();
                <?php endif; ?>
            });
        });
    }
    
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.swap-checkbox:checked')).map(cb => parseInt(cb.value));
            
            if (selected.length === 0) {
                alert('Please select at least one swap to delete.');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selected.length} selected swap(s)? This action cannot be undone.`)) {
                return;
            }
            
            const originalText = deleteSelectedBtn.innerHTML;
            deleteSelectedBtn.disabled = true;
            deleteSelectedBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
            
            // Delete swaps one by one
            let deleted = 0;
            let errors = [];
            
            Promise.all(selected.map(swapId => 
                fetch('<?= BASE_URL_PATH ?>/api/swaps/' + swapId, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        deleted++;
                    } else {
                        errors.push('Swap #' + swapId + ': ' + (data.message || 'Failed'));
                    }
                })
                .catch(error => {
                    errors.push('Swap #' + swapId + ': ' + error.message);
                })
            )).then(() => {
                if (deleted > 0) {
                    alert(`Successfully deleted ${deleted} swap(s).${errors.length > 0 ? '\n\nErrors:\n' + errors.join('\n') : ''}`);
                    window.location.reload();
                } else {
                    alert('Failed to delete swaps:\n' + errors.join('\n'));
                    deleteSelectedBtn.disabled = false;
                    deleteSelectedBtn.innerHTML = originalText;
                }
            });
        });
    }
    
    updateSelectedSwapsCount();
    <?php endif; ?>
    
    <?php if ($isManager): ?>
    // Initialize button state on page load for managers
    updateSelectedSwapsForSyncCount();
    
    // Also listen for any changes to checkboxes dynamically
    const observer = new MutationObserver(function() {
        updateSelectedSwapsForSyncCount();
    });
    
    // Observe the table for checkbox changes
    const swapsTable = document.querySelector('.swap-row')?.closest('tbody');
    if (swapsTable) {
        observer.observe(swapsTable, { childList: true, subtree: true, attributes: true, attributeFilter: ['checked'] });
    }
    <?php endif; ?>
});

// Price modal state
let pendingSwapIds = null;
let pendingSwapData = null;

function showPriceModalForSwap(swapId, swapItemData) {
    pendingSwapIds = [swapId];
    pendingSwapData = swapItemData;
    const itemName = (swapItemData.brand || '') + ' ' + (swapItemData.model || '');
    document.getElementById('swapPriceModalItemName').textContent = itemName || 'Swapped Item';
    document.getElementById('swapPriceModalEstimatedValue').textContent = parseFloat(swapItemData.estimated_value || 0).toFixed(2);
    document.getElementById('swapPriceModalResellPrice').value = swapItemData.estimated_value || '';
    document.getElementById('swapPriceModal').classList.remove('hidden');
}

function showBulkPriceModal(swapIds) {
    pendingSwapIds = swapIds;
    pendingSwapData = null;
    document.getElementById('swapPriceModalItemName').textContent = swapIds.length + ' selected swap(s)';
    document.getElementById('swapPriceModalEstimatedValue').textContent = '0.00';
    document.getElementById('swapPriceModalResellPrice').value = '';
    document.getElementById('swapPriceModal').classList.remove('hidden');
}

function closeSwapPriceModal() {
    document.getElementById('swapPriceModal').classList.add('hidden');
    pendingSwapIds = null;
    pendingSwapData = null;
}

function confirmSwapPrice() {
    const resellPrice = parseFloat(document.getElementById('swapPriceModalResellPrice').value);
    
    if (!resellPrice || resellPrice <= 0) {
        alert('Please enter a valid resell price greater than 0.');
        return;
    }
    
    if (!pendingSwapIds || pendingSwapIds.length === 0) {
        alert('No swaps selected.');
        return;
    }
    
    closeSwapPriceModal();
    
    // Show loading state
    const syncBtn = document.getElementById('syncSwapsToInventoryBtn');
    const originalText = syncBtn ? syncBtn.innerHTML : '';
    if (syncBtn) {
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...';
    }
    
    fetch('<?= BASE_URL_PATH ?>/api/swaps/sync-to-inventory', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            swap_ids: pendingSwapIds,
            resell_price: resellPrice
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('Server returned: ' + text.substring(0, 100));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message + (data.errors && data.errors.length > 0 ? '\n\nErrors: ' + data.errors.join(', ') : ''));
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('Error: ' + (data.message || 'Failed to sync swapped items'));
            if (syncBtn) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Sync error:', error);
        alert('Error syncing swapped items: ' + error.message);
        if (syncBtn) {
            syncBtn.disabled = false;
            syncBtn.innerHTML = originalText;
        }
    });
}

// Close price modal when clicking outside
document.getElementById('swapPriceModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSwapPriceModal();
    }
});

function deleteSwap(swapId) {
    if (!confirm('Are you sure you want to delete this swap? This action cannot be undone.')) {
        return;
    }
    
    fetch('<?= BASE_URL_PATH ?>/api/swaps/' + swapId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Swap deleted successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete swap'));
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Error deleting swap: ' + error.message);
    });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function showSwapDetailsModal(swapData) {
    currentSwapData = swapData;
    const modal = document.getElementById('swapDetailsModal');
    const content = document.getElementById('swapModalContent');
    
    // Parse customer specs from notes
    let customerSpecs = {};
    if (swapData.customer_notes) {
        try {
            const decoded = JSON.parse(swapData.customer_notes);
            if (typeof decoded === 'object' && decoded !== null) {
                customerSpecs = decoded;
            }
        } catch (e) {
            // Not JSON, ignore
        }
    }
    
    // Parse company specs
    let companySpecs = {};
    if (swapData.company_specs_json) {
        try {
            const decoded = JSON.parse(swapData.company_specs_json);
            if (typeof decoded === 'object' && decoded !== null) {
                companySpecs = decoded;
            }
        } catch (e) {
            // Not JSON, ignore
        }
    }
    
    // Build HTML content
    let html = `
        <div class="mb-4 p-3 md:p-4 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-600">Transaction Code:</span>
                    <p class="text-sm font-mono font-semibold">${swapData.transaction_code || 'N/A'}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Total Value:</span>
                    <p class="text-sm font-bold text-gray-900">₵${parseFloat(swapData.total_value || 0).toFixed(2)}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Date & Time:</span>
                    <p class="text-sm text-gray-700">${swapData.swap_date || swapData.created_at ? formatDateTime(swapData.swap_date || swapData.created_at) : 'N/A'}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Status:</span>
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                        swapData.status === 'completed' ? 'bg-green-100 text-green-800' :
                        swapData.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-purple-100 text-purple-800'
                    }">${(swapData.status || 'pending').charAt(0).toUpperCase() + (swapData.status || 'pending').slice(1)}</span>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
            <!-- Customer's Device -->
                            <div class="border-2 rounded-lg p-4 md:p-6" style="background-color: #f3e8ff; border-color: #a78bfa; color: #6b21a8;">
                <div class="flex items-center justify-between mb-3 md:mb-4">
                    <h4 class="text-base md:text-lg font-semibold" style="color: #6b21a8;">Customer's Device</h4>
                    ${swapData.customer_product_value ? `<span class="text-base md:text-lg font-bold" style="color: #6b21a8;">₵${parseFloat(swapData.customer_product_value).toFixed(2)}</span>` : ''}
                </div>
                
                <div class="space-y-3">
                    ${swapData.customer_product_brand ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Brand:</span>
                            <span class="text-sm font-semibold" style="color: #6b21a8;">${escapeHtml(swapData.customer_product_brand)}</span>
                        </div>
                    ` : ''}
                    ${swapData.customer_product_model ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Model:</span>
                            <span class="text-sm font-semibold" style="color: #6b21a8;">${escapeHtml(swapData.customer_product_model)}</span>
                        </div>
                    ` : ''}
                    ${swapData.customer_imei ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">IMEI:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(swapData.customer_imei)}</span>
                        </div>
                    ` : ''}
                    ${swapData.customer_condition ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Condition:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(swapData.customer_condition.charAt(0).toUpperCase() + swapData.customer_condition.slice(1))}</span>
                        </div>
                    ` : ''}
                    ${customerSpecs.storage ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Storage:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(customerSpecs.storage)}</span>
                        </div>
                    ` : ''}
                    ${customerSpecs.ram ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">RAM:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(customerSpecs.ram)}</span>
                        </div>
                    ` : ''}
                    ${customerSpecs.color ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Color:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(customerSpecs.color)}</span>
                        </div>
                    ` : ''}
                    ${customerSpecs.battery_health ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Battery:</span>
                            <span class="text-sm" style="color: #6b21a8;">${escapeHtml(customerSpecs.battery_health)}</span>
                        </div>
                    ` : ''}
                    ${swapData.resell_price ? `
                        <div class="flex items-center mt-3 pt-3 border-t" style="border-color: #a78bfa;">
                            <span class="text-sm font-medium w-24" style="color: #6b21a8;">Resell:</span>
                            <span class="text-sm font-bold" style="color: #6b21a8;">₵${parseFloat(swapData.resell_price).toFixed(2)}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
            
            <!-- Company's Device -->
            <div class="border-2 border-green-200 rounded-lg p-4 md:p-6 bg-green-50">
                <div class="flex items-center justify-between mb-3 md:mb-4">
                    <h4 class="text-base md:text-lg font-semibold text-green-900">Company's Device</h4>
                    ${swapData.company_product_price ? `<span class="text-base md:text-lg font-bold text-green-700">₵${parseFloat(swapData.company_product_price).toFixed(2)}</span>` : ''}
                </div>
                
                <div class="space-y-3">
                    ${swapData.company_product_name ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Product:</span>
                            <span class="text-sm text-gray-900 font-semibold">${escapeHtml(swapData.company_product_name)}</span>
                        </div>
                    ` : '<p class="text-sm text-gray-500">No product information available</p>'}
                    ${swapData.company_brand || swapData.company_product_brand ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Brand:</span>
                            <span class="text-sm text-gray-900 font-semibold">${escapeHtml(swapData.company_brand || swapData.company_product_brand || '')}</span>
                        </div>
                    ` : ''}
                    ${swapData.company_product_price ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Price:</span>
                            <span class="text-sm text-gray-900 font-semibold">₵${parseFloat(swapData.company_product_price).toFixed(2)}</span>
                        </div>
                    ` : ''}
                    ${companySpecs.storage ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Storage:</span>
                            <span class="text-sm text-gray-900">${escapeHtml(companySpecs.storage)}</span>
                        </div>
                    ` : ''}
                    ${companySpecs.ram ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">RAM:</span>
                            <span class="text-sm text-gray-900">${escapeHtml(companySpecs.ram)}</span>
                        </div>
                    ` : ''}
                    ${companySpecs.color ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Color:</span>
                            <span class="text-sm text-gray-900">${escapeHtml(companySpecs.color)}</span>
                        </div>
                    ` : ''}
                    ${swapData.company_product_model || swapData.company_model ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Model:</span>
                            <span class="text-sm text-gray-900">${escapeHtml(swapData.company_product_model || swapData.company_model || '')}</span>
                        </div>
                    ` : ''}
                    ${companySpecs.battery ? `
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Battery:</span>
                            <span class="text-sm text-gray-900">${escapeHtml(companySpecs.battery)}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-6 pt-4 border-t flex flex-wrap gap-3 justify-end">
            ${swapData.id ? `
                <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/${swapData.id}/receipt" target="_blank" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-receipt mr-2"></i>View Receipt
                </a>
                ${swapData.resale_status === 'in_stock' ? `
                    ${swapData.inventory_product_id && swapData.inventory_product_id !== null && swapData.inventory_product_id !== 'NULL' && parseInt(swapData.inventory_product_id) > 0 ? `
                        <button disabled
                                class="px-4 py-2 bg-gray-400 text-white rounded-md cursor-not-allowed opacity-50" 
                                title="Add to product already">
                            <i class="fas fa-box mr-2"></i>Add to Products
                        </button>
                    ` : `
                        <button onclick='closeSwapDetailsModal(); showPriceModalForSwap(${swapData.id}, ${JSON.stringify({brand: swapData.customer_product_brand || '', model: swapData.customer_product_model || '', estimated_value: swapData.customer_product_value || 0})})' 
                                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors" 
                                title="Add swapped item to products with price">
                            <i class="fas fa-box mr-2"></i>Add to Products
                        </button>
                    `}
                ` : ''}
                ${typeof isManager !== 'undefined' && isManager ? `
                    <button onclick='closeSwapDetailsModal(); deleteSwap(${swapData.id})' 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                ` : ''}
            ` : ''}
        </div>
    `;
    
    content.innerHTML = html;
    modal.classList.remove('hidden');
}

function closeSwapDetailsModal() {
    document.getElementById('swapDetailsModal').classList.add('hidden');
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Close modal when clicking outside
document.getElementById('swapDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSwapDetailsModal();
    }
});
</script>
