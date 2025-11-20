<?php
// Purchase Order Item Row Partial
// Variables available: $index, $item
$itemIndex = $index ?? 0;
$productId = $item['product_id'] ?? '';
$productName = htmlspecialchars($item['product_name'] ?? '');
$productSku = htmlspecialchars($item['product_sku'] ?? '');
?>
<div class="grid grid-cols-12 gap-2 items-end border p-2 rounded purchase-order-item-row" data-index="<?= $itemIndex ?>">
    <div class="col-span-4">
        <input type="hidden" name="items[<?= $itemIndex ?>][product_id]" 
               id="item_<?= $itemIndex ?>_product_id" 
               value="">
        <input type="text" 
               name="items[<?= $itemIndex ?>][product_name]" 
               id="item_<?= $itemIndex ?>_product_name"
               value="<?= $productName ?>" 
               placeholder="Enter product/item name" 
               required
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-500 mt-1">For tracking purposes only</p>
    </div>
    <div class="col-span-2">
        <input type="text" name="items[<?= $itemIndex ?>][product_sku]" 
               id="item_<?= $itemIndex ?>_product_sku"
               value="<?= $productSku ?>" 
               placeholder="SKU"
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div class="col-span-2">
        <input type="number" name="items[<?= $itemIndex ?>][quantity]" 
               value="<?= htmlspecialchars($item['quantity'] ?? 1) ?>" 
               placeholder="Qty" required min="1"
               onchange="calculateTotal()" 
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div class="col-span-2">
        <input type="number" name="items[<?= $itemIndex ?>][unit_cost]" 
               value="<?= htmlspecialchars($item['unit_cost'] ?? 0) ?>" 
               placeholder="Unit Cost" required step="0.01" min="0"
               onchange="calculateTotal()" 
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div class="col-span-2">
        <button type="button" onclick="this.parentElement.parentElement.remove(); calculateTotal();" 
                class="w-full bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700">
            <i class="fas fa-trash"></i> Remove
        </button>
    </div>
</div>

