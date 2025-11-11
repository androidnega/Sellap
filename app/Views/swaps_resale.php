<?php
// Swapped Items for Resale Management
?>

<div class="w-full p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Swapped Items for Resale</h1>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Swaps
        </a>
    </div>

    <!-- Status Filter -->
    <div class="mb-4 flex space-x-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/resale" class="px-4 py-2 rounded-md <?= !isset($_GET['status']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            All Items
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/resale?status=in_stock" class="px-4 py-2 rounded-md <?= (isset($_GET['status']) && $_GET['status'] == 'in_stock') ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            In Stock
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/swaps/resale?status=sold" class="px-4 py-2 rounded-md <?= (isset($_GET['status']) && $_GET['status'] == 'sold') ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
            Sold
        </a>
    </div>

    <?php if (empty($items)): ?>
        <div class="bg-white p-8 rounded-lg shadow text-center">
            <p class="text-gray-500">No swapped items found.</p>
        </div>
    <?php else: ?>
        <!-- Items Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transaction Code
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Device
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Condition
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estimated Value
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Resell Price
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Received Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm"><?= htmlspecialchars($item['transaction_code'] ?? 'N/A') ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['brand'] ?? '') ?> <?= htmlspecialchars($item['model'] ?? '') ?></div>
                                <?php if (!empty($item['imei'])): ?>
                                    <div class="text-xs text-gray-500">IMEI: <?= htmlspecialchars($item['imei']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900 capitalize"><?= htmlspecialchars($item['condition'] ?? 'N/A') ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium">₵<?= number_format($item['estimated_value'] ?? 0, 2) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-bold text-blue-600">₵<?= number_format($item['resell_price'] ?? 0, 2) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status = $item['status'] ?? 'in_stock';
                                $statusColors = [
                                    'in_stock' => 'bg-yellow-100 text-yellow-800',
                                    'sold' => 'bg-green-100 text-green-800'
                                ];
                                $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $colorClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php
                                $dateField = $item['created_at'] ?? null;
                                if ($dateField) {
                                    echo date('M j, Y', strtotime($dateField));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                                <?php if (!empty($item['resold_on'])): ?>
                                    <div class="text-xs text-gray-400">Sold: <?= date('M j, Y', strtotime($item['resold_on'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($status === 'in_stock'): ?>
                                    <button onclick='markAsSold(<?= $item['id'] ?>, <?= json_encode($item) ?>)'
                                            class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-check-circle mr-1"></i>Mark as Sold
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400">Sold</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Mark as Sold Modal -->
<div id="markSoldModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-11/12 max-w-md shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">Mark Item as Sold</h3>
            <button onclick="closeMarkSoldModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="markSoldForm" onsubmit="submitMarkSold(event)">
            <input type="hidden" id="itemId" name="item_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Device</label>
                <p class="text-sm text-gray-900 font-semibold" id="modalDeviceName"></p>
            </div>
            
            <div class="mb-4">
                <label for="actualPrice" class="block text-sm font-medium text-gray-700 mb-2">
                    Actual Selling Price <span class="text-red-500">*</span>
                </label>
                <input type="number" id="actualPrice" name="actual_price" step="0.01" min="0" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter actual selling price">
                <p class="text-xs text-gray-500 mt-1">Estimated resell price: ₵<span id="estimatedPrice"></span></p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeMarkSoldModal()" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Mark as Sold
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function markAsSold(itemId, item) {
    document.getElementById('itemId').value = itemId;
    document.getElementById('modalDeviceName').textContent = item.brand + ' ' + item.model;
    document.getElementById('estimatedPrice').textContent = parseFloat(item.resell_price || 0).toFixed(2);
    document.getElementById('actualPrice').value = item.resell_price || '';
    document.getElementById('markSoldModal').classList.remove('hidden');
}

function closeMarkSoldModal() {
    document.getElementById('markSoldModal').classList.add('hidden');
    document.getElementById('markSoldForm').reset();
}

function submitMarkSold(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const itemId = formData.get('item_id');
    const actualPrice = formData.get('actual_price');
    
    fetch('<?= BASE_URL_PATH ?>/api/swaps/mark-sold', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            item_id: itemId,
            actual_price: actualPrice
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Item marked as sold successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to mark item as sold'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Close modal when clicking outside
document.getElementById('markSoldModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMarkSoldModal();
    }
});
</script>

