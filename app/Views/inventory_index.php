<?php $basePhp = defined('BASE_URL_PATH') ? BASE_URL_PATH : (rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?: ''); ?>
<?php
$invUserRole = $_SESSION['user']['role'] ?? '';
$canManageBarcodes = in_array($invUserRole, ['manager', 'admin', 'system_admin'], true);
?>
<div class="w-full max-w-full overflow-x-hidden">
<div class="mb-5 bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Product Management</h2>
        <p class="text-sm sm:text-base text-gray-600 mt-1">Manage your product inventory and stock levels</p>
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
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <p class="text-gray-600">View and manage all products in your inventory</p>
        <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap">
            <div class="relative flex-1 md:w-80">
                <input id="inventorySearch" type="text" placeholder="Search by name, brand, model, SKU, category..." class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            <div class="relative w-full sm:w-auto">
                <button id="columnSelectorToggle" type="button" class="w-full sm:w-auto inline-flex items-center justify-between gap-2 px-3 py-2 border border-gray-300 bg-white rounded text-sm text-gray-700 hover:bg-gray-50">
                    <span>Columns</span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="columnSelectorMenu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-20 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Show columns</p>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="product-id" checked> Product ID</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="product" checked> Product</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="brand" checked> Brand</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="model" checked> Model</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="category" checked> Category</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="sku" checked> SKU / Barcode</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="price" checked> Price</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="quantity" checked> Quantity</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="location" checked> Location</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="inv-col-toggle" data-col="status" checked> Status</label>
                    </div>
                </div>
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
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <button id="selectAllBtn" class="flex-1 sm:flex-none bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm font-medium">
                    <i class="fas fa-check-square mr-1"></i>Select All
                </button>
                <button id="deleteSelectedBtn" class="flex-1 sm:flex-none bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm font-medium" disabled>
                    <i class="fas fa-trash mr-1"></i>Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
            <a href="<?= BASE_URL_PATH ?>/dashboard/inventory/create" class="w-full sm:w-auto text-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">+ Add Product</a>
        </div>
    </div>

    <!-- Inventory Summary Cards (moved to top) -->
    <?php
    // Low stock count is calculated in controller and passed as $lowStockCount
    $lowStockCount = $lowStockCount ?? 0;
    ?>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
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
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
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
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
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
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
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
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
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

<div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
    <table class="min-w-[1100px] w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="p-3 text-left w-12">
                    <input type="checkbox" id="selectAllCheckbox" class="cursor-pointer">
                </th>
                <th class="p-3 text-left" data-col="product-id">Product ID</th>
                <th class="p-3 text-left" data-col="product">Product</th>
                <th class="p-3 text-left" data-col="brand">Brand</th>
                <th class="p-3 text-left" data-col="model">Model</th>
                <th class="p-3 text-left" data-col="category">Category</th>
                <th class="p-3 text-left" data-col="sku">SKU / Barcode</th>
                <th class="p-3 text-left" data-col="price">Price</th>
                <th class="p-3 text-left" data-col="quantity">Quantity</th>
                <th class="p-3 text-left" data-col="location">Location</th>
                <th class="p-3 text-left" data-col="status">Status</th>
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
                    data-product-name="<?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-sku="<?= htmlspecialchars(trim((string)($product['sku'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
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
                    <td class="p-3 font-mono text-xs" data-col="product-id" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= $product['product_id'] ?? 'PID-' . str_pad($product['id'] ?? 0, 3, '0', STR_PAD_LEFT) ?></td>
                    <td class="p-3" data-col="product" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>>
                        <?= htmlspecialchars($product['name'] ?? '') ?>
                        <?php if ($isSwappedItem): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ml-2" style="background-color: #fca5a5; color: #991b1b;" title="Swapped Item - Received from customer">
                                <i class="fas fa-exchange-alt mr-1"></i>Swapped
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3" data-col="brand" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['brand_name'] ?? 'N/A') ?></td>
                    <td class="p-3" data-col="model" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['model_name'] ?? 'N/A') ?></td>
                    <td class="p-3" data-col="category" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                    <td class="p-3 font-mono text-xs max-w-[140px]" data-col="sku" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>>
                        <span class="inv-sku-cell"><?= htmlspecialchars(trim((string)($product['sku'] ?? '')) !== '' ? (string)$product['sku'] : '—') ?></span>
                    </td>
                    <td class="p-3" data-col="price" <?= $isSwappedItem ? 'style="color: #991b1b; font-weight: 600;"' : '' ?>>₵<?= number_format($product['price'] ?? 0, 2) ?></td>
                    <td class="p-3" data-col="quantity">
                        <span class="px-2 py-1 rounded text-xs <?= $isSwappedItem ? '' : (($product['quantity'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>" <?= $isSwappedItem ? 'style="background-color: #fca5a5; color: #991b1b;"' : '' ?>>
                            <?= $product['quantity'] ?? 0 ?>
                        </span>
                    </td>
                    <td class="p-3 text-xs" data-col="location" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>><?= htmlspecialchars($product['item_location'] ?? 'N/A') ?></td>
                    <td class="p-3" data-col="status">
                        <span class="px-2 py-1 rounded text-xs <?= $isSwappedItem ? '' : (($product['status'] ?? 'out_of_stock')=='available' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>" <?= $isSwappedItem ? 'style="background-color: #fca5a5; color: #991b1b;"' : '' ?>>
                            <?= ucfirst($product['status'] ?? 'out_of_stock') ?>
                        </span>
                    </td>
                    <td class="p-3 text-right space-x-2 whitespace-nowrap">
                        <?php if ($canManageBarcodes): ?>
                            <button type="button" class="inv-barcode-btn text-purple-600 hover:underline text-sm font-medium" data-id="<?= (int)$product['id'] ?>">Barcode</button>
                        <?php endif; ?>
                        <a href="<?= $basePhp ?>/dashboard/inventory/view/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-green-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #991b1b;"' : '' ?>>View</a>
                        <a href="<?= $basePhp ?>/dashboard/inventory/edit/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-blue-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #fca5a5;"' : '' ?>>Edit</a>
                        <a href="<?= $basePhp ?>/dashboard/inventory/delete/<?= $product['id'] ?>" class="hover:underline<?= !$isSwappedItem ? ' text-red-600' : '' ?>" <?= $isSwappedItem ? 'style="color: #fca5a5;"' : '' ?> onclick="return confirm('Delete this product?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr><td colspan="12" class="p-3 text-center text-gray-500">No products found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="inventoryFilterInfo" class="text-xs text-gray-500 mt-2 hidden">Filtered: <span id="inventoryFilteredCount">0</span> of <span id="inventoryTotalCount">0</span> items on this page</div>

<!-- Pagination -->
<div class="mt-6 mb-4">
    <?= \App\Helpers\PaginationHelper::render($pagination) ?>
</div>
</div>

<style>
    /* Prevent extra scrolling on inventory page */
    .main-content-container main {
        min-height: auto !important;
    }
    @media print {
        body * { visibility: hidden; }
        #invBarcodePrintArea, #invBarcodePrintArea * { visibility: visible; }
        #invBarcodePrintArea { position: absolute; left: 0; top: 0; width: 100%; }
    }
</style>

<script>window.INVENTORY_CAN_MANAGE_BARCODES = <?= !empty($canManageBarcodes) ? 'true' : 'false' ?>;</script>

<!-- Barcode modal (managers) -->
<?php if (!empty($canManageBarcodes)): ?>
<div id="invBarcodeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" aria-modal="true" role="dialog">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Product barcode</h3>
        <p id="invBarcodeProductName" class="text-sm text-gray-600 mb-4"></p>
        <div id="invBarcodePrintArea" class="flex flex-col items-center justify-center py-4 bg-gray-50 rounded border border-gray-200">
            <svg id="invBarcodeSvg" class="max-w-full h-auto"></svg>
            <p id="invBarcodeSkuText" class="mt-2 font-mono text-sm text-gray-800"></p>
        </div>
        <div class="flex flex-wrap gap-2 mt-4 justify-end">
            <button type="button" id="invBarcodeGenBtn" class="px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Generate code</button>
            <button type="button" id="invBarcodeRegenBtn" class="hidden px-3 py-2 bg-amber-600 text-white text-sm rounded hover:bg-amber-700">Regenerate</button>
            <button type="button" id="invBarcodePrintBtn" class="px-3 py-2 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300">Print</button>
            <button type="button" id="invBarcodeCloseBtn" class="px-3 py-2 border border-gray-300 text-sm rounded hover:bg-gray-50">Close</button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<?php endif; ?>

<script>
(function(){
    const searchInput = document.getElementById('inventorySearch');
    const stockFilter = document.getElementById('stockFilter');
    const tbody = document.getElementById('inventoryTableBody');
    const info = document.getElementById('inventoryFilterInfo');
    const filteredCountEl = document.getElementById('inventoryFilteredCount');
    const totalCountEl = document.getElementById('inventoryTotalCount');
    const columnToggleBtn = document.getElementById('columnSelectorToggle');
    const columnMenu = document.getElementById('columnSelectorMenu');
    const columnCheckboxes = document.querySelectorAll('.inv-col-toggle');
    const colStorageKey = 'manager_inventory_visible_columns_v1';
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
        const skuRaw = String(p.sku || '').trim();
        const skuDisp = skuRaw ? escapeHtml(skuRaw) : '—';
        const canBarcode = window.INVENTORY_CAN_MANAGE_BARCODES;
        const barcodeBtn = canBarcode ? `<button type="button" class="inv-barcode-btn text-purple-600 hover:underline text-sm font-medium" data-id="${id}">Barcode</button>` : '';
        return `
            <tr class="border-b hover:bg-gray-50 inventory-row" data-product-id="${id}" data-quantity="${qty}" data-product-name="${name}" data-sku="${escapeHtml(skuRaw)}">
                <td class="p-3">
                    <input type="checkbox" class="product-checkbox cursor-pointer" data-product-id="${id}" data-product-name="${name}">
                </td>
                <td class="p-3 font-mono text-xs" data-col="product-id">${p.product_id || ('PID-' + String(id||0).padStart(3,'0'))}</td>
                <td class="p-3" data-col="product">${escapeHtml(p.name || '')}</td>
                <td class="p-3" data-col="brand">${escapeHtml(p.brand_name || 'N/A')}</td>
                <td class="p-3" data-col="model">${escapeHtml(p.model_name || 'N/A')}</td>
                <td class="p-3" data-col="category">${escapeHtml(p.category_name || 'N/A')}</td>
                <td class="p-3 font-mono text-xs max-w-[140px]" data-col="sku"><span class="inv-sku-cell">${skuDisp}</span></td>
                <td class="p-3" data-col="price">₵${Number(p.price||0).toFixed(2)}</td>
                <td class="p-3" data-col="quantity"><span class="px-2 py-1 rounded text-xs ${qty>0?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${qty}</span></td>
                <td class="p-3 text-xs" data-col="location">${escapeHtml(p.item_location || 'N/A')}</td>
                <td class="p-3" data-col="status"><span class="px-2 py-1 rounded text-xs ${status==='available'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span></td>
                <td class="p-3 text-right space-x-2 whitespace-nowrap">
                    ${barcodeBtn}
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

    function currentVisibleColumns() {
        return Array.from(columnCheckboxes).filter(cb => cb.checked).map(cb => cb.dataset.col);
    }

    function applyVisibleColumns(visibleCols) {
        document.querySelectorAll('[data-col]').forEach(cell => {
            const key = cell.getAttribute('data-col');
            cell.classList.toggle('hidden', !visibleCols.includes(key));
        });
    }

    function persistVisibleColumns(cols) {
        localStorage.setItem(colStorageKey, JSON.stringify(cols));
    }

    function restoreVisibleColumns() {
        const raw = localStorage.getItem(colStorageKey);
        if (!raw) return;
        try {
            const cols = JSON.parse(raw);
            if (!Array.isArray(cols) || !cols.length) return;
            columnCheckboxes.forEach(cb => {
                cb.checked = cols.includes(cb.dataset.col);
            });
        } catch (e) {}
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
            applyVisibleColumns(currentVisibleColumns());
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
                applyVisibleColumns(currentVisibleColumns());
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
                    tbody.innerHTML = '<tr data-empty-placeholder="1"><td colspan="12" class="p-3 text-center text-gray-500">No matching products</td></tr>';
                    filteredCountEl.textContent = 0;
                    totalCountEl.textContent = 0;
                    info.classList.remove('hidden');
                    return;
                }
                tbody.innerHTML = results.map(rowHTML).join('');
                applyVisibleColumns(currentVisibleColumns());
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
                applyVisibleColumns(currentVisibleColumns());
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

    if (columnToggleBtn && columnMenu) {
        columnToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            columnMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function(e) {
            if (!columnMenu.contains(e.target) && !columnToggleBtn.contains(e.target)) {
                columnMenu.classList.add('hidden');
            }
        });
    }

    restoreVisibleColumns();
    applyVisibleColumns(currentVisibleColumns());
    columnCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const visible = currentVisibleColumns();
            if (!visible.length) {
                this.checked = true;
                return;
            }
            applyVisibleColumns(visible);
            persistVisibleColumns(visible);
        });
    });
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

<?php if (!empty($canManageBarcodes)): ?>
<script>
(function () {
    if (typeof JsBarcode === 'undefined') return;

    const modal = document.getElementById('invBarcodeModal');
    const nameEl = document.getElementById('invBarcodeProductName');
    const skuText = document.getElementById('invBarcodeSkuText');
    const svg = document.getElementById('invBarcodeSvg');
    const btnGen = document.getElementById('invBarcodeGenBtn');
    const btnRegen = document.getElementById('invBarcodeRegenBtn');
    const btnPrint = document.getElementById('invBarcodePrintBtn');
    const btnClose = document.getElementById('invBarcodeCloseBtn');
    if (!modal || !svg) return;

    let currentProductId = null;

    function basePath() {
        return (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
    }

    function renderBarcode(value) {
        const v = (value || '').trim();
        svg.innerHTML = '';
        skuText.textContent = v || '—';
        if (!v) return;
        try {
            JsBarcode(svg, v, {
                format: 'CODE128',
                width: 2,
                height: 56,
                displayValue: true,
                fontSize: 14,
                margin: 8
            });
        } catch (e) {
            skuText.textContent = v + ' (could not render — use alphanumeric)';
        }
    }

    function updateRowSku(productId, sku) {
        const row = document.querySelector('tr.inventory-row[data-product-id="' + productId + '"]');
        if (!row) return;
        row.setAttribute('data-sku', sku);
        const cell = row.querySelector('.inv-sku-cell');
        if (cell) cell.textContent = sku || '—';
    }

    function setButtonsForSku(hasSku) {
        if (btnGen) btnGen.classList.toggle('hidden', !!hasSku);
        if (btnRegen) btnRegen.classList.toggle('hidden', !hasSku);
    }

    function openModal(productId) {
        currentProductId = productId;
        const row = document.querySelector('tr.inventory-row[data-product-id="' + productId + '"]');
        const name = row ? (row.getAttribute('data-product-name') || row.querySelector('td:nth-child(3)')?.textContent?.trim() || '') : '';
        let sku = row ? (row.getAttribute('data-sku') || '').trim() : '';
        nameEl.textContent = name;
        setButtonsForSku(!!sku);
        renderBarcode(sku);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentProductId = null;
    }

    async function callApi(regenerate) {
        if (!currentProductId) return;
        const url = basePath() + '/api/inventory/product/' + currentProductId + '/generate-barcode';
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ regenerate: !!regenerate })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            alert(data.error || 'Request failed');
            return;
        }
        const sku = data.sku || '';
        updateRowSku(currentProductId, sku);
        setButtonsForSku(!!sku);
        renderBarcode(sku);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.inv-barcode-btn');
        if (!btn) return;
        e.preventDefault();
        const id = parseInt(btn.getAttribute('data-id'), 10);
        if (id) openModal(id);
    });

    if (btnGen) btnGen.addEventListener('click', function () { callApi(false); });
    if (btnRegen) btnRegen.addEventListener('click', function () {
        if (confirm('Generate a new barcode code? The old code will stop working for scans.')) callApi(true);
    });
    if (btnPrint) btnPrint.addEventListener('click', function () { window.print(); });
    if (btnClose) btnClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
})();
</script>
<?php endif; ?>
