<?php
// Technician Dashboard View
use App\Helpers\DashboardWidgets;
$currentYear = date('Y');
$showNewYear = DashboardWidgets::shouldShowNewYearMessage();
$todayRepairs = DashboardWidgets::getTodaySalesCount($companyId ?? null, $userId ?? null, 'technician');
?>

<div class="p-3 sm:p-4 pb-4 max-w-full bg-white min-h-screen">
    <?php if ($showNewYear): ?>
    <div class="mb-3 bg-gradient-to-r from-yellow-400 via-pink-500 to-purple-600 rounded-lg shadow p-3 text-white">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1">
                <div class="text-2xl">🎉</div>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold">Happy New Year <?= $currentYear ?>!</h2>
                    <p class="text-xs sm:text-sm opacity-90"><?= $todayRepairs > 0 ? "You've completed <strong>{$todayRepairs}</strong> " . ($todayRepairs === 1 ? 'repair' : 'repairs') . " today!" : 'Wishing you a prosperous year ahead!' ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800">Technician Dashboard</h1>
        <p class="text-sm sm:text-base text-gray-600 mt-1 sm:mt-2">Manage your repairs and bookings</p>
    </div>

    <!-- Repair Cost and Parts - One Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-4 sm:p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-medium text-blue-700 mb-1">Repair Cost</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900 truncate">₵<?= number_format($totalRepairCost, 2) ?></p>
                </div>
                <div class="p-2 sm:p-3 bg-blue-200 rounded-lg flex-shrink-0 ml-3">
                    <i class="fas fa-dollar-sign text-blue-700 text-lg sm:text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-4 sm:p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-medium text-purple-700 mb-1">Repair Parts</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900 truncate">₵<?= number_format($totalPartsCost, 2) ?></p>
                </div>
                <div class="p-2 sm:p-3 bg-purple-200 rounded-lg flex-shrink-0 ml-3">
                    <i class="fas fa-cog text-purple-700 text-lg sm:text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-lg border border-emerald-200 p-4 sm:p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-medium text-emerald-700 mb-1">Completed</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= $completedCount ?></p>
                </div>
                <div class="p-2 sm:p-3 bg-emerald-200 rounded-lg flex-shrink-0 ml-3">
                    <i class="fas fa-check-circle text-emerald-700 text-lg sm:text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg border border-amber-200 p-4 sm:p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-medium text-amber-700 mb-1">Pending</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= $pendingCount ?></p>
                </div>
                <div class="p-2 sm:p-3 bg-amber-200 rounded-lg flex-shrink-0 ml-3">
                    <i class="fas fa-clock text-amber-700 text-lg sm:text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg border border-indigo-200 p-4 sm:p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-medium text-indigo-700 mb-1">Delivered</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= $deliveredCount ?? 0 ?></p>
                </div>
                <div class="p-2 sm:p-3 bg-indigo-200 rounded-lg flex-shrink-0 ml-3">
                    <i class="fas fa-truck text-indigo-700 text-lg sm:text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-6 sm:mb-8">
        <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-3 sm:mb-4 flex items-center">
            <i class="fas fa-bolt text-yellow-500 mr-2"></i>
            Quick Actions
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
            <a href="<?= BASE_URL_PATH ?>/dashboard/booking" class="group flex items-center p-4 sm:p-5 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg hover:from-blue-100 hover:to-blue-200 hover:shadow-md transition-all duration-200">
                <div class="p-3 bg-blue-200 rounded-lg mr-4 group-hover:bg-blue-300 transition-colors">
                    <i class="fas fa-plus-circle text-blue-700 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800 text-base sm:text-lg mb-1">New Booking</h3>
                    <p class="text-xs sm:text-sm text-gray-600">Create a new repair booking</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400 group-hover:text-blue-600 transition-colors"></i>
            </a>
            
            <a href="<?= BASE_URL_PATH ?>/dashboard/repairs" class="group flex items-center p-4 sm:p-5 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg hover:from-green-100 hover:to-green-200 hover:shadow-md transition-all duration-200">
                <div class="p-3 bg-green-200 rounded-lg mr-4 group-hover:bg-green-300 transition-colors">
                    <i class="fas fa-tools text-green-700 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800 text-base sm:text-lg mb-1">My Repairs</h3>
                    <p class="text-xs sm:text-sm text-gray-600">View all my repairs</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400 group-hover:text-green-600 transition-colors"></i>
            </a>
        </div>
    </div>

    <!-- Pending Repairs -->
    <?php if (!empty($pendingRepairs)): ?>
        <div class="mb-6 sm:mb-8">
            <div class="pb-3 sm:pb-4 mb-4 sm:mb-5 border-b-2 border-yellow-300">
                <h2 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-clock text-yellow-600 mr-2"></i>
                    Pending Repairs
                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full"><?= count($pendingRepairs) ?></span>
                </h2>
            </div>
            <div class="w-full -mx-3 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                        <thead class="bg-gradient-to-r from-yellow-50 to-amber-50">
                            <tr>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Customer</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Device</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden md:table-cell">Issue</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_slice($pendingRepairs, 0, 5) as $repair): ?>
                                <tr class="hover:bg-yellow-50 transition-colors duration-150">
                                <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($repair['customer_name'] ?? 'Unknown Customer') ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php if (!empty($repair['customer_contact'])): ?>
                                            <?= htmlspecialchars($repair['customer_contact']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">No contact</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($repair['product_name'] ?? 'Customer Device') ?>
                                </td>
                                <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 text-sm text-gray-900 max-w-xs truncate hidden md:table-cell">
                                    <?= htmlspecialchars($repair['issue_description']) ?>
                                </td>
                                <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-xs sm:text-sm font-medium">
                                        <i class="fas fa-eye mr-1.5"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php if (count($pendingRepairs) > 5): ?>
                <div class="pt-4 sm:pt-5 text-center border-t border-gray-200 mt-4 sm:mt-5">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=pending" class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition-colors font-medium text-sm">
                        <i class="fas fa-list mr-2"></i> View all <?= count($pendingRepairs) ?> pending repairs
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- In Progress Repairs -->
    <?php if (!empty($inProgressRepairs)): ?>
        <div class="mb-6 sm:mb-8">
            <div class="pb-3 sm:pb-4 mb-4 sm:mb-5 border-b-2 border-blue-300">
                <h2 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-cog text-blue-600 mr-2"></i>
                    In Progress
                    <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full"><?= count($inProgressRepairs) ?></span>
                </h2>
            </div>
            <div class="w-full -mx-3 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                        <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                            <tr>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Customer</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Device</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Cost</th>
                                <th class="px-2 sm:px-4 lg:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_slice($inProgressRepairs, 0, 5) as $repair): ?>
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($repair['customer_name'] ?? 'Unknown Customer') ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php if (!empty($repair['customer_contact'])): ?>
                                            <?= htmlspecialchars($repair['customer_contact']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">No contact</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                    <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($repair['product_name'] ?? 'Customer Device') ?>
                                    </td>
                                    <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap text-sm text-gray-900">
                                        ₵<?= number_format($repair['total_cost'], 2) ?>
                                    </td>
                                    <td class="px-2 sm:px-4 lg:px-6 py-2 sm:py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?= BASE_URL_PATH ?>/dashboard/repairs/<?= $repair['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-xs sm:text-sm font-medium">
                                            <i class="fas fa-eye mr-1.5"></i> View
                                        </a>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
            <?php if (count($inProgressRepairs) > 5): ?>
                <div class="pt-4 sm:pt-5 text-center border-t border-gray-200 mt-4 sm:mt-5">
                    <a href="<?= BASE_URL_PATH ?>/dashboard/repairs?status=in_progress" class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition-colors font-medium text-sm">
                        <i class="fas fa-list mr-2"></i> View all <?= count($inProgressRepairs) ?> in-progress repairs
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

