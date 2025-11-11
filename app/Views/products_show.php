<div class="p-6">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/products" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Products
        </a>
        <h2 class="text-3xl font-bold text-gray-800">Product Details: <?= htmlspecialchars($product['name'] ?? 'N/A') ?></h2>
    </div>
    
    <div class="bg-white p-5 rounded shadow space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left Column: Product Info -->
        <div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Product Information</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600">Product Name</label>
                    <p class="text-gray-900"><?= htmlspecialchars($product['name'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($product['sku'])): ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">SKU</label>
                    <p class="text-gray-900 font-mono text-sm"><?= htmlspecialchars($product['sku']) ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Brand</label>
                    <p class="text-gray-900"><?= htmlspecialchars($product['brand_name'] ?? $product['brand'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Category</label>
                    <p class="text-gray-900"><?= htmlspecialchars($product['category_name'] ?? $product['category'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Price</label>
                    <p class="text-gray-900 text-lg font-semibold text-green-600">₵<?= number_format($product['price'] ?? 0, 2) ?></p>
                </div>
                <?php if (!empty($product['model_name']) || !empty($product['model'])): ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Model</label>
                    <p class="text-gray-900"><?= htmlspecialchars($product['model_name'] ?? $product['model'] ?? 'N/A') ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column: Stock & Status -->
        <div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Stock & Status</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600">Current Quantity</label>
                    <p class="text-gray-900 text-lg font-semibold <?= ($product['quantity'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $product['quantity'] ?? 0 ?>
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Status</label>
                    <p class="text-gray-900">
                        <span class="px-2 py-1 rounded text-xs <?= ($product['status'] ?? 'out_of_stock')=='available' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= ucfirst($product['status'] ?? 'out_of_stock') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Total Value</label>
                    <p class="text-gray-900 text-lg font-semibold">₵<?= number_format(($product['price'] ?? 0) * ($product['quantity'] ?? 0), 2) ?></p>
                </div>
                <?php if (!empty($product['product_id'])): ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Product ID</label>
                    <p class="text-gray-900 font-mono text-sm"><?= htmlspecialchars($product['product_id']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($product['item_location'])): ?>
                <div>
                    <label class="text-sm font-medium text-gray-600">Item Location</label>
                    <p class="text-gray-900"><?= htmlspecialchars($product['item_location']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Description Section -->
    <?php if (!empty($product['description'])): ?>
    <div class="mt-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-3">Description</h3>
        <div class="bg-gray-50 p-4 rounded">
            <p class="text-gray-700"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Product Specifications -->
    <?php if (!empty($product['specs'])): ?>
    <div class="mt-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-3">Specifications</h3>
        <div class="bg-gray-50 p-4 rounded">
            <?php 
            $specs = is_string($product['specs']) ? json_decode($product['specs'], true) : $product['specs'];
            if (is_array($specs) && !empty($specs)): 
            ?>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($specs as $key => $value): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-600"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) ?></dt>
                            <dd class="text-gray-900"><?= htmlspecialchars($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <p class="text-gray-700"><?= htmlspecialchars($product['specs']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Product Statistics -->
    <div class="mt-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-3">Product Statistics</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 p-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-boxes text-blue-600 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-blue-600">Stock Level</p>
                        <p class="text-lg font-semibold text-blue-800"><?= $product['quantity'] ?? 0 ?> units</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-dollar-sign text-green-600 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-green-600">Unit Price</p>
                        <p class="text-lg font-semibold text-green-800">₵<?= number_format($product['price'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-calculator text-purple-600 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm text-purple-600">Total Value</p>
                        <p class="text-lg font-semibold text-purple-800">₵<?= number_format(($product['price'] ?? 0) * ($product['quantity'] ?? 0), 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons (Read-only) -->
    <div class="flex justify-end gap-3 mt-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/products" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
            <i class="fas fa-arrow-left mr-2"></i>Back to Products
        </a>
    </div>
</div>
</div>

