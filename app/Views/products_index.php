<?php
// Products View for Salespeople (Read-only)
// Ensure $stats is set
if (!isset($stats)) {
    $stats = [
        'total_products' => 0,
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'swapped_items' => 0,
        'total_value' => 0
    ];
}
if (!isset($paginatedProducts)) {
    $paginatedProducts = [];
}
if (!isset($categories)) {
    $categories = [];
}
?>

<div class="p-6">
    <!-- Stats Cards -->
    <?php if (isset($stats)): ?>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="text-sm text-gray-600 mb-1">Total Products</div>
            <div class="text-2xl font-bold text-gray-800"><?= $stats['total_products'] ?? 0 ?></div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg shadow border border-green-200">
            <div class="text-sm text-green-700 mb-1">In Stock</div>
            <div class="text-2xl font-bold text-green-800"><?= $stats['in_stock'] ?? 0 ?></div>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg shadow border border-yellow-200">
            <div class="text-sm text-yellow-700 mb-1">Low Stock</div>
            <div class="text-2xl font-bold text-yellow-800"><?= $stats['low_stock'] ?? 0 ?></div>
        </div>
        <div class="bg-red-50 p-4 rounded-lg shadow border border-red-200">
            <div class="text-sm text-red-700 mb-1">Out of Stock</div>
            <div class="text-2xl font-bold text-red-800"><?= $stats['out_of_stock'] ?? 0 ?></div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg shadow border border-purple-200">
            <div class="text-sm text-purple-700 mb-1">Swapped Items</div>
            <div class="text-2xl font-bold text-purple-800"><?= $stats['swapped_items'] ?? 0 ?></div>
        </div>
        <div class="bg-blue-50 p-4 rounded-lg shadow border border-blue-200">
            <div class="text-sm text-blue-700 mb-1">Total Value</div>
            <div class="text-2xl font-bold text-blue-800">₵<?= number_format($stats['total_value'] ?? 0, 2) ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Products</h1>
    </div>

    <!-- Search and Filter -->
    <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3">
            <div class="md:col-span-2">
                <div class="relative">
                    <input type="text" id="productSearch" placeholder="Search products by name, brand, or SKU..." 
                           class="w-full border border-gray-300 rounded-md px-4 py-3 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            <div>
                <select id="categoryFilter" class="w-full border border-gray-300 rounded-md px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['name'] ?? '') ?>"><?= htmlspecialchars($category['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <select id="stockFilter" class="w-full border border-gray-300 rounded-md px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Stock</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
            <div>
                <a href="<?= BASE_URL_PATH ?>/dashboard/products<?= isset($_GET['swapped_items']) && $_GET['swapped_items'] == '1' ? '' : '?swapped_items=1' ?>" 
                   class="w-full px-4 py-3 border rounded-md text-center block <?= (isset($_GET['swapped_items']) && $_GET['swapped_items'] == '1') ? 'bg-purple-100 border-purple-500 text-purple-700 font-medium' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?> transition-colors">
                    <i class="fas fa-exchange-alt mr-2"></i>
                    <?= (isset($_GET['swapped_items']) && $_GET['swapped_items'] == '1') ? 'All Products' : 'Swapped Items Only' ?>
                </a>
            </div>
        </div>
        <div class="flex justify-end">
            <button id="clearFiltersBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-times mr-2"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="p-3 text-left">ID</th>
                        <th class="p-3 text-left">Product Name</th>
                        <th class="p-3 text-left">Brand</th>
                        <th class="p-3 text-left">Category</th>
                        <th class="p-3 text-left">Price</th>
                        <th class="p-3 text-left">Quantity</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-box-open text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium mb-2">No products available</h3>
                                    <p class="text-sm">Contact your manager to add products to the inventory.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php 
                            // Check if item is swapped - either has is_swapped_item flag OR has swap_ref_id
                            // Handle both integer and string values for is_swapped_item
                            $hasIsSwappedFlag = isset($product['is_swapped_item']) && (
                                $product['is_swapped_item'] == 1 || 
                                $product['is_swapped_item'] === '1' || 
                                $product['is_swapped_item'] === true ||
                                intval($product['is_swapped_item']) > 0
                            );
                            
                            // Check swap_ref_id - handle NULL, empty, and string 'NULL'
                            $hasSwapRef = false;
                            if (isset($product['swap_ref_id'])) {
                                $swapRef = $product['swap_ref_id'];
                                $hasSwapRef = !empty($swapRef) && 
                                             $swapRef !== 'NULL' && 
                                             $swapRef !== null && 
                                             trim(strval($swapRef)) !== '' &&
                                             intval($swapRef) > 0;
                            }
                            
                            // Additional check: verify if product ID exists in swapped_items.inventory_product_id
                            $isSwappedItem = $hasIsSwappedFlag || $hasSwapRef;
                            if (!$isSwappedItem && isset($product['id'])) {
                                try {
                                    require_once __DIR__ . '/../../config/database.php';
                                    $db = \Database::getInstance()->getConnection();
                                    $checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM swapped_items WHERE inventory_product_id = ?");
                                    $checkStmt->execute([$product['id']]);
                                    $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($checkResult && intval($checkResult['cnt']) > 0) {
                                        $isSwappedItem = true;
                                    }
                                } catch (\Exception $e) {
                                    // Silently fail - don't break the page if check fails
                                }
                            }
                            ?>
                            <?php 
                            // Ensure quantity is treated as integer (handle both 'quantity' and 'qty' keys)
                            $qty = intval($product['quantity'] ?? $product['qty'] ?? 0);
                            ?>
                            <tr class="border-b product-row" 
                                data-product-id="<?= $product['id'] ?>" 
                                data-product-name="<?= htmlspecialchars(strtolower($product['name'] ?? '')) ?>"
                                data-brand-name="<?= htmlspecialchars(strtolower($product['brand_name'] ?? '')) ?>"
                                data-category-name="<?= htmlspecialchars($product['category_name'] ?? '') ?>"
                                data-sku="<?= htmlspecialchars(strtolower($product['sku'] ?? '')) ?>"
                                data-quantity="<?= $qty ?>"
                                data-is-swapped="<?= $isSwappedItem ? '1' : '0' ?>"
                                <?php if ($isSwappedItem): ?>
                                    style="background-color: #fee2e2; color: #991b1b;" 
                                    onmouseover="this.style.backgroundColor='#fecaca'" 
                                    onmouseout="this.style.backgroundColor='#fee2e2'"
                                <?php else: ?>
                                    class="hover:bg-gray-50"
                                <?php endif; ?>>
                                <td class="p-3 font-mono text-xs<?= !$isSwappedItem ? ' text-gray-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>
                                    <?= $product['id'] ?? 'N/A' ?>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium<?= !$isSwappedItem ? ' text-gray-800' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>
                                            <?= htmlspecialchars($product['name'] ?? 'N/A') ?>
                                        </div>
                                        <?php if ($isSwappedItem): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium" style="background-color: #c4b5fd; color: #6b21a8;" title="Swapped Item - Received from customer">
                                                <i class="fas fa-exchange-alt mr-1"></i>Swapped
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($product['sku'])): ?>
                                        <div class="text-xs mt-1<?= !$isSwappedItem ? ' text-gray-500' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>SKU: <?= htmlspecialchars($product['sku']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($isSwappedItem && !empty($product['swap_ref_id'])): ?>
                                        <div class="text-xs mt-1">
                                            <a href="<?= BASE_URL_PATH ?>/dashboard/swaps" class="hover:underline" style="color: #6b21a8;">
                                                <i class="fas fa-link mr-1"></i>View Swap Details
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($product['brand_name'])): ?>
                                        <span class="inline-block px-2 py-1 rounded text-xs<?= !$isSwappedItem ? ' bg-gray-100' : '' ?>" <?= $isSwappedItem ? 'style="background-color: #c4b5fd; color: #6b21a8;"' : '' ?>>
                                            <?= htmlspecialchars($product['brand_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs<?= !$isSwappedItem ? ' text-gray-400' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($product['category_name'])): ?>
                                        <span class="inline-block px-2 py-1 rounded text-xs<?= !$isSwappedItem ? ' bg-blue-100' : '' ?>" <?= $isSwappedItem ? 'style="background-color: #c4b5fd; color: #6b21a8;"' : '' ?>>
                                            <?= htmlspecialchars($product['category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs<?= !$isSwappedItem ? ' text-gray-400' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <span class="font-semibold<?= !$isSwappedItem ? ' text-green-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>
                                        ₵<?= number_format($product['price'] ?? 0, 2) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium <?= $isSwappedItem ? '' : ($qty > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>" <?= $isSwappedItem ? 'style="background-color: #c4b5fd; color: #6b21a8;"' : '' ?>>
                                        <?= $qty ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php
                                    $status = $product['status'] ?? 'out_of_stock';
                                    if ($isSwappedItem) {
                                        $statusClass = '';
                                        $statusStyle = 'background-color: #c4b5fd; color: #6b21a8;';
                                    } else {
                                        $statusClass = ($status === 'available' || $qty > 0) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                        $statusStyle = '';
                                    }
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs <?= $statusClass ?>" <?= $statusStyle ? 'style="' . $statusStyle . '"' : '' ?>>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="<?= BASE_URL_PATH ?>/dashboard/products/<?= $product['id'] ?>" 
                                       class="text-sm font-medium hover:underline<?= !$isSwappedItem ? ' text-blue-600 hover:text-blue-800' : '' ?>" <?= $isSwappedItem ? 'style="color: #6b21a8;"' : '' ?>>
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (!empty($products) && isset($pagination)): ?>
        <div class="mt-6">
            <?= \App\Helpers\PaginationHelper::render($pagination) ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Enhanced search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const productRows = document.querySelectorAll('.product-row');
    
    function filterProducts() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedCategory = categoryFilter ? categoryFilter.value : '';
        const selectedStock = stockFilter ? stockFilter.value : '';
        const isSwappedItemsView = window.location.search.includes('swapped_items=1');
        
        // Debug log for stock filter
        if (selectedStock === 'low_stock') {
            console.log('Low Stock filter selected, filtering products...');
        }
        
        let visibleCount = 0;
        
        productRows.forEach(row => {
            // Get data from data attributes (more reliable)
            const productName = row.dataset.productName || '';
            const brandName = row.dataset.brandName || '';
            const categoryName = row.dataset.categoryName || '';
            const sku = row.dataset.sku || '';
            // Parse quantity - ensure it's a number, handle NaN cases
            let stockValue = parseInt(row.dataset.quantity || '0', 10);
            if (isNaN(stockValue)) {
                stockValue = 0;
            }
            const isSwappedItem = row.dataset.isSwapped === '1';
            
            // Search filter - check name, brand, and SKU
            let matchesSearch = true;
            if (searchTerm) {
                matchesSearch = productName.includes(searchTerm) || 
                               brandName.includes(searchTerm) || 
                               sku.includes(searchTerm);
            }
            
            // Category filter
            const matchesCategory = !selectedCategory || categoryName === selectedCategory;
            
            // Stock filter - improved logic with explicit number comparison
            let matchesStock = true;
            if (selectedStock === 'in_stock') {
                // In stock: quantity > 10 (above low stock threshold)
                matchesStock = stockValue > 10;
            } else if (selectedStock === 'low_stock') {
                // Low stock: quantity > 0 but <= 10
                matchesStock = stockValue > 0 && stockValue <= 10;
            } else if (selectedStock === 'out_of_stock') {
                // Out of stock: quantity is 0
                matchesStock = stockValue === 0;
            }
            
            // Swapped items filter - if viewing swapped items only, show only swapped items
            let matchesSwapped = true;
            if (isSwappedItemsView) {
                matchesSwapped = isSwappedItem;
            }
            
            // Show row if all filters match
            if (matchesSearch && matchesCategory && matchesStock && matchesSwapped) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide "no results" message
        const tbody = document.getElementById('productsTableBody');
        let noResultsRow = tbody ? tbody.querySelector('.no-results-message') : null;
        
        if (visibleCount === 0 && productRows.length > 0) {
            // Show "no results" message if needed
            if (!noResultsRow && tbody) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-message';
                noResultsRow.innerHTML = `
                    <td colspan="8" class="p-8 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-search text-4xl mb-4 text-gray-400"></i>
                            <h3 class="text-lg font-medium mb-2">No products found</h3>
                            <p class="text-sm">Try adjusting your search or filter criteria.</p>
                        </div>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
        } else {
            // Remove "no results" message if it exists
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    }
    
    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) {
            existing.remove();
        }
        
        const notification = document.createElement('div');
        notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-md shadow-lg ${
            type === 'success' ? 'bg-green-500 text-white' : 
            type === 'error' ? 'bg-red-500 text-white' : 
            'bg-blue-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    // Clear filters function
    function clearFilters() {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = '';
        if (stockFilter) stockFilter.value = '';
        filterProducts();
    }
    
    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterProducts);
    }
    if (stockFilter) {
        stockFilter.addEventListener('change', filterProducts);
    }
    
    // Clear filters button
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearFilters);
    }
    
    // Initial filter on page load
    filterProducts();
    
    // Debug: Log quantity values for first few products (remove in production)
    if (productRows.length > 0) {
        console.log('Product quantities check:');
        productRows.forEach((row, index) => {
            if (index < 5) { // Only log first 5
                const qty = parseInt(row.dataset.quantity || '0', 10);
                const name = row.dataset.productName || 'Unknown';
                console.log(`Product: ${name}, Quantity: ${qty}, Data attribute: "${row.dataset.quantity}"`);
            }
        });
    }
});
</script>