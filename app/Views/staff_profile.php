<!-- Staff Profile View -->
<div class="p-6">
    <div class="mb-6">
        <a href="<?php echo BASE_URL_PATH; ?>/dashboard/staff" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Staff List
        </a>
        <h2 class="text-3xl font-bold text-gray-800">Staff Profile</h2>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= $_SESSION['success_message'] ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Staff Information Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($staff['full_name']); ?></h3>
                    <p class="text-gray-600 mt-1"><?php echo ucfirst($staff['role']); ?></p>
                </div>

                <div class="space-y-4 border-t pt-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email</label>
                        <p class="text-gray-900 mt-1"><?php echo htmlspecialchars($staff['email']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Username</label>
                        <p class="text-gray-900 mt-1"><?php echo htmlspecialchars($staff['username']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone Number</label>
                        <p class="text-gray-900 mt-1"><?php echo htmlspecialchars($staff['phone_number'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Status</label>
                        <p class="mt-1">
                            <span class="px-3 py-1 rounded-full text-xs font-medium
                                <?php echo $staff['status'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $staff['status'] == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Member Since</label>
                        <p class="text-gray-900 mt-1"><?php echo date('F d, Y', strtotime($staff['created_at'])); ?></p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t space-y-2">
                    <a 
                        href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/edit/<?php echo $staff['id']; ?>" 
                        class="block w-full text-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 font-medium"
                    >
                        Edit Profile
                    </a>
                    <a 
                        href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/reset-password/<?php echo $staff['id']; ?>" 
                        class="block w-full text-center bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 font-medium"
                        onclick="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($staff['full_name']); ?>?');"
                    >
                        Reset Password
                    </a>
                </div>
            </div>
        </div>

        <!-- Sales Statistics Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Sales Performance</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm font-medium text-blue-600 mb-1">Total Sales</div>
                        <div class="text-2xl font-bold text-blue-900"><?php echo $salesStats['sales_count']; ?></div>
                        <div class="text-xs text-blue-600 mt-1">Transactions</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-sm font-medium text-green-600 mb-1">Total Revenue</div>
                        <div class="text-2xl font-bold text-green-900">₵<?php echo number_format($salesStats['total_revenue'], 2); ?></div>
                        <div class="text-xs text-green-600 mt-1">All time</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="text-sm font-medium text-purple-600 mb-1">Average Sale</div>
                        <div class="text-2xl font-bold text-purple-900">₵<?php echo number_format($salesStats['average_sale'], 2); ?></div>
                        <div class="text-xs text-purple-600 mt-1">Per transaction</div>
                    </div>
                </div>

                <?php if ($salesStats['sales_count'] > 0): ?>
                    <div class="mt-6 pt-6 border-t">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Performance Summary</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Sales Count:</span>
                                <span class="font-semibold text-gray-900"><?php echo $salesStats['sales_count']; ?> sales</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Revenue Generated:</span>
                                <span class="font-semibold text-green-600">₵<?php echo number_format($salesStats['total_revenue'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Average Sale Amount:</span>
                                <span class="font-semibold text-purple-600">₵<?php echo number_format($salesStats['average_sale'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-6 pt-6 border-t text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p class="text-gray-500 text-lg font-medium">No sales recorded yet</p>
                        <p class="text-gray-400 text-sm mt-2">Sales statistics will appear here once this staff member makes their first sale.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

