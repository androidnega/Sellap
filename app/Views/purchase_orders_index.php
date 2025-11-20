<?php
// Purchase Orders Index View
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Purchase Orders</h2>
        <p class="text-gray-600">Track purchase orders and supplier deliveries (Tracking Only - No Inventory Updates)</p>
    </div>
    
    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Total Orders</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_orders'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Pending</div>
            <div class="text-2xl font-bold text-yellow-600"><?= $stats['pending_orders'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Received</div>
            <div class="text-2xl font-bold text-green-600"><?= $stats['received_orders'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Total Spent</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($stats['total_spent'] ?? 0, 2) ?></div>
        </div>
    </div>
    
    <div class="flex justify-between items-center mb-4">
        <div class="flex gap-4 items-center">
            <form method="GET" action="<?= BASE_URL_PATH ?>/dashboard/purchase-orders" class="flex gap-2">
                <input type="text" name="search" placeholder="Search orders..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select name="status" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= ($_GET['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="received" <?= ($_GET['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                </select>
                <select name="supplier_id" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier['id'] ?>" <?= ($_GET['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($supplier['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['supplier_id'])): ?>
                    <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">
            <i class="fas fa-plus"></i> New Purchase Order
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($order['supplier_name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($order['item_count'] ?? 0) ?> items
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= number_format($order['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'draft' => 'bg-gray-100 text-gray-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'partially_received' => 'bg-orange-100 text-orange-800',
                                        'received' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusColor ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $paymentColors = [
                                        'unpaid' => 'bg-red-100 text-red-800',
                                        'partial' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800'
                                    ];
                                    $paymentColor = $paymentColors[$order['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $paymentColor ?>">
                                        <?= ucfirst($order['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/view/<?= $order['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($order['status'] === 'draft'): ?>
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/edit/<?= $order['id'] ?>" 
                                               class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php 
                                        // Show delete button for draft orders or for system admin
                                        $userRole = $_SESSION['user']['role'] ?? 'manager';
                                        if ($order['status'] === 'draft' || $userRole === 'system_admin'): 
                                        ?>
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/delete/<?= $order['id'] ?>" 
                                               class="text-red-600 hover:text-red-900" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this purchase order? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                No purchase orders found. <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/create" class="text-blue-600 hover:text-blue-800">Create your first purchase order</a>
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

