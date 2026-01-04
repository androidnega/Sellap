<?php $basePhp = defined('BASE_URL_PATH') ? BASE_URL_PATH : (rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?: ''); ?>
<div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Product Management</h2>
        <p class="text-gray-600">Manage your product inventory and stock levels</p>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <p class="text-gray-600">View and manage all products in your inventory</p>
        <div class="flex items-center gap-3 w-full md:w-auto flex-wrap">
            <div class="relative flex-1 md:w-80">
                <input id="inventorySearch" type="text" placeholder="Search by name, brand, model, SKU, category..." class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            <div class="w-full md:w-auto">
                <?php $currentStockFilter = $_GET['stock_filter'] ?? ''; ?>
                <select id="stockFilter" class="w-full md:w-48 border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="" <?= $currentStockFilter === '' ? 'selected' : '' ?>>All Stock</option>
                    <option value="in_stock" <?= $currentStockFilter === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low_stock" <?= $currentStockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $currentStockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    <option value="low_and_out" <?= $currentStockFilter === 'low_and_out' ? 'selected' : '' ?>>Low & Out of Stock</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button id="selectAllBtn" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm font-medium">
                    <i class="fas fa-check-square mr-1"></i>Select All
                </button>
                <button id="deleteSelectedBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm font-medium" disabled>
                    <i class="fas fa-trash mr-1"></i>Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
            <a href="<?= BASE_URL_PATH ?>/dashboard/inventory/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">+ Add Product</a>
        </div>
    </div>

    <!-- Inventory Summary Cards (moved to top) -->
    <?php
    // Low stock count is calculated in controller and passed as $lowStockCount
    $lowStockCount = $lowStockCount ?? 0;
    ?>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-boxes text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Products</p>
                    <p class="text-xl font-bold text-gray-900"><?= isset($totalItems) ? (int)$totalItems : count($products) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">In Stock</p>
                    <p class="text-xl font-bold text-gray-900">
                        <?= isset($inventoryStats['available_products']) ? (int)$inventoryStats['available_products'] : count(array_filter($products, function($p) { return ($p['quantity'] ?? 0) > 0; })) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Low Stock</p>
                    <p class="text-xl font-bold text-gray-900"><?= $lowStockCount ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Out of Stock</p>
                    <p class="text-xl font-bold text-gray-900">
                        <?= isset($inventoryStats['out_of_stock']) ? (int)$inventoryStats['out_of_stock'] : count(array_filter($products, function($p) { return ($p['quantity'] ?? 0) <= 0; })) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-dollar-sign text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Value</p>
                    <p class="text-xl font-bold text-gray-900">
                        <?php
                            if (isset($inventoryStats['total_value'])) {
                                echo '₵' . number_format((float)$inventoryStats['total_value'], 2);
                            } else {
                                echo '₵' . number_format(array_sum(array_map(function($p) { return ($p['price'] ?? 0) * ($p['quantity'] ?? 0); }, $products)), 2);
                            }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

<div class="overflow-x-auto bg-white rounded shadow">
    <table class="w-full text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
                <th class="p-3 text-left w-12">
                    <input type="checkbox" id="selectAllCheckbox" class="cursor-pointer">
                </th>
                <th class="p-3 text-left">Product ID</th>
                <th class="p-3 text-left">Product</th>
                <th class="p-3 text-left">Brand</th>
                <th class="p-3 text-left">Model</th>
                <th class="p-3 text-left">Category</th>
                <th class="p-3 text-left">Price</th>
                <th class="p-3 text-left">Quantity</th>
                <th class="p-3 text-left">Location</th>
                <th class="p-3 text-left">Status</th>
                <th class="p-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody id="inventoryTableBody">
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
                
                // Calculate quantity for filtering
                $qty = intval($product['quantity'] ?? $product['qty'] ?? 0);
                ?>
                <tr class="border-b inventory-row" data-product-id="<?= $product['id'] ?>" data-quantity="<?= $qty ?>"
                    <?php if ($isSwappedItem): ?>
                        style="background-color: #fee2e2; color: #991b1b;" 
                        onmouseover="this.style.backgroundColor='#fecaca'" 
                        onmouseout="this.style.backgroundColor='#fee2e2'"
                    <?php else: ?>
                        class="hover:bg-gray-50"
                    <?php endif; ?>>
                    <td class="p-3">
                        <input type="checkbox" class="product-checkbox cursor-pointer" data-product-id="<?= $product['id'] ?>" data-product-name="<?= htmlspecialchars($product['name'] ?? '') ?>">
                    </td>
                    <td class="p-3 font-mono text-xs" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= $product['product_id'] ?? 'PID-' . str_pad($product['id'] ?? 0, 3, '0', STR_PAD_LEFT) ?></td>
                    <td class="p-3" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>>
                        <?= htmlspecialchars($product['name'] ?? '') ?>
                        <?php if ($isSwappedItem): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ml-2" style="background-color: #fca5a5; color: #991b1b;" title="Swapped Item - Received from customer">
                                <i class="fas fa-exchange-alt mr-1"></i>Swapped
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['brand_name'] ?? 'N/A') ?></td>
                    <td class="p-3" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['model_name'] ?? 'N/A') ?></td>
                    <td class="p-3" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                    <td class="p-3" <?= $isSwappedItem ? 'style="color: #991b1b; font-weight: 600;"' : '' ?>>₵<?= number_format($product['price'] ?? 0, 2) ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $isSwappedItem ? '' : (($product['quantity'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>" <?= $isSwappedItem ? 'style="background-color: #fca5a5; color: #991b1b;"' : '' ?>>
                            <?= $product['quantity'] ?? 0 ?>
                        </span>
                    </td>
                    <td class="p-3 text-xs" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['item_location'] ?? 'N/A') ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $isSwappedItem ? '' : (($product['status'] ?? 'out_of_stock')=='available' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>" <?= $isSwappedItem ? 'style="background-color: #fca5a5; color: #991b1b;"' : '' ?>>
                            <?= ucfirst($product['status'] ?? 'out_of_stock') ?>
                        </span>
                    </td>
                    <td class="p-3 text-right space-x-2">
                        <a href="<?= $basePhp ?>/dashboard/inventory/view/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-green-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>>View</a>
                        <a href="<?= $basePhp ?>/dashboard/inventory/edit/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-blue-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #fca5a5;"' : '' ?>>Edit</a>
                        <a href="<?= $basePhp ?>/dashboard/inventory/delete/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-red-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #fca5a5;"' : '' ?> onclick="return confirm('Delete this product?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr><td colspan="11" class="p-3 text-center text-gray-500">No products found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="inventoryFilterInfo" class="text-xs text-gray-500 mt-2 hidden">Filtered: <span id="inventoryFilteredCount">0</span> of <span id="inventoryTotalCount">0</span> items on this page</div>

<!-- Pagination -->
<div class="mt-6 mb-4">
    <?= \App\Helpers\PaginationHelper::render($pagination) ?>
</div>

<style>
    /* Prevent extra scrolling on inventory page */
    .main-content-container main {
        min-height: auto !important;
    }
</style>

<script>
(function(){
    const searchInput = document.getElementById('inventorySearch');
    const stockFilter = document.getElementById('stockFilter');
    const tbody = document.getElementById('inventoryTableBody');
    const info = document.getElementById('inventoryFilterInfo');
    const filteredCountEl = document.getElementById('inventoryFilteredCount');
    const totalCountEl = document.getElementById('inventoryTotalCount');
    if (!searchInput || !tbody) return;

    // Keep original rows HTML for local filter fallback
    const originalHTML = tbody.innerHTML;
    
    // Stock filter function
    function applyStockFilter() {
        const selectedStock = stockFilter ? stockFilter.value : '';
        const rows = tbody.querySelectorAll('.inventory-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const qty = parseInt(row.dataset.quantity || '0', 10);
            let matchesStock = true;
            
            if (selectedStock === 'in_stock') {
                // In stock: quantity > 10
                matchesStock = qty > 10;
            } else if (selectedStock === 'low_stock') {
                // Low stock: quantity > 0 and <= 10
                matchesStock = qty > 0 && qty <= 10;
            } else if (selectedStock === 'out_of_stock') {
                // Out of stock: quantity is 0
                matchesStock = qty === 0;
            } else if (selectedStock === 'low_and_out') {
                // Low and out of stock: quantity <= 10
                matchesStock = qty <= 10;
            }
            
            if (matchesStock) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update filter info
        if (selectedStock && info) {
            filteredCountEl.textContent = visibleCount;
            totalCountEl.textContent = rows.length;
            info.classList.remove('hidden');
        } else if (info && !searchInput.value.trim()) {
            info.classList.add('hidden');
        }
        
        // Update selection count
        if (typeof window.updateSelectedCount === 'function') {
            setTimeout(() => {
                window.updateSelectedCount();
            }, 100);
        }
    }

    function textOfRow(tr){
        return (tr.textContent || '').toLowerCase();
    }

    async function remoteSearch(q){
        const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
        const url = `${base}/api/inventory/search?q=${encodeURIComponent(q)}`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('Search request failed');
        const data = await res.json();
        if (!data.success) return [];
        return data.data || [];
    }

    function rowHTML(p){
        const status = (p.status || 'out_of_stock');
        const qty = parseInt(p.quantity || p.qty || 0, 10);
        const id = p.id;
        const name = escapeHtml(p.name || '');
        return `
            <tr class="border-b hover:bg-gray-50 inventory-row" data-product-id="${id}" data-quantity="${qty}">
                <td class="p-3">
                    <input type="checkbox" class="product-checkbox cursor-pointer" data-product-id="${id}" data-product-name="${name}">
                </td>
                <td class="p-3 font-mono text-xs">${p.product_id || ('PID-' + String(id||0).padStart(3,'0'))}</td>
                <td class="p-3">${escapeHtml(p.name || '')}</td>
                <td class="p-3">${escapeHtml(p.brand_name || 'N/A')}</td>
                <td class="p-3">${escapeHtml(p.model_name || 'N/A')}</td>
                <td class="p-3">${escapeHtml(p.category_name || 'N/A')}</td>
                <td class="p-3">₵${Number(p.price||0).toFixed(2)}</td>
                <td class="p-3"><span class="px-2 py-1 rounded text-xs ${qty>0?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${qty}</span></td>
                <td class="p-3 text-xs">${escapeHtml(p.item_location || 'N/A')}</td>
                <td class="p-3"><span class="px-2 py-1 rounded text-xs ${status==='available'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span></td>
                <td class="p-3 text-right space-x-2">
                    <a href="${(typeof BASE_URL_PATH!=='undefined'?BASE_URL_PATH:'')}/dashboard/inventory/view/${id}" class="text-green-600 hover:underline">View</a>
                    <a href="${(typeof BASE_URL_PATH!=='undefined'?BASE_URL_PATH:'')}/dashboard/inventory/edit/${id}" class="text-blue-600 hover:underline">Edit</a>
                    <a href="${(typeof BASE_URL_PATH!=='undefined'?BASE_URL_PATH:'')}/dashboard/inventory/delete/${id}" class="text-red-600 hover:underline" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
            </tr>
        `;
    }

    function escapeHtml(str){
        return String(str).replace(/[&<>"]+/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
    }

    let lastQuery = '';
    let debounceTimer;
    
    // Combined filter function
    function applyFilters() {
        const q = (searchInput.value || '').trim();
        const selectedStock = stockFilter ? stockFilter.value : '';
        
        // If search is active, let search handle filtering
        if (q) {
            // Search will handle filtering, then apply stock filter after
            return;
        }
        
        // If no search, apply stock filter to original rows
        if (!q && selectedStock) {
            applyStockFilter();
        } else if (!q && !selectedStock) {
            // Restore original
            tbody.innerHTML = originalHTML;
            if (info) info.classList.add('hidden');
            if (typeof window.updateSelectedCount === 'function') {
                setTimeout(() => {
                    window.updateSelectedCount();
                }, 100);
            }
        }
    }
    
    searchInput.addEventListener('input', () => {
        const q = (searchInput.value || '').trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
            lastQuery = q;
            if (!q) {
                // Restore original page rows
                tbody.innerHTML = originalHTML;
                if (info) info.classList.add('hidden');
                // Update selection count after restoring
                setTimeout(() => {
                    if (typeof window.updateSelectedCount === 'function') {
                        window.updateSelectedCount();
                    }
                }, 100);
                return;
            }
            try {
                const results = await remoteSearch(q);
                // If query changed while awaiting, ignore
                if (lastQuery !== q) return;
                if (!results || results.length === 0) {
                    tbody.innerHTML = '<tr data-empty-placeholder="1"><td colspan="11" class="p-3 text-center text-gray-500">No matching products</td></tr>';
                    filteredCountEl.textContent = 0;
                    totalCountEl.textContent = 0;
                    info.classList.remove('hidden');
                    return;
                }
                tbody.innerHTML = results.map(rowHTML).join('');
                filteredCountEl.textContent = results.length;
                totalCountEl.textContent = results.length;
                info.classList.remove('hidden');
                // Update selection count after search results are loaded
                setTimeout(() => {
                    if (typeof window.updateSelectedCount === 'function') {
                        window.updateSelectedCount();
                    }
                }, 100);
            } catch (e) {
                // Fallback to local filter if remote fails
                tbody.innerHTML = originalHTML;
                info.classList.add('hidden');
                // Update selection count after restoring original HTML
                setTimeout(() => {
                    if (typeof window.updateSelectedCount === 'function') {
                        window.updateSelectedCount();
                    }
                }, 100);
            }
        }, 200);
    });
    
    // Stock filter event listener - redirect to URL with filter parameter
    if (stockFilter) {
        stockFilter.addEventListener('change', () => {
            const selectedFilter = stockFilter.value;
            const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
            const url = new URL(window.location.href);
            
            // Update or remove stock_filter parameter
            if (selectedFilter) {
                url.searchParams.set('stock_filter', selectedFilter);
            } else {
                url.searchParams.delete('stock_filter');
            }
            
            // Reset to page 1 when filter changes
            url.searchParams.set('page', '1');
            
            // Redirect to new URL
            window.location.href = url.toString();
        });
    }
})();

// Bulk selection and delete functionality
(function(){
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const selectedCountEl = document.getElementById('selectedCount');
    let allSelected = false;

    // Make updateSelectedCount available globally for search function
    window.updateSelectedCount = function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
        const count = checkedBoxes.length;
        selectedCountEl.textContent = count;
        deleteSelectedBtn.disabled = count === 0;
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.product-checkbox:not([data-empty-placeholder])');
        if (selectAllCheckbox) {
            if (allCheckboxes.length > 0) {
                selectAllCheckbox.checked = count === allCheckboxes.length && allCheckboxes.length > 0;
                selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }
        
        // Update select all button text
        if (selectAllBtn) {
            allSelected = count === allCheckboxes.length && allCheckboxes.length > 0;
            selectAllBtn.innerHTML = allSelected ? 
                '<i class="fas fa-square mr-1"></i>Deselect All' : 
                '<i class="fas fa-check-square mr-1"></i>Select All';
        }
    }

    function selectAllProducts() {
        allSelected = !allSelected;
        const checkboxes = document.querySelectorAll('.product-checkbox:not([data-empty-placeholder])');
        checkboxes.forEach(checkbox => {
            checkbox.checked = allSelected;
        });
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allSelected;
            selectAllCheckbox.indeterminate = false;
        }
        updateSelectedCount();
    }

    async function deleteSelectedProducts() {
        const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
        if (checkedBoxes.length === 0) return;
        
        const productIds = Array.from(checkedBoxes).map(cb => cb.dataset.productId);
        const productNames = Array.from(checkedBoxes).map(cb => cb.dataset.productName);
        
        if (!confirm(`Are you sure you want to delete ${productIds.length} selected product(s)?\n\n${productNames.slice(0, 5).join(', ')}${productNames.length > 5 ? '...' : ''}`)) {
            return;
        }

        try {
            const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
            const response = await fetch(`${base}/dashboard/inventory/bulk-delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: productIds })
            });

            const data = await response.json();
            
            if (data.success) {
                // Remove deleted rows from DOM
                productIds.forEach(id => {
                    const row = document.querySelector(`tr[data-product-id="${id}"]`);
                    if (row) {
                        row.remove();
                    }
                });
                
                // Reset selection
                allSelected = false;
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
                updateSelectedCount();
                
                // Show success message
                alert(`${productIds.length} product(s) deleted successfully!`);
                
                // Reload page to refresh the list (in case pagination is affected)
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to delete products'));
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('An error occurred while deleting products. Please try again.');
        }
    }

    // Event listeners
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            allSelected = this.checked;
            const checkboxes = document.querySelectorAll('.product-checkbox:not([data-empty-placeholder])');
            checkboxes.forEach(checkbox => {
                checkbox.checked = allSelected;
            });
            updateSelectedCount();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', selectAllProducts);
    }

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', deleteSelectedProducts);
    }

    // Listen for checkbox changes (including dynamically added ones)
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-checkbox')) {
            updateSelectedCount();
        }
    });

    // Initial count update
    updateSelectedCount();
})();
</script>
