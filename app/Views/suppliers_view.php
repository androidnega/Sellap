<?php
// Supplier View Page
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Suppliers
        </a>
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($supplier['name']) ?></h2>
                <p class="text-gray-600">Supplier Details and Information</p>
            </div>
            <div class="flex gap-2">
                <a href="<?= BASE_URL_PATH ?>/dashboard/suppliers/edit/<?= $supplier['id'] ?>" 
                   class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/purchase-orders/create?supplier_id=<?= $supplier['id'] ?>" 
                   class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-shopping-cart"></i> New Purchase Order
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Total Products</div>
            <div class="text-2xl font-bold text-blue-600"><?= $stats['total_products'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Total Orders</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_orders'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Completed Orders</div>
            <div class="text-2xl font-bold text-green-600"><?= $stats['completed_orders'] ?? 0 ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Total Spent</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($stats['total_spent'] ?? 0, 2) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600">Unpaid Amount</div>
            <div class="text-2xl font-bold text-red-600"><?= number_format($stats['unpaid_amount'] ?? 0, 2) ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Supplier Information -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Supplier Information</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['name']) ?></dd>
                </div>
                <?php if (!empty($supplier['contact_person'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Contact Person</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['contact_person']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['email'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['email']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['phone'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['phone']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['alternate_phone'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Alternate Phone</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['alternate_phone']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['address']) || !empty($supplier['city'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Address</dt>
                    <dd class="text-sm text-gray-900">
                        <?php
                        $addressParts = array_filter([
                            $supplier['address'],
                            $supplier['city'],
                            $supplier['state'],
                            $supplier['postal_code'],
                            $supplier['country']
                        ]);
                        echo htmlspecialchars(implode(', ', $addressParts));
                        ?>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['tax_id'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Tax ID</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['tax_id']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($supplier['payment_terms'])): ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment Terms</dt>
                    <dd class="text-sm text-gray-900"><?= htmlspecialchars($supplier['payment_terms']) ?></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Credit Limit</dt>
                    <dd class="text-sm text-gray-900"><?= number_format($supplier['credit_limit'] ?? 0, 2) ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="text-sm">
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
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Products Supplied -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Products Supplied</h3>
                <button onclick="openAddProductModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i>Add Product
                </button>
            </div>
            
            <?php if (!empty($products)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current Qty</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total Received</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Restock</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $product): ?>
                                <?php 
                                $tracking = $trackingData[$product['product_id']] ?? null;
                                ?>
                                <tr>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-sm"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <?php if (!empty($product['supplier_product_code'])): ?>
                                            <div class="text-xs text-gray-500">Code: <?= htmlspecialchars($product['supplier_product_code']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($product['is_preferred']): ?>
                                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Preferred</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm"><?= number_format($product['current_quantity'] ?? 0) ?></td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php if ($tracking): ?>
                                            <?= number_format($tracking['total_quantity_received']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php if ($tracking): ?>
                                            <?= number_format($tracking['total_amount_spent'], 2) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php if ($tracking && $tracking['last_restock_date']): ?>
                                            <?= date('M d, Y', strtotime($tracking['last_restock_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No products linked to this supplier yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($supplier['notes'])): ?>
    <div class="mt-6 bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Notes</h3>
        <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($supplier['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Add Product to Supplier</h3>
                <button onclick="closeAddProductModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form id="addProductForm" class="px-6 py-4">
            <input type="hidden" id="supplierId" value="<?= $supplier['id'] ?>">
            
            <div class="space-y-4">
                <div>
                    <label for="productSelect" class="block text-sm font-medium text-gray-700 mb-1">Product *</label>
                    <select id="productSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">Select a product</option>
                        <?php if (!empty($allProducts)): ?>
                            <?php 
                            // Filter out products already linked to this supplier
                            $linkedProductIds = array_column($products, 'product_id');
                            foreach ($allProducts as $prod): 
                                if (in_array($prod['id'], $linkedProductIds)) continue;
                                $cost = $prod['cost'] ?? $prod['cost_price'] ?? 0;
                            ?>
                                <option value="<?= $prod['id'] ?>" data-cost="<?= $cost ?>">
                                    <?= htmlspecialchars($prod['name']) ?> (SKU: <?= htmlspecialchars($prod['sku'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Initial Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="0" value="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Quantity to track (can be 0 if just linking)</p>
                </div>
                
                <div>
                    <label for="unitCost" class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                    <input type="number" id="unitCost" name="unit_cost" step="0.01" min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Cost per unit for tracking</p>
                </div>
                
                <div>
                    <label for="supplierProductCode" class="block text-sm font-medium text-gray-700 mb-1">Supplier Product Code</label>
                    <input type="text" id="supplierProductCode" name="supplier_product_code" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                           placeholder="Optional">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeAddProductModal()" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-link mr-2"></i>Link Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddProductModal() {
    document.getElementById('addProductModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddProductModal() {
    document.getElementById('addProductModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addProductForm').reset();
}

// Auto-fill unit cost when product is selected
document.getElementById('productSelect')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const cost = selectedOption.getAttribute('data-cost');
    if (cost && document.getElementById('unitCost')) {
        document.getElementById('unitCost').value = cost;
    }
});

// Handle form submission
document.getElementById('addProductForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const supplierId = document.getElementById('supplierId').value;
    const productId = document.getElementById('productSelect').value;
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const unitCost = parseFloat(document.getElementById('unitCost').value) || 0;
    const supplierProductCode = document.getElementById('supplierProductCode').value;
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Linking...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('<?= BASE_URL_PATH ?>/api/suppliers/link-product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                supplier_id: supplierId,
                product_id: productId,
                quantity: quantity,
                unit_cost: unitCost,
                supplier_product_code: supplierProductCode || null
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Product linked successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (result.error || 'Failed to link product'));
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error linking product: ' + error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Close modal when clicking outside
document.getElementById('addProductModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddProductModal();
    }
});
</script>

