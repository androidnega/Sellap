<!-- User Activity Logs -->
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">User Activity Logs</h2>
        <p class="text-gray-600">Track user login/logout activity and session duration</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-blue-600">Total Logs</div>
                <i class="fas fa-list-alt text-blue-500 text-xl"></i>
            </div>
            <div class="text-2xl font-bold text-blue-900"><?php echo number_format($stats['total_logs'] ?? 0); ?></div>
        </div>
        <div class="bg-green-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-green-600">Total Logins</div>
                <i class="fas fa-sign-in-alt text-green-500 text-xl"></i>
            </div>
            <div class="text-2xl font-bold text-green-900"><?php echo number_format($stats['total_logins'] ?? 0); ?></div>
        </div>
        <div class="bg-orange-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-orange-600">Total Logouts</div>
                <i class="fas fa-sign-out-alt text-orange-500 text-xl"></i>
            </div>
            <div class="text-2xl font-bold text-orange-900"><?php echo number_format($stats['total_logouts'] ?? 0); ?></div>
        </div>
        <div class="bg-purple-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-purple-600">Unique Users</div>
                <i class="fas fa-users text-purple-500 text-xl"></i>
            </div>
            <div class="text-2xl font-bold text-purple-900"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="<?php echo BASE_URL_PATH; ?>/dashboard/user-logs" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="user_role" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo (($_GET['user_role'] ?? '') === $role) ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                    <option value="">All Sessions</option>
                    <option value="active" <?php echo (($_GET['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active Sessions</option>
                    <option value="completed" <?php echo (($_GET['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>Completed Sessions</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                <select name="company_id" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" <?php echo (($_GET['company_id'] ?? '') == $company['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))); ?>" 
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? date('Y-m-d')); ?>" 
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500">
            </div>
            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 font-medium">
                    Filter
                </button>
                <a href="<?php echo BASE_URL_PATH; ?>/dashboard/user-logs" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 font-medium">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Activity Logs Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="p-3 text-left">User</th>
                        <th class="p-3 text-left">Role</th>
                        <th class="p-3 text-left">Company</th>
                        <th class="p-3 text-left">Event</th>
                        <th class="p-3 text-left">Login Time</th>
                        <th class="p-3 text-left">Logout Time</th>
                        <th class="p-3 text-left">Session Duration</th>
                        <th class="p-3 text-left">IP Address</th>
                        <th class="p-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($log['full_name'] ?? $log['username']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['username']); ?></div>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        $roleColors = [
                                            'system_admin' => 'bg-red-100 text-red-700',
                                            'admin' => 'bg-purple-100 text-purple-700',
                                            'manager' => 'bg-blue-100 text-blue-700',
                                            'salesperson' => 'bg-green-100 text-green-700',
                                            'technician' => 'bg-yellow-100 text-yellow-700'
                                        ];
                                        echo $roleColors[$log['user_role']] ?? 'bg-gray-100 text-gray-700';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $log['user_role'])); ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600">
                                    <?php echo htmlspecialchars($log['company_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="p-3">
                                    <?php 
                                    // Determine if this is an active session (login without logout_time)
                                    $isActiveSession = ($log['event_type'] === 'login' && empty($log['logout_time']));
                                    // Determine if this is a completed session (has logout_time)
                                    $isCompletedSession = !empty($log['logout_time']);
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        if ($isActiveSession) {
                                            // Active session - green badge
                                            echo 'bg-green-100 text-green-700';
                                        } elseif ($isCompletedSession) {
                                            // Completed session - red badge
                                            echo 'bg-red-100 text-red-700';
                                        } else {
                                            // Fallback
                                            echo 'bg-gray-100 text-gray-700';
                                        }
                                        ?>">
                                        <?php 
                                        if ($isActiveSession) {
                                            echo '<i class="fas fa-sign-in-alt mr-1"></i> Login';
                                        } elseif ($isCompletedSession) {
                                            echo '<i class="fas fa-sign-out-alt mr-1"></i> Logout';
                                        } else {
                                            echo ucfirst($log['event_type']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600 text-xs">
                                    <?php 
                                    if ($log['login_time']) {
                                        echo date('M d, Y H:i:s', strtotime($log['login_time']));
                                    } elseif ($log['event_type'] === 'login' && $log['created_at']) {
                                        // Fallback to created_at if login_time is not set
                                        echo date('M d, Y H:i:s', strtotime($log['created_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td class="p-3 text-gray-600 text-xs">
                                    <?php 
                                    if ($log['logout_time']) {
                                        echo date('M d, Y H:i:s', strtotime($log['logout_time']));
                                    } elseif ($isActiveSession) {
                                        echo '<span class="text-blue-600 font-medium">Active</span>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td class="p-3 text-gray-600">
                                    <?php 
                                    if ($isActiveSession && $log['login_time']) {
                                        // Calculate current session duration for active sessions
                                        $loginTime = strtotime($log['login_time']);
                                        $currentTime = time();
                                        $sessionDuration = max(0, $currentTime - $loginTime);
                                        
                                        $hours = floor($sessionDuration / 3600);
                                        $minutes = floor(($sessionDuration % 3600) / 60);
                                        $seconds = $sessionDuration % 60;
                                        
                                        if ($hours > 0) {
                                            echo '<span class="text-blue-600 font-medium">' . $hours . 'h ' . $minutes . 'm</span>';
                                        } elseif ($minutes > 0) {
                                            echo '<span class="text-blue-600 font-medium">' . $minutes . 'm ' . $seconds . 's</span>';
                                        } else {
                                            echo '<span class="text-blue-600 font-medium">' . $seconds . 's</span>';
                                        }
                                    } elseif ($log['session_duration_seconds'] > 0) {
                                        $hours = floor($log['session_duration_seconds'] / 3600);
                                        $minutes = floor(($log['session_duration_seconds'] % 3600) / 60);
                                        $seconds = $log['session_duration_seconds'] % 60;
                                        
                                        if ($hours > 0) {
                                            echo $hours . 'h ' . $minutes . 'm';
                                        } elseif ($minutes > 0) {
                                            echo $minutes . 'm ' . $seconds . 's';
                                        } else {
                                            echo $seconds . 's';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td class="p-3 text-gray-600 text-xs">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </td>
                                <td class="p-3 text-gray-600 text-xs">
                                    <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-lg font-medium">No activity logs found</p>
                                <p class="text-sm mt-2">Try adjusting your filters</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="mt-6 bg-white rounded-lg shadow p-4">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-gray-700">
                    Showing <span class="font-medium"><?= (($pagination['page'] - 1) * $pagination['limit']) + 1 ?></span> to 
                    <span class="font-medium"><?= min($pagination['page'] * $pagination['limit'], $pagination['total']) ?></span> of 
                    <span class="font-medium"><?= number_format($pagination['total']) ?></span> results
                </div>
                
                <div class="flex items-center gap-2">
                    <!-- Items per page selector -->
                    <label class="text-sm text-gray-600">Per page:</label>
                    <select onchange="changeLimit(this.value)" class="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:border-blue-500">
                        <option value="25" <?= $pagination['limit'] == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $pagination['limit'] == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $pagination['limit'] == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                    
                    <!-- Previous Button -->
                    <?php if ($pagination['has_previous']): ?>
                        <?php
                        $prevParams = $_GET;
                        $prevParams['page'] = $pagination['page'] - 1;
                        $prevUrl = BASE_URL_PATH . '/dashboard/user-logs?' . http_build_query($prevParams);
                        ?>
                        <a href="<?= htmlspecialchars($prevUrl) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <div class="flex items-center gap-1">
                        <?php
                        $maxPagesToShow = 5;
                        $startPage = max(1, $pagination['page'] - floor($maxPagesToShow / 2));
                        $endPage = min($pagination['total_pages'], $startPage + $maxPagesToShow - 1);
                        $startPage = max(1, $endPage - $maxPagesToShow + 1);
                        
                        // First page
                        if ($startPage > 1): 
                            $firstParams = $_GET;
                            $firstParams['page'] = 1;
                            $firstUrl = BASE_URL_PATH . '/dashboard/user-logs?' . http_build_query($firstParams);
                        ?>
                            <a href="<?= htmlspecialchars($firstUrl) ?>" 
                               class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                1
                            </a>
                            <?php if ($startPage > 2): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Page range -->
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php
                            $pageParams = $_GET;
                            $pageParams['page'] = $i;
                            $pageUrl = BASE_URL_PATH . '/dashboard/user-logs?' . http_build_query($pageParams);
                            ?>
                            <?php if ($i == $pagination['page']): ?>
                                <span class="px-3 py-2 border border-blue-500 rounded-md text-sm font-medium text-blue-700 bg-blue-50">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($pageUrl) ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <!-- Last page -->
                        <?php if ($endPage < $pagination['total_pages']): 
                            $lastParams = $_GET;
                            $lastParams['page'] = $pagination['total_pages'];
                            $lastUrl = BASE_URL_PATH . '/dashboard/user-logs?' . http_build_query($lastParams);
                        ?>
                            <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($lastUrl) ?>" 
                               class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <?= $pagination['total_pages'] ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Next Button -->
                    <?php if ($pagination['has_next']): ?>
                        <?php
                        $nextParams = $_GET;
                        $nextParams['page'] = $pagination['page'] + 1;
                        $nextUrl = BASE_URL_PATH . '/dashboard/user-logs?' . http_build_query($nextParams);
                        ?>
                        <a href="<?= htmlspecialchars($nextUrl) ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif ($pagination['total'] > 0): ?>
        <div class="mt-6 bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-700">
                Showing all <span class="font-medium"><?= number_format($pagination['total']) ?></span> results
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function changeLimit(limit) {
    const url = new URL(window.location.href);
    url.searchParams.set('limit', limit);
    url.searchParams.set('page', '1'); // Reset to first page when changing limit
    window.location.href = url.toString();
}
</script>

