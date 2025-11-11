<?php
// Swap Dashboard View
?>

<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Swap Dashboard</h1>
        <div class="flex space-x-4">
            <a href="/swaps" class="btn btn-outline">View All Swaps</a>
            <a href="/swaps/create" class="btn btn-primary">New Swap</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Swaps</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $stats['total_swaps'] ?? 0 ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Completed</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $stats['completed_swaps'] ?? 0 ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending Resale</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $stats['completed_swaps'] - $stats['resold_swaps'] ?? 0 ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Profit</p>
                    <p class="text-2xl font-bold text-gray-900">₵<?= number_format($stats['total_profit'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Active Swaps -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Active Swaps</h2>
                <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded">
                    <?= count($activeSwaps) ?> Items
                </span>
            </div>
            
            <div class="space-y-4">
                <?php if (empty($activeSwaps)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>No active swaps</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($activeSwaps, 0, 5) as $swap): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium text-gray-800"><?= htmlspecialchars($swap['customer_name']) ?></h3>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($swap['transaction_code']) ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?= date('M j, Y', strtotime($swap['swap_date'])) ?></span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-600">Company Product:</p>
                                    <p class="font-medium"><?= htmlspecialchars($swap['company_product_name']) ?></p>
                                    <p class="text-green-600">₵<?= number_format($swap['company_product_price'], 2) ?></p>
                                </div>
                                <div class="bg-red-700 p-2 rounded" style="background-color: #991b1b;">
                                    <p class="text-white text-sm" style="color: #fca5a5;">Customer Product:</p>
                                    <p class="font-medium text-white"><?= htmlspecialchars($swap['customer_product_brand']) ?> <?= htmlspecialchars($swap['customer_product_model']) ?></p>
                                    <p class="text-white font-semibold">₵<?= number_format($swap['resell_price'], 2) ?></p>
                                </div>
                            </div>
                            
                            <div class="mt-3 flex justify-between items-center">
                                <span class="text-xs text-gray-500">Handled by: <?= htmlspecialchars($swap['handled_by_name']) ?></span>
                                <a href="/swaps/<?= $swap['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($activeSwaps) > 5): ?>
                        <div class="text-center">
                            <a href="/swaps?status=completed" class="text-blue-600 hover:text-blue-800 text-sm">View all active swaps</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profit Tracking -->
        <div class="bg-white p-6 rounded-lg shadow-sm border">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Profit Tracking</h2>
                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                    Recent Activity
                </span>
            </div>
            
            <div class="space-y-4">
                <?php if (empty($profitTracking)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                        <p>No profit data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($profitTracking, 0, 5) as $profit): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium text-gray-800"><?= htmlspecialchars($profit['customer_name']) ?></h3>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($profit['transaction_code']) ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?= date('M j, Y', strtotime($profit['swap_date'])) ?></span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                                <div>
                                    <p class="text-gray-600">Estimated Profit:</p>
                                    <p class="font-medium text-blue-600">₵<?= number_format($profit['profit_estimate'], 2) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Final Profit:</p>
                                    <p class="font-medium <?= $profit['final_profit'] ? 'text-green-600' : 'text-gray-400' ?>">
                                        <?= $profit['final_profit'] ? '₵' . number_format($profit['final_profit'], 2) : 'Pending' ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs px-2 py-1 rounded <?= $profit['overall_status'] === 'Finalized' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= htmlspecialchars($profit['overall_status']) ?>
                                </span>
                                <a href="/swaps/<?= $profit['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($profitTracking) > 5): ?>
                        <div class="text-center">
                            <a href="/swaps/profit-tracking" class="text-blue-600 hover:text-blue-800 text-sm">View all profit data</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white p-6 rounded-lg shadow-sm border">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="/swaps/create" class="flex items-center p-4 border rounded-lg hover:bg-gray-50">
                <div class="p-2 bg-blue-100 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">New Swap</h3>
                    <p class="text-sm text-gray-600">Create a new swap transaction</p>
                </div>
            </a>
            
            <a href="/pos" class="flex items-center p-4 border rounded-lg hover:bg-gray-50">
                <div class="p-2 bg-green-100 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">POS System</h3>
                    <p class="text-sm text-gray-600">Access point of sale with swap mode</p>
                </div>
            </a>
            
            <a href="/swaps?status=completed" class="flex items-center p-4 border rounded-lg hover:bg-gray-50">
                <div class="p-2 bg-purple-100 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">View Reports</h3>
                    <p class="text-sm text-gray-600">Detailed swap reports and analytics</p>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
// Dashboard JavaScript
(function() {
    // Auto-refresh dashboard data every 30 seconds
    setInterval(() => {
        // You can add AJAX calls here to refresh data
        console.log('Dashboard auto-refresh');
    }, 30000);
})();
</script>
